<?php

/**
 * @file plugins/generic/emspubcore/classes/StripePaymentHandler.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @class StripePaymentHandler
 *
 * @ingroup plugins_generic_emspubcore
 *
 * @brief Handles Stripe Checkout and subscription management
 */

namespace APP\plugins\generic\emspubcore\classes;

use APP\plugins\generic\emspubcore\EmsPubCorePlugin;
use PKP\db\DAORegistry;
use PKP\core\Core;

class StripePaymentHandler
{
    /** @var EmsPubCorePlugin */
    private $plugin;

    /** @var string Stripe API base URL */
    private const STRIPE_API_BASE = 'https://api.stripe.com/v1';

    /**
     * Constructor
     */
    public function __construct(EmsPubCorePlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Get Stripe secret key
     */
    private function getSecretKey(): string
    {
        return $this->plugin->getStripeSecretKey();
    }

    /**
     * Make a Stripe API request
     */
    private function makeRequest(string $endpoint, string $method = 'GET', array $data = []): array
    {
        $url = self::STRIPE_API_BASE . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->getSecretKey(),
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        
        if ($httpCode >= 400) {
            throw new \Exception($result['error']['message'] ?? 'Stripe API error');
        }

        return $result;
    }

    /**
     * Create or get a Stripe customer for a journal
     */
    public function getOrCreateCustomer(int $journalId, string $email, string $name): string
    {
        $planDAO = DAORegistry::getDAO('JournalPlanDAO');
        $plan = $planDAO->getByJournalId($journalId);

        // Return existing customer if we have one
        if ($plan && $plan->getStripeCustomerId()) {
            return $plan->getStripeCustomerId();
        }

        // Create new customer
        $customer = $this->makeRequest('/customers', 'POST', [
            'email' => $email,
            'name' => $name,
            'metadata' => [
                'journal_id' => $journalId,
            ],
        ]);

        return $customer['id'];
    }

    /**
     * Create a Stripe Checkout session for plan upgrade
     */
    public function createCheckoutSession(
        int $journalId,
        string $planType,
        string $billingCycle,
        string $successUrl,
        string $cancelUrl,
        string $customerEmail,
        string $customerName
    ): array {
        $prices = EmsPubCorePlugin::getPlanPrices();
        $limits = EmsPubCorePlugin::getPlanLimits();
        
        if (!isset($prices[$planType])) {
            throw new \Exception(__('plugins.generic.emspubcore.error.invalidPlan'));
        }

        $amount = $prices[$planType][$billingCycle];
        $limit = $limits[$planType];

        // Get or create customer
        $customerId = $this->getOrCreateCustomer($journalId, $customerEmail, $customerName);

        // Create checkout session
        $sessionData = [
            'customer' => $customerId,
            'mode' => 'subscription',
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => ucfirst($planType) . ' Plan',
                        'description' => $limit . ' submissions per month',
                    ],
                    'unit_amount' => $amount,
                    'recurring' => [
                        'interval' => $billingCycle === 'yearly' ? 'year' : 'month',
                    ],
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'journal_id' => $journalId,
                'plan_type' => $planType,
                'billing_cycle' => $billingCycle,
            ],
        ];

        // Flatten the nested array for Stripe's form-encoded format
        $flatData = $this->flattenArray($sessionData);
        $session = $this->makeRequest('/checkout/sessions', 'POST', $flatData);

        return [
            'session_id' => $session['id'],
            'url' => $session['url'],
        ];
    }

    /**
     * Flatten nested array for Stripe API
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}[{$key}]" : $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        return $result;
    }

    /**
     * Handle successful checkout completion
     */
    public function handleCheckoutComplete(string $sessionId): void
    {
        $session = $this->makeRequest('/checkout/sessions/' . $sessionId);
        
        $journalId = (int) $session['metadata']['journal_id'];
        $planType = $session['metadata']['plan_type'];
        $billingCycle = $session['metadata']['billing_cycle'];
        $subscriptionId = $session['subscription'];
        $customerId = $session['customer'];

        $this->updateJournalPlan(
            $journalId,
            $planType,
            $billingCycle,
            $subscriptionId,
            $customerId
        );
    }

    /**
     * Update journal plan after successful payment
     */
    private function updateJournalPlan(
        int $journalId,
        string $planType,
        string $billingCycle,
        ?string $subscriptionId,
        ?string $customerId
    ): void {
        $planDAO = DAORegistry::getDAO('JournalPlanDAO');
        $limits = EmsPubCorePlugin::getPlanLimits();

        $plan = $planDAO->getByJournalId($journalId);
        
        if (!$plan) {
            $plan = new JournalPlan();
            $plan->setJournalId($journalId);
        }

        $plan->setPlanType($planType);
        $plan->setBillingCycle($billingCycle);
        $plan->setSubmissionsLimit($limits[$planType]);
        $plan->setStripeSubscriptionId($subscriptionId);
        $plan->setStripeCustomerId($customerId);
        $plan->setPlanStartDate(Core::getCurrentDate());
        $plan->setIsActive(true);

        // Set end date based on billing cycle
        if ($billingCycle === 'yearly') {
            $plan->setPlanEndDate(date('Y-m-d H:i:s', strtotime('+1 year')));
        } else {
            $plan->setPlanEndDate(date('Y-m-d H:i:s', strtotime('+1 month')));
        }

        $planDAO->upsert($plan);

        // Record payment
        $this->recordPayment($journalId, $planType, $billingCycle, $limits[$planType]);
    }

    /**
     * Record payment in history
     */
    private function recordPayment(
        int $journalId,
        string $planType,
        string $billingCycle,
        int $amount
    ): void {
        $prices = EmsPubCorePlugin::getPlanPrices();
        $priceAmount = $prices[$planType][$billingCycle] ?? 0;

        \Illuminate\Support\Facades\DB::table('emspubcore_payment_history')->insert([
            'journal_id' => $journalId,
            'amount' => $priceAmount,
            'currency' => 'USD',
            'plan_type' => $planType,
            'billing_cycle' => $billingCycle,
            'status' => 'completed',
            'created_at' => Core::getCurrentDate(),
        ]);
    }

    /**
     * Handle Stripe webhook events
     */
    public function handleWebhook(string $payload, string $signature): void
    {
        $webhookSecret = $this->plugin->getStripeWebhookSecret();
        
        // Verify webhook signature
        if ($webhookSecret) {
            $this->verifyWebhookSignature($payload, $signature, $webhookSecret);
        }

        $event = json_decode($payload, true);
        
        switch ($event['type']) {
            case 'checkout.session.completed':
                $this->handleCheckoutComplete($event['data']['object']['id']);
                break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event['data']['object']);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionCancelled($event['data']['object']);
                break;

            case 'invoice.payment_failed':
                $this->handlePaymentFailed($event['data']['object']);
                break;
        }
    }

    /**
     * Verify Stripe webhook signature
     */
    private function verifyWebhookSignature(string $payload, string $signature, string $secret): void
    {
        $elements = explode(',', $signature);
        $timestamp = null;
        $signatures = [];
        
        foreach ($elements as $element) {
            [$prefix, $value] = explode('=', $element, 2);
            if ($prefix === 't') {
                $timestamp = $value;
            } elseif ($prefix === 'v1') {
                $signatures[] = $value;
            }
        }

        if (!$timestamp || empty($signatures)) {
            throw new \Exception('Invalid webhook signature');
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        if (!in_array($expectedSignature, $signatures)) {
            throw new \Exception('Webhook signature verification failed');
        }

        // Check timestamp to prevent replay attacks (5 min tolerance)
        if (abs(time() - $timestamp) > 300) {
            throw new \Exception('Webhook timestamp too old');
        }
    }

    /**
     * Handle subscription update
     */
    private function handleSubscriptionUpdated(array $subscription): void
    {
        $planDAO = DAORegistry::getDAO('JournalPlanDAO');
        $plan = $planDAO->getByStripeSubscriptionId($subscription['id']);
        
        if (!$plan) {
            return;
        }

        // Update status based on subscription status
        $plan->setIsActive($subscription['status'] === 'active');
        
        // Update end date from current_period_end
        if (isset($subscription['current_period_end'])) {
            $plan->setPlanEndDate(date('Y-m-d H:i:s', $subscription['current_period_end']));
        }

        $planDAO->updateObject($plan);
    }

    /**
     * Handle subscription cancellation
     */
    private function handleSubscriptionCancelled(array $subscription): void
    {
        $planDAO = DAORegistry::getDAO('JournalPlanDAO');
        $plan = $planDAO->getByStripeSubscriptionId($subscription['id']);
        
        if (!$plan) {
            return;
        }

        // Downgrade to free plan
        $limits = EmsPubCorePlugin::getPlanLimits();
        $plan->setPlanType('free');
        $plan->setSubmissionsLimit($limits['free']);
        $plan->setStripeSubscriptionId(null);
        $plan->setIsActive(true);
        $plan->setPlanEndDate(null);

        $planDAO->updateObject($plan);
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentFailed(array $invoice): void
    {
        $subscriptionId = $invoice['subscription'] ?? null;
        if (!$subscriptionId) {
            return;
        }

        $planDAO = DAORegistry::getDAO('JournalPlanDAO');
        $plan = $planDAO->getByStripeSubscriptionId($subscriptionId);
        
        if ($plan) {
            // Mark plan as inactive due to payment failure
            $plan->setIsActive(false);
            $planDAO->updateObject($plan);
        }
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(int $journalId): bool
    {
        $planDAO = DAORegistry::getDAO('JournalPlanDAO');
        $plan = $planDAO->getByJournalId($journalId);
        
        if (!$plan || !$plan->getStripeSubscriptionId()) {
            return false;
        }

        try {
            $this->makeRequest(
                '/subscriptions/' . $plan->getStripeSubscriptionId(),
                'POST',
                ['cancel_at_period_end' => 'true']
            );
            return true;
        } catch (\Exception $e) {
            error_log('Failed to cancel subscription: ' . $e->getMessage());
            return false;
        }
    }
}
