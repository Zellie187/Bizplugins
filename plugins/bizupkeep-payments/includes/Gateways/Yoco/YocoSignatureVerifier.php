<?php

declare(strict_types=1);

namespace BizHub\Payments\Gateways\Yoco;

use WP_REST_Request;

/**
 * Verifies a Yoco webhook's authenticity against the Standard Webhooks
 * specification (https://www.standardwebhooks.com/) - the same spec
 * Svix, OpenAI, and Anthropic webhooks use, which Yoco's own webhook
 * delivery is built on. Confirmed against Yoco's own documentation
 * (developer.yoco.com/online/api-reference/webhooks/verifying-events)
 * during this plugin's build, not assumed from the generic spec alone.
 *
 * Three headers carry the proof: webhook-id, webhook-timestamp, and
 * webhook-signature. The signed content is the exact string
 * "{id}.{timestamp}.{raw body}" - HMAC-SHA256'd with the secret key
 * (after stripping its "whsec_" prefix and base64-decoding the
 * remainder to get the real key bytes), then base64-encoded. The
 * webhook-signature header can carry more than one space-separated
 * "v1,<signature>" entry (for key rotation); this class accepts a
 * match against any of them.
 *
 * @package BizHub\Payments\Gateways\Yoco
 */
final class YocoSignatureVerifier
{
    /**
     * Reject a webhook whose timestamp is older than this many
     * seconds, per Yoco's own replay-protection guidance.
     */
    private const MAX_TIMESTAMP_AGE_SECONDS = 180;

    /**
     * @param string $rawBody The exact, unparsed request body - any
     *                        re-serialization would produce a
     *                        different signature.
     */
    public function verify(WP_REST_Request $request, string $rawBody, string $secretKey): bool
    {
        $id = $request->get_header('webhook-id');
        $timestamp = $request->get_header('webhook-timestamp');
        $signatureHeader = $request->get_header('webhook-signature');

        if (! is_string($id) || ! is_string($timestamp) || ! is_string($signatureHeader)) {
            return false;
        }

        return $this->verifyHeaders($id, $timestamp, $signatureHeader, $rawBody, $secretKey);
    }

    /**
     * The actual cryptographic check, independent of WP_REST_Request -
     * separated out so it can be unit-tested with plain strings
     * (WP_REST_Request has no runnable implementation outside a real
     * WordPress request, only a static-analysis stub, matching why no
     * controller anywhere in this codebase has unit tests).
     */
    public function verifyHeaders(
        string $id,
        string $timestamp,
        string $signatureHeader,
        string $rawBody,
        string $secretKey
    ): bool {
        if (! $this->timestampIsFresh($timestamp)) {
            return false;
        }

        $expected = $this->expectedSignature($id, $timestamp, $rawBody, $secretKey);

        foreach (explode(' ', trim($signatureHeader)) as $entry) {
            $signature = str_starts_with($entry, 'v1,') ? substr($entry, 3) : $entry;

            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function timestampIsFresh(string $timestamp): bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        return abs(time() - (int) $timestamp) <= self::MAX_TIMESTAMP_AGE_SECONDS;
    }

    private function expectedSignature(string $id, string $timestamp, string $rawBody, string $secretKey): string
    {
        $secretBytes = base64_decode($this->stripSecretPrefix($secretKey), true) ?: '';
        $signedContent = "{$id}.{$timestamp}.{$rawBody}";

        return base64_encode(hash_hmac('sha256', $signedContent, $secretBytes, true));
    }

    private function stripSecretPrefix(string $secretKey): string
    {
        return str_starts_with($secretKey, 'whsec_') ? substr($secretKey, 6) : $secretKey;
    }
}
