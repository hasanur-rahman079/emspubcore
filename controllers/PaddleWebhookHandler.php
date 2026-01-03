<?php

/**
 * @file plugins/generic/emspubcore/controllers/PaddleWebhookHandler.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @class PaddleWebhookHandler
 *
 * @ingroup plugins_generic_emspubcore
 *
 * @brief Handle Paddle webhooks
 */

namespace APP\plugins\generic\emspubcore\controllers;

use APP\handler\Handler;
use APP\plugins\generic\emspubcore\EmsPubCorePlugin;
use PKP\plugins\PluginRegistry;

class PaddleWebhookHandler extends Handler
{
    /**
     * Handle incoming webhook
     */
    public function webhook($args, $request)
    {
        // Handle GET requests for health checks
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'ok',
                'message' => 'Webhook endpoint is active. This endpoint accepts POST requests from Paddle or Stripe.'
            ]);
            return;
        }
        
        // Apply rate limiting (60 requests per minute per IP)
        $rateLimiter = new \APP\plugins\generic\emspubcore\classes\RateLimiter(60, 60);
        if (!$rateLimiter->check()) {
            http_response_code(429);
            header('Retry-After: 60');
            echo json_encode(['error' => 'Too many requests. Please try again later.']);
            return;
        }
        
        $plugin = PluginRegistry::getPlugin('generic', 'emspubcore');
        if (!$plugin) {
            http_response_code(500);
            echo "Plugin not loaded";
            return;
        }

        $payload = @file_get_contents('php://input');
        $signature = $_SERVER['HTTP_PADDLE_SIGNATURE'] ?? '';

        try {
            $paymentHandler = new \APP\plugins\generic\emspubcore\classes\PaddlePaymentHandler($plugin);
            $paymentHandler->handleWebhook($payload, $signature);
            
            http_response_code(200);
            echo "Webhook processed";
        } catch (\Exception $e) {
            http_response_code(400);
            error_log('Paddle Webhook Error: ' . $e->getMessage());
            echo "Webhook error";
        }
        return true;
    }

    public function index($args, $request) { return $this->webhook($args, $request); }
}
