<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\LedgerEntry;
use PhpCommon\Factory\FactoryInterface;

/**
 * Factory for creating {@see LedgerEntry} entity instances from raw data arrays.
 *
 * Centralises all LedgerEntry construction logic so that controllers and services
 * never instantiate LedgerEntry objects directly. Implements the shared
 * {@see FactoryInterface} contract.
 *
 * @package App\Factory
 */
class LedgerEntryFactory implements FactoryInterface
{
    /**
     * Create and return a new LedgerEntry entity populated from the provided data array.
     *
     * Expected keys in $data:
     * - `transactionId` (int)    The ID of the transaction this entry belongs to.
     * - `accountId`     (int)    The ID of the account affected by this entry.
     * - `entryType`     (string) The type of entry: "debit" or "credit".
     * - `amount`        (string) The monetary amount as a decimal string.
     *
     * The `createdAt` timestamp is set automatically to the current UTC time.
     *
     * @param array<string, mixed> $data Associative array containing `transactionId`, `accountId`, `entryType`, and `amount`.
     *
     * @return LedgerEntry The newly created and populated LedgerEntry entity.
     */
    public function create(array $data): object
    {
        $entry = new LedgerEntry();
        $entry->setTransactionId((int) $data['transactionId']);
        $entry->setAccountId((int) $data['accountId']);
        $entry->setEntryType((string) $data['entryType']);
        $entry->setAmount((string) $data['amount']);
        $entry->setCreatedAt(new \DateTimeImmutable());

        return $entry;
    }
}
