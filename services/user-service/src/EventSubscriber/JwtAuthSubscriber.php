<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use PhpCommon\Exception\AuthenticationException;
use PhpCommon\Security\JwtService;
use PhpCommon\Security\TokenBlacklistService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Symfony kernel.request subscriber that enforces JWT blacklist checks.
 *
 * In the current architecture nginx routes service-prefixed requests directly
 * to each downstream service, bypassing the API Gateway. This subscriber runs
 * inside user-service to verify that tokens used on protected endpoints have
 * not been revoked via logout.
 *
 * Public routes (register, login, health) are whitelisted and bypass the check.
 *
 * @package App\EventSubscriber
 */
class JwtAuthSubscriber implements EventSubscriberInterface
{
    /**
     * Routes that are accessible without a JWT.
     *
     * @var array<int, array{method: string, path: string}>
     */
    private const PUBLIC_ROUTES = [
        ['method' => 'POST', 'path' => '/user/register'],
        ['method' => 'POST', 'path' => '/user/login'],
        ['method' => 'POST', 'path' => '/user/logout'],
    ];

    /**
     * The JWT service used to decode tokens.
     *
     * @var JwtService
     */
    private JwtService $jwtService;

    /**
     * The token blacklist service backed by Redis.
     *
     * @var TokenBlacklistService
     */
    private TokenBlacklistService $blacklistService;

    /**
     * Construct a new JwtAuthSubscriber.
     *
     * @param JwtService            $jwtService       The JWT verification service.
     * @param TokenBlacklistService $blacklistService The Redis-backed token blacklist.
     */
    public function __construct(JwtService $jwtService, TokenBlacklistService $blacklistService)
    {
        $this->jwtService       = $jwtService;
        $this->blacklistService = $blacklistService;
    }

    /**
     * Returns the events this subscriber listens to.
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
     * Check the JWT blacklist on every protected inbound request.
     *
     * Decodes the Bearer token and checks its JTI against the Redis blacklist.
     * Returns 401 if the token has been revoked via logout.
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

        // Allow health check endpoints
        if (str_ends_with($path, '/health')) {
            return;
        }

        // Allow whitelisted public routes
        foreach (self::PUBLIC_ROUTES as $route) {
            if ($method === $route['method'] && $path === $route['path']) {
                return;
            }
        }

        $authHeader = $request->headers->get('Authorization');
        if ($authHeader === null || !str_starts_with($authHeader, 'Bearer ')) {
            return; // Let the route handle missing auth
        }

        $token = substr($authHeader, 7);

        try {
            $payload = $this->jwtService->decode($token);
        } catch (AuthenticationException $e) {
            return; // Let the route handle invalid tokens
        }

        $jti = (string) ($payload->jti ?? '');
        if ($jti !== '' && $this->blacklistService->isBlacklisted($jti)) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Token has been revoked.', 'code' => 401],
                401
            ));
        }
    }
}
