<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data Transfer Object for initiating a fund transfer transaction.
 *
 * Carries the input data required to create a new Transaction entity.
 * Used to decouple the HTTP request payload from the domain layer.
 *
 * @package App\DTO
 */
class TransactionDTO
{
    /**
     * The ID of the account from which funds will be debited.
     *
     * @var int
     */
    public int $sourceAccountId;

    /**
     * The ID of the account to which funds will be credited.
     *
     * @var int
     */
    public int $destinationAccountId;

    /**
     * The amount to transfer, expressed as a decimal string.
     *
     * @var string
     */
    public string $amount;

    /**
     * The ISO 4217 currency code for the transfer (e.g. "USD", "EUR").
     *
     * @var string
     */
    public string $currency;
}
