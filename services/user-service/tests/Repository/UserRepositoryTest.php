<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see UserRepository}.
 *
 * The Doctrine EntityManagerInterface is mocked so no real database is required.
 *
 * @package App\Tests\Repository
 */
class UserRepositoryTest extends TestCase
{
    /**
     * Mock of the Doctrine entity manager.
     *
     * @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private EntityManagerInterface $emMock;

    /**
     * The repository under test.
     *
     * @var UserRepository
     */
    private UserRepository $repository;

    /**
     * Set up a fresh entity manager mock and repository before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->emMock     = $this->createMock(EntityManagerInterface::class);
        $this->repository = new UserRepository($this->emMock);
    }

    /**
     * Test that save() calls persist() and flush() on the entity manager.
     *
     * @return void
     */
    public function testSave(): void
    {
        $user = new User();
        $user->setName('Alice');
        $user->setEmail('alice@example.com');
        $user->setCreatedAt(new \DateTimeImmutable());

        $this->emMock->expects($this->once())->method('persist')->with($user);
        $this->emMock->expects($this->once())->method('flush');

        $this->repository->save($user);
    }

    /**
     * Test that findById() delegates to EntityManager::find() and returns the entity.
     *
     * @return void
     */
    public function testFindById(): void
    {
        $user = new User();
        $user->setName('Alice');
        $user->setEmail('alice@example.com');
        $user->setCreatedAt(new \DateTimeImmutable());

        $this->emMock
            ->expects($this->once())
            ->method('find')
            ->with(User::class, 1)
            ->willReturn($user);

        $result = $this->repository->findById(1);

        $this->assertSame($user, $result);
    }

    /**
     * Test that findById() returns null when no entity exists with the given id.
     *
     * @return void
     */
    public function testFindByIdReturnsNull(): void
    {
        $this->emMock
            ->expects($this->once())
            ->method('find')
            ->with(User::class, 999)
            ->willReturn(null);

        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    /**
     * Test that findAll() returns all users via the Doctrine entity repository.
     *
     * @return void
     */
    public function testFindAll(): void
    {
        $user = new User();
        $user->setName('Alice');
        $user->setEmail('alice@example.com');
        $user->setCreatedAt(new \DateTimeImmutable());

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->expects($this->once())->method('findAll')->willReturn([$user]);

        $this->emMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findAll();

        $this->assertCount(1, $result);
        $this->assertSame($user, $result[0]);
    }

    /**
     * Test that delete() removes the entity and flushes when the entity exists.
     *
     * @return void
     */
    public function testDelete(): void
    {
        $user = new User();
        $user->setName('Alice');
        $user->setEmail('alice@example.com');
        $user->setCreatedAt(new \DateTimeImmutable());

        $this->emMock
            ->expects($this->once())
            ->method('find')
            ->with(User::class, 1)
            ->willReturn($user);

        $this->emMock->expects($this->once())->method('remove')->with($user);
        $this->emMock->expects($this->once())->method('flush');

        $this->repository->delete(1);
    }

    /**
     * Test that delete() does nothing when no entity exists with the given id.
     *
     * @return void
     */
    public function testDeleteDoesNothingWhenNotFound(): void
    {
        $this->emMock
            ->expects($this->once())
            ->method('find')
            ->with(User::class, 999)
            ->willReturn(null);

        $this->emMock->expects($this->never())->method('remove');
        $this->emMock->expects($this->never())->method('flush');

        $this->repository->delete(999);
    }

    /**
     * Test that findByEmail() returns the matching user via findOneBy().
     *
     * @return void
     */
    public function testFindByEmail(): void
    {
        $user = new User();
        $user->setName('Alice');
        $user->setEmail('alice@example.com');
        $user->setCreatedAt(new \DateTimeImmutable());

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'alice@example.com'])
            ->willReturn($user);

        $this->emMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findByEmail('alice@example.com');

        $this->assertSame($user, $result);
    }

    /**
     * Test that findByEmail() returns null when no user has the given email.
     *
     * @return void
     */
    public function testFindByEmailReturnsNull(): void
    {
        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'unknown@example.com'])
            ->willReturn(null);

        $this->emMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findByEmail('unknown@example.com');

        $this->assertNull($result);
    }
}
