<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Auth;

use BadgerWise\WcConnect\Exception\WcConnectException;

/**
 * Builds the WordPress Application Password authorization URL and parses
 * the callback — the one-click flow for apps (e.g. a NativePHP mobile app).
 *
 * Flow:
 *  1. buildAuthorizeUrl() -> open in browser / in-app web view
 *  2. User logs in with their WP credentials and approves
 *  3. WordPress redirects to your success_url with site_url, user_login,
 *     and password in the query string
 *  4. fromCallback($_GET) -> ready-to-use ApplicationPassword auth
 */
final readonly class AuthorizationFlow
{
    public function __construct(private string $siteUrl)
    {
    }

    /**
     * @param string      $appName    Label shown to the user and in their profile
     * @param string|null $successUrl Where WordPress sends the credentials
     *                                (https:// or an app deep link)
     * @param string|null $rejectUrl  Where the user lands if they decline
     * @param string|null $appId      Optional UUID identifying your app
     */
    public function buildAuthorizeUrl(
        string $appName,
        ?string $successUrl = null,
        ?string $rejectUrl = null,
        ?string $appId = null,
    ): string {
        $params = ['app_name' => $appName];

        if ($successUrl !== null) {
            $params['success_url'] = $successUrl;
        }
        if ($rejectUrl !== null) {
            $params['reject_url'] = $rejectUrl;
        }
        if ($appId !== null) {
            $params['app_id'] = $appId;
        }

        return rtrim($this->siteUrl, '/')
            . '/wp-admin/authorize-application.php?'
            . http_build_query($params);
    }

    /**
     * Turn the callback query parameters into a ready-to-use auth object.
     *
     * @param array<string, mixed> $query Typically $_GET (or the deep-link
     *                                    query params in a mobile app)
     */
    public static function fromCallback(array $query): ApplicationPassword
    {
        $userLogin = $query['user_login'] ?? null;
        $password = $query['password'] ?? null;

        if (!is_string($userLogin) || $userLogin === ''
            || !is_string($password) || $password === ''
        ) {
            throw new WcConnectException(
                'Authorization callback is missing user_login and/or password. '
                . 'The user may have rejected the request, or the store is older '
                . 'than WordPress 5.6 / does not serve HTTPS.'
            );
        }

        return new ApplicationPassword($userLogin, $password);
    }
}
