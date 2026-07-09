<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect;

use BadgerWise\WcConnect\Auth\AuthInterface;
use BadgerWise\WcConnect\Exception\ApiException;
use BadgerWise\WcConnect\Exception\MissingHttpClientException;
use BadgerWise\WcConnect\Exception\WcConnectException;
use BadgerWise\WcConnect\Resource\Customers;
use BadgerWise\WcConnect\Resource\Orders;
use BadgerWise\WcConnect\Resource\Products;
use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Framework-agnostic WooCommerce REST API client.
 *
 * Uses whatever PSR-18 client the host project provides (auto-discovered),
 * or one injected explicitly via the constructor.
 */
final class WcConnect
{
    private readonly string $baseUrl;
    private readonly ClientInterface $client;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;

    // Lazily-built resource helpers, one instance per accessor.
    private ?Orders $orders = null;
    private ?Products $products = null;
    private ?Customers $customers = null;

    /**
     * @param string $siteUrl      Store root, e.g. https://store.example.com
     * @param string $apiNamespace REST namespace; wc/v3 default. Use e.g.
     *                             'wp/v2' to hit core WordPress endpoints.
     */
    public function __construct(
        string $siteUrl,
        private readonly AuthInterface $auth,
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        private readonly string $apiNamespace = 'wc/v3',
    ) {
        $this->baseUrl = rtrim($siteUrl, '/');

        try {
            $this->client = $client ?? Psr18ClientDiscovery::find();
            $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
            $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        } catch (NotFoundException $e) {
            throw MissingHttpClientException::create($e);
        }
    }

    /**
     * Typed helper for the /orders collection.
     */
    public function orders(): Orders
    {
        return $this->orders ??= new Orders($this);
    }

    /**
     * Typed helper for the /products collection.
     */
    public function products(): Products
    {
        return $this->products ??= new Products($this);
    }

    /**
     * Typed helper for the /customers collection.
     */
    public function customers(): Customers
    {
        return $this->customers ??= new Customers($this);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<mixed>
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, $query);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<mixed>
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, [], $data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<mixed>
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, [], $data);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<mixed>
     */
    public function delete(string $endpoint, array $query = []): array
    {
        return $this->request('DELETE', $endpoint, $query);
    }

    /**
     * @param array<string, mixed>      $query
     * @param array<string, mixed>|null $body
     *
     * @return array<mixed> Decoded JSON body
     *
     * @throws ApiException       on a non-2xx API response
     * @throws WcConnectException on transport failure or invalid JSON
     */
    public function request(string $method, string $endpoint, array $query = [], ?array $body = null): array
    {
        return $this->send($method, $endpoint, $query, $body)->data;
    }

    /**
     * Like {@see request()}, but returns the full {@see Response} (status,
     * headers, body) — needed for pagination metadata such as X-WP-Total.
     *
     * @param array<string, mixed>      $query
     * @param array<string, mixed>|null $body
     *
     * @throws ApiException       on a non-2xx API response
     * @throws WcConnectException on transport failure or invalid JSON
     */
    public function send(string $method, string $endpoint, array $query = [], ?array $body = null): Response
    {
        $uri = sprintf(
            '%s/wp-json/%s/%s',
            $this->baseUrl,
            trim($this->apiNamespace, '/'),
            ltrim($endpoint, '/'),
        );

        if ($query !== []) {
            $uri .= '?' . http_build_query($query);
        }

        $request = $this->requestFactory->createRequest($method, $uri);

        if ($body !== null) {
            $json = json_encode($body, JSON_THROW_ON_ERROR);
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($json));
        }

        return $this->dispatch($request);
    }

    /**
     * Upload a file to the WordPress Media Library and return the created media
     * object (with `id`, `source_url`, etc.). Always targets core WordPress'
     * `wp/v2/media`, regardless of this client's configured namespace.
     *
     * The authenticated user needs the WordPress `upload_files` capability.
     *
     * @param string $contents Raw file bytes.
     * @param string $filename Name WordPress stores the file under; its extension
     *                         determines the resulting attachment's type.
     * @param string $mimeType Content type, e.g. image/jpeg, image/png, application/pdf.
     *
     * @return array<mixed> The decoded media object.
     *
     * @throws ApiException       on a non-2xx API response
     * @throws WcConnectException on transport failure or invalid JSON
     */
    public function uploadMedia(string $contents, string $filename, string $mimeType): array
    {
        // Use the basename and strip characters that would break the header.
        $filename = str_replace(['"', "\r", "\n"], '', basename($filename));

        $request = $this->requestFactory
            ->createRequest('POST', sprintf('%s/wp-json/wp/v2/media', $this->baseUrl))
            ->withHeader('Content-Type', $mimeType)
            ->withHeader('Content-Disposition', sprintf('attachment; filename="%s"', $filename))
            ->withBody($this->streamFactory->createStream($contents));

        return $this->dispatch($request)->data;
    }

    /**
     * Apply the standard headers + authentication, send the request, and parse
     * the response.
     *
     * @throws WcConnectException on transport failure
     */
    private function dispatch(RequestInterface $request): Response
    {
        $request = $this->auth->authenticate(
            $request
                ->withHeader('Accept', 'application/json')
                ->withHeader('User-Agent', 'badgerwise/wc-connect'),
        );

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new WcConnectException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }

        return $this->parseResponse($response);
    }

    private function parseResponse(ResponseInterface $response): Response
    {
        $status = $response->getStatusCode();

        if ($status >= 400) {
            throw ApiException::fromResponse($response);
        }

        $raw = (string) $response->getBody();

        if ($raw === '' || $status === 204) {
            return new Response($status, [], $response->getHeaders());
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new WcConnectException(
                'Expected JSON response, got: ' . substr($raw, 0, 200)
            );
        }

        return new Response($status, $decoded, $response->getHeaders());
    }
}
