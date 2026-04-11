<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Transaction;
use PhpCommon\Repository\RepositoryInterface;

/**
 * Repository interface for Transaction entity data access operations.
 *
 * Extends the base {@see RepositoryInterface} with transaction-specific query methods.
 * All service classes must depend on this interface, never on the concrete implementation.
 *
 * @package App\Repository
 */
interface TransactionRepositoryInterface extends RepositoryInterface
{
    /**
     * Find all transactions involving a specific account (as source or destination).
     *
     * @param int $accountId The ID of the account whose transactions to retrieve.
     *
     * @return array<int, Transaction> An array of Transaction entities involving the given account (may be empty).
     */
    public function findByAccountId(int $accountId): array;
}
