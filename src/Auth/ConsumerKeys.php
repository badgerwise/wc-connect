<?php

declare(strict_types=1);

namespace BadgerWise\WcConnect\Auth;

use Psr\Http\Message\RequestInterface;
use SensitiveParameter;

/**
 * WooCommerce REST API consumer key/secret via HTTP Basic Auth.
 *
 * Only valid over HTTPS. For plain-HTTP dev stores, enable query-string
 * mode, which passes the keys as consumer_key/consumer_secret parameters.
 */
final readonly class ConsumerKeys implements AuthInterface
{
    public function __construct(
        private string $consumerKey,
        #[SensitiveParameter] private string $consumerSecret,
        private bool $useQueryString = false,
    ) {
    }

    public function authenticate(RequestInterface $request): RequestInterface
    {
        if (!$this->useQueryString) {
            $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

            return $request->withHeader('Authorization', 'Basic ' . $credentials);
        }

        $uri = $request->getUri();
        parse_str($uri->getQuery(), $params);
        $params['consumer_key'] = $this->consumerKey;
        $params['consumer_secret'] = $this->consumerSecret;

        return $request->withUri($uri->withQuery(http_build_query($params)));
    }
}
