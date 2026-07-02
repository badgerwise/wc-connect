<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Tests\Resource;

use BadgerWise\WcConnect\Auth\AuthInterface;
use BadgerWise\WcConnect\Resource\Customers;
use BadgerWise\WcConnect\Resource\Orders;
use BadgerWise\WcConnect\Resource\Products;
use BadgerWise\WcConnect\Tests\Support\FakeHttpClient;
use BadgerWise\WcConnect\WcConnect;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class ResourceAccessorsTest extends TestCase
{
    private FakeHttpClient $http;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
    }

    private function client(): WcConnect
    {
        $factory = new HttpFactory();
        $auth = new class implements AuthInterface {
            public function authenticate(RequestInterface $request): RequestInterface
            {
                return $request;
            }
        };

        return new WcConnect(
            siteUrl: 'https://store.example.com',
            auth: $auth,
            client: $this->http,
            requestFactory: $factory,
            streamFactory: $factory,
        );
    }

    #[Test]
    public function accessors_return_the_expected_resource_types(): void
    {
        $client = $this->client();

        self::assertInstanceOf(Orders::class, $client->orders());
        self::assertInstanceOf(Products::class, $client->products());
        self::assertInstanceOf(Customers::class, $client->customers());
    }

    #[Test]
    public function accessors_return_a_cached_instance(): void
    {
        $client = $this->client();

        self::assertSame($client->orders(), $client->orders());
        self::assertSame($client->products(), $client->products());
        self::assertSame($client->customers(), $client->customers());
    }

    #[Test]
    public function orders_hits_the_orders_endpoint(): void
    {
        $this->http->willReturn(200, '[]');

        $this->client()->orders()->list();

        self::assertSame(
            'https://store.example.com/wp-json/wc/v3/orders',
            (string) $this->http->lastRequest()->getUri(),
        );
    }

    #[Test]
    public function products_hits_the_products_endpoint(): void
    {
        $this->http->willReturn(200, '{"id":7}');

        $this->client()->products()->find(7);

        self::assertSame(
            'https://store.example.com/wp-json/wc/v3/products/7',
            (string) $this->http->lastRequest()->getUri(),
        );
    }

    #[Test]
    public function customers_hits_the_customers_endpoint(): void
    {
        $this->http->willReturn(201, '{"id":1}');

        $this->client()->customers()->create(['email' => 'a@b.test']);

        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'https://store.example.com/wp-json/wc/v3/customers',
            (string) $request->getUri(),
        );
    }
}
