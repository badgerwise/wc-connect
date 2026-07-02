<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Tests;

use BadgerWise\WcConnect\Auth\AuthInterface;
use BadgerWise\WcConnect\Exception\ApiException;
use BadgerWise\WcConnect\Exception\WcConnectException;
use BadgerWise\WcConnect\Tests\Support\FakeHttpClient;
use BadgerWise\WcConnect\WcConnect;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;

final class WcConnectTest extends TestCase
{
    private FakeHttpClient $http;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
    }

    private function client(string $siteUrl = 'https://store.example.com', string $namespace = 'wc/v3'): WcConnect
    {
        $factory = new HttpFactory();

        // Auth double that stamps a header we can assert on.
        $auth = new class implements AuthInterface {
            public function authenticate(RequestInterface $request): RequestInterface
            {
                return $request->withHeader('Authorization', 'Basic dGVzdA==');
            }
        };

        return new WcConnect(
            siteUrl: $siteUrl,
            auth: $auth,
            client: $this->http,
            requestFactory: $factory,
            streamFactory: $factory,
            apiNamespace: $namespace,
        );
    }

    #[Test]
    public function it_builds_the_wp_json_url_from_site_namespace_and_endpoint(): void
    {
        $this->http->willReturn(200, '[]');

        $this->client()->get('orders');

        self::assertSame(
            'https://store.example.com/wp-json/wc/v3/orders',
            (string) $this->http->lastRequest()->getUri(),
        );
    }

    #[Test]
    public function it_trims_slashes_from_site_url_and_endpoint(): void
    {
        $this->http->willReturn(200, '[]');

        $this->client(siteUrl: 'https://store.example.com/')->get('/products');

        self::assertSame(
            'https://store.example.com/wp-json/wc/v3/products',
            (string) $this->http->lastRequest()->getUri(),
        );
    }

    #[Test]
    public function it_honours_a_custom_api_namespace(): void
    {
        $this->http->willReturn(200, '[]');

        $this->client(namespace: 'wp/v2')->get('posts');

        self::assertSame(
            'https://store.example.com/wp-json/wp/v2/posts',
            (string) $this->http->lastRequest()->getUri(),
        );
    }

    #[Test]
    public function it_appends_query_parameters_to_get_requests(): void
    {
        $this->http->willReturn(200, '[]');

        $this->client()->get('orders', ['status' => 'processing', 'per_page' => 10]);

        self::assertSame(
            'https://store.example.com/wp-json/wc/v3/orders?status=processing&per_page=10',
            (string) $this->http->lastRequest()->getUri(),
        );
    }

    #[Test]
    public function it_applies_authentication_and_standard_headers(): void
    {
        $this->http->willReturn(200, '[]');

        $this->client()->get('orders');

        $request = $this->http->lastRequest();
        self::assertSame('Basic dGVzdA==', $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('badgerwise/wc-connect', $request->getHeaderLine('User-Agent'));
    }

    #[Test]
    public function it_sends_a_json_body_on_post(): void
    {
        $this->http->willReturn(201, '{"id":1}');

        $result = $this->client()->post('orders', ['status' => 'processing']);

        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('{"status":"processing"}', (string) $request->getBody());
        self::assertSame(['id' => 1], $result);
    }

    #[Test]
    public function it_sends_a_json_body_on_put(): void
    {
        $this->http->willReturn(200, '{"id":1,"status":"completed"}');

        $this->client()->put('orders/1', ['status' => 'completed']);

        $request = $this->http->lastRequest();
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('{"status":"completed"}', (string) $request->getBody());
    }

    #[Test]
    public function it_does_not_send_a_body_on_get(): void
    {
        $this->http->willReturn(200, '[]');

        $this->client()->get('orders');

        $request = $this->http->lastRequest();
        self::assertSame('', (string) $request->getBody());
        self::assertFalse($request->hasHeader('Content-Type'));
    }

    #[Test]
    public function it_passes_query_parameters_on_delete(): void
    {
        $this->http->willReturn(200, '{"id":1}');

        $this->client()->delete('orders/1', ['force' => 'true']);

        $request = $this->http->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame(
            'https://store.example.com/wp-json/wc/v3/orders/1?force=true',
            (string) $request->getUri(),
        );
    }

    #[Test]
    public function it_decodes_a_json_object_response(): void
    {
        $this->http->willReturn(200, '{"id":42,"total":"9.99"}');

        self::assertSame(['id' => 42, 'total' => '9.99'], $this->client()->get('orders/42'));
    }

    #[Test]
    public function it_decodes_a_json_array_response(): void
    {
        $this->http->willReturn(200, '[{"id":1},{"id":2}]');

        self::assertSame([['id' => 1], ['id' => 2]], $this->client()->get('orders'));
    }

    #[Test]
    public function it_returns_an_empty_array_for_a_204_response(): void
    {
        $this->http->willReturn(204, '');

        self::assertSame([], $this->client()->delete('orders/1'));
    }

    #[Test]
    public function it_returns_an_empty_array_for_an_empty_body(): void
    {
        $this->http->willReturn(200, '');

        self::assertSame([], $this->client()->get('orders'));
    }

    #[Test]
    public function it_throws_when_the_response_is_not_json(): void
    {
        $this->http->willReturn(200, '<html>not json</html>');

        $this->expectException(WcConnectException::class);
        $this->expectExceptionMessage('Expected JSON response');

        $this->client()->get('orders');
    }

    #[Test]
    public function it_throws_an_api_exception_on_a_4xx_response(): void
    {
        $this->http->willReturn(
            404,
            '{"code":"woocommerce_rest_shop_order_invalid_id","message":"Invalid ID."}',
        );

        try {
            $this->client()->get('orders/999');
            self::fail('Expected ApiException was not thrown.');
        } catch (ApiException $e) {
            self::assertSame(404, $e->statusCode);
            self::assertSame('woocommerce_rest_shop_order_invalid_id', $e->errorCode);
            self::assertSame('Invalid ID.', $e->body['message']);
        }
    }

    #[Test]
    public function send_returns_a_response_exposing_status_headers_and_body(): void
    {
        $this->http->willReturn(
            200,
            '[{"id":1}]',
            ['X-WP-Total' => '42', 'X-WP-TotalPages' => '5'],
        );

        $response = $this->client()->send('GET', 'orders');

        self::assertSame(200, $response->statusCode);
        self::assertSame([['id' => 1]], $response->data);
        self::assertSame(42, $response->intHeader('X-WP-Total'));
        self::assertSame(5, $response->intHeader('X-WP-TotalPages'));
    }

    #[Test]
    public function send_exposes_headers_on_an_empty_body_response(): void
    {
        $this->http->willReturn(204, '', ['X-Custom' => 'yes']);

        $response = $this->client()->send('DELETE', 'orders/1');

        self::assertSame(204, $response->statusCode);
        self::assertSame([], $response->data);
        self::assertSame('yes', $response->header('X-Custom'));
    }

    #[Test]
    public function it_wraps_transport_failures_in_a_wc_connect_exception(): void
    {
        $transportError = new class ('connection refused') extends \RuntimeException implements ClientExceptionInterface {
        };
        $this->http->willThrow($transportError);

        $this->expectException(WcConnectException::class);
        $this->expectExceptionMessage('HTTP request failed: connection refused');

        $this->client()->get('orders');
    }
}
