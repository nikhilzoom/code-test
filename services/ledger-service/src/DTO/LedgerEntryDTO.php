<?php

declare(strict_types=1);

namespace App\DTO;

use PhpCommon\DTO\DtoInterface;

/**
 * Data Transfer Object for ledger entry creation requests.
 *
 * Carries validated input from the HTTP request layer into the service layer,
 * decoupling the transport format from the domain entity.
 *
 * @package App\DTO
 */
class LedgerEntryDTO implements DtoInterface
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

    /**
     * Construct a LedgerEntryDTO from a raw data array.
     *
     * @param array<string, mixed> $data Must contain `transactionId`, `accountId`, `entryType`, and `amount`.
     */
    public function __construct(array $data)
    {
        $this->transactionId = isset($data['transactionId']) ? (int)    $data['transactionId'] : 0;
        $this->accountId     = isset($data['accountId'])     ? (int)    $data['accountId']     : 0;
        $this->entryType     = isset($data['entryType'])     ? (string) $data['entryType']     : '';
        $this->amount        = isset($data['amount'])        ? (string) $data['amount']        : '';
    }

    /**
     * Validate the DTO field values.
     *
     * Rules:
     * - `transactionId` must be a positive integer.
     * - `accountId`     must be a positive integer.
     * - `entryType`     must be exactly "debit" or "credit".
     * - `amount`        must be a positive numeric decimal string.
     *
     * @return array<string> Validation error messages; empty when valid.
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->transactionId <= 0) {
            $errors[] = 'transactionId must be a positive integer.';
        }

        if ($this->accountId <= 0) {
            $errors[] = 'accountId must be a positive integer.';
        }

        if (!in_array($this->entryType, ['debit', 'credit'], true)) {
            $errors[] = 'entryType must be "debit" or "credit".';
        }

        if ($this->amount === '' || !is_numeric($this->amount) || (float) $this->amount <= 0) {
            $errors[] = 'amount must be a positive numeric value.';
        }

        return $errors;
    }
}
