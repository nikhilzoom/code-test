<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see TransactionRepository}.
 *
 * The Doctrine EntityManagerInterface is mocked so no real database is required.
 *
 * @package App\Tests\Repository
 */
class TransactionRepositoryTest extends TestCase
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
     * @var TransactionRepository
     */
    private TransactionRepository $repository;

    /**
     * Set up a fresh entity manager mock and repository before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->emMock     = $this->createMock(EntityManagerInterface::class);
        $this->repository = new TransactionRepository($this->emMock);
    }

    /**
     * Build a Transaction entity with preset values for use in tests.
     *
     * @return Transaction A populated Transaction entity.
     */
    private function buildTransaction(): Transaction
    {
        $transaction = new Transaction();
        $transaction->setSourceAccountId(1);
        $transaction->setDestinationAccountId(2);
        $transaction->setAmount('100.00');
        $transaction->setCurrency('USD');
        $transaction->setStatus('pending');
        $transaction->setCreatedAt(new \DateTimeImmutable());

        return $transaction;
    }

    /**
     * Test that save() calls persist() and flush() on the entity manager.
     *
     * @return void
     */
    public function testSave(): void
    {
        $transaction = $this->buildTransaction();

        $this->emMock->expects($this->once())->method('persist')->with($transaction);
        $this->emMock->expects($this->once())->method('flush');

        $this->repository->save($transaction);
    }

    /**
     * Test that findById() delegates to EntityManager::find() and returns the entity.
     *
     * @return void
     */
    public function testFindById(): void
    {
        $transaction = $this->buildTransaction();

        $this->emMock
            ->expects($this->once())
            ->method('find')
            ->with(Transaction::class, 1)
            ->willReturn($transaction);

        $result = $this->repository->findById(1);

        $this->assertSame($transaction, $result);
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
            ->with(Transaction::class, 999)
            ->willReturn(null);

        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    /**
     * Test that findAll() returns all transactions via the Doctrine entity repository.
     *
     * @return void
     */
    public function testFindAll(): void
    {
        $transaction = $this->buildTransaction();

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->expects($this->once())->method('findAll')->willReturn([$transaction]);

        $this->emMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(Transaction::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findAll();

        $this->assertCount(1, $result);
        $this->assertSame($transaction, $result[0]);
    }

    /**
     * Test that delete() removes the entity and flushes when the entity exists.
     *
     * @return void
     */
    public function testDelete(): void
    {
        $transaction = $this->buildTransaction();

        $this->emMock
            ->expects($this->once())
            ->method('find')
            ->with(Transaction::class, 1)
            ->willReturn($transaction);

        $this->emMock->expects($this->once())->method('remove')->with($transaction);
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
            ->with(Transaction::class, 999)
            ->willReturn(null);

        $this->emMock->expects($this->never())->method('remove');
        $this->emMock->expects($this->never())->method('flush');

        $this->repository->delete(999);
    }

    /**
     * Test that findByAccountId() returns transactions involving the given account.
     *
     * @return void
     */
    public function testFindByAccountId(): void
    {
        $transaction = $this->buildTransaction();

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo
            ->expects($this->exactly(2))
            ->method('findBy')
            ->willReturnCallback(function (array $criteria) use ($transaction): array {
                if (isset($criteria['sourceAccountId']) && $criteria['sourceAccountId'] === 1) {
                    return [$transaction];
                }
                return [];
            });

        $this->emMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(Transaction::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findByAccountId(1);

        $this->assertCount(1, $result);
        $this->assertSame($transaction, $result[0]);
    }

    /**
     * Test that findByAccountId() returns an empty array when no transactions involve the account.
     *
     * @return void
     */
    public function testFindByAccountIdReturnsEmpty(): void
    {
        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo
            ->expects($this->exactly(2))
            ->method('findBy')
            ->willReturn([]);

        $this->emMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(Transaction::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findByAccountId(99);

        $this->assertSame([], $result);
    }
}
