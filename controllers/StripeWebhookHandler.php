<?php

/**
 * @file plugins/generic/emspubcore/controllers/StripeWebhookHandler.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @class StripeWebhookHandler
 *
 * @ingroup plugins_generic_emspubcore
 *
 * @brief Handle Stripe webhooks
 */

namespace APP\plugins\generic\emspubcore\controllers;

use APP\handler\Handler;
use APP\plugins\generic\emspubcore\EmsPubCorePlugin;
use PKP\plugins\PluginRegistry;

class StripeWebhookHandler extends Handler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Handle incoming webhook
     */
    public function webhook($args, $request)
    {
        $plugin = PluginRegistry::getPlugin('generic', 'emspubcore');
        if (!$plugin) {
            http_response_code(500);
            echo "Plugin not loaded";
            return;
        }

        $payload = @file_get_contents('php://input');
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        try {
            // Instantiate handler via plugin helper or direct instantiation
            // Note: StripePaymentHandler is instantiated by the plugin usually,
            // but here we might need to construct it if not exposed.
            // EmsPubCorePlugin doesn't expose it publically yet.
            // We'll trust the plugin to handle it via its logic if implemented,
            // or instantiate manually.
            
            // For now, simpler approach:
            $paymentHandler = new \APP\plugins\generic\emspubcore\classes\StripePaymentHandler($plugin);
            $paymentHandler->handleWebhook($payload, $signature);
            
            http_response_code(200);
            echo "Webhook processed";
        } catch (\Exception $e) {
            http_response_code(400);
            error_log('Stripe Webhook Error: ' . $e->getMessage());
            echo "Webhook error";
        }
        return true;
    }

    public function checkout($args, $request) { return true; }
    public function success($args, $request) { return true; }
    public function cancel($args, $request) { return true; }
}
