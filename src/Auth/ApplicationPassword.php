<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Auth;

use Psr\Http\Message\RequestInterface;
use SensitiveParameter;

/**
 * HTTP Basic Auth using a WordPress Application Password (WP 5.6+ core).
 *
 * Works against any WP REST namespace, including WooCommerce (wc/v3).
 * Spaces in the displayed password are ignored by WordPress, so we strip them.
 */
final readonly class ApplicationPassword implements AuthInterface
{
    private string $password;

    public function __construct(
        private string $username,
        #[SensitiveParameter] string $appPassword,
    ) {
        $this->password = str_replace(' ', '', $appPassword);
    }

    public function authenticate(RequestInterface $request): RequestInterface
    {
        $credentials = base64_encode($this->username . ':' . $this->password);

        return $request->withHeader('Authorization', 'Basic ' . $credentials);
    }
}
