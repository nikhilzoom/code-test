<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\LedgerEntryDTO;
use App\Entity\LedgerEntry;

/**
 * Factory for creating {@see LedgerEntry} entity instances from {@see LedgerEntryDTO}.
 *
 * Centralises all LedgerEntry construction logic so that controllers and services
 * never instantiate LedgerEntry objects directly.
 *
 * @package App\Factory
 */
class LedgerEntryFactory
{
    /**
     * Create and return a new LedgerEntry entity populated from the provided DTO.
     *
     * The `createdAt` timestamp is set automatically to the current UTC time.
     *
     * @param LedgerEntryDTO $dto Validated ledger entry data.
     *
     * @return LedgerEntry The newly created and populated LedgerEntry entity.
     */
    public function create(LedgerEntryDTO $dto): LedgerEntry
    {
        $entry = new LedgerEntry();
        $entry->setTransactionId($dto->transactionId);
        $entry->setAccountId($dto->accountId);
        $entry->setEntryType($dto->entryType);
        $entry->setAmount($dto->amount);
        $entry->setCreatedAt(new \DateTimeImmutable());

        return $entry;
    }
}
