<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Tests\Auth;

use BadgerWise\WcConnect\Auth\ApplicationPassword;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApplicationPasswordTest extends TestCase
{
    #[Test]
    public function it_sets_a_basic_auth_header(): void
    {
        $auth = new ApplicationPassword('admin', 'password');

        $request = $auth->authenticate(new Request('GET', 'https://store.example.com'));

        self::assertSame(
            'Basic ' . base64_encode('admin:password'),
            $request->getHeaderLine('Authorization'),
        );
    }

    #[Test]
    public function it_strips_spaces_from_the_displayed_application_password(): void
    {
        // WordPress shows app passwords as "xxxx xxxx xxxx xxxx xxxx xxxx".
        $auth = new ApplicationPassword('admin', 'abcd efgh ijkl mnop qrst uvwx');

        $request = $auth->authenticate(new Request('GET', 'https://store.example.com'));

        self::assertSame(
            'Basic ' . base64_encode('admin:abcdefghijklmnopqrstuvwx'),
            $request->getHeaderLine('Authorization'),
        );
    }

    #[Test]
    public function it_returns_a_new_request_instance(): void
    {
        $auth = new ApplicationPassword('admin', 'password');
        $original = new Request('GET', 'https://store.example.com');

        $authenticated = $auth->authenticate($original);

        self::assertNotSame($original, $authenticated);
        self::assertFalse($original->hasHeader('Authorization'));
    }
}
