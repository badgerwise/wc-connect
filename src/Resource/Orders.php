<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Resource;

/**
 * The WooCommerce orders collection (wc/v3 → /orders).
 */
final class Orders extends Resource
{
    protected function endpoint(): string
    {
        return 'orders';
    }
}
