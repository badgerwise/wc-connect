<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Resource;

use BadgerWise\WcConnect\WcConnect;

/**
 * Typed sugar over {@see WcConnect} for a single WooCommerce/WordPress
 * collection (orders, products, customers, …).
 *
 * Every method delegates to the generic client; subclasses only declare
 * their endpoint via {@see endpoint()}.
 */
abstract class Resource
{
    public function __construct(protected readonly WcConnect $client)
    {
    }

    /**
     * The collection's REST endpoint, relative to the namespace, e.g. "orders".
     */
    abstract protected function endpoint(): string;

    /**
     * List a page of records, with WooCommerce pagination metadata.
     *
     * @param array<string, mixed> $query e.g. ['per_page' => 20, 'page' => 2, 'status' => 'processing']
     */
    public function list(array $query = []): PaginatedResult
    {
        $response = $this->client->send('GET', $this->endpoint(), $query);

        /** @var list<array<mixed>> $items */
        $items = array_is_list($response->data) ? $response->data : [];

        $page = $this->intFromQuery($query, 'page', 1);
        $perPage = $this->intFromQuery($query, 'per_page', count($items));

        return new PaginatedResult(
            items: $items,
            total: $response->intHeader('X-WP-Total') ?? count($items),
            totalPages: $response->intHeader('X-WP-TotalPages') ?? 1,
            page: $page,
            perPage: $perPage,
        );
    }

    /**
     * Fetch a single record by ID.
     *
     * @param array<string, mixed> $query
     *
     * @return array<mixed>
     */
    public function find(int|string $id, array $query = []): array
    {
        return $this->client->get($this->endpoint() . '/' . $id, $query);
    }

    /**
     * Create a record.
     *
     * @param array<string, mixed> $data
     *
     * @return array<mixed>
     */
    public function create(array $data): array
    {
        return $this->client->post($this->endpoint(), $data);
    }

    /**
     * Update a record by ID.
     *
     * @param array<string, mixed> $data
     *
     * @return array<mixed>
     */
    public function update(int|string $id, array $data): array
    {
        return $this->client->put($this->endpoint() . '/' . $id, $data);
    }

    /**
     * Delete a record by ID. WooCommerce trashes by default; pass
     * ['force' => true] to delete permanently.
     *
     * @param array<string, mixed> $query
     *
     * @return array<mixed>
     */
    public function delete(int|string $id, array $query = []): array
    {
        return $this->client->delete($this->endpoint() . '/' . $id, $query);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function intFromQuery(array $query, string $key, int $default): int
    {
        $value = $query[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }
}
