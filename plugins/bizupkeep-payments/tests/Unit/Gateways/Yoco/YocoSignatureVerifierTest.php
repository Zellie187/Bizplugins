<?php

declare(strict_types=1);

namespace BizHub\Payments\Tests\Unit\Gateways\Yoco;

use BizHub\Payments\Gateways\Yoco\YocoSignatureVerifier;
use PHPUnit\Framework\TestCase;

final class YocoSignatureVerifierTest extends TestCase
{
    private YocoSignatureVerifier $verifier;

    protected function setUp(): void
    {
        $this->verifier = new YocoSignatureVerifier();
    }

    public function testAcceptsACorrectlySignedPayload(): void
    {
        $secret = 'whsec_' . base64_encode('a-32-byte-test-signing-key-000!');
        $id = 'msg_test123';
        $timestamp = (string) time();
        $body = '{"id":"evt_1","type":"payment.succeeded","payload":{"id":"ch_1"}}';

        $signature = $this->sign($id, $timestamp, $body, $secret);

        self::assertTrue($this->verifier->verifyHeaders($id, $timestamp, "v1,{$signature}", $body, $secret));
    }

    public function testRejectsATamperedBody(): void
    {
        $secret = 'whsec_' . base64_encode('a-32-byte-test-signing-key-000!');
        $id = 'msg_test123';
        $timestamp = (string) time();
        $body = '{"id":"evt_1","type":"payment.succeeded","payload":{"id":"ch_1"}}';

        $signature = $this->sign($id, $timestamp, $body, $secret);
        $tamperedBody = '{"id":"evt_1","type":"payment.succeeded","payload":{"id":"ch_2"}}';

        self::assertFalse($this->verifier->verifyHeaders($id, $timestamp, "v1,{$signature}", $tamperedBody, $secret));
    }

    public function testRejectsAWrongSecret(): void
    {
        $id = 'msg_test123';
        $timestamp = (string) time();
        $body = '{"id":"evt_1"}';

        $signature = $this->sign($id, $timestamp, $body, 'whsec_' . base64_encode('the-real-secret-key-0000000000!!'));
        $wrongSecret = 'whsec_' . base64_encode('a-completely-different-key-00000');

        self::assertFalse($this->verifier->verifyHeaders($id, $timestamp, "v1,{$signature}", $body, $wrongSecret));
    }

    public function testRejectsAStaleTimestamp(): void
    {
        $secret = 'whsec_' . base64_encode('a-32-byte-test-signing-key-000!');
        $id = 'msg_test123';
        $staleTimestamp = (string) (time() - 3600);
        $body = '{"id":"evt_1"}';

        $signature = $this->sign($id, $staleTimestamp, $body, $secret);

        self::assertFalse($this->verifier->verifyHeaders($id, $staleTimestamp, "v1,{$signature}", $body, $secret));
    }

    public function testRejectsANonNumericTimestamp(): void
    {
        $secret = 'whsec_' . base64_encode('a-32-byte-test-signing-key-000!');

        self::assertFalse($this->verifier->verifyHeaders('msg_1', 'not-a-number', 'v1,anything', '{}', $secret));
    }

    public function testAcceptsAMatchAmongMultipleSpaceSeparatedSignatures(): void
    {
        $secret = 'whsec_' . base64_encode('a-32-byte-test-signing-key-000!');
        $id = 'msg_test123';
        $timestamp = (string) time();
        $body = '{"id":"evt_1"}';

        $signature = $this->sign($id, $timestamp, $body, $secret);
        $header = "v1,bm90dGhlcmlnaHRvbmU= v1,{$signature}";

        self::assertTrue($this->verifier->verifyHeaders($id, $timestamp, $header, $body, $secret));
    }

    private function sign(string $id, string $timestamp, string $body, string $secretKey): string
    {
        $secretBytes = base64_decode(substr($secretKey, strlen('whsec_')), true);

        return base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $secretBytes, true));
    }
}
