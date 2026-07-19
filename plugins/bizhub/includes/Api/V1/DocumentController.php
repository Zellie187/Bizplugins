<?php

declare(strict_types=1);

namespace BizHub\Api\V1;

use BizHub\Api\Middleware\AuthenticateApi;
use BizHub\Documents\Exceptions\DocumentNotFoundException;
use BizHub\Documents\Services\DocumentSecurityService;
use BizHub\Documents\Services\DocumentService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Registers and handles the /bizhub/v1/documents REST routes.
 *
 * @package BizHub\Api\V1
 */
final class DocumentController
{
    private const NAMESPACE = 'bizhub/v1';

    public function __construct(
        private readonly DocumentService $documents,
        private readonly DocumentSecurityService $security,
        private readonly AuthenticateApi $authenticate
    ) {
    }

    /**
     * Register the module's REST routes.
     */
    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/documents/(?P<uuid>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'show'],
            'permission_callback' => $this->authenticate,
        ]);

        register_rest_route(self::NAMESPACE, '/documents/(?P<uuid>[a-zA-Z0-9-]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete'],
            'permission_callback' => $this->authenticate,
        ]);
    }

    /**
     * Retrieve a single document.
     */
    public function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();

        try {
            $document = $this->documents->getDocument((string) $request->get_param('uuid'));
        } catch (DocumentNotFoundException $e) {
            return new WP_Error('bizhub_document_not_found', $e->getMessage(), ['status' => 404]);
        }

        if (! $this->security->canView($userId, $document)) {
            return new WP_Error(
                'bizhub_document_forbidden',
                'You do not have access to this document.',
                ['status' => 403]
            );
        }

        return new WP_REST_Response($document->toArray(), 200);
    }

    /**
     * Delete a document.
     */
    public function delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();
        $uuid = (string) $request->get_param('uuid');

        try {
            $document = $this->documents->getDocument($uuid);
        } catch (DocumentNotFoundException $e) {
            return new WP_Error('bizhub_document_not_found', $e->getMessage(), ['status' => 404]);
        }

        if (! $this->security->canDelete($userId, $document)) {
            return new WP_Error(
                'bizhub_document_forbidden',
                'You do not have access to delete this document.',
                ['status' => 403]
            );
        }

        $this->documents->deleteDocument($uuid);

        return new WP_REST_Response(null, 204);
    }
}
