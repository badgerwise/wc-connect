<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * In-memory PSR-18 client for tests.
 *
 * Records every request it is asked to send and returns a preconfigured
 * response (or throws a preconfigured transport exception), so tests can
 * assert on the outgoing request and control the API's reply without a
 * real network round-trip.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    private ResponseInterface $response;

    private ?ClientExceptionInterface $exception = null;

    public function __construct(?ResponseInterface $response = null)
    {
        $this->response = $response ?? new Response(200, [], '[]');
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function willReturn(int $status, string $body = '', array $headers = []): self
    {
        $this->response = new Response($status, $headers, $body);
        $this->exception = null;

        return $this;
    }

    public function willThrow(ClientExceptionInterface $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->response;
    }

    public function lastRequest(): RequestInterface
    {
        if ($this->requests === []) {
            throw new RuntimeException('No request has been sent yet.');
        }

        return $this->requests[array_key_last($this->requests)];
    }
}
