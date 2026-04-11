<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Account;
use App\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see AccountRepository}.
 *
 * The Doctrine EntityManagerInterface is mocked so no real database is required.
 *
 * @package App\Tests\Repository
 */
class AccountRepositoryTest extends TestCase
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
     * @var AccountRepository
     */
    private AccountRepository $repository;

    /**
     * Set up a fresh entity manager mock and repository before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->emMock     = $this->createMock(EntityManagerInterface::class);
        $this->repository = new AccountRepository($this->emMock);
    }

    /**
     * Build an Account entity with preset values for use in tests.
     *
     * @return Account A populated Account entity.
     */
    private function buildAccount(): Account
    {
        $account = new Account();
        $account->setUserId(1);
        $account->setBalance('1000.00');
        $account->setCurrency('USD');
        $account->setCreatedAt(new \DateTimeImmutable());

        return $account;
    }

    /**
     * Test that save() calls persist() and flush() on the entity manager.
     *
     * @return void
     */
    public function testSave(): void
    {
        $account = $this->buildAccount();

        $this->emMock->expects($this->once())->method('persist')->with($account);
        $this->emMock->expects($this->once())->method('flush');

        $this->repository->save($account);
    }

    /**
     * Test that findById() delegates to EntityManager::find() and returns the entity.
     *
     * @return void
     */
    public function testFindById(): void
    {
        $account = $this->buildAccount();

        $this->emMock
            ->expects($this->once())
            ->method('find')
            ->with(Account::class, 1)
            ->willReturn($account);

        $result = $this->repository->findById(1);

        $this->assertSame($account, $result);
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
            ->with(Account::class, 999)
            ->willReturn(null);

        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    /**
     * Test that findAll() returns all accounts via the Doctrine entity repository.
     *
     * @return void
     */
    public function testFindAll(): void
    {
        $account = $this->buildAccount();

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->expects($this->once())->method('findAll')->willReturn([$account]);

        $this->emMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(Account::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findAll();

        $this->assertCount(1, $result);
        $this->assertSame($account, $result[0]);
    }

    /**
     * Test that delete() removes the entity and flushes when the entity exists.
     *
     * @return void
     */
    public function testDelete(): void
    {
        $account = $this->buildAccount();

        $this->emMock
            ->expects($this->once())
            ->method('find')
            ->with(Account::class, 1)
            ->willReturn($account);

        $this->emMock->expects($this->once())->method('remove')->with($account);
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
            ->with(Account::class, 999)
            ->willReturn(null);

        $this->emMock->expects($this->never())->method('remove');
        $this->emMock->expects($this->never())->method('flush');

        $this->repository->delete(999);
    }

    /**
     * Test that findByUserId() returns accounts belonging to the given user.
     *
     * @return void
     */
    public function testFindByUserId(): void
    {
        $account = $this->buildAccount();

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo
            ->expects($this->once())
            ->method('findBy')
            ->with(['userId' => 1])
            ->willReturn([$account]);

        $this->emMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(Account::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findByUserId(1);

        $this->assertCount(1, $result);
        $this->assertSame($account, $result[0]);
    }

    /**
     * Test that findByUserId() returns an empty array when no accounts exist for the user.
     *
     * @return void
     */
    public function testFindByUserIdReturnsEmpty(): void
    {
        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo
            ->expects($this->once())
            ->method('findBy')
            ->with(['userId' => 99])
            ->willReturn([]);

        $this->emMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(Account::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findByUserId(99);

        $this->assertSame([], $result);
    }
}
