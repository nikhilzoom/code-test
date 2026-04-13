<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\LedgerEntryDTO;
use App\Entity\LedgerEntry;
use App\Factory\LedgerEntryFactory;
use App\Repository\LedgerEntryRepositoryInterface;
use App\Service\LedgerService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see LedgerService}.
 *
 * All dependencies are mocked so that only the service's own logic is exercised.
 *
 * @package App\Tests\Service
 */
class LedgerServiceTest extends TestCase
{
    /**
     * Mock of the ledger entry repository interface.
     *
     * @var LedgerEntryRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private LedgerEntryRepositoryInterface $repositoryMock;

    /**
     * Mock of the ledger entry factory.
     *
     * @var LedgerEntryFactory&\PHPUnit\Framework\MockObject\MockObject
     */
    private LedgerEntryFactory $factoryMock;

    /**
     * The service under test.
     *
     * @var LedgerService
     */
    private LedgerService $service;

    /**
     * Set up fresh mocks and a new service instance before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(LedgerEntryRepositoryInterface::class);
        $this->factoryMock    = $this->createMock(LedgerEntryFactory::class);
        $this->service        = new LedgerService($this->repositoryMock, $this->factoryMock);
    }

    /**
     * Build a LedgerEntry entity with preset values for use in tests.
     *
     * @param int    $id            The entry ID to set via reflection.
     * @param int    $transactionId The transaction ID.
     * @param int    $accountId     The account ID.
     * @param string $entryType     The entry type ("debit" or "credit").
     * @param string $amount        The monetary amount.
     *
     * @return LedgerEntry A populated LedgerEntry entity.
     */
    private function buildEntry(int $id, int $transactionId, int $accountId, string $entryType, string $amount): LedgerEntry
    {
        $entry = new LedgerEntry();
        $entry->setTransactionId($transactionId);
        $entry->setAccountId($accountId);
        $entry->setEntryType($entryType);
        $entry->setAmount($amount);
        $entry->setCreatedAt(new \DateTimeImmutable());

        $ref = new \ReflectionProperty(LedgerEntry::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entry, $id);

        return $entry;
    }

    /**
     * Build a LedgerEntryDTO with preset values.
     *
     * @param int    $transactionId
     * @param int    $accountId
     * @param string $entryType
     * @param string $amount
     *
     * @return LedgerEntryDTO
     */
    private function buildDto(int $transactionId, int $accountId, string $entryType, string $amount): LedgerEntryDTO
    {
        return new LedgerEntryDTO([
            'transactionId' => $transactionId,
            'accountId'     => $accountId,
            'entryType'     => $entryType,
            'amount'        => $amount,
        ]);
    }

    /**
     * Test that recordEntry delegates to the factory and repository, then returns the entity.
     *
     * @return void
     */
    public function testRecordEntryHappyPath(): void
    {
        $dto   = $this->buildDto(1, 2, 'debit', '100.00');
        $entry = $this->buildEntry(1, 1, 2, 'debit', '100.00');

        $this->factoryMock->expects($this->once())->method('create')->with($dto)->willReturn($entry);
        $this->repositoryMock->expects($this->once())->method('save')->with($entry);

        $result = $this->service->recordEntry($dto);

        $this->assertSame($entry, $result);
        $this->assertSame(1, $result->getTransactionId());
        $this->assertSame(2, $result->getAccountId());
        $this->assertSame('debit', $result->getEntryType());
        $this->assertSame('100.00', $result->getAmount());
    }

    /**
     * Test that getEntriesByAccount returns entries for the given account.
     *
     * @return void
     */
    public function testGetEntriesByAccountWithResults(): void
    {
        $entry1 = $this->buildEntry(1, 1, 2, 'debit', '100.00');
        $entry2 = $this->buildEntry(2, 2, 2, 'credit', '50.00');

        $this->repositoryMock->expects($this->once())->method('findByAccountId')->with(2)->willReturn([$entry1, $entry2]);

        $result = $this->service->getEntriesByAccount(2);

        $this->assertCount(2, $result);
        $this->assertSame($entry1, $result[0]);
        $this->assertSame($entry2, $result[1]);
    }

    /**
     * Test that getEntriesByAccount returns an empty array when no entries exist.
     *
     * @return void
     */
    public function testGetEntriesByAccountReturnsEmpty(): void
    {
        $this->repositoryMock->expects($this->once())->method('findByAccountId')->with(99)->willReturn([]);

        $this->assertSame([], $this->service->getEntriesByAccount(99));
    }

    /**
     * Test that getEntriesByTransaction returns entries for the given transaction.
     *
     * @return void
     */
    public function testGetEntriesByTransactionWithResults(): void
    {
        $entry1 = $this->buildEntry(1, 5, 2, 'debit', '200.00');
        $entry2 = $this->buildEntry(2, 5, 3, 'credit', '200.00');

        $this->repositoryMock->expects($this->once())->method('findByTransactionId')->with(5)->willReturn([$entry1, $entry2]);

        $result = $this->service->getEntriesByTransaction(5);

        $this->assertCount(2, $result);
        $this->assertSame($entry1, $result[0]);
        $this->assertSame($entry2, $result[1]);
    }

    /**
     * Test that getEntriesByTransaction returns an empty array when no entries exist.
     *
     * @return void
     */
    public function testGetEntriesByTransactionReturnsEmpty(): void
    {
        $this->repositoryMock->expects($this->once())->method('findByTransactionId')->with(99)->willReturn([]);

        $this->assertSame([], $this->service->getEntriesByTransaction(99));
    }
}
