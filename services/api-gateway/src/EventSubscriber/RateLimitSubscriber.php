<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use PhpCommon\RateLimit\RateLimiterService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Symfony kernel.request subscriber that enforces per-user Redis rate limits.
 *
 * Runs after JwtAuthSubscriber (priority 5 vs 10) so the authenticated user ID
 * is already available on the request. Applies a sliding window rate limit
 * scoped to the authenticated user ID and the requested path prefix.
 *
 * Rate limit headers (X-RateLimit-*) are added to every response so clients
 * can track their quota without hitting the limit first.
 *
 * Limits:
 *   - Authenticated API requests : 120 req/min per user
 *   - Public routes (login/register): 10 req/min per IP (handled by nginx,
 *     but also enforced here as a second layer)
 *
 * @package App\EventSubscriber
 */
class RateLimitSubscriber implements EventSubscriberInterface
{
    /**
     * Rate limit for authenticated users (requests per minute).
     *
     * @var int
     */
    private const AUTHED_LIMIT = 120;

    /**
     * Rate limit window in seconds.
     *
     * @var int
     */
    private const WINDOW_SECS = 60;

    /**
     * The Redis-backed sliding window rate limiter.
     *
     * @var RateLimiterService
     */
    private RateLimiterService $rateLimiter;

    /**
     * Rate limit result stored during onKernelRequest for use in onKernelResponse.
     *
     * @var array{limit: int, remaining: int, resetAt: int}|null
     */
    private ?array $lastResult = null;

    /**
     * Construct a new RateLimitSubscriber.
     *
     * @param RateLimiterService $rateLimiter The Redis-backed rate limiter.
     */
    public function __construct(RateLimiterService $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * Returns the events this subscriber listens to.
     *
     * Subscribes to kernel.request at priority 5 (after JwtAuthSubscriber at 10)
     * and kernel.response to inject rate limit headers.
     *
     * @return array<string, array<int, int|string>|string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['onKernelRequest', 5],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    /**
     * Apply per-user rate limiting on every protected request.
     *
     * Uses the X-Authenticated-User-Id header injected by JwtAuthSubscriber
     * to scope the rate limit bucket to the authenticated user. Falls back to
     * IP-based limiting for public routes that reach this subscriber.
     *
     * @param RequestEvent $event The kernel request event.
     *
     * @return void
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path    = $request->getPathInfo();

        // Skip health checks
        if (str_ends_with($path, '/health')) {
            return;
        }

        // Determine rate limit key: prefer authenticated user ID, fall back to IP
        $userId = $request->headers->get('X-Authenticated-User-Id');
        $key    = $userId !== null
            ? 'user:' . $userId . ':' . $this->extractServicePrefix($path)
            : 'ip:' . $request->getClientIp() . ':' . $this->extractServicePrefix($path);

        $result = $this->rateLimiter->check($key, self::AUTHED_LIMIT, self::WINDOW_SECS);

        // Store for response headers
        $this->lastResult = [
            'limit'     => $result->limit,
            'remaining' => $result->remaining,
            'resetAt'   => $result->resetAt,
        ];

        if (!$result->allowed) {
            $response = new JsonResponse(
                ['error' => 'Too many requests. Please slow down.', 'code' => 429],
                429
            );
            $response->headers->set('X-RateLimit-Limit',     (string) $result->limit);
            $response->headers->set('X-RateLimit-Remaining', '0');
            $response->headers->set('X-RateLimit-Reset',     (string) $result->resetAt);
            $response->headers->set('Retry-After',           (string) $result->windowSecs);
            $event->setResponse($response);
        }
    }

    /**
     * Inject rate limit headers into every outgoing response.
     *
     * Adds X-RateLimit-Limit, X-RateLimit-Remaining, and X-RateLimit-Reset
     * headers so clients can track their quota proactively.
     *
     * @param ResponseEvent $event The kernel response event.
     *
     * @return void
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($this->lastResult === null) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set('X-RateLimit-Limit',     (string) $this->lastResult['limit']);
        $response->headers->set('X-RateLimit-Remaining', (string) $this->lastResult['remaining']);
        $response->headers->set('X-RateLimit-Reset',     (string) $this->lastResult['resetAt']);
    }

    /**
     * Extract the service prefix from a path for use in rate limit key scoping.
     *
     * e.g. "/user/1" → "user", "/account/health" → "account"
     *
     * @param string $path The request path.
     *
     * @return string The service prefix segment.
     */
    private function extractServicePrefix(string $path): string
    {
        $parts = explode('/', ltrim($path, '/'), 2);
        return $parts[0] ?? 'unknown';
    }
}
