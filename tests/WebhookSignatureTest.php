<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Tests;

use BadgerWise\WcConnect\WebhookSignature;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureTest extends TestCase
{
    private const PAYLOAD = '{"id":123,"status":"processing"}';
    private const SECRET = 'a-shared-webhook-secret';

    #[Test]
    public function it_computes_woocommerces_base64_hmac_sha256(): void
    {
        $expected = base64_encode(hash_hmac('sha256', self::PAYLOAD, self::SECRET, true));

        self::assertSame($expected, WebhookSignature::compute(self::PAYLOAD, self::SECRET));
    }

    #[Test]
    public function it_verifies_a_correct_signature(): void
    {
        $signature = WebhookSignature::compute(self::PAYLOAD, self::SECRET);

        self::assertTrue(WebhookSignature::verify(self::PAYLOAD, self::SECRET, $signature));
    }

    #[Test]
    public function it_rejects_a_wrong_signature(): void
    {
        self::assertFalse(WebhookSignature::verify(self::PAYLOAD, self::SECRET, 'not-the-signature'));
    }

    #[Test]
    public function it_rejects_an_empty_signature(): void
    {
        self::assertFalse(WebhookSignature::verify(self::PAYLOAD, self::SECRET, ''));
    }

    #[Test]
    public function it_rejects_a_signature_made_with_the_wrong_secret(): void
    {
        $signature = WebhookSignature::compute(self::PAYLOAD, 'some-other-secret');

        self::assertFalse(WebhookSignature::verify(self::PAYLOAD, self::SECRET, $signature));
    }

    #[Test]
    public function it_rejects_a_tampered_payload(): void
    {
        $signature = WebhookSignature::compute(self::PAYLOAD, self::SECRET);

        self::assertFalse(WebhookSignature::verify('{"id":999}', self::SECRET, $signature));
    }
}