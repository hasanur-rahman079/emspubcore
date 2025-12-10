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
    public function __construct()
    {
        parent::__construct();
        $this->plugin = \PKP\plugins\PluginRegistry::getPlugin('generic', 'emspubcore');
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
            default:
                $this->getDispatcher()->handle404();
        }
    }

    // ... (previous handle* methods) ...

    /**
     * Handle Manual Plan Assignment (Updated for Dynamic Plans)
     */
    public function assignPlan($args, $request)
    {
        if (!\PKP\security\Validation::isSiteAdmin()) {
            header('HTTP/1.0 403 Forbidden');
            echo 'Access denied.';
            exit;
        }

        $journalId = (int) $request->getUserVar('journalId');
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

        if ($plan) {
            $plan->setPlanType($planKey);
            $plan->setSubmissionsLimit($limit);
            $journalPlanDAO->updateObject($plan);
        } else {
            $newPlan = $journalPlanDAO->newDataObject();
            $newPlan->setJournalId($journalId);
            $newPlan->setPlanType($planKey);
            $newPlan->setSubmissionsLimit($limit);
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
}
