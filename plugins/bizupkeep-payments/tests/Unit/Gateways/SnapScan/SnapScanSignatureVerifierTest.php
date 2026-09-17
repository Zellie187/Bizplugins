<?php

declare(strict_types=1);

namespace BizHub\Payments\Tests\Unit\Gateways\SnapScan;

use BizHub\Payments\Gateways\SnapScan\SnapScanSignatureVerifier;
use PHPUnit\Framework\TestCase;

final class SnapScanSignatureVerifierTest extends TestCase
{
    private SnapScanSignatureVerifier $verifier;

    protected function setUp(): void
    {
        $this->verifier = new SnapScanSignatureVerifier();
    }

    public function testAcceptsACorrectlySignedPayload(): void
    {
        $webhookKey = 'test-webhook-key';
        $body = 'payload=' . urlencode('{"status":"completed","merchantReference":"abc-123","totalAmount":65000}');
        $header = 'SnapScan signature=' . hash_hmac('sha256', $body, $webhookKey);

        self::assertTrue($this->verifier->verifyHeader($header, $body, $webhookKey));
    }

    public function testRejectsATamperedBody(): void
    {
        $webhookKey = 'test-webhook-key';
        $body = 'payload=original';
        $header = 'SnapScan signature=' . hash_hmac('sha256', $body, $webhookKey);

        self::assertFalse($this->verifier->verifyHeader($header, 'payload=tampered', $webhookKey));
    }

    public function testRejectsAWrongKey(): void
    {
        $body = 'payload=original';
        $header = 'SnapScan signature=' . hash_hmac('sha256', $body, 'the-real-key');

        self::assertFalse($this->verifier->verifyHeader($header, $body, 'a-different-key'));
    }

    public function testRejectsAHeaderMissingTheExpectedPrefix(): void
    {
        $body = 'payload=original';
        $signature = hash_hmac('sha256', $body, 'test-webhook-key');

        self::assertFalse($this->verifier->verifyHeader($signature, $body, 'test-webhook-key'));
    }

    public function testRejectsAnEmptyHeader(): void
    {
        self::assertFalse($this->verifier->verifyHeader('', 'payload=original', 'test-webhook-key'));
    }
}
