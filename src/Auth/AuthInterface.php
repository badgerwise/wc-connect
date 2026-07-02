<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Auth;

use Psr\Http\Message\RequestInterface;

/**
 * Applies authentication credentials to an outgoing PSR-7 request.
 */
interface AuthInterface
{
    public function authenticate(RequestInterface $request): RequestInterface;
}
