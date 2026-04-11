<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AuthController;
use App\Entity\User;
use App\Service\AuthService;
use PhpCommon\Exception\AuthenticationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for AuthController.
 *
 * Covers register (201, 409, 400) and login (200, 401, 400) paths.
 *
 * @package App\Tests\Controller
 */
class AuthControllerTest extends TestCase
{
    /**
     * @var AuthService&\PHPUnit\Framework\MockObject\MockObject
     */
    private AuthService $authService;

    /**
     * @var AuthController
     */
    private AuthController $controller;

    /**
     * Set up mock AuthService and the controller under test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->authService = $this->createMock(AuthService::class);
        $this->controller  = new AuthController($this->authService);
    }

    /**
     * Test that register() returns HTTP 201 with user data on success.
     *
     * @return void
     */
    public function testRegisterReturns201OnSuccess(): void
    {
        $user = new User();
        $user->setName('Alice');
        $user->setEmail('alice@example.com');
        $user->setCreatedAt(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));

        $this->authService
            ->expects($this->once())
            ->method('register')
            ->willReturn($user);

        $request  = new Request([], [], [], [], [], [], json_encode([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'password' => 'secret123',
        ]));
        $response = $this->controller->register($request);

        $this->assertSame(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('Alice', $body['name']);
        $this->assertSame('alice@example.com', $body['email']);
        $this->assertArrayNotHasKey('password', $body);
    }

    /**
     * Test that register() returns HTTP 409 when email is already registered.
     *
     * @return void
     */
    public function testRegisterReturns409OnDuplicateEmail(): void
    {
        $this->authService
            ->method('register')
            ->willThrowException(new \RuntimeException('Email already registered.'));

        $request  = new Request([], [], [], [], [], [], json_encode([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'password' => 'secret123',
        ]));
        $response = $this->controller->register($request);

        $this->assertSame(409, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame(409, $body['code']);
    }

    /**
     * Test that register() returns HTTP 400 on invalid JSON body.
     *
     * @return void
     */
    public function testRegisterReturns400OnInvalidJson(): void
    {
        $request  = new Request([], [], [], [], [], [], 'not-json');
        $response = $this->controller->register($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    /**
     * Test that login() returns HTTP 200 with a token on valid credentials.
     *
     * @return void
     */
    public function testLoginReturns200WithToken(): void
    {
        $this->authService
            ->expects($this->once())
            ->method('login')
            ->willReturn('signed.jwt.token');

        $request  = new Request([], [], [], [], [], [], json_encode([
            'email'    => 'alice@example.com',
            'password' => 'secret123',
        ]));
        $response = $this->controller->login($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('signed.jwt.token', $body['token']);
    }

    /**
     * Test that login() returns HTTP 401 on invalid credentials.
     *
     * @return void
     */
    public function testLoginReturns401OnBadCredentials(): void
    {
        $this->authService
            ->method('login')
            ->willThrowException(new AuthenticationException('Invalid credentials.'));

        $request  = new Request([], [], [], [], [], [], json_encode([
            'email'    => 'alice@example.com',
            'password' => 'wrong',
        ]));
        $response = $this->controller->login($request);

        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame(401, $body['code']);
    }

    /**
     * Test that login() returns HTTP 400 on missing fields.
     *
     * @return void
     */
    public function testLoginReturns400OnMissingFields(): void
    {
        $request  = new Request([], [], [], [], [], [], json_encode(['email' => 'alice@example.com']));
        $response = $this->controller->login($request);

        $this->assertSame(400, $response->getStatusCode());
    }
}
