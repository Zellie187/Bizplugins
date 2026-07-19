<?php

declare(strict_types=1);

namespace BizHub\Api\V1;

use BizHub\Api\Middleware\AuthenticateApi;
use BizHub\ClientPortal\Contracts\ClientServiceInterface;
use BizHub\ClientPortal\DTO\ProfileData;
use BizHub\ClientPortal\Exceptions\ClientNotFoundException;
use BizHub\ClientPortal\Services\ProfileService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Registers and handles the /bizhub/v1/profile REST routes.
 *
 * @package BizHub\Api\V1
 */
final class ProfileController
{
    private const NAMESPACE = 'bizhub/v1';

    public function __construct(
        private readonly ClientServiceInterface $clients,
        private readonly ProfileService $profiles,
        private readonly AuthenticateApi $authenticate
    ) {
    }

    /**
     * Register the module's REST routes.
     */
    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/profile', [
            'methods' => 'GET',
            'callback' => [$this, 'show'],
            'permission_callback' => $this->authenticate,
        ]);

        register_rest_route(self::NAMESPACE, '/profile', [
            'methods' => 'PUT',
            'callback' => [$this, 'update'],
            'permission_callback' => $this->authenticate,
        ]);
    }

    /**
     * Retrieve the current user's client profile.
     */
    public function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $client = $this->clients->getClientByWpUserId(get_current_user_id());
        } catch (ClientNotFoundException $e) {
            return new WP_Error('bizhub_client_not_found', $e->getMessage(), ['status' => 404]);
        }

        return new WP_REST_Response($client->toArray(), 200);
    }

    /**
     * Update the current user's client profile.
     */
    public function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $client = $this->clients->getClientByWpUserId(get_current_user_id());
        } catch (ClientNotFoundException $e) {
            return new WP_Error('bizhub_client_not_found', $e->getMessage(), ['status' => 404]);
        }

        $profileData = new ProfileData(
            (string) $request->get_param('first_name'),
            (string) $request->get_param('last_name'),
            (string) ($request->get_param('phone') ?? ''),
            $request->get_param('avatar_url')
        );

        $updated = $this->profiles->updateProfile($client->getUuid(), $profileData);

        return new WP_REST_Response($updated->toArray(), 200);
    }
}
