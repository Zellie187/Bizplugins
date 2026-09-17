<?php

declare(strict_types=1);

namespace BizHub\Stub\Contracts;

use BizHub\Stub\Exceptions\StubApiException;

/**
 * The one contract that actually talks to Stub's Connect API
 * (developers.stub.africa). Every method throws StubApiException on a
 * transport failure, a non-2xx response, or a response that doesn't
 * match the shape it documents.
 *
 * @package BizHub\Stub\Contracts
 */
interface StubApiClientInterface
{
    /**
     * GET/POST /api/verify/apikey - confirms the configured apikey/appid
     * are valid.
     */
    public function verifyApiKey(): bool;

    /**
     * POST /api/auth/token - a 1-hour token for business creation and
     * the embedded-widget SDK. $uid is omitted when creating a brand
     * new business (see pushBusiness()); required once a business
     * exists.
     *
     * @return array{token:string}
     */
    public function requestAuthToken(?string $uid = null): array;

    /**
     * POST /api/push/business - creates a new Stub business (or
     * returns the existing one if the email already has a Stub
     * account). Returns the raw response, which includes "uid" and a
     * fresh token.
     *
     * @param array<string,mixed> $data
     * @return array{uid:string,token?:string}
     */
    public function pushBusiness(array $data): array;

    /**
     * POST /api/push/many - bulk-push income and expense rows for one
     * business in a single call (used by the historical migration -
     * see Services\StubMigrationService).
     *
     * @param array<int,array<string,mixed>> $income
     * @param array<int,array<string,mixed>> $expenses
     */
    public function pushMany(string $uid, array $income, array $expenses): array;

    /**
     * POST /api/realtime/summary - synchronous read, no webhook.
     *
     * @return array<string,mixed>
     */
    public function realtimeSummary(string $token, string $uid): array;

    /**
     * POST /api/realtime/insights - synchronous read, no webhook.
     *
     * @return array<string,mixed>
     */
    public function realtimeInsights(string $token, string $uid): array;

    /**
     * GET /api/realtime/expenses - synchronous read, no webhook.
     *
     * @return array<int,array<string,mixed>>
     */
    public function realtimeExpenses(string $token, string $uid): array;

    /**
     * GET /api/realtime/income - synchronous read, no webhook.
     *
     * @return array<int,array<string,mixed>>
     */
    public function realtimeIncome(string $token, string $uid): array;
}
