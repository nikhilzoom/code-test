<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data Transfer Object for ledger entry creation requests.
 *
 * Carries the raw input data from the HTTP request layer into the service layer,
 * decoupling the transport format from the domain entity.
 *
 * @package App\DTO
 */
class LedgerEntryDTO
{
    /**
     * The ID of the transaction this ledger entry belongs to.
     *
     * @var int
     */
    public int $transactionId;

    /**
     * The ID of the account affected by this ledger entry.
     *
     * @var int
     */
    public int $accountId;

    /**
     * The type of ledger entry: "debit" or "credit".
     *
     * @var string
     */
    public string $entryType;

    /**
     * The monetary amount of this ledger entry as a decimal string.
     *
     * @var string
     */
    public string $amount;
}
