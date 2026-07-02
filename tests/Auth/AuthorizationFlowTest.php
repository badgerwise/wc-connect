<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Tests\Auth;

use BadgerWise\WcConnect\Auth\ApplicationPassword;
use BadgerWise\WcConnect\Auth\AuthorizationFlow;
use BadgerWise\WcConnect\Exception\WcConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuthorizationFlowTest extends TestCase
{
    #[Test]
    public function it_builds_a_minimal_authorize_url(): void
    {
        $flow = new AuthorizationFlow('https://store.example.com');

        $url = $flow->buildAuthorizeUrl('My App');

        self::assertStringStartsWith(
            'https://store.example.com/wp-admin/authorize-application.php?',
            $url,
        );
        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $params);
        self::assertSame('My App', $params['app_name']);
        self::assertArrayNotHasKey('success_url', $params);
        self::assertArrayNotHasKey('reject_url', $params);
        self::assertArrayNotHasKey('app_id', $params);
    }

    #[Test]
    public function it_includes_optional_parameters_when_provided(): void
    {
        $flow = new AuthorizationFlow('https://store.example.com/');

        $url = $flow->buildAuthorizeUrl(
            appName: 'My App',
            successUrl: 'myapp://auth/success',
            rejectUrl: 'myapp://auth/reject',
            appId: 'a1b2c3d4',
        );

        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $params);
        self::assertSame('My App', $params['app_name']);
        self::assertSame('myapp://auth/success', $params['success_url']);
        self::assertSame('myapp://auth/reject', $params['reject_url']);
        self::assertSame('a1b2c3d4', $params['app_id']);
    }

    #[Test]
    public function it_trims_a_trailing_slash_from_the_site_url(): void
    {
        $flow = new AuthorizationFlow('https://store.example.com/');

        $url = $flow->buildAuthorizeUrl('My App');

        self::assertStringStartsWith(
            'https://store.example.com/wp-admin/authorize-application.php?',
            $url,
        );
    }

    #[Test]
    public function from_callback_builds_a_working_application_password_auth(): void
    {
        $auth = AuthorizationFlow::fromCallback([
            'site_url' => 'https://store.example.com',
            'user_login' => 'admin',
            'password' => 'abcd efgh ijkl mnop',
        ]);

        self::assertInstanceOf(ApplicationPassword::class, $auth);

        $request = $auth->authenticate(new Request('GET', 'https://store.example.com'));
        self::assertSame(
            'Basic ' . base64_encode('admin:abcdefghijklmnop'),
            $request->getHeaderLine('Authorization'),
        );
    }

    #[Test]
    public function from_callback_throws_when_credentials_are_missing(): void
    {
        $this->expectException(WcConnectException::class);
        $this->expectExceptionMessage('missing user_login and/or password');

        AuthorizationFlow::fromCallback(['site_url' => 'https://store.example.com']);
    }

    #[Test]
    public function from_callback_throws_when_credentials_are_empty(): void
    {
        $this->expectException(WcConnectException::class);

        AuthorizationFlow::fromCallback(['user_login' => '', 'password' => '']);
    }

    #[Test]
    public function from_callback_throws_when_credentials_are_not_strings(): void
    {
        $this->expectException(WcConnectException::class);

        AuthorizationFlow::fromCallback(['user_login' => ['admin'], 'password' => 123]);
    }
}
