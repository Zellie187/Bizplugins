<?php

declare(strict_types=1);

namespace BizHub\Api\V1;

use BizHub\Api\Middleware\AuthenticateApi;
use BizHub\Api\Resources\ApplicationResource;
use BizHub\Applications\Contracts\ApplicationServiceInterface;
use BizHub\Applications\Exceptions\ApplicationNotFoundException;
use BizHub\Applications\Services\ApplicationWorkflowService;
use BizHub\ClientPortal\Contracts\ClientServiceInterface;
use BizHub\ClientPortal\Exceptions\ClientNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Registers and handles the /bizhub/v1/applications REST routes.
 *
 * @package BizHub\Api\V1
 */
final class ApplicationController
{
    private const NAMESPACE = 'bizhub/v1';

    public function __construct(
        private readonly ApplicationServiceInterface $applications,
        private readonly ApplicationWorkflowService $workflow,
        private readonly AuthenticateApi $authenticate,
        private readonly ClientServiceInterface $clients
    ) {
    }

    /**
     * Register the module's REST routes.
     */
    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/applications', [
            'methods' => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => $this->authenticate,
        ]);

        register_rest_route(self::NAMESPACE, '/applications/(?P<uuid>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'show'],
            'permission_callback' => $this->authenticate,
        ]);

        register_rest_route(self::NAMESPACE, '/applications/(?P<uuid>[a-zA-Z0-9-]+)/submit', [
            'methods' => 'POST',
            'callback' => [$this, 'submit'],
            'permission_callback' => $this->authenticate,
        ]);
    }

    /**
     * List application summaries for the current user.
     *
     * getApplicationSummaries() takes the numeric bizhub_clients.id, not
     * the WordPress wp_users.ID current_user_id() returns - those are
     * different ID spaces, so the current user's WP ID must first be
     * resolved to their client record. A user with no client account
     * yet (e.g. never completed onboarding) simply has no applications.
     */
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $clientId = $this->clients->getClientByWpUserId(get_current_user_id())->getId();
        } catch (ClientNotFoundException) {
            return new WP_REST_Response([], 200);
        }

        if ($clientId === null) {
            return new WP_REST_Response([], 200);
        }

        $summaries = $this->applications->getApplicationSummaries($clientId);

        return new WP_REST_Response(
            array_map(static fn ($summary): array => $summary->toArray(), $summaries),
            200
        );
    }

    /**
     * Retrieve a single application.
     */
    public function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $application = $this->applications->getApplication((string) $request->get_param('uuid'));
        } catch (ApplicationNotFoundException $e) {
            return new WP_Error('bizhub_application_not_found', $e->getMessage(), ['status' => 404]);
        }

        return new WP_REST_Response(ApplicationResource::make($application), 200);
    }

    /**
     * Submit an application for review.
     */
    public function submit(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $application = $this->workflow->submit((string) $request->get_param('uuid'));
        } catch (ApplicationNotFoundException $e) {
            return new WP_Error('bizhub_application_not_found', $e->getMessage(), ['status' => 404]);
        }

        return new WP_REST_Response(ApplicationResource::make($application), 200);
    }
}
