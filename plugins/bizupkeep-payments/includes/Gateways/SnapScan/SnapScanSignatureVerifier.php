<?php

declare(strict_types=1);

namespace BizHub\Payments\Gateways\SnapScan;

use WP_REST_Request;

/**
 * Verifies a SnapScan webhook's authenticity, per SnapScan's own
 * merchant API documentation (developer.getsnapscan.com). Unlike
 * Yoco's Standard Webhooks headers, SnapScan signs the whole raw
 * request body with a single HMAC-SHA256, carried as a
 * `SnapScan signature=<hex>` value in the standard Authorization
 * header - no separate id/timestamp fields, so there is no built-in
 * replay-window check (SnapScan's own recommendation is dedup on the
 * merchant reference instead, which PaymentAttemptRepository's
 * markSucceededOnce() atomic transition already provides).
 *
 * @package BizHub\Payments\Gateways\SnapScan
 */
final class SnapScanSignatureVerifier
{
    private const HEADER_PREFIX = 'SnapScan signature=';

    public function verify(WP_REST_Request $request, string $rawBody, string $webhookKey): bool
    {
        $authorization = $request->get_header('authorization');

        if (! is_string($authorization)) {
            return false;
        }

        return $this->verifyHeader($authorization, $rawBody, $webhookKey);
    }

    /**
     * The actual cryptographic check, independent of WP_REST_Request -
     * separated out so it can be unit-tested with a plain string, the
     * same reasoning as YocoSignatureVerifier::verifyHeaders().
     */
    public function verifyHeader(string $authorizationHeader, string $rawBody, string $webhookKey): bool
    {
        if (! str_starts_with($authorizationHeader, self::HEADER_PREFIX)) {
            return false;
        }

        $providedSignature = substr($authorizationHeader, strlen(self::HEADER_PREFIX));
        $expectedSignature = hash_hmac('sha256', $rawBody, $webhookKey);

        return hash_equals($expectedSignature, $providedSignature);
    }
}
