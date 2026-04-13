<?php

declare(strict_types=1);

namespace App\DTO;

use PhpCommon\DTO\DtoInterface;

/**
 * Data Transfer Object for account creation requests.
 *
 * Carries validated input from the HTTP request layer into the service layer,
 * decoupling the transport format from the domain entity.
 *
 * @package App\DTO
 */
class AccountDTO implements DtoInterface
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

    /**
     * Construct an AccountDTO from a raw data array.
     *
     * @param array<string, mixed> $data Must contain `userId`, `balance`, and `currency`.
     */
    public function __construct(array $data)
    {
        $this->userId   = isset($data['userId'])   ? (int)    $data['userId']   : 0;
        $this->balance  = isset($data['balance'])  ? (string) $data['balance']  : '';
        $this->currency = isset($data['currency']) ? (string) $data['currency'] : '';
    }

    /**
     * Validate the DTO field values.
     *
     * Rules:
     * - `userId`   must be a positive integer.
     * - `balance`  must be a non-negative decimal string.
     * - `currency` must be a 3-character uppercase ISO 4217 code.
     *
     * @return array<string> Validation error messages; empty when valid.
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->userId <= 0) {
            $errors[] = 'userId must be a positive integer.';
        }

        if ($this->balance === '' || !is_numeric($this->balance) || (float) $this->balance < 0) {
            $errors[] = 'balance must be a non-negative numeric value.';
        }

        if (!preg_match('/^[A-Z]{3}$/', $this->currency)) {
            $errors[] = 'currency must be a 3-character uppercase ISO 4217 code (e.g. "USD").';
        }

        return $errors;
    }
}
