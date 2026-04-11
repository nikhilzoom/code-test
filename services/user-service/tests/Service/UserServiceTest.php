<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Repository\UserRepositoryInterface;
use App\Service\UserService;
use PhpCommon\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see UserService}.
 *
 * All dependencies are mocked so that only the service's own logic is exercised.
 *
 * @package App\Tests\Service
 */
class UserServiceTest extends TestCase
{
    /**
     * Mock of the user repository interface.
     *
     * @var UserRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private UserRepositoryInterface $repositoryMock;

    /**
     * Mock of the user factory.
     *
     * @var UserFactory&\PHPUnit\Framework\MockObject\MockObject
     */
    private UserFactory $factoryMock;

    /**
     * The service under test.
     *
     * @var UserService
     */
    private UserService $service;

    /**
     * Set up fresh mocks and a new service instance before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
        $this->factoryMock    = $this->createMock(UserFactory::class);
        $this->service        = new UserService($this->repositoryMock, $this->factoryMock);
    }

    /**
     * Test that createUser delegates to the factory and repository, then returns the entity.
     *
     * @return void
     */
    public function testCreateUserHappyPath(): void
    {
        $data = ['name' => 'Alice', 'email' => 'alice@example.com'];

        $user = new User();
        $user->setName('Alice');
        $user->setEmail('alice@example.com');
        $user->setCreatedAt(new \DateTimeImmutable());

        $this->factoryMock
            ->expects($this->once())
            ->method('create')
            ->with($data)
            ->willReturn($user);

        $this->repositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($user);

        $result = $this->service->createUser($data);

        $this->assertSame($user, $result);
        $this->assertSame('Alice', $result->getName());
        $this->assertSame('alice@example.com', $result->getEmail());
    }

    /**
     * Test that getUserById returns the user when found.
     *
     * @return void
     */
    public function testGetUserByIdHappyPath(): void
    {
        $user = new User();
        $user->setName('Bob');
        $user->setEmail('bob@example.com');
        $user->setCreatedAt(new \DateTimeImmutable());

        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with(42)
            ->willReturn($user);

        $result = $this->service->getUserById(42);

        $this->assertSame($user, $result);
    }

    /**
     * Test that getUserById throws NotFoundException when the user does not exist.
     *
     * @return void
     */
    public function testGetUserByIdThrowsNotFoundException(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with(99)
            ->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('User with id 99 not found.');

        $this->service->getUserById(99);
    }

    /**
     * Test that getAllUsers returns an empty array when no users exist.
     *
     * @return void
     */
    public function testGetAllUsersReturnsEmptyArray(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $result = $this->service->getAllUsers();

        $this->assertSame([], $result);
    }

    /**
     * Test that getAllUsers returns all users when they exist.
     *
     * @return void
     */
    public function testGetAllUsersReturnsUsers(): void
    {
        $user1 = new User();
        $user1->setName('Alice');
        $user1->setEmail('alice@example.com');
        $user1->setCreatedAt(new \DateTimeImmutable());

        $user2 = new User();
        $user2->setName('Bob');
        $user2->setEmail('bob@example.com');
        $user2->setCreatedAt(new \DateTimeImmutable());

        $this->repositoryMock
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$user1, $user2]);

        $result = $this->service->getAllUsers();

        $this->assertCount(2, $result);
        $this->assertSame($user1, $result[0]);
        $this->assertSame($user2, $result[1]);
    }
}
