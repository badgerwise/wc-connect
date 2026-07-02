<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Exception;

use Throwable;

/**
 * Thrown when no PSR-18 HTTP client (or PSR-17 factory) can be discovered.
 */
final class MissingHttpClientException extends WcConnectException
{
    public static function create(?Throwable $previous = null): self
    {
        return new self(
            "No PSR-18 HTTP client (or PSR-17 factories) found.\n"
            . "Install one, e.g.:\n"
            . "  composer require guzzlehttp/guzzle\n"
            . "or:\n"
            . "  composer require symfony/http-client nyholm/psr7\n"
            . 'Laravel and Symfony projects usually already include one. '
            . 'Alternatively, pass your own client explicitly: '
            . 'new WcConnect($url, $auth, client: $yourPsr18Client)',
            0,
            $previous,
        );
    }
}
