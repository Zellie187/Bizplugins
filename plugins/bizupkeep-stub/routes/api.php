<?php

/**
 * REST API routes for BizUpKeep Stub.
 *
 * Required by BizHub\Stub\Bootstrap\Plugin::registerRoutes(), itself
 * hooked to 'rest_api_init'. Controllers are resolved from BizHub's
 * shared DI container.
 *
 * @package BizHub\Stub
 */

declare(strict_types=1);

use BizHub\Stub\Http\Controllers\StubTokenRefreshController;

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

const BIZUPKEEP_STUB_REST_NAMESPACE = 'bizupkeep-stub/v1';

/** @var StubTokenRefreshController $tokenRefreshController */
$tokenRefreshController = $container->get(StubTokenRefreshController::class);

register_rest_route(
    BIZUPKEEP_STUB_REST_NAMESPACE,
    '/token',
    [
        'methods' => 'POST',
        'callback' => [$tokenRefreshController, 'refresh'],
        'permission_callback' => static fn (): bool => is_user_logged_in(),
        'args' => [
            'company_uuid' => ['required' => true, 'type' => 'string'],
        ],
    ]
);
