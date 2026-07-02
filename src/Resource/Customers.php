<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Resource;

/**
 * The WooCommerce customers collection (wc/v3 → /customers).
 */
final class Customers extends Resource
{
    protected function endpoint(): string
    {
        return 'customers';
    }
}
