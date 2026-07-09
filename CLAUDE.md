# badgerwise/wc-connect

Framework-agnostic Composer library: WooCommerce REST API client with
WordPress Application Password authentication. Installable in any PHP
project (Laravel, plain PHP, NativePHP mobile apps).

## Design decisions (do not revisit without asking)

- **PHP 8.3+ only.** Use modern features: readonly classes, `#[SensitiveParameter]`.
- **No enforced HTTP client.** Code against PSR-18/PSR-17 interfaces;
  `php-http/discovery` finds the host project's client (Laravel → Guzzle).
  Guzzle is a dev dependency only. If discovery fails, throw
  `MissingHttpClientException` with actionable install suggestions.
- **Primary auth = WordPress Application Passwords** (Basic Auth, WP 5.6+ core).
  Chosen so a NativePHP mobile app can authenticate users via
  `/wp-admin/authorize-application.php` (see `Auth\AuthorizationFlow`).
  Secondary: WooCommerce consumer key/secret (`Auth\ConsumerKeys`).
- Namespace `BadgerWise\WcConnect`, PSR-4 from `src/`.
- Default REST namespace `wc/v3`; constructor arg allows `wp/v2` etc.

## Architecture

- `src/WcConnect.php` — client. `get/post/put/delete()` → `request()` builds
  `{siteUrl}/wp-json/{namespace}/{endpoint}`, JSON in/out, throws
  `ApiException` (status, Woo error `code`, decoded body) on >= 400.
- `src/Auth/AuthInterface.php` — `authenticate(RequestInterface): RequestInterface`.
- `src/Exception/` — `WcConnectException` base (extends RuntimeException).

## Dev environment

- `composer` is NOT on PATH; system `php` is an old Zend build. Use Herd:
  `export PATH="$HOME/Library/Application Support/Herd/bin:$PATH"`,
  then `php84` and `composer` work.
- Run tests: `composer test` (PHPUnit 11). Static analysis:
  `composer analyse` (PHPStan level 8 — keep it passing).
- Owner uses PhpStorm; interpreter is Herd PHP 8.4.

## Workflow preference

The owner wants **one step at a time**: propose a step, do it, report the
result, and wait for confirmation before the next step.

## Status / next steps

- [x] Skeleton, composer.json, deps installed (2026-07-02)
- [x] Core classes, PHPStan level 8 clean
- [x] PHPUnit tests (36 tests: fake PSR-18 client covers auth headers, URL
      building, error handling, AuthorizationFlow callback parsing;
      PHPStan level 8 clean on tests too) (2026-07-02)
- [x] Typed resource helpers: `$wc->orders()`, `->products()`, `->customers()`
      (lazily cached), each extending `Resource\Resource` with
      list/find/create/update/delete. `WcConnect::send()` returns a `Response`
      DTO (status, headers, body) so `list()` reads X-WP-Total / X-WP-TotalPages
      into a `PaginatedResult` (iterable + countable). `request()`/get/post/put/
      delete unchanged and backward compatible. (2026-07-02)
- [x] README with usage examples (app password flow + consumer keys,
      authorization flow, generic client, resource helpers, pagination,
      error handling, custom HTTP client) (2026-07-02)
- [x] Git init, GitHub repo (public, badgerwise/wc-connect), tag v0.1.0
      pushed (2026-07-02). Renamed vendor/namespace goldengrip -> badgerwise.
      Pushed from the `parljohn` gh account (badgerwise org) over HTTPS.
- [x] Published to Packagist as badgerwise/wc-connect @ v0.1.0 (2026-07-02).
      Auto-update webhook (packagist.org/api/github) active on the repo via
      the Packagist GitHub OAuth connection. `composer require badgerwise/wc-connect`.
- [x] v0.2.0 (2026-07-06): `Auth\ApplicationPassword` exposes `username()` and
      `appPassword()` accessors so consuming apps can persist the credential
      after the authorize-application flow (`appPassword()` returns the
      space-stripped storable form; store URL intentionally not exposed — not
      this credential's concern). 57 tests, PHPStan level 8 clean. Committed to
      main (c31d2ae), tag v0.2.0 pushed; Packagist auto-updates via webhook.
- [x] v0.3.0 (2026-07-09): `WcConnect::uploadMedia($contents, $filename, $mimeType)`
      posts a file to WordPress' Media Library (`wp/v2/media`, raw body +
      Content-Disposition), returning the media object — targets `wp/v2` regardless
      of the client's namespace. Shared send/parse extracted into a private
      `dispatch()`. Reusable primitive for attaching images/PDFs to orders (the
      consuming app references the returned id/url in order meta_data). 60 tests,
      PHPStan level 8 clean. Tag v0.3.0 pushed; Packagist auto-updates via webhook.
