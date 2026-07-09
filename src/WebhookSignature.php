<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect;

/**
 * Verifies WooCommerce webhook signatures.
 *
 * WooCommerce signs each webhook delivery as
 * {@code base64(HMAC-SHA256(rawBody, secret))} and sends it in the
 * {@code X-WC-Webhook-Signature} header, where {@code secret} is the webhook's
 * configured secret and {@code rawBody} is the exact request body bytes.
 *
 * A receiver must verify this before trusting the payload. Compare against the
 * **raw** body (not a re-encoded array) so the bytes match what was signed.
 */
final class WebhookSignature
{
    /**
     * Compute the signature WooCommerce would send for a payload:
     * {@code base64(HMAC-SHA256(payload, secret))}.
     */
    public static function compute(string $payload, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $payload, $secret, true));
    }

    /**
     * Timing-safe check that {@code $signature} (from the
     * {@code X-WC-Webhook-Signature} header) matches {@code $payload} (the raw
     * request body) signed with {@code $secret}. Returns false for an empty or
     * mismatched signature.
     */
    public static function verify(string $payload, string $secret, string $signature): bool
    {
        return hash_equals(self::compute($payload, $secret), $signature);
    }
}