<?php

declare(strict_types=1);

namespace BizHub\Api\V1;

use BizHub\Api\Middleware\AuthenticateApi;
use BizHub\Api\Resources\CompanyResource;
use BizHub\ClientPortal\Contracts\ClientServiceInterface;
use BizHub\ClientPortal\Exceptions\ClientNotFoundException;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Registers and handles the /bizhub/v1/companies REST routes.
 *
 * @package BizHub\Api\V1
 */
final class CompanyController
{
    private const NAMESPACE = 'bizhub/v1';

    public function __construct(
        private readonly CompanyServiceInterface $companies,
        private readonly AuthenticateApi $authenticate,
        private readonly ClientServiceInterface $clients
    ) {
    }

    /**
     * Register the module's REST routes.
     */
    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/companies', [
            'methods' => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => $this->authenticate,
        ]);

        register_rest_route(self::NAMESPACE, '/companies/(?P<uuid>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'show'],
            'permission_callback' => $this->authenticate,
        ]);
    }

    /**
     * List company summaries for the current user.
     */
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $summaries = $this->companies->getCompanySummaries(get_current_user_id());

        return new WP_REST_Response(
            array_map(static fn ($summary): array => $summary->toArray(), $summaries),
            200
        );
    }

    /**
     * Retrieve a single company.
     *
     * Ownership is enforced here rather than trusted from the route: a
     * company belonging to a different client returns the same 404 as
     * a company that doesn't exist at all, so the endpoint never
     * confirms another tenant's UUID is valid.
     */
    public function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $notFound = new WP_Error(
            'bizhub_company_not_found',
            __('Company not found.', 'bizhub'),
            ['status' => 404]
        );

        try {
            $clientId = $this->clients->getClientByWpUserId(get_current_user_id())->getId();
        } catch (ClientNotFoundException) {
            return $notFound;
        }

        if ($clientId === null) {
            return $notFound;
        }

        try {
            $company = $this->companies->getCompany((string) $request->get_param('uuid'));
        } catch (CompanyNotFoundException $e) {
            return new WP_Error('bizhub_company_not_found', $e->getMessage(), ['status' => 404]);
        }

        if ($company->getClientId() !== $clientId) {
            return $notFound;
        }

        return new WP_REST_Response(CompanyResource::make($company), 200);
    }
}
