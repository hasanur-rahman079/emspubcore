<?php

/**
 * @file plugins/generic/emspubcore/classes/PaddlePaymentHandler.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @class PaddlePaymentHandler
 *
 * @ingroup plugins_generic_emspubcore
 *
 * @brief Handles Paddle Billing (v2) events and subscription management
 */

namespace APP\plugins\generic\emspubcore\classes;

use APP\plugins\generic\emspubcore\EmsPubCorePlugin;
use PKP\db\DAORegistry;
use PKP\core\Core;

class PaddlePaymentHandler
{
    /** @var EmsPubCorePlugin */
    private $plugin;

    /**
     * Constructor
     */
    public function __construct(EmsPubCorePlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Handle Paddle webhook events
     */
    public function handleWebhook(string $payload, string $signature): void
    {
        $apiKey = $this->plugin->getSetting(0, 'paddleApiKey');
        $webhookSecret = $this->plugin->getSetting(0, 'paddleWebhookSecret');
        $isTestMode = (bool) $this->plugin->getSetting(0, 'paddleTestMode');

        if (!$apiKey || !$webhookSecret) {
            error_log('Paddle Webhook Error: API Key or Webhook Secret not configured.');
            return;
        }

        // Load SDK
        if (!class_exists('\Paddle\SDK\Client')) {
             if (file_exists($this->plugin->getPluginPath() . '/vendor/autoload.php')) {
                 require_once($this->plugin->getPluginPath() . '/vendor/autoload.php');
             }
        }

        try {
            // Verify signature using the SDK Verifier
            $verifier = new \Paddle\SDK\Notifications\Verifier();
            $isValid = $verifier->verify(
                new \Paddle\SDK\Notifications\Secret($webhookSecret),
                $signature,
                $payload
            );

            if (!$isValid) {
                error_log('Paddle Webhook Error: Invalid signature.');
                return;
            }

            // Parse the event manually as a raw array for maximum reliability
            $event = json_decode($payload, true);
            $data = $event['data'] ?? [];
            $eventType = $event['event_type'] ?? '';

            switch ($eventType) {
                case 'subscription.created':
                case 'subscription.updated':
                    $this->handleSubscriptionUpdated($data);
                    break;

                case 'subscription.canceled':
                    $this->handleSubscriptionCancelled($data);
                    break;
                    
                case 'transaction.completed':
                    $this->handleTransactionCompleted($data);
                    break;
            }
        } catch (\Exception $e) {
            error_log('Paddle Webhook Verification Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle subscription update/creation
     */
    private function handleSubscriptionUpdated(array $subscription): void
    {
        $customData = (array) ($subscription['custom_data'] ?? []);
        $journalId = (int) ($customData['journalId'] ?? 0);
        
        if (!$journalId) {
            return;
        }

        $planDAO = DAORegistry::getDAO('JournalPlanDAO');
        $plan = $planDAO->getByJournalId($journalId);
        if (!$plan) {
            return;
        }

        $plan->setPaddleSubscriptionId($subscription['id']);
        $plan->setPaddleCustomerId($subscription['customer_id']);
        $plan->setIsActive($subscription['status'] === 'active');
        
        if (isset($subscription['current_billing_period']['ends_at'])) {
            $plan->setPlanEndDate(date('Y-m-d H:i:s', strtotime($subscription['current_billing_period']['ends_at'])));
        }

        $planDAO->updateObject($plan);
    }

    /**
     * Handle subscription cancellation
     */
    private function handleSubscriptionCancelled(array $subscription): void
    {
        $customData = (array) ($subscription['custom_data'] ?? []);
        $journalId = (int) ($customData['journalId'] ?? 0);
        
        if (!$journalId) {
            return;
        }

        $planDAO = DAORegistry::getDAO('JournalPlanDAO');
        $plan = $planDAO->getByJournalId($journalId);
        
        if ($plan) {
            $limits = EmsPubCorePlugin::getPlanLimits();
            $plan->setPlanType('free');
            $plan->setSubmissionsLimit($limits['free']);
            $plan->setPaddleSubscriptionId(null);
            $plan->setIsActive(true);
            $plan->setPlanEndDate(null);
            $planDAO->updateObject($plan);
        }
    }

    /**
     * Handle completed transaction (Payment)
     */
    private function handleTransactionCompleted(array $transaction): void
    {
        $customData = (array) ($transaction['custom_data'] ?? []);
        $journalId = (int) ($customData['journalId'] ?? 0);
        if (!$journalId) return;

        $planType = $customData['planType'] ?? 'unknown';

        $paymentWrapperDAO = $this->plugin->getPaymentHistoryDAO();
        $paymentWrapperDAO->logPayment([
            'journal_id' => $journalId,
            'amount' => (float) ($transaction['details']['totals']['total'] ?? 0) / 100,
            'currency' => strtoupper($transaction['currency_code'] ?? 'USD'),
            'transaction_id' => $transaction['id'],
            'status' => 'succeeded',
            'payment_date' => Core::getCurrentDate(),
            'plan_type' => $planType
        ]);
    }
}
