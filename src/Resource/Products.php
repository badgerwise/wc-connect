<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Resource;

/**
 * The WooCommerce products collection (wc/v3 → /products).
 */
final class Products extends Resource
{
    protected function endpoint(): string
    {
        return 'products';
    }
}
