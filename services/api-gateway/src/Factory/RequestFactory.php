<?php

declare(strict_types=1);

namespace App\Factory;

use PhpCommon\Factory\FactoryInterface;

/**
 * Factory for building outbound HTTP request descriptor objects.
 *
 * Implements the shared {@see FactoryInterface} contract to produce a
 * plain-object descriptor that encapsulates all information needed to
 * dispatch an HTTP request via Symfony HttpClient: method, target URL,
 * request headers, and an optional body payload.
 *
 * @package App\Factory
 */
class RequestFactory implements FactoryInterface
{
    /**
     * Create and return a new outbound request descriptor from the provided data array.
     *
     * Expected keys in $data:
     * - `method`  (string)               HTTP verb (e.g. GET, POST, PUT, DELETE).
     * - `url`     (string)               Fully-qualified target URL.
     * - `headers` (array<string,string>) Optional map of header name → value pairs.
     * - `body`    (string|null)          Optional raw request body string.
     *
     * @param array<string, mixed> $data Associative array containing `method`, `url`,
     *                                   and optionally `headers` and `body`.
     *
     * @return object An anonymous object with public properties:
     *                `method` (string), `url` (string),
     *                `headers` (array<string,string>), `body` (?string).
     */
    public function create(array $data): object
    {
        return new class (
            (string) $data['method'],
            (string) $data['url'],
            isset($data['headers']) && is_array($data['headers']) ? $data['headers'] : [],
            isset($data['body']) ? (string) $data['body'] : null
        ) {
            /**
             * The HTTP method for the outbound request (e.g. GET, POST).
             *
             * @var string
             */
            public string $method;

            /**
             * The fully-qualified target URL for the outbound request.
             *
             * @var string
             */
            public string $url;

            /**
             * Map of HTTP header names to their values.
             *
             * @var array<string, string>
             */
            public array $headers;

            /**
             * Optional raw request body string.
             *
             * @var string|null
             */
            public ?string $body;

            /**
             * Initialise the request descriptor.
             *
             * @param string               $method  HTTP verb.
             * @param string               $url     Target URL.
             * @param array<string,string> $headers Header map.
             * @param string|null          $body    Optional body.
             */
            public function __construct(string $method, string $url, array $headers, ?string $body)
            {
                $this->method  = $method;
                $this->url     = $url;
                $this->headers = $headers;
                $this->body    = $body;
            }
        };
    }
}
