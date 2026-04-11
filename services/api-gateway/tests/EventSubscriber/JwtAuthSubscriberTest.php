<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\JwtAuthSubscriber;
use PhpCommon\Exception\AuthenticationException;
use PhpCommon\Security\JwtService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Unit tests for JwtAuthSubscriber.
 *
 * Covers: valid token passes through with userId injected, missing header
 * returns 401, invalid token returns 401, public routes bypass validation,
 * and health routes bypass validation.
 *
 * @package App\Tests\EventSubscriber
 */
class JwtAuthSubscriberTest extends TestCase
{
    /**
     * @var JwtService&\PHPUnit\Framework\MockObject\MockObject
     */
    private JwtService $jwtService;

    /**
     * @var JwtAuthSubscriber
     */
    private JwtAuthSubscriber $subscriber;

    /**
     * Set up mock JwtService and the subscriber under test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->jwtService = $this->createMock(JwtService::class);
        $this->subscriber = new JwtAuthSubscriber($this->jwtService);
    }

    /**
     * Build a RequestEvent for the given request.
     *
     * @param Request $request
     *
     * @return RequestEvent
     */
    private function makeEvent(Request $request): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    /**
     * Test that a valid token results in X-Authenticated-User-Id being set
     * and the Authorization header being removed.
     *
     * @return void
     */
    public function testValidTokenInjectsUserIdAndStripsAuthorization(): void
    {
        $payload       = new \stdClass();
        $payload->sub  = 7;
        $payload->email = 'alice@example.com';

        $this->jwtService
            ->expects($this->once())
            ->method('decode')
            ->with('valid.token.here')
            ->willReturn($payload);

        $request = Request::create('/account/1', 'GET');
        $request->headers->set('Authorization', 'Bearer valid.token.here');

        $event = $this->makeEvent($request);
        $this->subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse(), 'No response should be set for a valid token.');
        $this->assertSame('7', $request->headers->get('X-Authenticated-User-Id'));
        $this->assertNull($request->headers->get('Authorization'));
    }

    /**
     * Test that a missing Authorization header results in a 401 response.
     *
     * @return void
     */
    public function testMissingAuthorizationHeaderReturns401(): void
    {
        $request = Request::create('/account/1', 'GET');
        $event   = $this->makeEvent($request);

        $this->subscriber->onKernelRequest($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('Authorization header missing.', $body['error']);
    }

    /**
     * Test that an invalid token results in a 401 response.
     *
     * @return void
     */
    public function testInvalidTokenReturns401(): void
    {
        $this->jwtService
            ->method('decode')
            ->willThrowException(new AuthenticationException('Invalid or expired token.'));

        $request = Request::create('/account/1', 'GET');
        $request->headers->set('Authorization', 'Bearer bad.token');

        $event = $this->makeEvent($request);
        $this->subscriber->onKernelRequest($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('Invalid or expired token.', $body['error']);
    }

    /**
     * Test that POST /user/register bypasses JWT validation.
     *
     * @return void
     */
    public function testRegisterRouteIsPublic(): void
    {
        $this->jwtService->expects($this->never())->method('decode');

        $request = Request::create('/user/register', 'POST');
        $event   = $this->makeEvent($request);

        $this->subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    /**
     * Test that POST /user/login bypasses JWT validation.
     *
     * @return void
     */
    public function testLoginRouteIsPublic(): void
    {
        $this->jwtService->expects($this->never())->method('decode');

        $request = Request::create('/user/login', 'POST');
        $event   = $this->makeEvent($request);

        $this->subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    /**
     * Test that health check endpoints bypass JWT validation.
     *
     * @return void
     */
    public function testHealthRouteIsPublic(): void
    {
        $this->jwtService->expects($this->never())->method('decode');

        foreach (['/user/health', '/account/health', '/transaction/health', '/ledger/health', '/api/health'] as $path) {
            $request = Request::create($path, 'GET');
            $event   = $this->makeEvent($request);

            $this->subscriber->onKernelRequest($event);

            $this->assertNull($event->getResponse(), "Health route {$path} should be public.");
        }
    }

    /**
     * Test that getSubscribedEvents returns the correct event configuration.
     *
     * @return void
     */
    public function testGetSubscribedEvents(): void
    {
        $events = JwtAuthSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('kernel.request', $events);
    }
}
