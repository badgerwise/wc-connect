# wc-connect

Framework-agnostic [WooCommerce REST API](https://woocommerce.github.io/woocommerce-rest-api-docs/)
client for PHP, with first-class support for **WordPress Application Password**
authentication. Drop it into any PHP project — Laravel, plain PHP, or a
[NativePHP](https://nativephp.com) mobile app.

- **No enforced HTTP client.** Codes against PSR-18/PSR-17 interfaces and
  auto-discovers whatever client your project already has (Laravel → Guzzle).
- **Application Passwords first** (WordPress 5.6+ core), so a mobile app can
  authenticate a user through the browser and never store their real password.
- WooCommerce **consumer key/secret** supported too.
- Typed resource helpers (`$wc->orders()`, `->products()`, `->customers()`)
  with pagination, on top of a generic `get/post/put/delete` client.

## Requirements

- PHP **8.3+**
- A [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client and
  [PSR-17](https://www.php-fig.org/psr/psr-17/) factories in your project.
  Most already have one; if not:

  ```bash
  composer require guzzlehttp/guzzle
  # or
  composer require symfony/http-client nyholm/psr7
  ```

  If none can be discovered, the constructor throws
  `MissingHttpClientException` with install suggestions.

## Installation

```bash
composer require badgerwise/wc-connect
```

## Quick start

### With a WordPress Application Password

Create one under **Users → Profile → Application Passwords** in wp-admin.

```php
use BadgerWise\WcConnect\WcConnect;
use BadgerWise\WcConnect\Auth\ApplicationPassword;

$wc = new WcConnect(
    siteUrl: 'https://store.example.com',
    auth: new ApplicationPassword('your-wp-username', 'xxxx xxxx xxxx xxxx xxxx xxxx'),
);

$orders = $wc->get('orders', ['per_page' => 20, 'status' => 'processing']);
```

The spaces WordPress shows in the password are ignored — paste it as-is.

### With WooCommerce consumer keys

Generate a key/secret under **WooCommerce → Settings → Advanced → REST API**.

```php
use BadgerWise\WcConnect\WcConnect;
use BadgerWise\WcConnect\Auth\ConsumerKeys;

$wc = new WcConnect(
    siteUrl: 'https://store.example.com',
    auth: new ConsumerKeys('ck_xxxxxxxx', 'cs_xxxxxxxx'),
);
```

Consumer keys are sent as HTTP Basic auth (HTTPS only). For a plain-HTTP dev
store, pass them as query-string parameters instead:

```php
new ConsumerKeys('ck_xxxxxxxx', 'cs_xxxxxxxx', useQueryString: true);
```

## The Application Password authorization flow (mobile / one-click)

Instead of asking users to generate a password by hand, send them through
WordPress's built-in authorization screen. This is the recommended flow for a
NativePHP mobile app: WordPress hands your app a scoped application password,
and the user's real credentials never touch your code.

```php
use BadgerWise\WcConnect\Auth\AuthorizationFlow;

$flow = new AuthorizationFlow('https://store.example.com');

// 1. Send the user here (browser / in-app web view).
$url = $flow->buildAuthorizeUrl(
    appName: 'My Store App',
    successUrl: 'myapp://auth/success', // or an https:// callback
    rejectUrl: 'myapp://auth/reject',
);

// 2. The user logs in and approves. WordPress redirects to your success_url
//    with site_url, user_login and password in the query string.

// 3. Turn those callback params into ready-to-use auth.
$auth = AuthorizationFlow::fromCallback($_GET); // or your deep-link params

$wc = new WcConnect('https://store.example.com', $auth);
```

`fromCallback()` throws a `WcConnectException` if the user rejected the request
or the credentials are missing (e.g. the store predates WordPress 5.6 or does
not serve HTTPS).

## Generic client

`get`, `post`, `put`, and `delete` all return the decoded JSON body as an array.

```php
$products = $wc->get('products', ['per_page' => 50]);
$order    = $wc->get('orders/123');

$created  = $wc->post('products', ['name' => 'Widget', 'regular_price' => '9.99']);
$updated  = $wc->put('products/123', ['regular_price' => '7.99']);
$deleted  = $wc->delete('products/123', ['force' => true]);
```

Need response headers (e.g. for pagination)? Use `send()`, which returns a
`Response` (status code, headers, decoded body):

```php
$response = $wc->send('GET', 'orders', ['per_page' => 20]);

$response->statusCode;                 // 200
$response->data;                       // array of orders
$response->intHeader('X-WP-Total');    // e.g. 137
```

### A different REST namespace

The default namespace is `wc/v3`. Pass another to hit core WordPress or other
plugins:

```php
$wp = new WcConnect('https://store.example.com', $auth, apiNamespace: 'wp/v2');
$posts = $wp->get('posts');
```

### Uploading a file to the Media Library

`uploadMedia()` posts a file to WordPress' Media Library (`wp/v2/media`) and
returns the created media object — regardless of the client's namespace. Handy
for attaching images/PDFs (e.g. a delivery photo) and then referencing the
returned `id` / `source_url` from an order's `meta_data`.

```php
$media = $wc->uploadMedia(
    contents: file_get_contents('/path/to/porch.jpg'),
    filename: 'porch.jpg',
    mimeType: 'image/jpeg',
);

$media['id'];         // 510
$media['source_url']; // https://store.example.com/wp-content/uploads/porch.jpg
```

The authenticated user needs the WordPress `upload_files` capability.

## Resource helpers

Typed sugar over the generic client. Each helper exposes
`list`, `find`, `create`, `update`, and `delete`.

```php
$wc->orders();
$wc->products();
$wc->customers();
```

```php
$order   = $wc->orders()->find(123);
$created  = $wc->products()->create(['name' => 'Widget', 'regular_price' => '9.99']);
$updated  = $wc->products()->update(123, ['regular_price' => '7.99']);
$wc->products()->delete(123, ['force' => true]); // permanent; omit to trash
```

### Pagination

`list()` returns a `PaginatedResult` — iterable and countable — that also
carries WooCommerce's `X-WP-Total` / `X-WP-TotalPages` metadata:

```php
$page = $wc->orders()->list(['per_page' => 20, 'status' => 'processing']);

foreach ($page as $order) {
    // ...
}

$page->total;         // total matching orders across all pages
$page->totalPages;    // total number of pages
$page->page;          // 1
$page->hasMorePages(); // bool
$page->nextPage();     // 2, or null on the last page
```

Walk every page:

```php
$resource = $wc->orders();
$page = 1;

do {
    $result = $resource->list(['per_page' => 100, 'page' => $page]);
    foreach ($result as $order) {
        // ...
    }
    $page = $result->nextPage();
} while ($page !== null);
```

## Verifying incoming webhooks

WooCommerce can push events (new order, status change, …) to your app. Each
delivery is signed as `base64(HMAC-SHA256(rawBody, secret))` in the
`X-WC-Webhook-Signature` header. Verify it against the **raw** request body
before trusting the payload:

```php
use BadgerWise\WcConnect\WebhookSignature;

if (! WebhookSignature::verify($rawBody, $webhookSecret, $signatureHeader)) {
    // reject: 401 / ignore
}
// trusted — decode $rawBody and handle the event
```

The check is timing-safe and returns `false` for an empty or mismatched
signature. Use the exact bytes received (e.g. Laravel's `$request->getContent()`),
not a re-encoded array, or the signature won't match.

## Error handling

Every failure is a `WcConnectException` (extends `RuntimeException`), so a single
catch covers transport errors, invalid JSON, and API errors.

A non-2xx API response throws `ApiException`, which exposes the WooCommerce error
details:

```php
use BadgerWise\WcConnect\Exception\ApiException;
use BadgerWise\WcConnect\Exception\WcConnectException;

try {
    $order = $wc->orders()->find(999999);
} catch (ApiException $e) {
    $e->statusCode; // 404
    $e->errorCode;  // 'woocommerce_rest_shop_order_invalid_id'
    $e->body;       // full decoded error body
} catch (WcConnectException $e) {
    // transport failure or malformed response
}
```

## Injecting your own HTTP client

Discovery is convenient, but you can pass everything explicitly — useful in
tests or when you want a preconfigured client (timeouts, retries, logging):

```php
$wc = new WcConnect(
    siteUrl: 'https://store.example.com',
    auth: $auth,
    client: $yourPsr18Client,
    requestFactory: $yourPsr17RequestFactory,
    streamFactory: $yourPsr17StreamFactory,
);
```

## Development

`composer` is not on PATH by default here; use Herd's binaries:

```bash
export PATH="$HOME/Library/Application Support/Herd/bin:$PATH"

composer test      # PHPUnit
composer analyse   # PHPStan level 8 (src)
```

## License

MIT
