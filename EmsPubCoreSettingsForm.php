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
            'stripePublishableKey' => $this->plugin->getSetting(null, 'stripePublishableKey'),
            'stripeSecretKey' => $this->plugin->getSetting(null, 'stripeSecretKey'),
            'stripeWebhookSecret' => $this->plugin->getSetting(null, 'stripeWebhookSecret'),
            'stripeTestMode' => $this->plugin->getSetting(null, 'stripeTestMode'),
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
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        // Site-level settings (contextId = null)
        $this->plugin->updateSetting(null, 'stripePublishableKey', $this->getData('stripePublishableKey'), 'string');
        $this->plugin->updateSetting(null, 'stripeSecretKey', $this->getData('stripeSecretKey'), 'string');
        $this->plugin->updateSetting(null, 'stripeWebhookSecret', $this->getData('stripeWebhookSecret'), 'string');
        $this->plugin->updateSetting(null, 'stripeTestMode', (bool) $this->getData('stripeTestMode'), 'bool');

        parent::execute(...$functionArgs);
    }
}
