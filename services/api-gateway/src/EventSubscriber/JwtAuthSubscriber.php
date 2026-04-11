<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use PhpCommon\Exception\AuthenticationException;
use PhpCommon\Security\JwtService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Symfony kernel.request subscriber that enforces JWT authentication.
 *
 * Runs before routing on every inbound request. Public routes (register,
 * login, health checks) are whitelisted and pass through without a token.
 * All other routes require a valid `Authorization: Bearer <token>` header.
 *
 * On a valid token the subscriber:
 *   - Extracts the `sub` (userId) claim from the payload
 *   - Injects it as the `X-Authenticated-User-Id` request header
 *   - Strips the `Authorization` header so it is not forwarded downstream
 *
 * @package App\EventSubscriber
 */
class JwtAuthSubscriber implements EventSubscriberInterface
{
    /**
     * Routes that are accessible without a JWT.
     * Each entry is [method, path] where path is an exact match or prefix.
     *
     * @var array<int, array{method: string, path: string}>
     */
    private const PUBLIC_ROUTES = [
        ['method' => 'POST', 'path' => '/user/register'],
        ['method' => 'POST', 'path' => '/user/login'],
    ];

    /**
     * The JWT service used to verify incoming tokens.
     *
     * @var JwtService
     */
    private JwtService $jwtService;

    /**
     * Construct a new JwtAuthSubscriber.
     *
     * @param JwtService $jwtService The JWT verification service.
     */
    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Returns the events this subscriber listens to.
     *
     * Subscribes to kernel.request at priority 10 (before routing).
     *
     * @return array<string, array<int, int|string>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    /**
     * Validate the JWT on every protected inbound request.
     *
     * Public routes and health endpoints bypass validation. For all other
     * routes, a valid Bearer token is required. On success the authenticated
     * userId is injected as X-Authenticated-User-Id and the Authorization
     * header is stripped before the request is forwarded downstream.
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
        $method  = $request->getMethod();

        // Allow health check endpoints on any service prefix
        if (str_ends_with($path, '/health')) {
            return;
        }

        // Allow explicitly whitelisted public routes
        foreach (self::PUBLIC_ROUTES as $route) {
            if ($method === $route['method'] && $path === $route['path']) {
                return;
            }
        }

        // Require Authorization header
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader === null || $authHeader === '') {
            $event->setResponse(new JsonResponse(
                ['error' => 'Authorization header missing.', 'code' => 401],
                401
            ));
            return;
        }

        // Extract Bearer token
        if (!str_starts_with($authHeader, 'Bearer ')) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Invalid or expired token.', 'code' => 401],
                401
            ));
            return;
        }

        $token = substr($authHeader, 7);

        // Verify token
        try {
            $payload = $this->jwtService->decode($token);
        } catch (AuthenticationException $e) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Invalid or expired token.', 'code' => 401],
                401
            ));
            return;
        }

        // Inject userId and strip Authorization header
        $userId = (int) ($payload->sub ?? 0);
        $request->headers->set('X-Authenticated-User-Id', (string) $userId);
        $request->headers->remove('Authorization');
    }
}
