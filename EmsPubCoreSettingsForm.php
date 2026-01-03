<?php

/**
 * @file plugins/generic/emspubcore/EmsPubCoreSettingsForm.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @class EmsPubCoreSettingsForm
 *
 * @ingroup plugins_generic_emspubcore
 *
 * @brief Form for site administrators to configure EmsPubCore plugin settings
 */

namespace APP\plugins\generic\emspubcore;

use PKP\form\Form;
use PKP\form\validation\FormValidatorPost;
use PKP\form\validation\FormValidatorCSRF;
use APP\template\TemplateManager;

class EmsPubCoreSettingsForm extends Form
{
    /** @var EmsPubCorePlugin */
    private $plugin;

    /** @var int|null */
    private $contextId;

    /**
     * Constructor
     *
     * @param EmsPubCorePlugin $plugin
     * @param int|null $contextId
     */
    public function __construct($plugin, $contextId)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * Initialize form data
     */
    public function initData()
    {
        $this->_data = [
            'stripePublishableKey' => $this->plugin->getSetting(0, 'stripePublishableKey'),
            'stripeSecretKey' => $this->plugin->getSetting(0, 'stripeSecretKey'),
            'stripeWebhookSecret' => $this->plugin->getSetting(0, 'stripeWebhookSecret'),
            'stripeTestMode' => $this->plugin->getSetting(0, 'stripeTestMode'),
            'paddleVendorId' => $this->plugin->getSetting(0, 'paddleVendorId'),
            'paddleApiKey' => $this->plugin->getSetting(0, 'paddleApiKey'),
            'paddleClientToken' => $this->plugin->getSetting(0, 'paddleClientToken'),
            'paddleProductIdBasic' => $this->plugin->getSetting(0, 'paddleProductIdBasic'),
            'paddleProductIdStandard' => $this->plugin->getSetting(0, 'paddleProductIdStandard'),
            'paddleProductIdPremium' => $this->plugin->getSetting(0, 'paddleProductIdPremium'),
            'paddleWebhookSecret' => $this->plugin->getSetting(0, 'paddleWebhookSecret'),
            'paddleTestMode' => $this->plugin->getSetting(0, 'paddleTestMode'),
            'subscriptionPaymentGateway' => $this->plugin->getSetting(0, 'subscriptionPaymentGateway') ?: 'stripe',
        ];
    }

    /**
     * Assign form data to user-submitted data
     */
    public function readInputData()
    {
        $this->readUserVars([
            'stripePublishableKey',
            'stripeSecretKey',
            'stripeWebhookSecret',
            'stripeTestMode',
            'paddleVendorId',
            'paddleApiKey',
            'paddleClientToken',
            'paddleProductIdBasic',
            'paddleProductIdStandard',
            'paddleProductIdPremium',
            'paddleTestMode',
            'paddleWebhookSecret',
            'subscriptionPaymentGateway',
        ]);
    }

    /**
     * @copydoc Form::fetch()
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        $templateMgr->assign('planLimits', EmsPubCorePlugin::getPlanLimits());
        $templateMgr->assign('planPrices', EmsPubCorePlugin::getPlanPrices());
        $templateMgr->assign('gatewayOptions', [
            'stripe' => 'Stripe',
            'paddle' => 'Paddle',
        ]);
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        // Site-level settings (contextId = null)
        $this->plugin->updateSetting(0, 'stripePublishableKey', $this->getData('stripePublishableKey'), 'string');
        $this->plugin->updateSetting(0, 'stripeSecretKey', $this->getData('stripeSecretKey'), 'string');
        $this->plugin->updateSetting(0, 'stripeWebhookSecret', $this->getData('stripeWebhookSecret'), 'string');
        $this->plugin->updateSetting(0, 'stripeTestMode', (bool) $this->getData('stripeTestMode'), 'bool');
        $this->plugin->updateSetting(0, 'paddleVendorId', $this->getData('paddleVendorId'), 'string');
        $this->plugin->updateSetting(0, 'paddleApiKey', $this->getData('paddleApiKey'), 'string');
        $this->plugin->updateSetting(0, 'paddleClientToken', $this->getData('paddleClientToken'), 'string');
        $this->plugin->updateSetting(0, 'paddleProductIdBasic', $this->getData('paddleProductIdBasic'), 'string');
        $this->plugin->updateSetting(0, 'paddleProductIdStandard', $this->getData('paddleProductIdStandard'), 'string');
        $this->plugin->updateSetting(0, 'paddleProductIdPremium', $this->getData('paddleProductIdPremium'), 'string');
        $this->plugin->updateSetting(0, 'paddleWebhookSecret', $this->getData('paddleWebhookSecret'), 'string');
        $this->plugin->updateSetting(0, 'paddleTestMode', (bool) $this->getData('paddleTestMode'), 'bool');
        $this->plugin->updateSetting(0, 'subscriptionPaymentGateway', $this->getData('subscriptionPaymentGateway'), 'string');

        parent::execute(...$functionArgs);
    }
}
