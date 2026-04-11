<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ProxyService resolves and forwards incoming HTTP requests to downstream microservices.
 *
 * A route map of path-prefix → service base URL is injected at construction time.
 * {@see forward()} matches the incoming request path against that map, strips the
 * matched prefix, builds the target URL, and proxies the request using Symfony
 * HttpClient. On any transport or HTTP-level failure a 502 JSON error envelope is
 * returned so that callers always receive a well-formed response.
 *
 * @package App\Service
 */
class ProxyService
{
    /**
     * Symfony HTTP client used to dispatch outbound requests.
     *
     * @var HttpClientInterface
     */
    private HttpClientInterface $httpClient;

    /**
     * Map of path prefix (e.g. "/user") to service base URL (e.g. "http://user-service:9001").
     *
     * @var array<string, string>
     */
    private array $routeMap;

    /**
     * Construct a new ProxyService.
     *
     * @param HttpClientInterface   $httpClient Symfony HTTP client for outbound requests.
     * @param array<string, string> $routeMap   Map of path prefix → service base URL.
     */
    public function __construct(HttpClientInterface $httpClient, array $routeMap)
    {
        $this->httpClient = $httpClient;
        $this->routeMap   = $routeMap;
    }

    /**
     * Forward an incoming Symfony Request to the appropriate downstream service.
     *
     * Resolution steps:
     * 1. Extract the path from the incoming request.
     * 2. Match the path against the route map by longest-prefix first.
     * 3. Strip the matched prefix and append the remainder to the service base URL.
     * 4. Forward the original HTTP method, headers, and body via HttpClient.
     * 5. Stream the downstream status code, headers, and body back to the caller.
     *
     * On {@see TransportExceptionInterface} or any other exception during forwarding
     * a 502 JSON error envelope is returned.
     *
     * Request format : any HTTP method; path must start with a registered prefix.
     * Response format: downstream response verbatim, or {"error":"…","code":502} on failure.
     *
     * @param Request $request The incoming Symfony HTTP request to forward.
     *
     * @return Response The proxied downstream response, or a 502 JSON error envelope.
     */
    public function forward(Request $request): Response
    {
        $path   = $request->getPathInfo();
        $prefix = $this->resolvePrefix($path);

        if ($prefix === null) {
            return new JsonResponse(
                ['error' => 'No route found for path: ' . $path, 'code' => 502],
                Response::HTTP_BAD_GATEWAY
            );
        }

        $baseUrl   = $this->routeMap[$prefix];
        $remainder = substr($path, strlen($prefix));
        $targetUrl = rtrim($baseUrl, '/') . '/' . ltrim($remainder, '/');

        $queryString = $request->getQueryString();
        if ($queryString !== null && $queryString !== '') {
            $targetUrl .= '?' . $queryString;
        }

        try {
            $options = [
                'headers' => $this->extractHeaders($request),
            ];

            $body = $request->getContent();
            if ($body !== '' && $body !== false) {
                $options['body'] = $body;
            }

            $downstreamResponse = $this->httpClient->request(
                $request->getMethod(),
                $targetUrl,
                $options
            );

            $statusCode = $downstreamResponse->getStatusCode();
            $content    = $downstreamResponse->getContent(false);
            $headers    = $downstreamResponse->getHeaders(false);

            $response = new Response($content, $statusCode);

            foreach ($headers as $name => $values) {
                foreach ($values as $value) {
                    $response->headers->set($name, $value, false);
                }
            }

            return $response;
        } catch (TransportExceptionInterface $e) {
            return new JsonResponse(
                ['error' => 'Bad gateway: ' . $e->getMessage(), 'code' => 502],
                Response::HTTP_BAD_GATEWAY
            );
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['error' => 'Bad gateway: ' . $e->getMessage(), 'code' => 502],
                Response::HTTP_BAD_GATEWAY
            );
        }
    }

    /**
     * Resolve the longest matching path prefix from the route map for the given path.
     *
     * Iterates all registered prefixes and returns the one that is both a prefix of
     * $path and the longest match, ensuring more-specific prefixes take precedence.
     *
     * @param string $path The incoming request path (e.g. "/user/123").
     *
     * @return string|null The matched prefix string, or null if no prefix matches.
     */
    private function resolvePrefix(string $path): ?string
    {
        $matched       = null;
        $matchedLength = 0;

        foreach ($this->routeMap as $prefix => $baseUrl) {
            if (str_starts_with($path, $prefix) && strlen($prefix) > $matchedLength) {
                $matched       = $prefix;
                $matchedLength = strlen($prefix);
            }
        }

        return $matched;
    }

    /**
     * Extract forwarding-safe headers from the incoming request.
     *
     * Strips hop-by-hop headers (Host, Connection, Transfer-Encoding) that must
     * not be forwarded to downstream services.
     *
     * @param Request $request The incoming Symfony HTTP request.
     *
     * @return array<string, string> Associative map of header name → value.
     */
    private function extractHeaders(Request $request): array
    {
        $skip    = ['host', 'connection', 'transfer-encoding'];
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            if (in_array(strtolower($name), $skip, true)) {
                continue;
            }
            $headers[$name] = implode(', ', $values);
        }

        return $headers;
    }
}
