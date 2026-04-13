<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\LedgerEntryDTO;
use App\Entity\LedgerEntry;
use App\Factory\LedgerEntryFactory;
use App\Repository\LedgerEntryRepositoryInterface;

/**
 * Service class encapsulating business logic for ledger operations.
 *
 * Depends on {@see LedgerEntryRepositoryInterface} (never the concrete implementation)
 * and {@see LedgerEntryFactory} for entity creation. Provides methods to record
 * new ledger entries and query existing ones by account or transaction.
 *
 * @package App\Service
 */
class LedgerService
{
    /**
     * The repository used for all ledger entry data access operations.
     *
     * @var LedgerEntryRepositoryInterface
     */
    private LedgerEntryRepositoryInterface $ledgerEntryRepository;

    /**
     * The factory used to create new LedgerEntry entity instances.
     *
     * @var LedgerEntryFactory
     */
    private LedgerEntryFactory $ledgerEntryFactory;

    /**
     * Construct a new LedgerService.
     *
     * @param LedgerEntryRepositoryInterface $ledgerEntryRepository The ledger entry repository interface.
     * @param LedgerEntryFactory             $ledgerEntryFactory    The ledger entry entity factory.
     */
    public function __construct(
        LedgerEntryRepositoryInterface $ledgerEntryRepository,
        LedgerEntryFactory $ledgerEntryFactory
    ) {
        $this->ledgerEntryRepository = $ledgerEntryRepository;
        $this->ledgerEntryFactory    = $ledgerEntryFactory;
    }

    /**
     * Record a new ledger entry from the provided DTO and persist it.
     *
     * Delegates entity construction to {@see LedgerEntryFactory::create()} and
     * persists the result via the repository.
     *
     * @param LedgerEntryDTO $dto Validated ledger entry data.
     *
     * @return LedgerEntry The newly created and persisted LedgerEntry entity.
     */
    public function recordEntry(LedgerEntryDTO $dto): LedgerEntry
    {
        $entry = $this->ledgerEntryFactory->create($dto);
        $this->ledgerEntryRepository->save($entry);

        return $entry;
    }

    /**
     * Retrieve all ledger entries associated with a specific account.
     *
     * @param int $accountId The ID of the account whose ledger entries to retrieve.
     *
     * @return array<int, LedgerEntry> An array of LedgerEntry entities for the given account (may be empty).
     */
    public function getEntriesByAccount(int $accountId): array
    {
        return $this->ledgerEntryRepository->findByAccountId($accountId);
    }

    /**
     * Retrieve all ledger entries associated with a specific transaction.
     *
     * @param int $transactionId The ID of the transaction whose ledger entries to retrieve.
     *
     * @return array<int, LedgerEntry> An array of LedgerEntry entities for the given transaction (may be empty).
     */
    public function getEntriesByTransaction(int $transactionId): array
    {
        return $this->ledgerEntryRepository->findByTransactionId($transactionId);
    }
}
