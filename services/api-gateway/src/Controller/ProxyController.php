<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ProxyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * ProxyController acts as the catch-all gateway for all non-health requests.
 *
 * Every inbound request whose path does not match a more-specific route (such as
 * /health) is captured by the wildcard route defined on {@see proxy()} and
 * delegated to {@see ProxyService::forward()}, which resolves the correct
 * downstream microservice and returns its response verbatim.
 *
 * On transport or routing failure {@see ProxyService} returns a 502 JSON error
 * envelope which this controller passes through unchanged.
 *
 * @package App\Controller
 */
class ProxyController extends AbstractController
{
    /**
     * The proxy service responsible for forwarding requests to downstream services.
     *
     * @var ProxyService
     */
    private ProxyService $proxyService;

    /**
     * Construct a new ProxyController.
     *
     * @param ProxyService $proxyService Service that resolves and forwards requests.
     */
    public function __construct(ProxyService $proxyService)
    {
        $this->proxyService = $proxyService;
    }

    /**
     * Catch-all route that forwards every non-health request to the appropriate microservice.
     *
     * Matches any path not already handled by a higher-priority route (e.g. /health).
     * The full incoming request — method, headers, query string, and body — is forwarded
     * to the downstream service resolved by {@see ProxyService}. The downstream response
     * is returned verbatim, including status code and headers.
     *
     * Request format : any HTTP method; path must begin with a registered service prefix
     *                  (/user, /account, /transaction, /ledger).
     * Response format: downstream response body and status code, or
     *                  {"error":"…","code":502} with HTTP 502 on failure.
     *
     * @Route("/{path}", name="proxy_catchall", requirements={"path"=".*"}, priority=-10)
     *
     * @param Request $request The incoming HTTP request to proxy.
     *
     * @return Response The proxied downstream response or a 502 error envelope.
     */
    #[Route('/{path}', name: 'proxy_catchall', requirements: ['path' => '.*'], priority: -10)]
    public function proxy(Request $request): Response
    {
        return $this->proxyService->forward($request);
    }
}
