<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LedgerEntry;
use PhpCommon\Repository\RepositoryInterface;

/**
 * Repository interface for LedgerEntry entity data access operations.
 *
 * Extends the base {@see RepositoryInterface} with ledger-specific query methods.
 * All service classes must depend on this interface, never on the concrete implementation.
 *
 * @package App\Repository
 */
interface LedgerEntryRepositoryInterface extends RepositoryInterface
{
    /**
     * Find all ledger entries associated with a specific account.
     *
     * @param int $accountId The ID of the account whose ledger entries to retrieve.
     *
     * @return array<int, LedgerEntry> An array of LedgerEntry entities for the given account (may be empty).
     */
    public function findByAccountId(int $accountId): array;

    /**
     * Find all ledger entries associated with a specific transaction.
     *
     * @param int $transactionId The ID of the transaction whose ledger entries to retrieve.
     *
     * @return array<int, LedgerEntry> An array of LedgerEntry entities for the given transaction (may be empty).
     */
    public function findByTransactionId(int $transactionId): array;
}
