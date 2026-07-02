<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Tests\Exception;

use BadgerWise\WcConnect\Exception\ApiException;
use BadgerWise\WcConnect\Exception\WcConnectException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiExceptionTest extends TestCase
{
    #[Test]
    public function it_extends_the_base_package_exception(): void
    {
        self::assertInstanceOf(
            WcConnectException::class,
            new ApiException('boom', 500),
        );
    }

    #[Test]
    public function from_response_extracts_the_woo_error_code_message_and_body(): void
    {
        $response = new Response(
            404,
            [],
            json_encode([
                'code' => 'woocommerce_rest_shop_order_invalid_id',
                'message' => 'Invalid ID.',
                'data' => ['status' => 404],
            ], JSON_THROW_ON_ERROR),
        );

        $exception = ApiException::fromResponse($response);

        self::assertSame(404, $exception->statusCode);
        self::assertSame('woocommerce_rest_shop_order_invalid_id', $exception->errorCode);
        self::assertSame('Invalid ID.', $exception->body['message']);
        self::assertSame(['status' => 404], $exception->body['data']);
    }

    #[Test]
    public function from_response_builds_a_descriptive_message(): void
    {
        $response = new Response(
            403,
            [],
            json_encode([
                'code' => 'woocommerce_rest_cannot_view',
                'message' => 'Sorry, you cannot list resources.',
            ], JSON_THROW_ON_ERROR),
        );

        $exception = ApiException::fromResponse($response);

        self::assertSame(
            'WooCommerce API error 403 [woocommerce_rest_cannot_view]: Sorry, you cannot list resources.',
            $exception->getMessage(),
        );
        self::assertSame(403, $exception->getCode());
    }

    #[Test]
    public function from_response_falls_back_to_the_reason_phrase_when_body_is_not_json(): void
    {
        $response = new Response(502, [], '<html>Bad Gateway</html>');

        $exception = ApiException::fromResponse($response);

        self::assertSame(502, $exception->statusCode);
        self::assertNull($exception->errorCode);
        self::assertSame([], $exception->body);
        self::assertSame('WooCommerce API error 502: Bad Gateway', $exception->getMessage());
    }

    #[Test]
    public function from_response_falls_back_to_the_reason_phrase_when_body_has_no_message(): void
    {
        $response = new Response(500, [], json_encode(['foo' => 'bar'], JSON_THROW_ON_ERROR));

        $exception = ApiException::fromResponse($response);

        self::assertNull($exception->errorCode);
        self::assertSame(['foo' => 'bar'], $exception->body);
        self::assertSame('WooCommerce API error 500: Internal Server Error', $exception->getMessage());
    }

    #[Test]
    public function from_response_ignores_a_non_string_error_code(): void
    {
        $response = new Response(
            400,
            [],
            json_encode(['code' => 12345, 'message' => 'Bad request.'], JSON_THROW_ON_ERROR),
        );

        $exception = ApiException::fromResponse($response);

        self::assertNull($exception->errorCode);
        self::assertSame('WooCommerce API error 400: Bad request.', $exception->getMessage());
    }
}
