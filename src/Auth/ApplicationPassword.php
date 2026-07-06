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

    /**
     * The WordPress username these credentials authenticate as.
     *
     * Exposed so a consuming application can persist the credential — e.g.
     * store it against a user/device after the authorize-application flow.
     */
    public function username(): string
    {
        return $this->username;
    }

    /**
     * The application password, with display spaces already stripped.
     *
     * Sensitive: read this only to persist the credential securely (encrypt at
     * rest); never log it.
     */
    public function appPassword(): string
    {
        return $this->password;
    }
}
