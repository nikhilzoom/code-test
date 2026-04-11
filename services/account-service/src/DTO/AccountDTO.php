<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data Transfer Object for account creation requests.
 *
 * Carries the raw input data from the HTTP request layer into the service layer,
 * decoupling the transport format from the domain entity.
 *
 * @package App\DTO
 */
class AccountDTO
{
    /**
     * The ID of the user who will own the account.
     *
     * @var int
     */
    public int $userId;

    /**
     * The initial balance of the account as a decimal string.
     *
     * @var string
     */
    public string $balance;

    /**
     * The ISO 4217 currency code for the account (e.g. "USD", "EUR").
     *
     * @var string
     */
    public string $currency;
}
