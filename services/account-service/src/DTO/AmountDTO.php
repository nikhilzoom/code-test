<?php

declare(strict_types=1);

namespace App\DTO;

use PhpCommon\DTO\DtoInterface;

/**
 * Data Transfer Object for debit and credit amount requests.
 *
 * Used by the debit and credit endpoints to carry and validate the
 * `amount` field from the HTTP request body.
 *
 * @package App\DTO
 */
class AmountDTO implements DtoInterface
{
    /**
     * The monetary amount as a decimal string (e.g. "100.00").
     *
     * @var string
     */
    public string $amount;

    /**
     * Construct an AmountDTO from a raw data array.
     *
     * @param array<string, mixed> $data Must contain `amount`.
     */
    public function __construct(array $data)
    {
        $this->amount = isset($data['amount']) ? (string) $data['amount'] : '';
    }

    /**
     * Validate the DTO field values.
     *
     * Rules:
     * - `amount` must be a positive numeric decimal string.
     *
     * @return array<string> Validation error messages; empty when valid.
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->amount === '' || !is_numeric($this->amount) || (float) $this->amount <= 0) {
            $errors[] = 'amount must be a positive numeric value.';
        }

        return $errors;
    }
}
