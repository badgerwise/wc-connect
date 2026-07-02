<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Exception;

use Psr\Http\Message\ResponseInterface;

/**
 * A non-2xx response from the WooCommerce / WordPress REST API.
 */
final class ApiException extends WcConnectException
{
    /**
     * @param array<string, mixed> $body Decoded JSON error body (if any)
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $errorCode = null,
        public readonly array $body = [],
    ) {
        parent::__construct($message, $statusCode);
    }

    public static function fromResponse(ResponseInterface $response): self
    {
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $decoded = json_decode($raw, true);
        $body = is_array($decoded) ? $decoded : [];

        // WP/Woo error bodies look like: {"code": "...", "message": "...", "data": {...}}
        $errorCode = isset($body['code']) && is_string($body['code']) ? $body['code'] : null;
        $message = isset($body['message']) && is_string($body['message'])
            ? $body['message']
            : $response->getReasonPhrase();

        return new self(
            sprintf('WooCommerce API error %d%s: %s', $status, $errorCode !== null ? " [{$errorCode}]" : '', $message),
            $status,
            $errorCode,
            $body,
        );
    }
}
