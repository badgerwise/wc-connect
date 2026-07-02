<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Resource;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * A single page of a list response, plus WooCommerce's pagination metadata
 * ({@code X-WP-Total} / {@code X-WP-TotalPages}).
 *
 * Iterable and countable, so you can {@code foreach} the page directly:
 *
 *   foreach ($wc->orders()->list(['per_page' => 20]) as $order) { ... }
 *
 * @implements IteratorAggregate<int, array<mixed>>
 */
final readonly class PaginatedResult implements IteratorAggregate, Countable
{
    /**
     * @param list<array<mixed>> $items      Rows on this page
     * @param int                $total      Total matching records across all pages
     * @param int                $totalPages Total number of pages
     * @param int                $page       The page this result represents (1-based)
     * @param int                $perPage    Requested page size
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $totalPages,
        public int $page,
        public int $perPage,
    ) {
    }

    /**
     * True if there is at least one more page after this one.
     */
    public function hasMorePages(): bool
    {
        return $this->page < $this->totalPages;
    }

    /**
     * The next page number, or null if this is the last page.
     */
    public function nextPage(): ?int
    {
        return $this->hasMorePages() ? $this->page + 1 : null;
    }

    /**
     * @return Traversable<int, array<mixed>>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
