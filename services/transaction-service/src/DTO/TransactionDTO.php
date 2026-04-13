<?php

declare(strict_types=1);

namespace App\DTO;

use PhpCommon\DTO\DtoInterface;

/**
 * Data Transfer Object for initiating a fund transfer transaction.
 *
 * Carries validated input required to create a new Transaction entity.
 * Used to decouple the HTTP request payload from the domain layer.
 *
 * @package App\DTO
 */
class TransactionDTO implements DtoInterface
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

    /**
     * Construct a TransactionDTO from a raw data array.
     *
     * @param array<string, mixed> $data Must contain `sourceAccountId`, `destinationAccountId`, `amount`, and `currency`.
     */
    public function __construct(array $data)
    {
        $this->sourceAccountId      = isset($data['sourceAccountId'])      ? (int)    $data['sourceAccountId']      : 0;
        $this->destinationAccountId = isset($data['destinationAccountId']) ? (int)    $data['destinationAccountId'] : 0;
        $this->amount               = isset($data['amount'])               ? (string) $data['amount']               : '';
        $this->currency             = isset($data['currency'])             ? (string) $data['currency']             : '';
    }

    /**
     * Validate the DTO field values.
     *
     * Rules:
     * - `sourceAccountId`      must be a positive integer.
     * - `destinationAccountId` must be a positive integer.
     * - `amount`               must be a positive numeric decimal string.
     * - `currency`             must be a 3-character uppercase ISO 4217 code.
     *
     * @return array<string> Validation error messages; empty when valid.
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->sourceAccountId <= 0) {
            $errors[] = 'sourceAccountId must be a positive integer.';
        }

        if ($this->destinationAccountId <= 0) {
            $errors[] = 'destinationAccountId must be a positive integer.';
        }

        if ($this->amount === '' || !is_numeric($this->amount) || (float) $this->amount <= 0) {
            $errors[] = 'amount must be a positive numeric value.';
        }

        if (!preg_match('/^[A-Z]{3}$/', $this->currency)) {
            $errors[] = 'currency must be a 3-character uppercase ISO 4217 code (e.g. "USD").';
        }

        return $errors;
    }
}
