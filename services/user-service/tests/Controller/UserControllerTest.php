<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\UserController;
use App\Entity\User;
use App\Service\UserService;
use PhpCommon\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for {@see UserController}.
 *
 * {@see UserService} is mocked so that only the controller's own logic is exercised.
 *
 * @package App\Tests\Controller
 */
class UserControllerTest extends TestCase
{
    /**
     * Mock of the user service.
     *
     * @var UserService&\PHPUnit\Framework\MockObject\MockObject
     */
    private UserService $serviceMock;

    /**
     * The controller under test.
     *
     * @var UserController
     */
    private UserController $controller;

    /**
     * Set up a fresh service mock and controller before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->serviceMock = $this->createMock(UserService::class);
        $this->controller  = new UserController($this->serviceMock);
    }

    /**
     * Build a User entity with preset values for use in tests.
     *
     * @param int    $id    The user ID to set via reflection.
     * @param string $name  The user's name.
     * @param string $email The user's email.
     *
     * @return User A populated User entity.
     */
    private function buildUser(int $id, string $name, string $email): User
    {
        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setCreatedAt(new \DateTimeImmutable('2024-01-01T00:00:00+00:00'));

        // Set the private $id via reflection since there is no public constructor param
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, $id);

        return $user;
    }

    // -------------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------------

    /**
     * Test that create() returns HTTP 201 with the new user data on success.
     *
     * @return void
     */
    public function testCreateReturns201OnSuccess(): void
    {
        $user = $this->buildUser(1, 'Alice', 'alice@example.com');

        $this->serviceMock
            ->expects($this->once())
            ->method('createUser')
            ->with(['name' => 'Alice', 'email' => 'alice@example.com'])
            ->willReturn($user);

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Alice', 'email' => 'alice@example.com'])
        );

        $response = $this->controller->create($request);

        $this->assertSame(201, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(1, $body['id']);
        $this->assertSame('Alice', $body['name']);
        $this->assertSame('alice@example.com', $body['email']);
    }

    /**
     * Test that create() returns HTTP 400 when the request body is not valid JSON.
     *
     * @return void
     */
    public function testCreateReturns400OnInvalidJson(): void
    {
        $request = new Request([], [], [], [], [], [], 'not-json');

        $response = $this->controller->create($request);

        $this->assertSame(400, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(400, $body['code']);
        $this->assertArrayHasKey('error', $body);
    }

    /**
     * Test that create() returns HTTP 500 when the service throws an unexpected exception.
     *
     * @return void
     */
    public function testCreateReturns500OnException(): void
    {
        $this->serviceMock
            ->method('createUser')
            ->willThrowException(new \RuntimeException('DB error'));

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Alice', 'email' => 'alice@example.com'])
        );

        $response = $this->controller->create($request);

        $this->assertSame(500, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(500, $body['code']);
        $this->assertSame('DB error', $body['error']);
    }

    // -------------------------------------------------------------------------
    // getById()
    // -------------------------------------------------------------------------

    /**
     * Test that getById() returns HTTP 200 with the user data when found.
     *
     * @return void
     */
    public function testGetByIdReturns200OnSuccess(): void
    {
        $user = $this->buildUser(5, 'Bob', 'bob@example.com');

        $this->serviceMock
            ->expects($this->once())
            ->method('getUserById')
            ->with(5)
            ->willReturn($user);

        $response = $this->controller->getById(5);

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(5, $body['id']);
        $this->assertSame('Bob', $body['name']);
    }

    /**
     * Test that getById() returns HTTP 404 when the user is not found.
     *
     * @return void
     */
    public function testGetByIdReturns404WhenNotFound(): void
    {
        $this->serviceMock
            ->expects($this->once())
            ->method('getUserById')
            ->with(99)
            ->willThrowException(new NotFoundException('User with id 99 not found.'));

        $response = $this->controller->getById(99);

        $this->assertSame(404, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(404, $body['code']);
        $this->assertStringContainsString('99', $body['error']);
    }

    /**
     * Test that getById() returns HTTP 500 on an unexpected exception.
     *
     * @return void
     */
    public function testGetByIdReturns500OnException(): void
    {
        $this->serviceMock
            ->method('getUserById')
            ->willThrowException(new \RuntimeException('Unexpected error'));

        $response = $this->controller->getById(1);

        $this->assertSame(500, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(500, $body['code']);
    }

    // -------------------------------------------------------------------------
    // list()
    // -------------------------------------------------------------------------

    /**
     * Test that list() returns HTTP 200 with an array of users.
     *
     * @return void
     */
    public function testListReturns200WithUsers(): void
    {
        $user1 = $this->buildUser(1, 'Alice', 'alice@example.com');
        $user2 = $this->buildUser(2, 'Bob', 'bob@example.com');

        $this->serviceMock
            ->expects($this->once())
            ->method('getAllUsers')
            ->willReturn([$user1, $user2]);

        $response = $this->controller->list();

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertCount(2, $body);
        $this->assertSame('Alice', $body[0]['name']);
        $this->assertSame('Bob', $body[1]['name']);
    }

    /**
     * Test that list() returns HTTP 200 with an empty array when no users exist.
     *
     * @return void
     */
    public function testListReturns200WithEmptyArray(): void
    {
        $this->serviceMock
            ->expects($this->once())
            ->method('getAllUsers')
            ->willReturn([]);

        $response = $this->controller->list();

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame([], $body);
    }

    /**
     * Test that list() returns HTTP 500 on an unexpected exception.
     *
     * @return void
     */
    public function testListReturns500OnException(): void
    {
        $this->serviceMock
            ->method('getAllUsers')
            ->willThrowException(new \RuntimeException('DB failure'));

        $response = $this->controller->list();

        $this->assertSame(500, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(500, $body['code']);
        $this->assertSame('DB failure', $body['error']);
    }
}
