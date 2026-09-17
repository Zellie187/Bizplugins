<?php

declare(strict_types=1);

namespace BizHub\Stub\Http\Controllers;

use BizHub\ClientPortal\Contracts\ClientRepositoryInterface;
use BizHub\Companies\Contracts\CompanyServiceInterface;
use BizHub\Companies\Exceptions\CompanyNotFoundException;
use BizHub\Stub\Contracts\StubAuthTokenServiceInterface;
use BizHub\Stub\Contracts\StubBusinessProvisionerInterface;
use BizHub\Stub\Contracts\StubBusinessRepositoryInterface;
use BizHub\Stub\Exceptions\StubException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The one authenticated REST route this plugin exposes.
 *
 * The FIRST token for a client's own embedded-widget page is issued
 * server-side, inline into the page's HTML at render time (see the
 * template change in astra-child) - matching this codebase's
 * established "container service call, no REST hop" convention
 * (see bizupkeep-payments' routes/api.php docblock). This route exists
 * only because a token expires after an hour and the client may stay
 * on the page longer than that; JS needs *something* live to call for
 * a refreshed one, which a server-rendered page cannot provide by
 * itself.
 *
 * @package BizHub\Stub\Http\Controllers
 */
final class StubTokenRefreshController
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
        private readonly CompanyServiceInterface $companies,
        private readonly StubBusinessRepositoryInterface $businesses,
        private readonly StubBusinessProvisionerInterface $provisioner,
        private readonly StubAuthTokenServiceInterface $tokens
    ) {
    }

    public function refresh(WP_REST_Request $request): WP_REST_Response
    {
        $companyUuid = (string) $request->get_param('company_uuid');

        if ($companyUuid === '') {
            return new WP_REST_Response(['error' => 'company_uuid is required.'], 400);
        }

        $client = $this->clients->findByWpUserId(get_current_user_id());

        if ($client === null || $client->getId() === null) {
            return new WP_REST_Response(['error' => 'No client record for the current user.'], 403);
        }

        try {
            $company = $this->companies->getCompany($companyUuid);
        } catch (CompanyNotFoundException) {
            return new WP_REST_Response(['error' => 'Company not found.'], 404);
        }

        // The actual trust boundary - re-verified on every refresh, not
        // just trusted from whatever page the request came from.
        if ($company->getClientId() !== $client->getId()) {
            return new WP_REST_Response(['error' => 'This company does not belong to you.'], 403);
        }

        try {
            $business = $this->businesses->findByCompanyUuid($companyUuid);
            $stubBusinessUid = $business?->stubBusinessUid ?? $this->provisioner->findOrCreate($client, $company);
            $token = $this->tokens->getToken($stubBusinessUid);
        } catch (StubException $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 502);
        }

        return new WP_REST_Response(['token' => $token, 'businessId' => $stubBusinessUid], 200);
    }
}
