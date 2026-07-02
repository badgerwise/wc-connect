<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Tests\Auth;

use BadgerWise\WcConnect\Auth\ConsumerKeys;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConsumerKeysTest extends TestCase
{
    #[Test]
    public function it_uses_a_basic_auth_header_by_default(): void
    {
        $auth = new ConsumerKeys('ck_123', 'cs_456');

        $request = $auth->authenticate(new Request('GET', 'https://store.example.com/wp-json/wc/v3/orders'));

        self::assertSame(
            'Basic ' . base64_encode('ck_123:cs_456'),
            $request->getHeaderLine('Authorization'),
        );
    }

    #[Test]
    public function header_mode_does_not_touch_the_query_string(): void
    {
        $auth = new ConsumerKeys('ck_123', 'cs_456');

        $request = $auth->authenticate(
            new Request('GET', 'https://store.example.com/wp-json/wc/v3/orders?per_page=10'),
        );

        self::assertSame('per_page=10', $request->getUri()->getQuery());
    }

    #[Test]
    public function query_string_mode_appends_credentials_as_parameters(): void
    {
        $auth = new ConsumerKeys('ck_123', 'cs_456', useQueryString: true);

        $request = $auth->authenticate(new Request('GET', 'http://dev.local/wp-json/wc/v3/orders'));

        parse_str($request->getUri()->getQuery(), $params);
        self::assertSame('ck_123', $params['consumer_key']);
        self::assertSame('cs_456', $params['consumer_secret']);
        self::assertFalse($request->hasHeader('Authorization'));
    }

    #[Test]
    public function query_string_mode_preserves_existing_query_parameters(): void
    {
        $auth = new ConsumerKeys('ck_123', 'cs_456', useQueryString: true);

        $request = $auth->authenticate(
            new Request('GET', 'http://dev.local/wp-json/wc/v3/orders?status=processing&per_page=10'),
        );

        parse_str($request->getUri()->getQuery(), $params);
        self::assertSame('processing', $params['status']);
        self::assertSame('10', $params['per_page']);
        self::assertSame('ck_123', $params['consumer_key']);
        self::assertSame('cs_456', $params['consumer_secret']);
    }
}
