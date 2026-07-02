<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect;

/**
 * A decoded, successful ({@code < 400}) response from the REST API.
 *
 * Wraps the decoded JSON body together with the status code and response
 * headers, so callers that need pagination metadata (WooCommerce sends
 * {@code X-WP-Total} / {@code X-WP-TotalPages}) can reach it. Most callers
 * only want the body — {@see WcConnect::request()} unwraps this for them.
 */
final readonly class Response
{
    /**
     * @param array<mixed>                  $data    Decoded JSON body
     * @param array<string, array<string>>  $headers Response headers, keyed as returned by PSR-7
     */
    public function __construct(
        public int $statusCode,
        public array $data,
        /** @var array<string, array<string>> */
        public array $headers,
    ) {
    }

    /**
     * First value of a header, matched case-insensitively, or null if absent.
     */
    public function header(string $name): ?string
    {
        $needle = strtolower($name);

        foreach ($this->headers as $key => $values) {
            if (strtolower($key) === $needle) {
                return $values[0] ?? null;
            }
        }

        return null;
    }

    /**
     * Convenience: a header parsed as an integer, or null if absent/non-numeric.
     */
    public function intHeader(string $name): ?int
    {
        $value = $this->header($name);

        return $value !== null && $value !== '' && ctype_digit($value) ? (int) $value : null;
    }
}
