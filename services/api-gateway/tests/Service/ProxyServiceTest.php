<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ProxyService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Unit tests for {@see ProxyService}.
 *
 * Verifies successful request forwarding, 502 error envelope on transport failure,
 * and correct URL rewriting for each registered path prefix.
 *
 * @package App\Tests\Service
 */
class ProxyServiceTest extends TestCase
{
    /**
     * Default route map used across tests.
     *
     * @var array<string, string>
     */
    private array $routeMap = [
        '/user'        => 'http://user-service:9001',
        '/account'     => 'http://account-service:9002',
        '/transaction' => 'http://transaction-service:9003',
        '/ledger'      => 'http://ledger-service:9004',
    ];

    /**
     * Test that a successful downstream response is forwarded with the correct status and body.
     *
     * @return void
     */
    public function testForwardReturnsDownstreamResponseOnSuccess(): void
    {
        $downstreamResponse = $this->createMock(ResponseInterface::class);
        $downstreamResponse->method('getStatusCode')->willReturn(200);
        $downstreamResponse->method('getContent')->willReturn('{"id":1}');
        $downstreamResponse->method('getHeaders')->willReturn(['content-type' => ['application/json']]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'http://user-service:9001/123', $this->anything())
            ->willReturn($downstreamResponse);

        $service  = new ProxyService($httpClient, $this->routeMap);
        $request  = Request::create('/user/123', 'GET');
        $response = $service->forward($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"id":1}', $response->getContent());
    }

    /**
     * Test that a TransportExceptionInterface causes a 502 JSON error envelope.
     *
     * @return void
     */
    public function testForwardReturns502OnTransportException(): void
    {
        $transportException = new class ('connection refused') extends \RuntimeException implements TransportExceptionInterface {};

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willThrowException($transportException);

        $service  = new ProxyService($httpClient, $this->routeMap);
        $request  = Request::create('/user/1', 'GET');
        $response = $service->forward($request);

        $this->assertSame(502, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(502, $data['code']);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Test that a generic exception during forwarding also returns a 502 error envelope.
     *
     * @return void
     */
    public function testForwardReturns502OnGenericException(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willThrowException(new \RuntimeException('unexpected'));

        $service  = new ProxyService($httpClient, $this->routeMap);
        $request  = Request::create('/account/5', 'POST');
        $response = $service->forward($request);

        $this->assertSame(502, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(502, $data['code']);
    }

    /**
     * Test that a path with no matching prefix returns a 502 error envelope.
     *
     * @return void
     */
    public function testForwardReturns502WhenNoPrefixMatches(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $service  = new ProxyService($httpClient, $this->routeMap);
        $request  = Request::create('/unknown/path', 'GET');
        $response = $service->forward($request);

        $this->assertSame(502, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(502, $data['code']);
    }

    /**
     * Test URL rewriting for the /user prefix.
     *
     * @return void
     */
    public function testUrlRewritingForUserPrefix(): void
    {
        $downstreamResponse = $this->createMock(ResponseInterface::class);
        $downstreamResponse->method('getStatusCode')->willReturn(200);
        $downstreamResponse->method('getContent')->willReturn('[]');
        $downstreamResponse->method('getHeaders')->willReturn([]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'http://user-service:9001/42', $this->anything())
            ->willReturn($downstreamResponse);

        $service = new ProxyService($httpClient, $this->routeMap);
        $service->forward(Request::create('/user/42', 'GET'));
    }

    /**
     * Test URL rewriting for the /account prefix.
     *
     * @return void
     */
    public function testUrlRewritingForAccountPrefix(): void
    {
        $downstreamResponse = $this->createMock(ResponseInterface::class);
        $downstreamResponse->method('getStatusCode')->willReturn(201);
        $downstreamResponse->method('getContent')->willReturn('{}');
        $downstreamResponse->method('getHeaders')->willReturn([]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'http://account-service:9002/', $this->anything())
            ->willReturn($downstreamResponse);

        $service = new ProxyService($httpClient, $this->routeMap);
        $service->forward(Request::create('/account', 'POST'));
    }

    /**
     * Test URL rewriting for the /transaction prefix.
     *
     * @return void
     */
    public function testUrlRewritingForTransactionPrefix(): void
    {
        $downstreamResponse = $this->createMock(ResponseInterface::class);
        $downstreamResponse->method('getStatusCode')->willReturn(200);
        $downstreamResponse->method('getContent')->willReturn('{}');
        $downstreamResponse->method('getHeaders')->willReturn([]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'http://transaction-service:9003/99', $this->anything())
            ->willReturn($downstreamResponse);

        $service = new ProxyService($httpClient, $this->routeMap);
        $service->forward(Request::create('/transaction/99', 'GET'));
    }

    /**
     * Test URL rewriting for the /ledger prefix.
     *
     * @return void
     */
    public function testUrlRewritingForLedgerPrefix(): void
    {
        $downstreamResponse = $this->createMock(ResponseInterface::class);
        $downstreamResponse->method('getStatusCode')->willReturn(200);
        $downstreamResponse->method('getContent')->willReturn('[]');
        $downstreamResponse->method('getHeaders')->willReturn([]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'http://ledger-service:9004/account/7', $this->anything())
            ->willReturn($downstreamResponse);

        $service = new ProxyService($httpClient, $this->routeMap);
        $service->forward(Request::create('/ledger/account/7', 'GET'));
    }

    /**
     * Test that query string parameters are appended to the target URL.
     *
     * @return void
     */
    public function testQueryStringIsForwarded(): void
    {
        $downstreamResponse = $this->createMock(ResponseInterface::class);
        $downstreamResponse->method('getStatusCode')->willReturn(200);
        $downstreamResponse->method('getContent')->willReturn('[]');
        $downstreamResponse->method('getHeaders')->willReturn([]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'http://user-service:9001/?page=2', $this->anything())
            ->willReturn($downstreamResponse);

        $service = new ProxyService($httpClient, $this->routeMap);
        $service->forward(Request::create('/user', 'GET', ['page' => '2']));
    }
}
