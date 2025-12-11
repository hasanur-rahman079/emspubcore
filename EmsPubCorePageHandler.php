<?php

/**
 * @file plugins/generic/emspubcore/EmsPubCorePageHandler.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @class EmsPubCorePageHandler
 *
 * @ingroup plugins_generic_emspubcore
 *
 * @brief Page handler for EmsPubCore plugin routes
 */

namespace APP\plugins\generic\emspubcore;

use APP\handler\Handler;
use APP\plugins\generic\emspubcore\controllers\StripeWebhookHandler;

class EmsPubCorePageHandler extends Handler
{
    /** @var EmsPubCorePlugin */
    private $plugin;

    /**
     * Constructor
     */
    /**
     * Constructor
     * 
     * @param EmsPubCorePlugin|null $plugin
     */
    public function __construct($plugin = null)
    {
        parent::__construct();
        $this->plugin = $plugin;
        
        // Fallback: Try to get from registry if not passed
        if (!$this->plugin) {
             $this->plugin = \PKP\plugins\PluginRegistry::getPlugin('generic', 'emspubcore');
        }
    }

    /**
     * Get plugin instance safely
     */
    private function getPlugin()
    {
        if (!$this->plugin) {
             // Second effort fallback
             $this->plugin = \PKP\plugins\PluginRegistry::getPlugin('generic', 'emspubcore');
        }
        return $this->plugin;
    }

    /**
     * Route requests to the appropriate handler
     *
     * @param array $args
     * @param \APP\core\Request $request
     */
    public function index($args, $request)
    {
        $op = array_shift($args) ?? '';
        
        switch ($op) {
            case 'webhook':
                return $this->handleWebhook($args, $request);
            case 'checkout':
                return $this->handleCheckout($args, $request);
            case 'success':
                return $this->handleSuccess($args, $request);
            case 'cancel':
                return $this->handleCancel($args, $request);
            case 'plans':
                return $this->showPlans($args, $request);
            case 'assignPlan':
                return $this->assignPlan($args, $request);
            case 'savePlan':
                return $this->savePlan($args, $request);
            case 'deletePlan':
                return $this->deletePlan($args, $request);
            case 'pendingPayments':
                return $this->pendingPayments($args, $request);
            case 'paySubmission':
                return $this->paySubmission($args, $request);
            case 'downloadInvoice':
                return $this->downloadInvoice($args, $request);
            default:
                $this->getDispatcher()->handle404();
        }
    }

    /**
     * Show Pending Payments
     */
    public function pendingPayments($args, $request)
    {
        $context = $request->getContext();
        $user = $request->getUser();
        
        if (!$context || !$user) {
             $request->redirect(null, 'login');
             return;
        }

        // Get Payment Manager
        $paymentManager = \APP\core\Application::get()->getPaymentManager($context);
        $completedPaymentDAO = \PKP\db\DAORegistry::getDAO('OJSCompletedPaymentDAO');
        
        $pendingPayments = [];
        
        // 1. Check if Publication Fee is enabled
        // Note: We check generic 'publication fee'. OJS might have FastTrack, Submission, etc.
        // For simplicity, we focus on Publication Fee as it's the most common "Processing" fee.
        $publicationFeeEnabled = $paymentManager->isConfigured(); 
        // Logic: specific check logic depends on OJS version, usually via cost check
        
        // Get user's submissions
        $submissions = \APP\facades\Repo::submission()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->assignedTo([$user->getId()])
            ->getMany();

        // Initialize payment manager and DAOs
        $paymentManager = \APP\core\Application::get()->getPaymentManager($context);
        $completedPaymentDao = \PKP\db\DAORegistry::getDAO('OJSCompletedPaymentDAO'); /** @var OJSCompletedPaymentDAO $completedPaymentDao */
        $queuedPaymentDao = \PKP\db\DAORegistry::getDAO('QueuedPaymentDAO'); /** @var QueuedPaymentDAO $queuedPaymentDao */
        
        $pendingPayments = [];
        
        // Get all "Payment Required" notifications for this user in this context
        // Using Eloquent model directly as NotificationDAO is deprecated/removed
        $paymentNotifications = \PKP\notification\Notification::withUserId($user->getId())
            ->withContextId($context->getId())
            ->withType(\PKP\notification\Notification::NOTIFICATION_TYPE_PAYMENT_REQUIRED)
            ->get();
            
        // $paymentNotifications is already a Collection, so toArray returns array of models or raw attributes?
        // Eloquent get() return Collection. Iterating collection yields Model objects.
        // We can iterate directly or convert to array.
        // Let's iterate the collection directly in the loop below.


        // 1. Check for Completed Payment (Paid or Waived)
        foreach ($submissions as $submission) {
            $paymentStatus = null; 
            $completedPayment = null;
            $payUrl = '';
            $invoiceUrl = '';

            // Check if ANYONE paid for this submission (admin, author, etc)
            $completedPayment = $completedPaymentDao->getByAssoc(null, $paymentManager::PAYMENT_TYPE_PUBLICATION, $submission->getId());
            
            if ($completedPayment) {
                if ($completedPayment->getAmount() > 0) {
                    $paymentStatus = 'Paid';
                    $invoiceUrl = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'emspubcore', 'downloadInvoice', null, ['submissionId' => $submission->getId()]);
                } else {
                    $paymentStatus = 'Waived';
                }

                // Cleanup: If paid/waived, remove any pending payment notifications for this submission
                foreach ($paymentNotifications as $notification) {
                     if ($notification->assocType == \APP\core\Application::ASSOC_TYPE_QUEUED_PAYMENT) {
                          $queuedPayment = $queuedPaymentDao->getById($notification->assocId);
                          if ($queuedPayment && 
                              $queuedPayment->getType() == $paymentManager::PAYMENT_TYPE_PUBLICATION && 
                              $queuedPayment->getAssocId() == $submission->getId()) {
                                  // This notification is stale (already paid/waived), delete it
                                  $notification->delete();
                          }
                     }
                }
            } else {
                // 2. Check for Pending Payment Request (via Notification)
                foreach ($paymentNotifications as $notification) {
                     if ($notification->assocType == \APP\core\Application::ASSOC_TYPE_QUEUED_PAYMENT) {
                          $queuedPayment = $queuedPaymentDao->getById($notification->assocId);
                          // Check if valid qp, type is publication, and matches submission
                          if ($queuedPayment && 
                              $queuedPayment->getType() == $paymentManager::PAYMENT_TYPE_PUBLICATION && 
                              $queuedPayment->getAssocId() == $submission->getId()) {
                                  
                               $paymentStatus = 'Pending';
                               $payUrl = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'payment', 'pay', [$queuedPayment->getId()]);
                               break; 
                          }
                     }
                }
            }
            
            if ($paymentStatus) {
                // Determine the actual amount
                $defaultFee = (float) $context->getData('publicationFee');
                if ($completedPayment) {
                    $actualAmount = $completedPayment->getAmount();
                } elseif (isset($queuedPayment) && $queuedPayment) {
                    $actualAmount = $queuedPayment->getAmount();
                } else {
                    $actualAmount = $defaultFee;
                }
                
                // Check if this is a discounted payment
                $isDiscounted = ($actualAmount > 0 && $actualAmount < $defaultFee);
                
                $pendingPayments[] = [
                    'id' => $submission->getId(),
                    'title' => $submission->getCurrentPublication()->getLocalizedTitle(),
                    'amount' => $actualAmount,
                    'currency' => $context->getData('currency'),
                    'status' => $paymentStatus,
                    'payUrl' => $payUrl,
                    'invoiceUrl' => $invoiceUrl,
                    'date' => $completedPayment ? $completedPayment->getTimestamp() : null,
                    'isDiscounted' => $isDiscounted,
                    'originalFee' => $defaultFee,
                ];
            }
        }
        
        // Pagination settings
        $itemsPerPage = 10;
        $currentPage = max(1, (int) $request->getUserVar('page'));
        $totalItems = count($pendingPayments);
        $totalPages = max(1, ceil($totalItems / $itemsPerPage));
        
        // Ensure current page is within bounds
        $currentPage = min($currentPage, $totalPages);
        
        // Slice array for current page
        $offset = ($currentPage - 1) * $itemsPerPage;
        $pagedPayments = array_slice($pendingPayments, $offset, $itemsPerPage);
        
        // Calculate pagination display range
        $startItem = $totalItems > 0 ? $offset + 1 : 0;
        $endItem = min($offset + $itemsPerPage, $totalItems);
        
        $templateMgr = \APP\template\TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $templateMgr->setupBackendPage();
        
        // Build base URL for pagination
        $baseUrl = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'emspubcore', 'pendingPayments');
        
        $templateMgr->assign([
            'pendingPayments' => $pagedPayments,
            'pageTitle' => 'Article Processing Payments',
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'startItem' => $startItem,
            'endItem' => $endItem,
            'baseUrl' => $baseUrl,
        ]);
        
        return $templateMgr->display($this->getPlugin()->getTemplateResource('pendingPayments.tpl'));
    }

    /**
     * Handle Manual Submission Payment Redirect
     * This acts as a bridge to trigger the OJS payment flow or custom flow
     */
    public function paySubmission($args, $request)
    {
        $context = $request->getContext();
        $user = $request->getUser();
        $submissionId = (int) $request->getUserVar('submissionId');
        
        if (!$context || !$user || !$submissionId) {
            $request->redirect(null, 'index');
            return;
        }

        // We delegate to the standard OJS payment system which will pick up our Stripe plugin
        // Standard OJS Payment Link: /payment/pay/types-publication-assoc-{submissionId}
        // Actually, we should construct the payment plugin URL directly or use the PaymentManager queue.
        
        $paymentManager = \APP\core\Application::get()->getPaymentManager($context);
        $queuedPayment = $paymentManager->createQueuedPayment(
            $request,
            $paymentManager::PAYMENT_TYPE_PUBLICATION,
            $user->getId(),
            $submissionId,
            $paymentManager->getCost($paymentManager::PAYMENT_TYPE_PUBLICATION),
            $paymentManager->getCurrency()
        );
        
        $paymentManager->queuePayment($queuedPayment);
        
        // The queuePayment usually redirects to the payment plugin. 
        // If not, we manually ensure redirect.
        return; 
    }

    /**
     * Show Plans (Upgrade Page)
     */
    public function showPlans($args, $request)
    {
        $context = $request->getContext();
        if (!$context) {
             $request->redirect(null, 'index');
             return;
        }

        // Check permission (Journal Manager or Site Admin)
        if (!\PKP\security\Validation::isJournalManager($context->getId()) && !\PKP\security\Validation::isSiteAdmin()) {
             header('HTTP/1.0 403 Forbidden');
             echo 'Access denied.';
             exit;
        }

        // 1. Get Current Plan
        $journalPlanDAO = \PKP\db\DAORegistry::getDAO('JournalPlanDAO');
        $currentPlan = $journalPlanDAO->getByJournalId($context->getId());

        // 2. Get Usage
        $usageDAO = $this->getPlugin()->getSubmissionUsageDAO();
        $currentUsage = $usageDAO->getYearlyCount($context->getId());

        // 3. Get Limits
        $limits = \APP\plugins\generic\emspubcore\EmsPubCorePlugin::getPlanLimits();
        
        // 4. Get Prices
        $prices = [
            'free' => ['monthly' => 0, 'yearly' => 0],
            'basic' => ['monthly' => 2900, 'yearly' => 29000],
            'premium' => ['monthly' => 9900, 'yearly' => 99000]
        ];

        $templateMgr = \PKP\template\TemplateManager::getManager($request);
        $templateMgr->assign([
            'currentPlan' => $currentPlan,
            'currentUsage' => $currentUsage,
            'planLimits' => $limits,
            'planPrices' => $prices,
            'stripePublishableKey' => $this->getPlugin()->getSetting(0, 'stripePublishableKey'),
        ]);

        return $templateMgr->display($this->getPlugin()->getTemplateResource('plans.tpl'));
    }

    /**
     * Handle Checkout (Redirect to Stripe)
     */
    public function handleCheckout($args, $request)
    {
        $journalId = (int) $request->getUserVar('journalId');
        if ($journalId) {
             $contextDao = \PKP\db\DAORegistry::getDAO('JournalDAO');
             $context = $contextDao->getById($journalId);
        } else {
             $context = $request->getContext();
        }

        if (!$context) {
            echo 'Context not found.';
            return;
        }

        if (!\PKP\security\Validation::isJournalManager($context->getId()) && !\PKP\security\Validation::isSiteAdmin()) {
             echo 'Prior permission required.';
             return;
        }

        $planType = $request->getUserVar('plan');
        $billingCycle = $request->getUserVar('billing'); 

        $validPlans = ['basic', 'premium'];
        if (!in_array($planType, $validPlans)) {
             echo 'Invalid plan.'; 
             return;
        }

        $prices = [
            'basic' => ['monthly' => 2900, 'yearly' => 29000],
            'premium' => ['monthly' => 9900, 'yearly' => 99000]
        ];
        
        $amount = $prices[$planType][$billingCycle] ?? null;
        if (!$amount) {
            echo 'Invalid billing cycle.';
            return;
        }

        $secretKey = $this->getPlugin()->getSetting(0, 'stripeSecretKey');
        if (!$secretKey) {
            echo 'Payment gateway not configured.';
            return;
        }
        
        // Attempt to load Stripe
        if (!class_exists('\Stripe\Stripe')) {
             if (file_exists($this->getPlugin()->getPluginPath() . '/vendor/autoload.php')) {
                 require_once($this->getPlugin()->getPluginPath() . '/vendor/autoload.php');
             }
        }

        if (!class_exists('\Stripe\Stripe')) {
             echo 'Stripe library not found.';
             return;
        }

        \Stripe\Stripe::setApiKey($secretKey);

        $successUrl = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'emspubcore', 'success', null, ['session_id' => '{CHECKOUT_SESSION_ID}', 'journalId' => $context->getId()]);
        $cancelUrl = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'emspubcore', 'cancel', null, ['journalId' => $context->getId()]);

        try {
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => ucfirst($planType) . ' Plan (' . ucfirst($billingCycle) . ')',
                        ],
                        'unit_amount' => $amount,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'journal_id' => $context->getId(),
                    'plan_type' => $planType,
                    'billing_cycle' => $billingCycle,
                ],
            ]);

            header("Location: " . $session->url);
            exit;
        } catch (\Exception $e) {
            echo 'Error creating Stripe session: ' . $e->getMessage();
        }
    }

    /**
     * Handle Success
     */
    public function handleSuccess($args, $request)
    {
        $context = $request->getContext();
        $sessionId = $request->getUserVar('session_id');
        
        if (!$sessionId || !$context) {
             $request->redirect(null, 'index');
             return;
        }

        $secretKey = $this->getPlugin()->getSetting(0, 'stripeSecretKey');
        
        if (!class_exists('\Stripe\Stripe')) {
             if (file_exists($this->getPlugin()->getPluginPath() . '/vendor/autoload.php')) {
                 require_once($this->getPlugin()->getPluginPath() . '/vendor/autoload.php');
             }
        }
        
        \Stripe\Stripe::setApiKey($secretKey);

        try {
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            
            // Basic verification
            if ($session->payment_status === 'paid') {
                $journalId = $context->getId();
                
                // Retrieve metadata
                $planType = $session->metadata->plan_type ?? 'free';
                $billingCycle = $session->metadata->billing_cycle ?? 'monthly';
                
                // Update Plan in DB
                $journalPlanDAO = \PKP\db\DAORegistry::getDAO('JournalPlanDAO');
                $limits = \APP\plugins\generic\emspubcore\EmsPubCorePlugin::getPlanLimits();
                $limit = $limits[$planType] ?? 100;

                $startDate = \PKP\core\Core::getCurrentDate();
                $duration = ($billingCycle === 'yearly') ? '+1 year' : '+1 month';
                $endDate = date('Y-m-d H:i:s', strtotime($duration, strtotime($startDate)));

                $plan = $journalPlanDAO->getByJournalId($journalId);
                
                if ($plan) {
                    $plan->setPlanType($planType);
                    $plan->setBillingCycle($billingCycle);
                    $plan->setSubmissionsLimit($limit);
                    $plan->setPlanStartDate($startDate);
                    $plan->setPlanEndDate($endDate);
                    $plan->setStripeCustomerId($session->customer);
                    $journalPlanDAO->updateObject($plan);
                } else {
                    $newPlan = $journalPlanDAO->newDataObject();
                    $newPlan->setJournalId($journalId);
                    $newPlan->setPlanType($planType);
                    $newPlan->setBillingCycle($billingCycle);
                    $newPlan->setSubmissionsLimit($limit);
                    $newPlan->setPlanStartDate($startDate);
                    $newPlan->setPlanEndDate($endDate);
                    $newPlan->setStripeCustomerId($session->customer);
                    $newPlan->setIsActive(1);
                    $journalPlanDAO->insertObject($newPlan);
                }
                
                // Log Payment
                 $paymentWrapperDAO = $this->getPlugin()->getPaymentHistoryDAO();
                 $paymentWrapperDAO->logPayment([
                    'journal_id' => $journalId,
                    'amount' => $session->amount_total / 100,
                    'currency' => strtoupper($session->currency),
                    'stripe_payment_intent_id' => $session->payment_intent,
                    'status' => 'succeeded',
                    'payment_date' => \PKP\core\Core::getCurrentDate(),
                    'plan_type' => $planType
                 ]);

                $templateMgr = \PKP\template\TemplateManager::getManager($request);
                $templateMgr->assign('pageTitle', 'Payment Successful');
                $templateMgr->assign('message', 'Thank you! Your payment was successful and your plan has been upgraded.');
                $templateMgr->assign('backLink', $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'management', 'settings', ['workflow']));
                $templateMgr->assign('backLinkLabel', 'Back to Settings');
                $templateMgr->display('frontend/pages/message.tpl');
                return;
            }
        } catch (\Exception $e) {
            error_log('Stripe Verification Failed: ' . $e->getMessage());
        }

        echo '<h3>Payment verification failed. Please contact support.</h3>';
    }

    /**
     * Handle Cancel
     */
    public function handleCancel($args, $request)
    {
        $templateMgr = \PKP\template\TemplateManager::getManager($request);
        $templateMgr->assign('pageTitle', 'Payment Cancelled');
        $templateMgr->assign('message', 'The payment process was cancelled. No charges were made.');
        $templateMgr->assign('backLink', $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'emspubcore', 'plans'));
        $templateMgr->assign('backLinkLabel', 'Try Again');
        $templateMgr->display('frontend/pages/message.tpl');
    }

    /**
     * Handle Manual Plan Assignment (Updated for Dynamic Plans)
     */
    public function assignPlan($args, $request)
    {
        $journalId = (int) $request->getUserVar('journalId');
        
        // Allow Site Admin, Journal Manager, or Editor of this journal
        $isAuthorized = \PKP\security\Validation::isSiteAdmin();
        if (!$isAuthorized && $journalId) {
            $isAuthorized = \PKP\security\Validation::isAuthorized(\PKP\security\Role::ROLE_ID_MANAGER, $journalId) ||
                           \PKP\security\Validation::isAuthorized(\PKP\security\Role::ROLE_ID_SUB_EDITOR, $journalId);
        }
        
        if (!$isAuthorized) {
            header('HTTP/1.0 403 Forbidden');
            echo 'Access denied.';
            exit;
        }
        $planKey = $request->getUserVar('planType');

        if (!$journalId || !$planKey) {
            $this->getDispatcher()->handle404();
            return;
        }

        $journalPlanDAO = \PKP\db\DAORegistry::getDAO('JournalPlanDAO');
        
        // Fetch dynamic limits
        $limits = \APP\plugins\generic\emspubcore\EmsPubCorePlugin::getPlanLimits();
        if (!isset($limits[$planKey])) {
            $planKey = 'free';
        }
        $limit = $limits[$planKey];

        $plan = $journalPlanDAO->getByJournalId($journalId);

        $startDate = \PKP\core\Core::getCurrentDate();
        $endDate = date('Y-m-d H:i:s', strtotime('+1 year', strtotime($startDate)));

        if ($plan) {
            $plan->setPlanType($planKey);
            $plan->setSubmissionsLimit($limit);
            $plan->setBillingCycle('yearly');
            $plan->setPlanStartDate($startDate);
            $plan->setPlanEndDate($endDate);
            $plan->setIsActive(1);
            $journalPlanDAO->updateObject($plan);
        } else {
            $newPlan = $journalPlanDAO->newDataObject();
            $newPlan->setJournalId($journalId);
            $newPlan->setPlanType($planKey);
            $newPlan->setSubmissionsLimit($limit);
            $newPlan->setBillingCycle('yearly');
            $newPlan->setPlanStartDate($startDate);
            $newPlan->setPlanEndDate($endDate);
            $newPlan->setIsActive(1);
            $journalPlanDAO->insertObject($newPlan);
        }

        // Redirect back to appropriate page
        $context = $request->getContext();
        if ($context && $context->getId() == $journalId) {
             // Pass array for path argument
             $request->redirect(null, 'management', 'settings', ['workflow'], null, 'emspubcorePlan');
        } else {
             // Admin Context
             $request->redirect(null, 'admin', 'wizard', [$journalId], null, 'emspubcorePlan');
        }
    }

    /**
     * Save (Create/Update) a Site Plan
     */
    public function savePlan($args, $request)
    {
        if (!\PKP\security\Validation::isSiteAdmin()) {
            return;
        }

        $planDAO = \PKP\db\DAORegistry::getDAO('PlanDAO');
        $planId = (int) $request->getUserVar('planId');
        
        $name = $request->getUserVar('name');
        $price = (float) $request->getUserVar('price');
        $discountedPrice = $request->getUserVar('discounted_price');
        $discountedPrice = ($discountedPrice === '' || $discountedPrice === null) ? null : (float) $discountedPrice;
        $limit = (int) $request->getUserVar('submission_limit');

        if ($planId) {
            $plan = $planDAO->getById($planId);
        } else {
            $plan = $planDAO->newDataObject();
        }

        $plan->setName($name);
        $plan->setPrice($price);
        $plan->setDiscountedPrice($discountedPrice);
        $plan->setSubmissionLimit($limit);
        
        if ($planId) {
            $planDAO->updateObject($plan);
        } else {
            $planDAO->insertObject($plan);
        }

        $request->redirect(null, 'admin', 'settings', null, null, 'emspubcoreSitePlans');
    }

    /**
     * Delete a Site Plan
     */
    public function deletePlan($args, $request)
    {
        if (!\PKP\security\Validation::isSiteAdmin()) {
            return;
        }

        $planId = (int) $request->getUserVar('planId');
        if ($planId) {
            $planDAO = \PKP\db\DAORegistry::getDAO('PlanDAO');
            $planDAO->deleteObject($planId);
        }

        $request->redirect(null, 'admin', 'settings', null, null, 'emspubcoreSitePlans');
    }

    /**
     * Save Gateway Settings
     */
    public function saveGatewaySettings($args, $request)
    {
        try {
            if (!\PKP\security\Validation::isSiteAdmin()) {
                echo '<h1>Access Denied</h1><p>You must be a site administrator to perform this action.</p>';
                return;
            }

            $publishableKey = $request->getUserVar('stripePublishableKey');
            $secretKey = $request->getUserVar('stripeSecretKey');
            $webhookSecret = $request->getUserVar('stripeWebhookSecret');
            $testMode = $request->getUserVar('stripeTestMode') ? 1 : 0;
            
            $plugin = $this->getPlugin();
            if (!$plugin) {
                 throw new \Exception('Plugin instance could not be loaded.');
            }

            $plugin->updateSetting(0, 'stripePublishableKey', $publishableKey, 'string');
            $plugin->updateSetting(0, 'stripeSecretKey', $secretKey, 'string');
            $plugin->updateSetting(0, 'stripeWebhookSecret', $webhookSecret, 'string');
            $plugin->updateSetting(0, 'stripeTestMode', $testMode, 'bool');
            
            // Redirect using standard method
            $url = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'admin', 'settings', null, null, 'emspubcorePaymentGateways');
            $request->redirectUrl($url);
            
        } catch (\Exception $e) {
            echo '<h1>Error Saving Settings</h1>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<pre>' . $e->getTraceAsString() . '</pre>';
        }
    }

    /**
     * Download Invoice
     */
    public function downloadInvoice($args, $request)
    {
        if (!$request->getUser()) {
            $request->redirect(null, 'login');
            return;
        }
        
        $submissionId = $request->getUserVar('submissionId');
        if (!$submissionId) $request->redirect(null, 'index');
        
        $context = $request->getContext();
        $submission = \APP\facades\Repo::submission()->get($submissionId);
        if (!$submission || $submission->getData('contextId') != $context->getId()) {
            $this->getDispatcher()->handle404();
        }
        
        $completedPaymentDAO = \PKP\db\DAORegistry::getDAO('OJSCompletedPaymentDAO');
        $paymentManager = \APP\core\Application::get()->getPaymentManager($context);
        $payment = $completedPaymentDAO->getByAssoc(null, $paymentManager::PAYMENT_TYPE_PUBLICATION, $submission->getId());
        
        if (!$payment) {
            $request->redirect(null, null, 'pendingPayments');
            return;
        }
        
        $templateMgr = \APP\template\TemplateManager::getManager($request);
        $templateMgr->assign([
            'pageTitle' => 'Invoice #' . $payment->getId(),
            'submission' => $submission,
            'payment' => $payment,
            'journal' => $context,
            'user' => $request->getUser(),
            'dateClean' => date('d M Y', strtotime($payment->getTimestamp()))
        ]);
        
        return $templateMgr->display($this->getPlugin()->getTemplateResource('invoice.tpl'));
    }
}
