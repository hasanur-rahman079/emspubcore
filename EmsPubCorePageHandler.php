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
use Paddle\SDK\Environment;
use Paddle\SDK\Client as PaddleClient;
use Paddle\SDK\Options as PaddleOptions;

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
        
        // Role assignments for all operations
        // Authors can access their invoices
        $this->addRoleAssignment(
            [\PKP\security\Role::ROLE_ID_AUTHOR],
            ['pendingPayments', 'downloadInvoice']
        );
        
        // Managers and Admins can access admin functions
        $this->addRoleAssignment(
            [\PKP\security\Role::ROLE_ID_MANAGER, \PKP\security\Role::ROLE_ID_SITE_ADMIN, \PKP\security\Role::ROLE_ID_SUBSCRIPTION_MANAGER],
            ['pendingPayments', 'pendingPaymentsAdmin', 'downloadInvoice', 'plans', 'checkout', 'success', 'cancel', 'assignPlan', 'savePlan', 'saveJournalDiscount', 'paddleCheckout', 'paddleSuccess', 'getPaddleApcProductId', 'savePaddleApcProductId']
        );
        
        // Webhook is public (no auth needed)
        $this->addRoleAssignment(
            [],
            ['webhook']
        );
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
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new \PKP\security\authorization\ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Route requests to the appropriate handler
     *
     * @param array $args
     * @param \APP\core\Request $request
     */
    public function index($args, $request)
    {
        $this->getDispatcher()->handle404();
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
            'pageTitle' => 'Article Processing Charges (APCs)',
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
     * Show Pending Payments for Admin/Manager (Grid Tab)
     */
    public function pendingPaymentsAdmin($args, $request)
    {
        $context = $request->getContext();
        $user = $request->getUser();
        
        if (!$context || !$user) {
             $request->redirect(null, 'login');
             return;
        }

        // Check permission: JM, Admin, or SubManager
        $isAuthorized = \PKP\security\Validation::isSiteAdmin() || 
                       \PKP\security\Validation::isAuthorized(\PKP\security\Role::ROLE_ID_MANAGER, $context->getId()) || 
                       \PKP\security\Validation::isAuthorized(\PKP\security\Role::ROLE_ID_SUBSCRIPTION_MANAGER, $context->getId());

        if (!$isAuthorized) {
            header('HTTP/1.0 403 Forbidden');
            echo 'Access denied.';
            exit;
        }

        // DEBUG: Check if we reach this point
        error_log('EmsPubCore: pendingPaymentsAdmin - User authorized, proceeding...');

        // Get payment manager and DAOs
        $paymentManager = \APP\core\Application::get()->getPaymentManager($context);
        $completedPaymentDao = \PKP\db\DAORegistry::getDAO('OJSCompletedPaymentDAO');
        $queuedPaymentDao = \PKP\db\DAORegistry::getDAO('QueuedPaymentDAO');
        
        $pendingPayments = [];
        
        // Only get submissions that have an active payment request notification
        // This is the key change - only show articles where payment was explicitly requested
        $paymentNotifications = \PKP\notification\Notification::where('context_id', (int) $context->getId())
            ->where('type', \PKP\notification\Notification::NOTIFICATION_TYPE_PAYMENT_REQUIRED)
            ->get();

        // Track which submissions we've already processed (avoid duplicates)
        $processedSubmissions = [];

        // Iterate through payment notifications, not all submissions
        foreach ($paymentNotifications as $notification) {
            if ($notification->assocType != \APP\core\Application::ASSOC_TYPE_QUEUED_PAYMENT) {
                continue;
            }
            
            $queuedPayment = $queuedPaymentDao->getById($notification->assocId);
            if (!$queuedPayment || $queuedPayment->getType() != $paymentManager::PAYMENT_TYPE_PUBLICATION) {
                continue;
            }
            
            $submissionId = $queuedPayment->getAssocId();
            
            // Skip if already processed
            if (in_array($submissionId, $processedSubmissions)) {
                continue;
            }
            $processedSubmissions[] = $submissionId;
            
            // Check if already paid
            $completedPayment = $completedPaymentDao->getByAssoc(null, $paymentManager::PAYMENT_TYPE_PUBLICATION, $submissionId);
            if ($completedPayment) {
                // Already paid - could delete stale notification here
                continue;
            }
            
            // Get submission
            $submission = \APP\facades\Repo::submission()->get($submissionId);
            if (!$submission || $submission->getData('contextId') != $context->getId()) {
                continue;
            }
            
            // Get title - skip incomplete
            $publication = $submission->getCurrentPublication();
            if (!$publication) continue;
            
            $title = $publication->getLocalizedTitle();
            if (empty(trim($title))) continue;
            
            $actualAmount = $queuedPayment->getAmount();
            if ($actualAmount <= 0) continue;

            $viewUrl = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'workflow', 'index', [$submission->getId(), 4]); 

            $pendingPayments[] = [
                'id' => $submission->getId(),
                'title' => $title,
                'amount' => $actualAmount,
                'currency' => $context->getData('currency'),
                'paymentType' => 'Publication Fee',
                'viewUrl' => $viewUrl,
            ];
        }
        
        // Sort by ID descending (most recent first)
        usort($pendingPayments, function($a, $b) {
            return $b['id'] - $a['id'];
        });
        
        // Pagination
        $itemsPerPage = 15;
        $currentPage = max(1, (int) $request->getUserVar('page'));
        $totalItems = count($pendingPayments);
        $totalPages = max(1, ceil($totalItems / $itemsPerPage));
        $currentPage = min($currentPage, $totalPages);
        $offset = ($currentPage - 1) * $itemsPerPage;
        $pagedPayments = array_slice($pendingPayments, $offset, $itemsPerPage);
        
        $templateMgr = \APP\template\TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $templateMgr->setupBackendPage();
        
        $baseUrl = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'emspubcore', 'pendingPaymentsAdmin');
        
        $templateMgr->assign([
            'pendingPayments' => $pagedPayments,
            'pageTitle' => 'Pending Publication Payments (' . $totalItems . ')',
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'startItem' => $totalItems > 0 ? $offset + 1 : 0,
            'endItem' => min($offset + $itemsPerPage, $totalItems),
            'baseUrl' => $baseUrl,
        ]);
        
        // Return JSON message response for OJS tab AJAX loading
        $html = $templateMgr->fetch($this->getPlugin()->getTemplateResource('pendingPaymentsAdmin.tpl'));
        return new \PKP\core\JSONMessage(true, $html);
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
    public function plans($args, $request)
    {
        $context = $request->getContext();
        if (!$context) {
             $request->redirect(null, 'index');
             return;
        }

        // Check permission (Journal Manager or Site Admin)
        if (!\PKP\security\Validation::isAuthorized(\PKP\security\Role::ROLE_ID_MANAGER, $context->getId()) && !\PKP\security\Validation::isSiteAdmin()) {
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
            'stripePublishableKey' => $this->getPlugin()->getSetting(null, 'stripePublishableKey'),
        ]);

        return $templateMgr->display($this->getPlugin()->getTemplateResource('plans.tpl'));
    }

    /**
     * Handle Checkout (Redirect to Stripe/Paddle)
     */
    public function checkout($args, $request)
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

        if (!\PKP\security\Validation::isAuthorized(\PKP\security\Role::ROLE_ID_MANAGER, $context->getId()) && !\PKP\security\Validation::isSiteAdmin()) {
             echo 'Prior permission required.';
             return;
        }

        $planType = $request->getUserVar('plan');
        $billingCycle = $request->getUserVar('billing'); 

        if ($planType === 'free' || strpos($planType, 'free') !== false) {
             echo 'Free plans do not require checkout.'; 
             return;
        }
        
        $planDAO = $this->getPlugin()->getPlanDAO();
        $plans = $planDAO->getAll();
        $targetPlan = null;
        foreach ($plans as $p) {
            if (strtolower(str_replace(' ', '', $p->getName())) === strtolower($planType)) {
                // If we haven't found a plan yet, or if this one has a Price ID while the previous one didn't
                if (!$targetPlan || ($p->getPaddlePriceId() && !$targetPlan->getPaddlePriceId())) {
                    $targetPlan = $p;
                }
                // If we found a match and it has a Price ID, we are good
                if ($targetPlan->getPaddlePriceId()) break;
            }
        }

        $amount = null;
        $paddlePriceId = null;

        if ($targetPlan) {
            $baseAmount = ($billingCycle === 'yearly' && $targetPlan->getDiscountedPrice()) 
                ? (float) $targetPlan->getDiscountedPrice()
                : (float) $targetPlan->getPrice();
            
            // Apply Journal-wide Discount Percentage
            $journalDiscount = (int) $this->getPlugin()->getSetting($context->getId(), 'journalDiscount');
            if ($journalDiscount > 0) {
                $baseAmount = $baseAmount * (1 - ($journalDiscount / 100));
            }

            $amount = (int) round($baseAmount * 100);
            $paddlePriceId = $targetPlan->getPaddlePriceId();
        }

        if (!$amount) {
            echo 'Invalid plan or billing cycle.';
            return;
        }

        $gateway = $this->getPlugin()->getSetting(0, 'subscriptionPaymentGateway') ?: 'stripe';


        error_log('EmsPubCore Debug: Retrieved Gateway: ' . $gateway);

        if ($gateway === 'paddle') {
            return $this->handlePaddleCheckout($context, $planType, $billingCycle, $amount, $paddlePriceId, $request);
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

        // OJS url() method URL-encodes {CHECKOUT_SESSION_ID}, which breaks Stripe.
        // We use a temporary string and replace it manually to preserve the literal placeholder.
        $successUrlRaw = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'emspubcore', 'success', null, ['session_id' => 'REPLACE_ME', 'journalId' => $context->getId()], null, false, true);
        $successUrl = str_replace('REPLACE_ME', '{CHECKOUT_SESSION_ID}', $successUrlRaw);
        
        $cancelUrl = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'emspubcore', 'cancel', null, ['journalId' => $context->getId()], null, false, true);

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
    public function success($args, $request)
    {
        try {
            $context = $request->getContext();
            $sessionId = $request->getUserVar('session_id');
            $journalId = (int) $request->getUserVar('journalId');

            // Fallback context detection for Stripe redirects
            if (!$context && $journalId) {
                $contextDao = \PKP\db\DAORegistry::getDAO('JournalDAO');
                $context = $contextDao->getById($journalId);
            }
            
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
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            
            if ($session->payment_status === 'paid') {
                $journalId = $context->getId();
                
                $planType = $session->metadata->plan_type ?? 'free';
                $billingCycle = $session->metadata->billing_cycle ?? 'yearly';
                
                $journalPlanDAO = $this->getPlugin()->getJournalPlanDAO();
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
                
                $paymentWrapperDAO = $this->getPlugin()->getPaymentHistoryDAO();
                $paymentWrapperDAO->logPayment([
                    'journal_id' => (int) $journalId,
                    'amount' => $session->amount_total,
                    'tax_amount' => $session->total_details->amount_tax ?? 0,
                    'currency' => strtoupper($session->currency),
                    'stripe_payment_intent_id' => $session->payment_intent,
                    'status' => 'succeeded',
                    'payment_date' => date('Y-m-d H:i:s'),
                    'plan_type' => $planType
                ]);

                $backLink = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'management', 'settings', ['workflow'], null, 'emspubcorePlan');
                
                $messageTranslated = __('plugins.generic.emspubcore.success.message');
                if (strpos($messageTranslated, '##') === 0) {
                    $messageTranslated = 'Thank you! Your payment was successful and your plan has been upgraded.';
                }
                $messageTranslated .= '<script>setTimeout(function(){ window.location.href="' . $backLink . '"; }, 5000);</script>';
                $messageTranslated .= '<div style="margin-top: 20px; font-style: italic; color: #666;">Redirecting to settings in 5 seconds...</div>';

                $templateMgr = \APP\template\TemplateManager::getManager($request);
                $templateMgr->assign([
                    'pageTitle' => 'plugins.generic.emspubcore.success.title',
                    'messageTranslated' => $messageTranslated,
                    'backLink' => $backLink,
                    'backLinkLabel' => 'plugins.generic.emspubcore.success.backToSettings'
                ]);
                $templateMgr->display('frontend/pages/message.tpl');
                return;
            } else {
                $templateMgr = \APP\template\TemplateManager::getManager($request);
                $templateMgr->assign([
                    'pageTitle' => 'plugins.generic.emspubcore.success.pending.title',
                    'message' => 'plugins.generic.emspubcore.success.pending.message',
                    'messageParams' => ['status' => $session->payment_status],
                    'backLink' => $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'management', 'settings', ['workflow'], null, 'emspubcorePlan'),
                    'backLinkLabel' => 'plugins.generic.emspubcore.success.backToSettings'
                ]);
                $templateMgr->display('frontend/pages/message.tpl');
                return;
            }
        } catch (\Exception $e) {
            $templateMgr = \APP\template\TemplateManager::getManager($request);
            $templateMgr->assign([
                'pageTitle' => 'plugins.generic.emspubcore.success.error.title',
                'message' => 'plugins.generic.emspubcore.success.error.message',
                'messageParams' => ['error' => $e->getMessage()],
                'backLink' => $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'management', 'settings', ['workflow'], null, 'emspubcorePlan'),
                'backLinkLabel' => 'plugins.generic.emspubcore.success.backToSettings'
            ]);
            $templateMgr->display('frontend/pages/message.tpl');
            return;
        }
    }

    /**
     * Handle Cancel
     */
    public function cancel($args, $request)
    {
        $templateMgr = \APP\template\TemplateManager::getManager($request);
        $templateMgr->assign([
            'pageTitle' => 'plugins.generic.emspubcore.cancel.title',
            'message' => 'plugins.generic.emspubcore.cancel.message',
            'backLink' => $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'management', 'settings', ['workflow'], null, 'emspubcorePlan'),
            'backLinkLabel' => 'plugins.generic.emspubcore.cancel.tryAgain'
        ]);
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
            // Fallback to the first available plan if 'free' is not found
            if (isset($limits['free'])) {
                $planKey = 'free';
            } else {
                $keys = array_keys($limits);
                $planKey = $keys[0] ?? 'free';
            }
        }
        $limit = $limits[$planKey] ?? 0;

        // Security Check: Only allow direct assignment if it's a free plan ($0)
        // Site Admins can override this.
        if (!\PKP\security\Validation::isSiteAdmin()) {
            $planDAO = $this->getPlanDAO();
            $allPlans = $planDAO->getAll();
            $targetPlan = null;
            foreach ($allPlans as $p) {
                if (strtolower(str_replace(' ', '', $p->getName())) === strtolower(str_replace(' ', '', $planKey))) {
                    $targetPlan = $p;
                    break;
                }
            }

            if ($targetPlan) {
                $basePrice = $targetPlan->getDiscountedPrice() ?: $targetPlan->getPrice();
                $journalDiscount = (int)$this->getPlugin()->getSetting($journalId, 'journalDiscount');
                $finalPrice = $basePrice * (1 - $journalDiscount / 100);
                
                if ($finalPrice > 0) {
                     header('HTTP/1.0 403 Forbidden');
                     echo 'Paid plans must be purchased via Checkout.';
                     exit;
                }
            }
        }

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
        $paddlePriceId = $request->getUserVar('paddle_price_id');

        if ($planId) {
            $plan = $planDAO->getById($planId);
        } else {
            $plan = $planDAO->newDataObject();
        }

        $plan->setName($name);
        $plan->setPrice($price);
        $plan->setDiscountedPrice($discountedPrice);
        $plan->setSubmissionLimit($limit);
        $plan->setPaddlePriceId($paddlePriceId);
        
        if ($planId) {
            $planDAO->updateObject($plan);
        } else {
            $planDAO->insertObject($plan);
        }

        $request->redirect(null, 'admin', 'settings', null, null, 'emspubcoreSitePlans');
    }

    /**
     * Save Journal Discount (AJAX)
     */
    public function saveJournalDiscount($args, $request)
    {
        if (!\PKP\security\Validation::isSiteAdmin()) {
            return new \PKP\core\JSONMessage(false, 'Access denied.');
        }

        $journalId = (int) $request->getUserVar('journalId');
        $discount = (int) $request->getUserVar('discount');

        if (!$journalId) {
            return new \PKP\core\JSONMessage(false, 'Invalid journal ID.');
        }

        // Clamp discount between 0 and 100
        $discount = max(0, min(100, $discount));

        $this->getPlugin()->updateSetting($journalId, 'journalDiscount', $discount, 'int');

        return new \PKP\core\JSONMessage(true);
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

            $plugin->updateSetting(0, 'paddleVendorId', $request->getUserVar('paddleVendorId'), 'string');
            $plugin->updateSetting(0, 'paddleApiKey', $request->getUserVar('paddleApiKey'), 'string');
            $plugin->updateSetting(0, 'paddleClientToken', $request->getUserVar('paddleClientToken'), 'string');
            $plugin->updateSetting(0, 'paddleTestMode', $request->getUserVar('paddleTestMode') ? 1 : 0, 'bool');
            $plugin->updateSetting(0, 'subscriptionPaymentGateway', $request->getUserVar('subscriptionPaymentGateway'), 'string');
            
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
            'paymentMethod' => (strpos($payment->getPayMethodPluginName(), 'paddle') !== false) ? 'Paddle' : 'Stripe',
            'journal' => $context,
            'user' => $request->getUser(),
            'dateClean' => date('d M Y', strtotime($payment->getTimestamp()))
        ]);
        
        $html = $templateMgr->fetch($this->getPlugin()->getTemplateResource('invoice.tpl'));
        
        // Load Dompdf autoloader
        require_once __DIR__ . '/vendor/autoload.php';

        // Dompdf Setup
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Stream the PDF
        $fileName = 'Invoice-' . $payment->getId() . '.pdf';
        $dompdf->stream($fileName, ["Attachment" => 1]);
        exit;
    }

    public function handlePaddleCheckout($context, $planType, $billingCycle, $amount, $paddlePriceId, $request)
    {
        $plugin = $this->getPlugin();
        $apiKey = $plugin->getSetting(0, 'paddleApiKey');
        $clientToken = $plugin->getSetting(0, 'paddleClientToken');
        $isTestMode = (bool) $plugin->getSetting(0, 'paddleTestMode');

        if (!$apiKey || !$clientToken) {
            echo 'Paddle gateway not configured.';
            return;
        }

        // Load Paddle SDK
        if (!class_exists('\Paddle\SDK\Client')) {
             if (file_exists($plugin->getPluginPath() . '/vendor/autoload.php')) {
                 require_once($plugin->getPluginPath() . '/vendor/autoload.php');
             }
        }

        if (!class_exists('\Paddle\SDK\Client')) {
             echo 'Paddle SDK not found. Please run composer update.';
             return;
        }

        // Get Price ID from plans table (robust lookup)
        $planDAO = \PKP\db\DAORegistry::getDAO('PlanDAO');
        $allPlans = $planDAO->getAll();
        $plan = null;
        
        // Normalize the requested plan type for comparison (lowercase, no spaces)
        $normRequested = strtolower(str_replace(' ', '', $planType));
        
        foreach ($allPlans as $p) {
            $normName = strtolower(str_replace(' ', '', $p->getName()));
            if ($normName === $normRequested) {
                $plan = $p;
                break;
            }
        }
        
        $productId = $plan ? $plan->getPaddlePriceId() : null;

        if (!$productId) {
            // Fallback to settings (legacy way)
            $settingKey = 'paddleProductId' . ucfirst($planType); // e.g., paddleProductIdBasic
            $productId = $plugin->getSetting(0, $settingKey);
        }

        if (!$productId) {
            echo 'Paddle Price ID not configured for ' . ucfirst($planType) . ' plan.';
            return;
        }

        try {
            $paddle = new PaddleClient(
                apiKey: $apiKey,
                options: new PaddleOptions($isTestMode ? Environment::SANDBOX : Environment::PRODUCTION)
            );

            // Determine if we are using a Product ID or Price ID
            $isProductId = str_starts_with($productId, 'pro_');

            $items = [];
            if ($isProductId) {
                // Use Product ID: Create a non-catalog price to support OJS discounts
                $items[] = [
                    'price' => [
                        'description' => ($plan ? $plan->getName() : ucfirst($planType)) . ' Plan (' . ucfirst($billingCycle) . ')',
                        'name' => ($plan ? $plan->getName() : ucfirst($planType)) . ' Plan',
                        'unit_price' => [
                            'amount' => (string) round($amount),
                            'currency_code' => 'USD'
                        ],
                        'tax_mode' => 'external',
                        'product_id' => $productId,
                        'quantity' => [
                            'minimum' => 1,
                            'maximum' => 1
                        ]
                    ],
                    'quantity' => 1
                ];
            } else {
                // Use Price ID: Use catalog price directly
                $items[] = [
                    'price_id' => $productId,
                    'quantity' => 1
                ];
            }

            // Create Transaction with Price Override using postRaw for maximum compatibility
            $response = $paddle->postRaw('/transactions', [
                'items' => $items,
                'custom_data' => [
                    'journalId' => (int) $context->getId(),
                    'planType' => $planType,
                    'billingCycle' => $billingCycle
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $transactionId = $responseData['data']['id'] ?? null;

            if (!$transactionId) {
                error_log('Paddle Transaction Creation Failed: ' . print_r($responseData, true));
                echo 'Failed to create Paddle transaction: ' . ($responseData['error']['detail'] ?? 'Invalid request');
                return;
            }

            $templateMgr = \APP\template\TemplateManager::getManager($request);
            $templateMgr->assign([
                'paddleClientToken' => $clientToken,
                'paddleEnv' => $isTestMode ? 'sandbox' : 'production',
                'transactionId' => $transactionId,
                'successUrl' => $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'emspubcore', 'paddleSuccess', [], ['journalId' => $context->getId()]),
                'cancelUrl' => $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'management', 'settings', ['workflow'], null, 'emspubcorePlan'),
            ]);
            
            $templateMgr->display($plugin->getTemplateResource('paddleLauncher.tpl'));
            exit;

        } catch (\Exception $e) {
            echo 'Paddle Error: ' . $e->getMessage();
            return;
        }
    }

    /**
     * Handle Paddle Success
     */
    public function paddleSuccess($args, $request)
    {
        $transactionId = $request->getUserVar('transaction_id');
        $journalId = (int) $request->getUserVar('journalId');
        $context = $request->getContext();
        
        if (!$transactionId || !$journalId) {
             $request->redirect(null, 'index');
             return;
        }

        $plugin = $this->getPlugin();
        $apiKey = $plugin->getSetting(0, 'paddleApiKey');
        $isTestMode = (bool) $plugin->getSetting(0, 'paddleTestMode');
        
        // Load Paddle SDK
        if (!class_exists('\Paddle\SDK\Client')) {
             if (file_exists($plugin->getPluginPath() . '/vendor/autoload.php')) {
                 require_once($plugin->getPluginPath() . '/vendor/autoload.php');
             }
        }

        try {
            $paddle = new PaddleClient(
                apiKey: $apiKey,
                options: new PaddleOptions($isTestMode ? Environment::SANDBOX : Environment::PRODUCTION)
            );

            // Fetch transaction
            $response = $paddle->getRaw("/transactions/{$transactionId}");
            $responseData = json_decode($response->getBody()->getContents(), true);
            $transaction = $responseData['data'] ?? null;
            
            if (!$transaction) {
                error_log("Paddle Success: Transaction not found: {$transactionId}");
                $request->redirect(null, 'index');
                return;
            }

            if ($transaction['status'] === 'completed' || $transaction['status'] === 'paid') {
                // Determine Plan Details from Custom Data
                $customData = (array) ($transaction['custom_data'] ?? []);
                $planType = $customData['planType'] ?? 'free';
                $billingCycle = $customData['billingCycle'] ?? 'yearly';

                // Calculate Carryover from previous plan
                $journalPlanDAO = \PKP\db\DAORegistry::getDAO('JournalPlanDAO');
                $usageDAO = \PKP\db\DAORegistry::getDAO('SubmissionUsageDAO');
                $planDAO = \PKP\db\DAORegistry::getDAO('PlanDAO');

                $oldPlan = $journalPlanDAO->getByJournalId($journalId);
                $remaining = 0;
                if ($oldPlan) {
                    $oldLimit = $oldPlan->getSubmissionsLimit();
                    $usage = ($oldPlan->getBillingCycle() === 'yearly') 
                        ? $usageDAO->getYearlyCount($journalId) 
                        : $usageDAO->getCurrentMonthCount($journalId);
                    $remaining = max(0, $oldLimit - $usage);
                }

                // Get base limit of NEW plan (robust lookup)
                $allPlans = $planDAO->getAll();
                $targetPlanBase = null;
                $normRequested = strtolower(str_replace(' ', '', $planType));
                foreach ($allPlans as $p) {
                    $normName = strtolower(str_replace(' ', '', $p->getName()));
                    if ($normName === $normRequested) {
                        $targetPlanBase = $p;
                        break;
                    }
                }
                
                $baseLimit = $targetPlanBase ? $targetPlanBase->getSubmissionLimit() : 100;
                
                // Carryover only if it's an UPGRADE (different plan type)
                $isUpgrade = ($oldPlan && strtolower($oldPlan->getPlanType()) !== strtolower($planType));
                $finalLimit = $baseLimit + ($isUpgrade ? $remaining : 0);

                $startDate = date('Y-m-d H:i:s');
                $duration = ($billingCycle === 'yearly') ? '+1 year' : '+1 month';
                $endDate = date('Y-m-d H:i:s', strtotime($duration, strtotime($startDate)));

                $plan = $oldPlan; // Re-use the object we already fetched
                
                if ($plan) {
                    $plan->setPlanType($planType);
                    $plan->setBillingCycle($billingCycle);
                    $plan->setSubmissionsLimit($finalLimit);
                    $plan->setPlanStartDate($startDate);
                    $plan->setPlanEndDate($endDate);
                    $plan->setPaddleSubscriptionId($transaction['subscription_id'] ?? null); 
                    $plan->setPaddleCustomerId($transaction['customer_id'] ?? null);
                    $plan->setIsActive(1);
                    $journalPlanDAO->updateObject($plan);
                } else {
                    $newPlan = $journalPlanDAO->newDataObject();
                    $newPlan->setJournalId($journalId);
                    $newPlan->setPlanType($planType);
                    $newPlan->setBillingCycle($billingCycle);
                    $newPlan->setSubmissionsLimit($finalLimit);
                    $newPlan->setPlanStartDate($startDate);
                    $newPlan->setPlanEndDate($endDate);
                    $newPlan->setPaddleSubscriptionId($transaction['subscription_id'] ?? null);
                    $newPlan->setPaddleCustomerId($transaction['customer_id'] ?? null);
                    $newPlan->setIsActive(1);
                    $journalPlanDAO->insertObject($newPlan);
                }

                // Log Payment
                try {
                    $paymentWrapperDAO = \PKP\db\DAORegistry::getDAO('PaymentHistoryDAO');
                    $paymentWrapperDAO->logPayment([
                        'journal_id' => $journalId,
                        'amount' => (int) ($transaction['details']['totals']['total'] ?? 0),
                        'tax_amount' => (int) ($transaction['details']['totals']['tax'] ?? 0),
                        'currency' => $transaction['currency_code'] ?? 'USD',
                        'transaction_id' => $transaction['id'],
                        'status' => 'succeeded',
                        'payment_date' => date('Y-m-d H:i:s'),
                        'plan_type' => $planType,
                        'billing_cycle' => $billingCycle
                    ]);
                } catch (\Exception $e) {
                    error_log('EmsPubCore Success: Failed to log payment for journal ' . $journalId . ' (Transaction: ' . $transaction['id'] . '): ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                }

                // Success Page with Auto-Redirect
                // In OJS 3.4+, workflow settings are at /management/settings/workflow
                $backLink = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'management', 'settings', ['workflow'], null, 'emspubcorePlan');
                
                $messageTranslated = __('plugins.generic.emspubcore.success.message');
                if (strpos($messageTranslated, '##') === 0) {
                    $messageTranslated = 'Thank you! Your payment was successful and your plan has been upgraded.';
                }
                $messageTranslated .= '<script>setTimeout(function(){ window.location.href="' . $backLink . '"; }, 3000);</script>';
                $messageTranslated .= '<div style="margin-top: 20px; font-style: italic; color: #666;">Redirecting to settings in 3 seconds...</div>';

                $templateMgr = \APP\template\TemplateManager::getManager($request);
                $templateMgr->assign([
                    'pageTitle' => 'plugins.generic.emspubcore.success.title',
                    'messageTranslated' => $messageTranslated,
                    'backLink' => $backLink,
                    'backLinkLabel' => 'plugins.generic.emspubcore.success.backToSettings'
                ]);
                $templateMgr->display('frontend/pages/message.tpl');
                return;
            } else {
                // Pending or other status
                $templateMgr = \APP\template\TemplateManager::getManager($request);
                $templateMgr->assign([
                    'pageTitle' => 'plugins.generic.emspubcore.success.pending.title',
                    'message' => 'plugins.generic.emspubcore.success.pending.message',
                    'messageParams' => ['status' => $transaction['status'] ?? 'unknown'],
                    'backLink' => $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'management', 'settings', ['workflow'], null, 'emspubcorePlan'),
                    'backLinkLabel' => 'plugins.generic.emspubcore.success.backToSettings'
                ]);
                $templateMgr->display('frontend/pages/message.tpl');
                return;
            }
        } catch (\Exception $e) {
            $templateMgr = \APP\template\TemplateManager::getManager($request);
            $templateMgr->assign([
                'pageTitle' => 'plugins.generic.emspubcore.success.error.title',
                'message' => 'plugins.generic.emspubcore.success.error.message',
                'messageParams' => ['error' => $e->getMessage()],
                'backLink' => $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'management', 'settings', ['workflow'], null, 'emspubcorePlan'),
                'backLinkLabel' => 'plugins.generic.emspubcore.success.backToSettings'
            ]);
            $templateMgr->display('frontend/pages/message.tpl');
            return;
        }
    }

    /**
     * Handle Webhook routing
     */
    public function webhook($args, $request)
    {
        // Detect Gateway
        $signature = $_SERVER['HTTP_PADDLE_SIGNATURE'] ?? null;
        if ($signature) {
            $handler = new \APP\plugins\generic\emspubcore\controllers\PaddleWebhookHandler();
            return $handler->webhook($args, $request);
        }

        // Fallback to Stripe
        $handler = new \APP\plugins\generic\emspubcore\controllers\StripeWebhookHandler();
        return $handler->webhook($args, $request);
    }

    /**
     * AJAX: Get Paddle APC Product ID
     */
    public function getPaddleApcProductId($args, $request)
    {
        $context = $request->getContext();
        header('Content-Type: application/json');
        
        if (!$context) {
            echo json_encode(['paddleApcProductId' => '']);
            return;
        }
        
        $paddleApcProductId = $this->getPlugin()->getSetting($context->getId(), 'paddleApcProductId');
        echo json_encode(['paddleApcProductId' => $paddleApcProductId ?: '']);
    }

    /**
     * AJAX: Save Paddle APC Product ID
     */
    public function savePaddleApcProductId($args, $request)
    {
        $context = $request->getContext();
        header('Content-Type: application/json');
        
        if (!$context) {
            echo json_encode(['success' => false, 'error' => 'No context']);
            return;
        }
        
        // Verify user has permission
        if (!\PKP\security\Validation::isSiteAdmin() && 
            !\PKP\security\Validation::isAuthorized(\PKP\security\Role::ROLE_ID_MANAGER, $context->getId())) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        
        $paddleApcProductId = $request->getUserVar('paddleApcProductId');
        $this->getPlugin()->updateSetting($context->getId(), 'paddleApcProductId', $paddleApcProductId, 'string');
        
        echo json_encode(['success' => true]);
    }

}
