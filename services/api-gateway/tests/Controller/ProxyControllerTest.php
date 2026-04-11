<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ProxyController;
use App\Service\ProxyService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit tests for {@see ProxyController}.
 *
 * Verifies that the controller delegates to {@see ProxyService} and passes
 * both successful and error responses through unchanged.
 *
 * @package App\Tests\Controller
 */
class ProxyControllerTest extends TestCase
{
    /**
     * Test that a successful downstream response is returned verbatim.
     *
     * @return void
     */
    public function testProxyReturnsSuccessfulDownstreamResponse(): void
    {
        $expectedResponse = new Response('{"id":1}', 200, ['Content-Type' => 'application/json']);

        $proxyService = $this->createMock(ProxyService::class);
        $proxyService->expects($this->once())
            ->method('forward')
            ->willReturn($expectedResponse);

        $controller = new ProxyController($proxyService);
        $request    = Request::create('/user/1', 'GET');
        $response   = $controller->proxy($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"id":1}', $response->getContent());
    }

    /**
     * Test that a 502 error envelope from ProxyService is passed through unchanged.
     *
     * @return void
     */
    public function testProxyPassesThroughErrorResponse(): void
    {
        $errorResponse = new JsonResponse(['error' => 'Bad gateway: connection refused', 'code' => 502], 502);

        $proxyService = $this->createMock(ProxyService::class);
        $proxyService->expects($this->once())
            ->method('forward')
            ->willReturn($errorResponse);

        $controller = new ProxyController($proxyService);
        $request    = Request::create('/user/1', 'GET');
        $response   = $controller->proxy($request);

        $this->assertSame(502, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(502, $data['code']);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Test that the incoming Request object is forwarded to ProxyService as-is.
     *
     * @return void
     */
    public function testProxyForwardsRequestToProxyService(): void
    {
        $request          = Request::create('/account/5', 'POST', [], [], [], [], '{"amount":"100"}');
        $expectedResponse = new Response('{"id":5}', 201);

        $proxyService = $this->createMock(ProxyService::class);
        $proxyService->expects($this->once())
            ->method('forward')
            ->with($this->identicalTo($request))
            ->willReturn($expectedResponse);

        $controller = new ProxyController($proxyService);
        $response   = $controller->proxy($request);

        $this->assertSame(201, $response->getStatusCode());
    }

    /**
     * Test that a non-200 but non-error downstream response (e.g. 404) is passed through.
     *
     * @return void
     */
    public function testProxyPassesThroughNon200DownstreamResponse(): void
    {
        $notFoundResponse = new Response('{"error":"not found"}', 404);

        $proxyService = $this->createMock(ProxyService::class);
        $proxyService->method('forward')->willReturn($notFoundResponse);

        $controller = new ProxyController($proxyService);
        $response   = $controller->proxy(Request::create('/user/999', 'GET'));

        $this->assertSame(404, $response->getStatusCode());
    }
}
