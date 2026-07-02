<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Tests\Resource;

use BadgerWise\WcConnect\Auth\AuthInterface;
use BadgerWise\WcConnect\Resource\PaginatedResult;
use BadgerWise\WcConnect\Resource\Resource;
use BadgerWise\WcConnect\Tests\Support\FakeHttpClient;
use BadgerWise\WcConnect\WcConnect;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class ResourceTest extends TestCase
{
    private FakeHttpClient $http;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
    }

    private function resource(): Resource
    {
        $factory = new HttpFactory();
        $auth = new class implements AuthInterface {
            public function authenticate(RequestInterface $request): RequestInterface
            {
                return $request;
            }
        };

        $client = new WcConnect(
            siteUrl: 'https://store.example.com',
            auth: $auth,
            client: $this->http,
            requestFactory: $factory,
            streamFactory: $factory,
        );

        return new class ($client) extends Resource {
            protected function endpoint(): string
            {
                return 'things';
            }
        };
    }

    #[Test]
    public function list_returns_a_paginated_result_with_metadata(): void
    {
        $this->http->willReturn(
            200,
            '[{"id":1},{"id":2}]',
            ['X-WP-Total' => '57', 'X-WP-TotalPages' => '6'],
        );

        $result = $this->resource()->list(['per_page' => 10, 'page' => 2]);

        self::assertInstanceOf(PaginatedResult::class, $result);
        self::assertSame([['id' => 1], ['id' => 2]], $result->items);
        self::assertSame(57, $result->total);
        self::assertSame(6, $result->totalPages);
        self::assertSame(2, $result->page);
        self::assertSame(10, $result->perPage);
        self::assertTrue($result->hasMorePages());
        self::assertSame(3, $result->nextPage());
    }

    #[Test]
    public function list_hits_the_endpoint_with_the_query(): void
    {
        $this->http->willReturn(200, '[]');

        $this->resource()->list(['status' => 'processing', 'per_page' => 20]);

        self::assertSame(
            'https://store.example.com/wp-json/wc/v3/things?status=processing&per_page=20',
            (string) $this->http->lastRequest()->getUri(),
        );
    }

    #[Test]
    public function list_falls_back_when_pagination_headers_are_absent(): void
    {
        $this->http->willReturn(200, '[{"id":1},{"id":2},{"id":3}]');

        $result = $this->resource()->list();

        self::assertSame(3, $result->total);
        self::assertSame(1, $result->totalPages);
        self::assertSame(1, $result->page);
        self::assertSame(3, $result->perPage);
        self::assertFalse($result->hasMorePages());
        self::assertNull($result->nextPage());
    }

    #[Test]
    public function list_result_is_iterable_and_countable(): void
    {
        $this->http->willReturn(200, '[{"id":1},{"id":2}]');

        $result = $this->resource()->list();

        self::assertCount(2, $result);

        $ids = [];
        foreach ($result as $row) {
            $ids[] = $row['id'];
        }
        self::assertSame([1, 2], $ids);
    }

    #[Test]
    public function find_gets_a_single_record_by_id(): void
    {
        $this->http->willReturn(200, '{"id":42}');

        $result = $this->resource()->find(42);

        self::assertSame(['id' => 42], $result);
        $request = $this->http->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame(
            'https://store.example.com/wp-json/wc/v3/things/42',
            (string) $request->getUri(),
        );
    }

    #[Test]
    public function create_posts_the_data(): void
    {
        $this->http->willReturn(201, '{"id":1,"name":"Widget"}');

        $result = $this->resource()->create(['name' => 'Widget']);

        self::assertSame(['id' => 1, 'name' => 'Widget'], $result);
        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://store.example.com/wp-json/wc/v3/things', (string) $request->getUri());
        self::assertSame('{"name":"Widget"}', (string) $request->getBody());
    }

    #[Test]
    public function update_puts_the_data_to_the_id(): void
    {
        $this->http->willReturn(200, '{"id":42,"name":"Renamed"}');

        $this->resource()->update(42, ['name' => 'Renamed']);

        $request = $this->http->lastRequest();
        self::assertSame('PUT', $request->getMethod());
        self::assertSame(
            'https://store.example.com/wp-json/wc/v3/things/42',
            (string) $request->getUri(),
        );
        self::assertSame('{"name":"Renamed"}', (string) $request->getBody());
    }

    #[Test]
    public function delete_sends_a_delete_with_query(): void
    {
        $this->http->willReturn(200, '{"id":42,"deleted":true}');

        $this->resource()->delete(42, ['force' => 'true']);

        $request = $this->http->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame(
            'https://store.example.com/wp-json/wc/v3/things/42?force=true',
            (string) $request->getUri(),
        );
    }
}
