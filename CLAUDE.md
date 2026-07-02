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
- [ ] Git init, GitHub repo, tag v0.1.0, publish to Packagist
