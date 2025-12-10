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
require_once(__DIR__ . '/EmsPubCoreSettingsForm.php');
require_once(__DIR__ . '/EmsPubCorePageHandler.php');

class EmsPubCorePlugin extends GenericPlugin
{
    /** @var JournalPlanDAO */
    private $journalPlanDAO;

    /** @var SubmissionUsageDAO */
    private $submissionUsageDAO;

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
            
            // Hook into submission creation to track usage
            Hook::add('Submission::add', [$this, 'trackSubmissionUsage']);
            
            // Hook into Backend Header to show limit badge
            Hook::add('Template::Layout::Backend::HeaderActions', [$this, 'renderHeaderBadge']);

            // Hook into Admin Wizard sidebar to add Plan Tab (Journal Level)
            Hook::add('Template::Settings::admin::contextSettings::setup', [$this, 'addPlanUiTab']);
            
            // Hook into Site Settings to add Plans Tab (Site Level)
            Hook::add('Template::Settings::admin', [$this, 'addSitePlansTab']);
            
            // Hook into Workflow Settings > Submission to add Plan Tab
            Hook::add('Template::Settings::workflow::submission', [$this, 'addWorkflowSubmissionTab']);
            
            // Register page handler for plugin routes
            Hook::add('LoadHandler', [$this, 'setupPageHandler']);
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
                ['name' => 'Basic', 'price' => 29.00, 'submission_limit' => 100],
                ['name' => 'Premium', 'price' => 49.00, 'submission_limit' => 200]
            ]);
        }

        // 2. Journal Plans Table
        if (!\Illuminate\Support\Facades\Schema::hasTable('emspubcore_journal_plans')) {
            \Illuminate\Support\Facades\Schema::create('emspubcore_journal_plans', function ($table) {
                $table->bigIncrements('plan_id');
                $table->bigInteger('journal_id');
                $table->string('plan_type')->default('free');
                $table->string('billing_cycle')->default('monthly');
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
     * Setup the page handler for plugin routes
     *
     * @param string $hookName
     * @param array $args
     */
    public function setupPageHandler($hookName, $args)
    {
        $page = $args[0];
        
        if ($page === 'emspubcore') {
            $args[3] = new EmsPubCorePageHandler();
            return true;
        }
        
        return false;
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
     */
    public function getActions($request, $verb)
    {
        $router = $request->getRouter();
        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ] : [],
            parent::getActions($request, $verb)
        );
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

                $form = new EmsPubCoreSettingsForm($this, $context ? $context->getId() : 0);

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
                'monthly' => $plan->getPrice() * 100, // Convert to cents
                'yearly' => ($plan->getDiscountedPrice() ?: $plan->getPrice() * 10) * 100 // Approximation or actual
            ];
        }
        return $prices;
    }

    /**
     * Add Plan UI Tab to Site Settings (Admin)
     */
    public function addSitePlansTab($hookName, $args)
    {
        $params = $args[0];
        $templateMgr = $args[1];
        $output = &$args[2];

        $planDAO = $this->getPlanDAO();
        $plans = $planDAO->getAll();

        $templateMgr->assign([
            'emspubcorePlans' => $plans,
        ]);

        $output .= $templateMgr->fetch($this->getTemplateResource('adminSitePlansTab.tpl'));
        
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

        $currentUsage = $this->getSubmissionUsageDAO()->getCurrentMonthCount($context->getId());

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
        $submission = $args[0];
        $contextId = $submission->getData('contextId');

        if ($contextId) {
            $this->getSubmissionUsageDAO()->incrementCount($contextId);
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
        $title = 'Loading plan details...';
        
        try {
            $plan = $this->getJournalPlanDAO()->getByJournalId($context->getId());
            $planType = $plan ? $plan->getPlanType() : 'free';
            $limits = self::getPlanLimits();
            $title = __('plugins.generic.emspubcore.badge.title', ['used' => $currentUsage, 'limit' => $limit]);
        // Calculate effective limit (sync with settings tab)
        $limit = $limits[$planType] ?? $limits['free'];
        
        $currentUsage = $this->getSubmissionUsageDAO()->getCurrentMonthCount($context->getId());
        // Show "Used / Limit" instead of "Remaining / Limit" standard convention
        $displayCount = $currentUsage . '/' . $limit;
        
        $isOverLimit = $currentUsage >= $limit;
        
        // Dynamic Styles
        if ($isOverLimit) {
            // Warning Style (Red)
            $badgeBg = '#d9534f';
            $badgeColor = '#fff';
            $badgeBorder = '#d43f3a';
            $textColor = '#fff';
            $title .= ' - Limit Reached! Click to Upgrade.';
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
            // If data fetch fails, we will still render the badge but with '?' to indicate valid hook but invalid data
            $planType = 'Error';
            $remaining = '?';
            error_log('EmsPubCorePlugin Error: ' . $e->getMessage());
        }

        // Always output HTML if we are this far
        // We use INLINE STYLES to guarantee appearance and position
        // We use margin-left: auto to push it to the right, but before the user nav
        
        $badgeHtml = '
        <div class="app__headerAction" style="display: flex; align-items: center; margin-left: auto; margin-right: 10px;">
            <div title="' . $title . '" style="background: #fff; color: #006798; padding: 2px 12px; border-radius: 20px; font-size: 13px; font-weight: 700; display: flex; gap: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); cursor: help; white-space: nowrap; border: 1px solid #e0e0e0; align-items: center; height: 24px;">
                <span style="text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; color: #555; padding-right: 8px; border-right: 1px solid #eee; line-height: 1;">' . ucfirst($planType) . '</span> 
                <span style="color: #006798; line-height: 1;">' . $remaining . '/' . $limit . '</span>
            </div>
        </div>';

        $output .= $badgeHtml;

        return \PKP\plugins\Hook::CONTINUE;
    }



    /**
     * Get Stripe publishable key
     */
    public function getStripePublishableKey()
    {
        return $this->getSetting(0, 'stripePublishableKey');
    }

    /**
     * Get Stripe secret key
     */
    public function getStripeSecretKey()
    {
        return $this->getSetting(0, 'stripeSecretKey');
    }

    /**
     * Get Stripe webhook secret
     */
    public function getStripeWebhookSecret()
    {
        return $this->getSetting(0, 'stripeWebhookSecret');
    }

    /**
     * Check if Stripe is in test mode
     */
    public function isStripeTestMode()
    {
        return (bool) $this->getSetting(0, 'stripeTestMode');
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
        
        // Calculate effective limit for display (sync with badge logic)
        $planType = $currentPlan ? $currentPlan->getPlanType() : 'free';
        $currentLimit = $limits[strtolower(str_replace(' ', '', $planType))] ?? $defaultLimit;

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
            'emspubcoreCurrentUsage' => $this->getSubmissionUsageDAO()->getCurrentMonthCount($journalId),
            'emspubcoreCurrentLimit' => $currentLimit
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
        
        // Calculate effective limit for display
        $planType = $currentPlan ? $currentPlan->getPlanType() : 'free';
        $currentLimit = $limits[strtolower(str_replace(' ', '', $planType))] ?? $defaultLimit;

        $templateMgr->assign([
            'emspubcoreCurrentPlan' => $currentPlan,
            'emspubcorePaymentHistory' => $paymentHistory,
            'emspubcoreJournalId' => $journalId,
            'emspubcoreJournalName' => $context->getLocalizedName(),
            'emspubcorePlanOptions' => $planOptions,
            'emspubcorePlansObject' => $plans,
            'emspubcoreDefaultLimit' => $defaultLimit,
            // Only Site Admins can edit plans here
            'emspubcoreCanEdit' => \PKP\security\Validation::isSiteAdmin(),
            'emspubcoreCurrentUsage' => $this->getSubmissionUsageDAO()->getCurrentMonthCount($journalId),
            'emspubcoreCurrentLimit' => $currentLimit
        ]);

        // Add CSS for the plan cards
        $templateMgr->addStyleSheet(
            'emspubcoreStyles',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/styles/emspubcore.css'
        );

        // Fetch modified template that iterates over plans
        $output .= $templateMgr->fetch($this->getTemplateResource('adminPlanTab.tpl'));
        
        return Hook::CONTINUE;
    }

    public function getPaymentHistoryDAO()
    {
        return $this->paymentHistoryDAO;
    }
}
