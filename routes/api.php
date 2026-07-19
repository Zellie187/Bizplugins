<?php

/**
 * REST API routes for BizUpKeep Workflow.
 *
 * Required by BizHub\Workflow\Bootstrap\Plugin::registerRoutes(),
 * itself hooked to 'rest_api_init'. Controllers are resolved from
 * BizHub's shared DI container - this plugin never constructs them
 * directly, so every dependency (services, repositories, the workflow
 * engine) is wired exactly the same way it would be if this code
 * lived inside BizHub itself.
 *
 * @package BizHub\Workflow
 */

declare(strict_types=1);

use BizHub\Workflow\Http\Controllers\CompanyRegistrationController;

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

/** @var CompanyRegistrationController $controller */
$controller = $container->get(CompanyRegistrationController::class);

const BIZUPKEEP_WORKFLOW_REST_NAMESPACE = 'bizupkeep-workflow/v1';

register_rest_route(
    BIZUPKEEP_WORKFLOW_REST_NAMESPACE,
    '/company-registrations',
    [
        'methods' => 'POST',
        'callback' => [$controller, 'start'],
        'permission_callback' => static fn (): bool => is_user_logged_in(),
        'args' => [
            'company_uuid' => [
                'type' => 'string',
                'required' => true,
            ],
        ],
    ]
);

register_rest_route(
    BIZUPKEEP_WORKFLOW_REST_NAMESPACE,
    '/company-registrations/(?P<uuid>[a-f0-9\-]{36})',
    [
        'methods' => 'GET',
        'callback' => [$controller, 'show'],
        'permission_callback' => static fn (): bool => is_user_logged_in(),
    ]
);

register_rest_route(
    BIZUPKEEP_WORKFLOW_REST_NAMESPACE,
    '/company-registrations/(?P<uuid>[a-f0-9\-]{36})/actions',
    [
        'methods' => 'POST',
        'callback' => [$controller, 'performAction'],
        'permission_callback' => static fn (): bool => is_user_logged_in(),
        'args' => [
            'action' => [
                'type' => 'string',
                'required' => true,
            ],
            'reason' => [
                'type' => 'string',
                'required' => false,
            ],
            'context' => [
                'type' => 'object',
                'required' => false,
            ],
        ],
    ]
);

register_rest_route(
    BIZUPKEEP_WORKFLOW_REST_NAMESPACE,
    '/company-registrations/(?P<uuid>[a-f0-9\-]{36})/rollback',
    [
        'methods' => 'POST',
        'callback' => [$controller, 'rollback'],
        'permission_callback' => static fn (): bool => is_user_logged_in(),
        'args' => [
            'reason' => [
                'type' => 'string',
                'required' => true,
            ],
        ],
    ]
);
