<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Tests;

use BadgerWise\WcConnect\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    #[Test]
    public function it_exposes_status_data_and_headers(): void
    {
        $response = new Response(200, ['id' => 1], ['X-WP-Total' => ['42']]);

        self::assertSame(200, $response->statusCode);
        self::assertSame(['id' => 1], $response->data);
        self::assertSame(['X-WP-Total' => ['42']], $response->headers);
    }

    #[Test]
    public function header_returns_the_first_value_case_insensitively(): void
    {
        $response = new Response(200, [], ['X-WP-TotalPages' => ['5', '6']]);

        self::assertSame('5', $response->header('x-wp-totalpages'));
        self::assertSame('5', $response->header('X-WP-TotalPages'));
    }

    #[Test]
    public function header_returns_null_when_absent(): void
    {
        $response = new Response(200, [], []);

        self::assertNull($response->header('X-WP-Total'));
    }

    #[Test]
    public function int_header_parses_numeric_values(): void
    {
        $response = new Response(200, [], ['X-WP-Total' => ['42']]);

        self::assertSame(42, $response->intHeader('X-WP-Total'));
    }

    #[Test]
    public function int_header_returns_null_for_missing_or_non_numeric_values(): void
    {
        $response = new Response(200, [], ['X-Weird' => ['not-a-number']]);

        self::assertNull($response->intHeader('X-WP-Total'));
        self::assertNull($response->intHeader('X-Weird'));
    }
}
