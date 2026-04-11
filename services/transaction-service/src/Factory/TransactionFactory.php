<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Transaction;
use PhpCommon\Factory\FactoryInterface;

/**
 * Factory for creating {@see Transaction} entity instances from raw data arrays.
 *
 * Centralises all Transaction construction logic so that controllers and services
 * never instantiate Transaction objects directly. Implements the shared
 * {@see FactoryInterface} contract.
 *
 * @package App\Factory
 */
class TransactionFactory implements FactoryInterface
{
    /**
     * Create and return a new Transaction entity populated from the provided data array.
     *
     * Expected keys in $data:
     * - `sourceAccountId`      (int)    The ID of the account to debit.
     * - `destinationAccountId` (int)    The ID of the account to credit.
     * - `amount`               (string) The transfer amount as a decimal string.
     * - `currency`             (string) The ISO 4217 currency code (3 characters).
     * - `status`               (string) Optional initial status; defaults to 'pending'.
     *
     * The `createdAt` timestamp is set automatically to the current UTC time.
     *
     * @param array<string, mixed> $data Associative array containing transaction data.
     *
     * @return Transaction The newly created and populated Transaction entity.
     */
    public function create(array $data): object
    {
        $transaction = new Transaction();
        $transaction->setSourceAccountId((int) $data['sourceAccountId']);
        $transaction->setDestinationAccountId((int) $data['destinationAccountId']);
        $transaction->setAmount((string) $data['amount']);
        $transaction->setCurrency((string) $data['currency']);
        $transaction->setStatus((string) ($data['status'] ?? 'pending'));
        $transaction->setCreatedAt(new \DateTimeImmutable());

        return $transaction;
    }
}
