<?php

/**
 * @file plugins/generic/emspubcore/EmsPubCorePlugin.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @class EmsPubCorePlugin
 *
 * @ingroup plugins_generic_emspubcore
 *
 * @brief Journal subscription plans with submission limits and Stripe payments
 */

namespace APP\plugins\generic\emspubcore;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\db\DAORegistry;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\context\PKPContextForm;

// Manually require classes to ensure they are loaded
// Manually require classes to ensure they are loaded
require_once(__DIR__ . '/classes/JournalPlanDAO.php');
require_once(__DIR__ . '/classes/JournalPlan.php');
require_once(__DIR__ . '/classes/SubmissionUsageDAO.php');
require_once(__DIR__ . '/classes/PlanDAO.php');
require_once(__DIR__ . '/classes/Plan.php');
require_once(__DIR__ . '/classes/PaymentHistoryDAO.php');
require_once(__DIR__ . '/EmsPubCoreSettingsForm.php');
require_once(__DIR__ . '/EmsPubCorePageHandler.php');

class EmsPubCorePlugin extends GenericPlugin
{
    /** @var JournalPlanDAO */
    private $journalPlanDAO;

    /** @var SubmissionUsageDAO */
    private $submissionUsageDAO;

    /** @var PaymentHistoryDAO */
    private $paymentHistoryDAO;

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (Application::isUnderMaintenance()) {
            return true;
        }
        
        if ($success) {
            // Register DAOs
            $this->journalPlanDAO = new classes\JournalPlanDAO();
            $this->submissionUsageDAO = new classes\SubmissionUsageDAO();
            $this->planDAO = new classes\PlanDAO();
            DAORegistry::registerDAO('JournalPlanDAO', $this->journalPlanDAO);
            DAORegistry::registerDAO('SubmissionUsageDAO', $this->submissionUsageDAO);
            DAORegistry::registerDAO('PlanDAO', $this->planDAO);
            $this->paymentHistoryDAO = new classes\PaymentHistoryDAO();
            DAORegistry::registerDAO('PaymentHistoryDAO', $this->paymentHistoryDAO);

            // Ensure tables exist (Self-healing for dev)
            $this->checkAndCreateTables();

            // Hook into submission validation to check limits
            Hook::add('Submission::validateSubmit', [$this, 'checkSubmissionLimit']);
            
            // Hook into submission creation/update to track finalized usage
            Hook::add('Submission::add', [$this, 'trackSubmissionUsage']);
            Hook::add('Submission::edit', [$this, 'trackSubmissionUsage']);
            
            // Hook into Backend Header to show limit badge and inject tabs
            Hook::add('Template::Layout::Backend::HeaderActions', [$this, 'renderHeaderBadge']);

            // Hook into Admin Wizard sidebar to add Plan Tab (Journal Level)
            Hook::add('Template::Settings::admin::contextSettings::setup', [$this, 'addPlanUiTab']);
            
            // Hook into Site Settings to add Plans Tab (Site Level)
            Hook::add('Template::Settings::admin', [$this, 'addSitePlansTab']);
            
            // Hook into Workflow Settings > Submission to add Plan Tab
            Hook::add('Template::Settings::workflow::submission', [$this, 'addWorkflowSubmissionTab']);
            
            // Override payments/index.tpl to add Pending Payments tab
            Hook::add('TemplateResource::getFilename', [$this, 'overridePaymentsTemplate']);
            
            // Hook into TemplateManager::display to modify UI (Hide banner, Add Sidebar Item)
            Hook::add('TemplateManager::display', [$this, 'modifyDisplay']);
            
            // Register page handler for plugin routes
            Hook::add('LoadHandler', [$this, 'setupPageHandler']);
            // Register page handler for plugin routes
            Hook::add('LoadHandler', [$this, 'setupPageHandler']);

            // Override Payments Grid to add Article Details
            Hook::add('LoadComponentHandler', [$this, 'setupGridHandler']);
        }
        
        return $success;
    }

    /**
     * Get or create JournalPlanDAO instance
     */
    private function getJournalPlanDAO(): classes\JournalPlanDAO
    {
        if (!$this->journalPlanDAO) {
            $this->journalPlanDAO = new classes\JournalPlanDAO();
        }
        return $this->journalPlanDAO;
    }

    /**
     * Get or create SubmissionUsageDAO instance
     */
    private function getSubmissionUsageDAO(): classes\SubmissionUsageDAO
    {
        if (!$this->submissionUsageDAO) {
            $this->submissionUsageDAO = new classes\SubmissionUsageDAO();
        }
        return $this->submissionUsageDAO;
    }

    /**
     * Get or create PaymentHistoryDAO instance
     */
    private function getPaymentHistoryDAO(): classes\PaymentHistoryDAO
    {
        if (!$this->paymentHistoryDAO) {
            $this->paymentHistoryDAO = new classes\PaymentHistoryDAO();
        }
        return $this->paymentHistoryDAO;
    }

    /**
     * Get or create PlanDAO instance
     */
    public function getPlanDAO(): classes\PlanDAO
    {
        if (!isset($this->planDAO)) {
            $this->planDAO = new classes\PlanDAO();
        }
        return $this->planDAO;
    }

    /**
     * Check and create necessary tables if missing
     */
    public function checkAndCreateTables()
    {
        // 1. Plans Table
        if (!\Illuminate\Support\Facades\Schema::hasTable('emspubcore_plans')) {
            \Illuminate\Support\Facades\Schema::create('emspubcore_plans', function ($table) {
                $table->bigIncrements('plan_id');
                $table->string('name');
                $table->decimal('price', 10, 2)->default(0.00);
                $table->decimal('discounted_price', 10, 2)->nullable();
                $table->integer('submission_limit')->default(0);
                $table->text('description')->nullable();
            });

            // Insert default plans
            \Illuminate\Support\Facades\DB::table('emspubcore_plans')->insert([
                ['name' => 'Free', 'price' => 0.00, 'submission_limit' => 5],
                ['name' => 'Basic', 'price' => 290.00, 'submission_limit' => 100],
                ['name' => 'Premium', 'price' => 490.00, 'submission_limit' => 200]
            ]);
        }

        // 2. Journal Plans Table
        if (!\Illuminate\Support\Facades\Schema::hasTable('emspubcore_journal_plans')) {
            \Illuminate\Support\Facades\Schema::create('emspubcore_journal_plans', function ($table) {
                $table->bigIncrements('plan_id');
                $table->bigInteger('journal_id');
                $table->string('plan_type')->default('free');
                $table->string('billing_cycle')->default('yearly');
                $table->integer('submissions_limit')->default(5);
                $table->string('stripe_subscription_id')->nullable();
                $table->string('stripe_customer_id')->nullable();
                $table->dateTime('plan_start_date')->nullable();
                $table->dateTime('plan_end_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. Submission Usage Table
        if (!\Illuminate\Support\Facades\Schema::hasTable('emspubcore_submission_usage')) {
            \Illuminate\Support\Facades\Schema::create('emspubcore_submission_usage', function ($table) {
                $table->bigIncrements('id');
                $table->bigInteger('journal_id');
                $table->string('year_month', 7); // YYYY-MM
                $table->integer('submission_count')->default(0);
                
                $table->unique(['journal_id', 'year_month']);
            });
        }

        // 4. Payment History Table
        if (!\Illuminate\Support\Facades\Schema::hasTable('emspubcore_payment_history')) {
            \Illuminate\Support\Facades\Schema::create('emspubcore_payment_history', function ($table) {
                $table->bigIncrements('id');
                $table->bigInteger('journal_id');
                $table->integer('amount'); // In cents
                $table->string('currency', 3)->default('USD');
                $table->string('status');
                $table->string('stripe_payment_intent_id')->nullable();
                $table->string('stripe_invoice_id')->nullable();
                $table->string('plan_type')->nullable();
                $table->dateTime('payment_date');
            });
        }
    }

    /**
     * Override payments/index.tpl to add Pending Payments tab
     */
    public function overridePaymentsTemplate($hookName, $args)
    {
        $filePath = &$args[0];
        
        // Only override payments/index.tpl
        if (strpos($filePath, 'payments/index.tpl') !== false || 
            strpos($filePath, 'payments' . DIRECTORY_SEPARATOR . 'index.tpl') !== false) {
            
            $request = \APP\core\Application::get()->getRequest();
            $context = $request->getContext();
            
            // Only override for authorized users
            if ($context && (\PKP\security\Validation::isSiteAdmin() || 
                \PKP\security\Validation::isAuthorized(\PKP\security\Role::ROLE_ID_MANAGER, $context->getId()) || 
                \PKP\security\Validation::isAuthorized(\PKP\security\Role::ROLE_ID_SUBSCRIPTION_MANAGER, $context->getId()))) {
                
                $overridePath = $this->getPluginPath() . '/templates/payments/index.tpl';
                if (file_exists($overridePath)) {
                    $filePath = $overridePath;
                }
            }
        }
        
        return false;
    }

    /**
     * Modify Template Display
     * 1. Hide upgrade banner for non-admins
     * 2. Inject "My Invoices" into Sidebar Menu
     */
    public function modifyDisplay($hookName, $args)
    {
        $templateMgr = $args[0];
        $template = $args[1] ?? '';
        
        // DEBUG:
        // if ($template === 'layouts/backend.tpl' || strpos($template, 'backend') !== false) {
        //    die('Hook running for: ' . $template . ' Menu: ' . var_export($templateMgr->getTemplateVars('menu'), true));
        // }
        
        // 1. Hide Upgrade Banner
        if (strpos($template, 'management/') === 0 || $template === 'admin/index.tpl' || $template === 'admin/settings.tpl') {
            if (!\PKP\security\Validation::isSiteAdmin()) {
                $templateMgr->assign('newVersionAvailable', false);
                
                // Hide Plugins tab for non-admins via head injection
                $style = '<style>
                    #plugins-button, 
                    #installedPlugins-button, 
                    #pluginGallery-button,
                    .pkp_tab_plugins,
                    [id^="plugins-button"],
                    [id^="installedPlugins-button"],
                    [id^="pluginGallery-button"],
                    [aria-controls="plugins"],
                    [aria-controls="installedPlugins"],
                    [aria-controls="pluginGallery"] { 
                        display: none !important; 
                    }
                </style>';
                $templateMgr->addHeader('emsHidePlugins', $style, ['contexts' => ['backend']]);
            }
        }
        
        // 2. Inject "Pending Payments" tab into Subscriptions page (for Admins/Managers)
        if ($template === 'payments/index.tpl') {
            $request = \APP\core\Application::get()->getRequest();
            $context = $request->getContext();
            if ($context && (\PKP\security\Validation::isSiteAdmin() || 
                \PKP\security\Validation::isAuthorized(\PKP\security\Role::ROLE_ID_MANAGER, $context->getId()) || 
                \PKP\security\Validation::isAuthorized(\PKP\security\Role::ROLE_ID_SUBSCRIPTION_MANAGER, $context->getId()))) {
                
                $url = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'emspubcore', 'pendingPaymentsAdmin');
                $script = '<script>
                    $(function() {
                        var injectPendingTab = function() {
                            var $tabs = $("#subscriptionsTabs");
                            if ($tabs.length && !$("#pendingPaymentsAdminTab").length) {
                                var tabList = $tabs.find("> ul");
                                if (tabList.length) {
                                    tabList.append(\'<li id="pendingPaymentsAdminTab"><a name="pendingPaymentsAdmin" href="' . $url . '">Pending Payments</a></li>\');
                                    if (typeof $tabs.tabs === "function") {
                                        try { $tabs.tabs("refresh"); } catch(e) {}
                                    }
                                }
                            }
                        };
                        // Multiple attempts to ensure it works with OJS async loading
                        injectPendingTab();
                        setTimeout(injectPendingTab, 500);
                        setTimeout(injectPendingTab, 1000);
                        $(document).ajaxComplete(injectPendingTab);
                    });
                </script>';
                $templateMgr->addHeader('emsPendingPaymentsTab', $script, ['contexts' => ['backend']]);
            }
        }
        
        // 3. Inject Favicon if missing (Backend only)
        $request = \APP\core\Application::get()->getRequest();
        $context = $request->getContext();
        $site = $request->getSite();

        // Check if a favicon is already set at the context or site level
        $favicon = $context ? $context->getLocalizedData('favicon') : $site->getLocalizedData('favicon');
        
        if (empty($favicon)) {
            // Ultimate fallback to EMS favicon from this plugin
            // This ensures a consistent "EMS" branding in the dashboard when no other favicon is set
            $faviconUrl = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/images/favicon.png';
            $templateMgr->addHeader(
                'emsThemeFavicon',
                '<link rel="icon" type="image/png" href="' . $faviconUrl . '">',
                ['contexts' => ['backend']] // Frontend is typically handled by themes
            );
        }

        // 4. Inject Sidebar Item
        // The sidebar menu is passed as 'state' to the Vue app, not as a Smarty variable (in 3.4+)
        $menu = $templateMgr->getState('menu');
        
        if (is_array($menu)) {
            $request = \APP\core\Application::get()->getRequest();
            $user = $request->getUser();
            $context = $request->getContext();
            
            if ($user && $context) {
                 // Check if user has Author role
                 $roleDao = \PKP\db\DAORegistry::getDAO('RoleDAO');
                 $isAuthor = $roleDao->userHasRole($context->getId(), $user->getId(), \PKP\security\Role::ROLE_ID_AUTHOR);
                 
                 if ($isAuthor) {
                     $url = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'emspubcore', 'pendingPayments');
                     
                     // Add new menu item
                     $menu['processingPayments'] = [
                         'name' => 'My Invoices',
                         'url' => $url,
                         'isCurrent' => $request->getRequestedPage() === 'emspubcore' && $request->getRequestedOp() === 'pendingPayments',
                         'icon' => 'Payment' 
                     ];
                     
                     // Update the state
                     $templateMgr->setState(['menu' => $menu]);
                 }
            }
        }
        
        return \PKP\plugins\Hook::CONTINUE;
    }

    /**
     * Setup the page handler for plugin routes
     *
     * @param string $hookName
     * @param array $args
     */
    public function setupPageHandler($hookName, $args)
    {
        $page = $args[0];
        
        if ($page === 'emspubcore') {
            $args[3] = new EmsPubCorePageHandler($this);
            return true;
        }
        
        return false;
    }

    /**
     * Intercept Grid Handler loading to inject our Enhanced Payments Grid
     */
    public function setupGridHandler($hookName, $args)
    {
        $component =& $args[0];
        $componentInstance =& $args[2];

        if ($component === 'grid.subscriptions.PaymentsGridHandler') {
            // Load our custom handler and dependencies
            require_once(__DIR__ . '/controllers/grid/EmsPubPaymentsGridCellProvider.php');
            require_once(__DIR__ . '/controllers/grid/EmsPubPaymentsGridHandler.php');
            
            // Instantiate and assign our custom handler
            $componentInstance = new \APP\plugins\generic\emspubcore\controllers\grid\EmsPubPaymentsGridHandler();
            return true;
        }

        // Block unauthorized access to Plugin management grids
        if ($component === 'grid.settings.plugins.SettingsPluginGridHandler' || $component === 'grid.plugins.PluginGalleryGridHandler') {
            if (!\PKP\security\Validation::isSiteAdmin()) {
                header('HTTP/1.1 403 Forbidden');
                echo 'Access Denied: Only EMS Site Administrators can manage plugins.';
                exit;
            }
        }

        return false;
    }
    /**
     * @copydoc Plugin::isSitePlugin()
     * This plugin should only be manageable by Site Admins
     */
    public function isSitePlugin()
    {
        return true;
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.generic.emspubcore.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.generic.emspubcore.description');
    }

    /**
     * @copydoc Plugin::getActions()
     * No plugin-level settings needed - all configuration is done via Site Admin
     */
    public function getActions($request, $verb)
    {
        // No settings link - plugin is managed at site level only
        return parent::getActions($request, $verb);
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        // Restrict management to Site Admins only
        if (!\PKP\security\Validation::isSiteAdmin()) {
            return new \APP\core\JSONMessage(false, __('user.authorization.accessDenied'));
        }

        switch ($request->getUserVar('verb')) {
            case 'settings':
                require_once(__DIR__ . '/EmsPubCoreSettingsForm.php');
                $context = $request->getContext();
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->registerPlugin('function', 'plugin_url', $this->smartyPluginUrl(...));

                $form = new EmsPubCoreSettingsForm($this, $context ? $context->getId() : null);

                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new \APP\core\JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }
                return new \APP\core\JSONMessage(true, $form->fetch($request));
        }
        return parent::manage($args, $request);
    }

    /**
     * Get the plan type limits (Dynamic from DB)
     */
    public static function getPlanLimits(): array
    {
        $planDAO = DAORegistry::getDAO('PlanDAO');
        // Fallback for static calls if DAO isn't registered yet in some contexts
        if (!$planDAO) return ['free' => 5];

        $plans = $planDAO->getAll();
        $limits = ['free' => 5]; // Default fallback
        
        foreach ($plans as $plan) {
            // Normalize name to key (lowercase, no spaces)
            $key = strtolower(str_replace(' ', '', $plan->getName()));
            $limits[$key] = $plan->getSubmissionLimit();
        }
        return $limits;
    }

    /**
     * Get plan prices (Dynamic from DB)
     */
    public static function getPlanPrices(): array
    {
        $planDAO = DAORegistry::getDAO('PlanDAO');
        if (!$planDAO) return [];

        $plans = $planDAO->getAll();
        $prices = [];

        foreach ($plans as $plan) {
            $key = strtolower(str_replace(' ', '', $plan->getName()));
            if ($key === 'free') continue;

            $prices[$key] = [
                'monthly' => null,
                'yearly' => ($plan->getDiscountedPrice() ?: $plan->getPrice()) * 100
            ];
        }
        return $prices;
    }

    /**
     * Add UI Tabs to Site Settings (Admin)
     * Hook: Template::Settings::admin
     */
    public function addSitePlansTab($hookName, $args)
    {
        $params = $args[0];
        $templateMgr = $args[1];
        $output = &$args[2];

        // 1. Submission Plans Tab
        try {
            $planDAO = $this->getPlanDAO();
            $plans = $planDAO->getAll();
            $templateMgr->assign('emspubcorePlans', $plans);
            $output .= $templateMgr->fetch($this->getTemplateResource('adminSitePlansTab.tpl'));
        } catch (\Exception $e) {
            error_log('EmsPubCorePlugin: SitePlansTab Error ' . $e->getMessage());
        }

        // 2. Journal Management Tab (New - for Status & Discounts)
        try {
            $contextDao = \PKP\db\DAORegistry::getDAO('JournalDAO');
            $contexts = $contextDao->getAll();
            $journalManagement = [];
            $journalPlanCtxDAO = $this->getJournalPlanDAO();

            while ($journal = $contexts->next()) {
                $jId = (int) $journal->getId();
                $plan = $journalPlanCtxDAO->getByJournalId($jId);
                
                $planName = $plan ? ucfirst($plan->getPlanType()) : 'Free';
                $subDateRaw = $plan ? $plan->getPlanStartDate() : null;
                $subDate = $subDateRaw ? date('Y-m-d', strtotime($subDateRaw)) : '-';
                $nextPaymentRaw = $plan ? $plan->getPlanEndDate() : null;
                $nextPayment = $nextPaymentRaw ? date('Y-m-d', strtotime($nextPaymentRaw)) : '-';
                $journalDiscount = (int) $this->getSetting($jId, 'journalDiscount');

                $journalManagement[] = [
                    'id' => $jId,
                    'name' => $journal->getLocalizedName(),
                    'subscriptionDate' => $subDate,
                    'planName' => $planName,
                    'nextPayment' => $nextPayment,
                    'discount' => $journalDiscount
                ];
            }
            $templateMgr->assign('emspubcoreJournalManagement', $journalManagement);
            $output .= $templateMgr->fetch($this->getTemplateResource('adminJournalManagementTab.tpl'));
        } catch (\Exception $e) {
             error_log('EmsPubCorePlugin: JournalManagementTab Error ' . $e->getMessage());
        }

        // 3. Payment History Tab (Repurposed - Site-wide log)
        try {
            $paymentWrapperDAO = $this->getPaymentHistoryDAO();
            $allPayments = $paymentWrapperDAO->getAll();
            
            // Add journal names to payments
            $contextDao = \PKP\db\DAORegistry::getDAO('JournalDAO');
            foreach ($allPayments as &$payment) {
                if ($payment->journal_id) {
                    $journal = $contextDao->getById($payment->journal_id);
                    $payment->journal_name = $journal ? $journal->getLocalizedName() : 'Unknown';
                }
            }
            
            $templateMgr->assign('emspubcoreAllPayments', $allPayments);
            $output .= $templateMgr->fetch($this->getTemplateResource('adminJournalPaymentsTab.tpl'));
        } catch (\Exception $e) {
            error_log('EmsPubCorePlugin: JournalPaymentsTab Error ' . $e->getMessage());
        }

        // 4. Payment Gateways Tab
        try {
            $templateMgr->assign([
                'stripePublishableKey' => $this->getSetting(null, 'stripePublishableKey'),
                'stripeSecretKey' => $this->getSetting(null, 'stripeSecretKey'),
                'stripeWebhookSecret' => $this->getSetting(null, 'stripeWebhookSecret'),
                'stripeTestMode' => (bool) $this->getSetting(null, 'stripeTestMode'),
            ]);
            
            $output .= $templateMgr->fetch($this->getTemplateResource('adminPaymentGatewaysTab.tpl'));
        } catch (\Exception $e) {
             error_log('EmsPubCorePlugin: PaymentGatewaysTab Error ' . $e->getMessage());
        }
        
        return Hook::CONTINUE;
    }

    /**
     * Check submission limit before allowing submission
     *
     * @param string $hookName
     * @param array $args
     */
    public function checkSubmissionLimit($hookName, $args)
    {
        $errors = &$args[0];
        $submission = $args[1];
        $context = $args[2];

        if (!$context) {
            return Hook::CONTINUE;
        }

        $plan = $this->getJournalPlanDAO()->getByJournalId($context->getId());
        
        // If no plan is set, default to free plan
        $planType = $plan ? $plan->getPlanType() : 'free';
        $limits = self::getPlanLimits();
        $limit = $limits[$planType] ?? $limits['free'];

        $currentUsage = $this->getSubmissionUsageDAO()->getYearlyCount($context->getId());

        if ($currentUsage >= $limit) {
            $errors['submissionLimit'] = [__(
                'plugins.generic.emspubcore.error.limitReached',
                [
                    'limit' => $limit,
                    'plan' => ucfirst($planType)
                ]
            )];
            return Hook::ABORT;
        }

        return Hook::CONTINUE;
    }

    /**
     * Track submission usage after a new submission is added
     *
     * @param string $hookName
     * @param array $args
     */
    public function trackSubmissionUsage($hookName, $args)
    {
        if ($hookName === 'Submission::add') {
            $submission = $args[0];
            // Only count if it's already finalized (unlikely on add but possible via API)
            if (!$submission->getData('submissionProgress')) {
                $contextId = $submission->getData('contextId');
                if ($contextId) {
                    $this->getSubmissionUsageDAO()->incrementCount($contextId);
                }
            }
        } elseif ($hookName === 'Submission::edit') {
            $newSubmission = $args[0];
            $oldSubmission = $args[1];
            $params = $args[2];

            // Transition from in-progress to finalized
            if (!empty($oldSubmission->getData('submissionProgress')) && 
                isset($params['submissionProgress']) && $params['submissionProgress'] === '') {
                
                $contextId = $newSubmission->getData('contextId');
                if ($contextId) {
                    $this->getSubmissionUsageDAO()->incrementCount($contextId);
                }
            }
        }

        return Hook::CONTINUE;
    }

    /**
     * Render submission limit badge in the backend header
     * Hook: Template::Layout::Backend::HeaderActions
     */
    public function renderHeaderBadge($hookName, $args)
    {
        $templateMgr = $args[1];
        $output = &$args[2];
        
        // Use fully qualified name to be safe
        $request = \APP\core\Application::get()->getRequest();
        $context = $request->getContext();
        
        if (!$context || !$request->getUser()) {
            return \PKP\plugins\Hook::CONTINUE;
        }

        // Restrict visibility to Site Admin, Journal Manager, and Journal Editor (Section Editor)
        $user = $request->getUser();
        $userId = $user->getId();
        $contextId = $context->getId();
        $roleDao = \PKP\db\DAORegistry::getDAO('RoleDAO');
        
        $isSiteAdmin = \PKP\security\Validation::isSiteAdmin();
        $isManager = $roleDao->userHasRole($contextId, $userId, \PKP\security\Role::ROLE_ID_MANAGER);
        $isEditor = $roleDao->userHasRole($contextId, $userId, \PKP\security\Role::ROLE_ID_SUB_EDITOR); // Sub Editor = Section Editor
        
        if (!$isSiteAdmin && !$isManager && !$isEditor) {
            return \PKP\plugins\Hook::CONTINUE;
        }

        $planType = '...';
        
        try {
            $plan = $this->getJournalPlanDAO()->getByJournalId($context->getId());
            $planType = $plan ? $plan->getPlanType() : 'free';
            $limits = self::getPlanLimits();
            
            // Use journal's actual limit (includes carryover), fall back to base plan limit
            if ($plan && $plan->getSubmissionsLimit() > 0) {
                $limit = $plan->getSubmissionsLimit();
            } else {
                $limit = $limits[strtolower(str_replace(' ', '', $planType))] ?? $limits['free'];
            }
        
        $currentUsage = $this->getSubmissionUsageDAO()->getYearlyCount($context->getId());
        // Show "Used / Limit" instead of "Remaining / Limit" standard convention
        $displayCount = $currentUsage . '/' . $limit;
        
        $isOverLimit = $currentUsage >= $limit;
        
        // Build informative tooltip
        $remaining = $limit - $currentUsage;
        $validUntil = ($plan && $plan->getPlanEndDate()) ? date('M j, Y', strtotime($plan->getPlanEndDate())) : 'N/A';
        $title = ucfirst($planType) . ' Plan | ' . $currentUsage . ' of ' . $limit . ' submissions used | ' . $remaining . ' remaining | Valid until: ' . $validUntil;
        
        if ($isOverLimit) {
            $title .= ' | ⚠️ Limit Reached - Click to Upgrade';
        }
        
        // Dynamic Styles
        if ($isOverLimit) {
            // Warning Style (Red)
            $badgeBg = '#d9534f';
            $badgeColor = '#fff';
            $badgeBorder = '#d43f3a';
            $textColor = '#fff';
        } else {
            // Normal Style (White/Blue)
            $badgeBg = '#fff';
            $badgeColor = '#006798';
            $badgeBorder = '#e0e0e0';
            $textColor = '#555';
        }

        // Add CSS directly to ensure it loads even if head is already rendered
        // Note: Inline styles used below for reliability
        
        // Link to the workflow settings plan tab
        $planUrl = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'management', 'settings', ['workflow'], null, 'emspubcorePlan');

        $badgeHtml = '
        <div class="app__headerAction" style="display: flex; align-items: center; order: 99; margin-left: 10px; margin-right: 0;">
            <a href="' . $planUrl . '" title="' . $title . '" style="text-decoration: none; display: block;">
                <div style="background: ' . $badgeBg . '; color: ' . $badgeColor . '; padding: 2px 12px; border-radius: 20px; font-size: 13px; font-weight: 700; display: flex; gap: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); cursor: pointer; white-space: nowrap; border: 1px solid ' . $badgeBorder . '; align-items: center; height: 28px; transition: all 0.2s ease;">
                    <span style="text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; color: ' . $textColor . '; padding-right: 8px; border-right: 1px solid ' . ($isOverLimit ? 'rgba(255,255,255,0.3)' : '#eee') . '; line-height: 1;">' . ucfirst($planType) . '</span> 
                    <span style="color: ' . ($isOverLimit ? '#fff' : '#006798') . '; line-height: 1;">' . $displayCount . '</span>
                    ' . ($isOverLimit ? '<span style="font-size: 10px; background: rgba(0,0,0,0.2); padding: 2px 6px; border-radius: 4px; margin-left: -4px;">UPGRADE</span>' : '') . '
                </div>
            </a>
        </div>';

        $output .= $badgeHtml;

        return \PKP\plugins\Hook::CONTINUE;
        } catch (\Exception $e) {
            // If data fetch fails, show error state
            error_log('EmsPubCorePlugin Error: ' . $e->getMessage());
            
            $badgeHtml = '
            <div class="app__headerAction" style="display: flex; align-items: center; margin-left: auto; margin-right: 10px;">
                <div title="Error loading plan details. Please refresh." style="background: #fff; color: #d9534f; padding: 2px 12px; border-radius: 20px; font-size: 13px; font-weight: 700; display: flex; gap: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); cursor: help; white-space: nowrap; border: 1px solid #e0e0e0; align-items: center; height: 24px;">
                    <span style="text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; color: #d9534f;">Error</span>
                </div>
            </div>';
            $output .= $badgeHtml;
            return \PKP\plugins\Hook::CONTINUE;
        }
    }



    /**
     * Get Stripe publishable key
     */
    public function getStripePublishableKey()
    {
        return $this->getSetting(null, 'stripePublishableKey');
    }

    /**
     * Get Stripe secret key
     */
    public function getStripeSecretKey()
    {
        return $this->getSetting(null, 'stripeSecretKey');
    }

    /**
     * Get Stripe webhook secret
     */
    public function getStripeWebhookSecret()
    {
        return $this->getSetting(null, 'stripeWebhookSecret');
    }

    /**
     * Check if Stripe is in test mode
     */
    public function isStripeTestMode()
    {
        return (bool) $this->getSetting(null, 'stripeTestMode');
    }
    /**
     * Add Plan UI Tab to Admin Wizard
     */
    public function addPlanUiTab($hookName, $args)
    {
        $params = $args[0];
        $templateMgr = $args[1];
        $output = &$args[2];

        // Get the edited context from the template (try multiple keys)
        $context = $templateMgr->getTemplateVars('editContext');
        if (!$context) $context = $templateMgr->getTemplateVars('currentContext');
        if (!$context) $context = $templateMgr->getTemplateVars('context');
        
        if (!$context) {
            error_log("EmsPubCorePlugin: Context not found in template. Keys: " . implode(',', array_keys($templateMgr->getTemplateVars())));
            return Hook::CONTINUE;
        }

        // Prepare data for the tab
        $journalId = $context->getId();
        $journalPlanDAO = $this->getJournalPlanDAO();
        $planDAO = $this->getPlanDAO();
        $historyDAO = \PKP\db\DAORegistry::getDAO('PaymentHistoryDAO');

        $currentPlan = $journalPlanDAO->getByJournalId($journalId);
        $paymentHistory = $historyDAO->getByJournalId($journalId);
        
        $plans = $planDAO->getAll();
        $planOptions = [];
        $defaultLimit = 5; // Fallback
        $limits = self::getPlanLimits(); // Get global limits
        
        foreach ($plans as $plan) {
            $key = strtolower(str_replace(' ', '', $plan->getName()));
            $planOptions[$key] = $plan->getName() . ' (' . ($plan->getSubmissionLimit() > 0 ? $plan->getSubmissionLimit() . ' submissions' : 'Unlimited') . ')';
            
            // Capture the Free plan's limit to use as default
            if ($key === 'free') {
                $defaultLimit = $plan->getSubmissionLimit();
            }
        }
        
        // Calculate effective limit for display - use journal's actual limit (includes carryover)
        if ($currentPlan && $currentPlan->getSubmissionsLimit() > 0) {
            $currentLimit = $currentPlan->getSubmissionsLimit();
        } else {
            $planType = $currentPlan ? $currentPlan->getPlanType() : 'free';
            $currentLimit = $limits[strtolower(str_replace(' ', '', $planType))] ?? $defaultLimit;
        }

        $templateMgr->assign([
            'emspubcoreCurrentPlan' => $currentPlan,
            'emspubcorePaymentHistory' => $paymentHistory,
            'emspubcoreJournalId' => $journalId,
            'emspubcoreJournalName' => $context->getLocalizedName(), // Pass journal name
            'emspubcorePlanOptions' => $planOptions,
            'emspubcorePlansObject' => $plans, // Pass full objects for card rendering
            'emspubcoreDefaultLimit' => $defaultLimit,
             // Admin Wizard is always editable by Site Admins
            'emspubcoreCanEdit' => true,
            'emspubcoreCurrentUsage' => $this->getSubmissionUsageDAO()->getYearlyCount($journalId),
            'emspubcoreCurrentLimit' => $currentLimit,
            'emspubcoreJournalPath' => $context->getPath()
        ]);

        // Add CSS for the plan cards
        $request = \APP\core\Application::get()->getRequest();
        $templateMgr->addStyleSheet(
            'emspubcoreStyles',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/styles/emspubcore.css'
        );

        // Fetch modified template that iterates over plans
        $output .= $templateMgr->fetch($this->getTemplateResource('adminPlanTab.tpl'));
        
        return Hook::CONTINUE;
    }

    /**
     * Add Plan Tab to Workflow Settings > Submission
     */
    public function addWorkflowSubmissionTab($hookName, $args)
    {
        $params = $args[0];
        $templateMgr = $args[1];
        $output = &$args[2];

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        
        if (!$context) {
            return Hook::CONTINUE;
        }

        // Prepare data for the tab
        $journalId = $context->getId();
        $journalPlanDAO = $this->getJournalPlanDAO();
        $planDAO = $this->getPlanDAO();
        $historyDAO = \PKP\db\DAORegistry::getDAO('PaymentHistoryDAO');

        $currentPlan = $journalPlanDAO->getByJournalId($journalId);
        $paymentHistory = $historyDAO->getByJournalId($journalId);
        
        $plans = $planDAO->getAll();
        $planOptions = [];
        $defaultLimit = 5; // Fallback
        $limits = self::getPlanLimits();
        
        foreach ($plans as $plan) {
            $key = strtolower(str_replace(' ', '', $plan->getName()));
            $planOptions[$key] = $plan->getName() . ' (' . ($plan->getSubmissionLimit() > 0 ? $plan->getSubmissionLimit() . ' submissions' : 'Unlimited') . ')';
            
            // Capture the Free plan's limit to use as default
            if ($key === 'free') {
                $defaultLimit = $plan->getSubmissionLimit();
            }
        }
        
        // Calculate effective limit for display - use journal's actual limit (includes carryover)
        // Fall back to base plan limit if journal limit is not set
        if ($currentPlan && $currentPlan->getSubmissionsLimit() > 0) {
            $currentLimit = $currentPlan->getSubmissionsLimit();
        } else {
            $planType = $currentPlan ? $currentPlan->getPlanType() : 'free';
            $currentLimit = $limits[strtolower(str_replace(' ', '', $planType))] ?? $defaultLimit;
        }

        $templateMgr->assign([
            'emspubcoreCurrentPlan' => $currentPlan,
            'emspubcorePaymentHistory' => $paymentHistory,
            'emspubcoreJournalId' => $journalId,
            'emspubcoreJournalName' => $context->getLocalizedName(),
            'emspubcorePlanOptions' => $planOptions,
            'emspubcorePlansObject' => $plans,
            'emspubcoreDefaultLimit' => $defaultLimit,
            // Allow Site Admin, Journal Manager, or Editor to upgrade plans
            'emspubcoreCanEdit' => \PKP\security\Validation::isSiteAdmin() || 
                                   \PKP\security\Validation::isAuthorized(\PKP\security\Role::ROLE_ID_MANAGER, $journalId) ||
                                   \PKP\security\Validation::isAuthorized(\PKP\security\Role::ROLE_ID_SUB_EDITOR, $journalId),
            'emspubcoreCurrentUsage' => $this->getSubmissionUsageDAO()->getYearlyCount($journalId),
            'emspubcoreCurrentLimit' => $currentLimit,
            'emspubcoreJournalPath' => $context->getPath(),
            'emspubcoreJournalDiscount' => (int) $this->getSetting($journalId, 'journalDiscount')
        ]);

        // Add CSS for the plan cards
        $templateMgr->addStyleSheet(
            'emspubcoreStyles',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/styles/emspubcore.css'
        );

        // Fetch modified template that iterates over plans
        $output .= $templateMgr->fetch($this->getTemplateResource('adminPlanTab.tpl'));
        
        return \PKP\plugins\Hook::CONTINUE;
    }

    /**
     * Add "My Invoices" Link to Sidebar
     */
    public function addSidebarItem($hookName, $args)
    {
        $templateMgr = $args[1];
        $output = &$args[2];
        
        $request = \APP\core\Application::get()->getRequest();
        $user = $request->getUser();
        
        if (!$user) return \PKP\plugins\Hook::CONTINUE;

        // Custom Menu Item HTML
        $url = $request->getDispatcher()->url($request, \PKP\core\PKPApplication::ROUTE_PAGE, null, 'emspubcore', 'pendingPayments');
        
        // Use SVG for icon (e.g. credit card)
        $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-credit-card"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>';
        
        $item = '
            <li>
                <a href="' . $url . '" class="pkp_controllers_linkAction">
                   <span class="app__sidebarIcon">' . $icon . '</span>
                   <span class="app__sidebarLabel">My Invoices</span>
                </a>
            </li>
        ';
        
        $output .= $item;
        
        return \PKP\plugins\Hook::CONTINUE;
    }
}
