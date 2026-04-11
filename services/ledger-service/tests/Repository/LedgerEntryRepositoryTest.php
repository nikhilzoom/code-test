<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\LedgerEntry;
use App\Repository\LedgerEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see LedgerEntryRepository}.
 *
 * The Doctrine EntityManagerInterface is mocked so no real database is required.
 *
 * @package App\Tests\Repository
 */
class LedgerEntryRepositoryTest extends TestCase
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
     * @var LedgerEntryRepository
     */
    private LedgerEntryRepository $repository;

    /**
     * Set up a fresh entity manager mock and repository before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->emMock     = $this->createMock(EntityManagerInterface::class);
        $this->repository = new LedgerEntryRepository($this->emMock);
    }

    /**
     * Build a LedgerEntry entity with preset values for use in tests.
     *
     * @return LedgerEntry A populated LedgerEntry entity.
     */
    private function buildEntry(): LedgerEntry
    {
        $entry = new LedgerEntry();
        $entry->setTransactionId(1);
        $entry->setAccountId(2);
        $entry->setEntryType('debit');
        $entry->setAmount('100.00');
        $entry->setCreatedAt(new \DateTimeImmutable());

        return $entry;
    }

    /**
     * Test that save() calls persist() and flush() on the entity manager.
     *
     * @return void
     */
    public function testSave(): void
    {
        $entry = $this->buildEntry();

        $this->emMock->expects($this->once())->method('persist')->with($entry);
        $this->emMock->expects($this->once())->method('flush');

        $this->repository->save($entry);
    }

    /**
     * Test that findById() delegates to EntityManager::find() and returns the entity.
     *
     * @return void
     */
    public function testFindById(): void
    {
        $entry = $this->buildEntry();

        $this->emMock
            ->expects($this->once())
            ->method('find')
            ->with(LedgerEntry::class, 1)
            ->willReturn($entry);

        $result = $this->repository->findById(1);

        $this->assertSame($entry, $result);
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
            ->with(LedgerEntry::class, 999)
            ->willReturn(null);

        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    /**
     * Test that findAll() returns all ledger entries via the Doctrine entity repository.
     *
     * @return void
     */
    public function testFindAll(): void
    {
        $entry = $this->buildEntry();

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo->expects($this->once())->method('findAll')->willReturn([$entry]);

        $this->emMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(LedgerEntry::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findAll();

        $this->assertCount(1, $result);
        $this->assertSame($entry, $result[0]);
    }

    /**
     * Test that delete() removes the entity and flushes when the entity exists.
     *
     * @return void
     */
    public function testDelete(): void
    {
        $entry = $this->buildEntry();

        $this->emMock
            ->expects($this->once())
            ->method('find')
            ->with(LedgerEntry::class, 1)
            ->willReturn($entry);

        $this->emMock->expects($this->once())->method('remove')->with($entry);
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
            ->with(LedgerEntry::class, 999)
            ->willReturn(null);

        $this->emMock->expects($this->never())->method('remove');
        $this->emMock->expects($this->never())->method('flush');

        $this->repository->delete(999);
    }

    /**
     * Test that findByAccountId() returns entries belonging to the given account.
     *
     * @return void
     */
    public function testFindByAccountId(): void
    {
        $entry = $this->buildEntry();

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo
            ->expects($this->once())
            ->method('findBy')
            ->with(['accountId' => 2])
            ->willReturn([$entry]);

        $this->emMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(LedgerEntry::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findByAccountId(2);

        $this->assertCount(1, $result);
        $this->assertSame($entry, $result[0]);
    }

    /**
     * Test that findByAccountId() returns an empty array when no entries exist for the account.
     *
     * @return void
     */
    public function testFindByAccountIdReturnsEmpty(): void
    {
        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo
            ->expects($this->once())
            ->method('findBy')
            ->with(['accountId' => 99])
            ->willReturn([]);

        $this->emMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(LedgerEntry::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findByAccountId(99);

        $this->assertSame([], $result);
    }

    /**
     * Test that findByTransactionId() returns entries belonging to the given transaction.
     *
     * @return void
     */
    public function testFindByTransactionId(): void
    {
        $entry1 = $this->buildEntry();
        $entry2 = $this->buildEntry();

        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo
            ->expects($this->once())
            ->method('findBy')
            ->with(['transactionId' => 1])
            ->willReturn([$entry1, $entry2]);

        $this->emMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(LedgerEntry::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findByTransactionId(1);

        $this->assertCount(2, $result);
        $this->assertSame($entry1, $result[0]);
        $this->assertSame($entry2, $result[1]);
    }

    /**
     * Test that findByTransactionId() returns an empty array when no entries exist for the transaction.
     *
     * @return void
     */
    public function testFindByTransactionIdReturnsEmpty(): void
    {
        $doctrineRepo = $this->createMock(EntityRepository::class);
        $doctrineRepo
            ->expects($this->once())
            ->method('findBy')
            ->with(['transactionId' => 99])
            ->willReturn([]);

        $this->emMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(LedgerEntry::class)
            ->willReturn($doctrineRepo);

        $result = $this->repository->findByTransactionId(99);

        $this->assertSame([], $result);
    }
}
