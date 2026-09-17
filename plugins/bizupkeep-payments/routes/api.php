<?php

/**
 * REST API webhook routes for BizUpKeep Payments.
 *
 * Required by BizHub\Payments\Bootstrap\Plugin::registerRoutes(),
 * itself hooked to 'rest_api_init'. Controllers are resolved from
 * BizHub's shared DI container. These are the only public,
 * unauthenticated endpoints this plugin exposes - everything
 * client-facing (starting a payment) is called directly from
 * astra-child's own template_redirect form handlers via
 * PaymentAttemptServiceInterface, the same "container service call,
 * no REST hop" pattern already established for Bookkeeping's
 * Invoicing tab.
 *
 * @package BizHub\Payments
 */

declare(strict_types=1);

use BizHub\Payments\Http\Controllers\SnapScanWebhookController;
use BizHub\Payments\Http\Controllers\YocoWebhookController;

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('bizhub')) {
    return;
}

$application = bizhub();

if ($application === null) {
    return;
}

$container = $application->container();

const BIZUPKEEP_PAYMENTS_REST_NAMESPACE = 'bizupkeep-payments/v1';

/** @var YocoWebhookController $yocoWebhookController */
$yocoWebhookController = $container->get(YocoWebhookController::class);

register_rest_route(
    BIZUPKEEP_PAYMENTS_REST_NAMESPACE,
    '/webhooks/yoco',
    [
        'methods' => 'POST',
        'callback' => [$yocoWebhookController, 'handle'],
        'permission_callback' => '__return_true',
    ]
);

/** @var SnapScanWebhookController $snapScanWebhookController */
$snapScanWebhookController = $container->get(SnapScanWebhookController::class);

register_rest_route(
    BIZUPKEEP_PAYMENTS_REST_NAMESPACE,
    '/webhooks/snapscan',
    [
        'methods' => 'POST',
        'callback' => [$snapScanWebhookController, 'handle'],
        'permission_callback' => '__return_true',
    ]
);
