<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AuthController;
use App\DTO\LoginDTO;
use App\DTO\RegisterDTO;
use App\Entity\User;
use App\Service\AuthService;
use PhpCommon\Exception\AuthenticationException;
use PhpCommon\Security\JwtService;
use PhpCommon\Security\TokenBlacklistService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for {@see AuthController}.
 *
 * Covers register (201, 409, 400 validation) and login (200, 401, 400 validation) paths.
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
     * Set up mock dependencies and the controller under test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->authService = $this->createMock(AuthService::class);
        $jwtService        = $this->createMock(JwtService::class);
        $blacklistService  = $this->createMock(TokenBlacklistService::class);

        $this->controller = new AuthController($this->authService, $jwtService, $blacklistService);
    }

    // ── register() ────────────────────────────────────────────────────────────

    /**
     * Test that register() returns HTTP 201 with user data on success.
     *
     * @return void
     */
    public function testRegisterReturns201OnSuccess(): void
    {
        $user = new User();
        $user->setId(1);
        $user->setName('Alice');
        $user->setEmail('alice@example.com');
        $user->setCreatedAt(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));

        $this->authService
            ->expects($this->once())
            ->method('register')
            ->with($this->isInstanceOf(RegisterDTO::class))
            ->willReturn($user);

        $request  = new Request([], [], [], [], [], [], json_encode([
            'name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'secret123',
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
        $this->authService->method('register')->willThrowException(new \RuntimeException('Email already registered.'));

        $request  = new Request([], [], [], [], [], [], json_encode([
            'name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'secret123',
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
        $response = $this->controller->register(new Request([], [], [], [], [], [], 'not-json'));

        $this->assertSame(400, $response->getStatusCode());
    }

    /**
     * Test that register() returns HTTP 400 when DTO validation fails (short password).
     *
     * @return void
     */
    public function testRegisterReturns400OnValidationFailure(): void
    {
        $request  = new Request([], [], [], [], [], [], json_encode([
            'name' => 'Alice', 'email' => 'alice@example.com', 'password' => '123',
        ]));
        $response = $this->controller->register($request);

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $body);
    }

    /**
     * Test that register() returns HTTP 400 when email is invalid.
     *
     * @return void
     */
    public function testRegisterReturns400OnInvalidEmail(): void
    {
        $request  = new Request([], [], [], [], [], [], json_encode([
            'name' => 'Alice', 'email' => 'not-an-email', 'password' => 'secret123',
        ]));
        $response = $this->controller->register($request);

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $body);
    }

    // ── login() ───────────────────────────────────────────────────────────────

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
            ->with($this->isInstanceOf(LoginDTO::class))
            ->willReturn('signed.jwt.token');

        $request  = new Request([], [], [], [], [], [], json_encode([
            'email' => 'alice@example.com', 'password' => 'secret123',
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
        $this->authService->method('login')->willThrowException(new AuthenticationException('Invalid credentials.'));

        $request  = new Request([], [], [], [], [], [], json_encode([
            'email' => 'alice@example.com', 'password' => 'wrong',
        ]));
        $response = $this->controller->login($request);

        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame(401, $body['code']);
    }

    /**
     * Test that login() returns HTTP 400 when email is missing.
     *
     * @return void
     */
    public function testLoginReturns400OnMissingEmail(): void
    {
        $request  = new Request([], [], [], [], [], [], json_encode(['password' => 'secret123']));
        $response = $this->controller->login($request);

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $body);
    }

    /**
     * Test that login() returns HTTP 400 on invalid JSON.
     *
     * @return void
     */
    public function testLoginReturns400OnInvalidJson(): void
    {
        $response = $this->controller->login(new Request([], [], [], [], [], [], 'not-json'));

        $this->assertSame(400, $response->getStatusCode());
    }
}
