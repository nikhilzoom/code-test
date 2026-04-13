<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\TransactionDTO;
use App\Entity\Transaction;

/**
 * Factory for creating {@see Transaction} entity instances from {@see TransactionDTO}.
 *
 * Centralises all Transaction construction logic so that controllers and services
 * never instantiate Transaction objects directly.
 *
 * @package App\Factory
 */
class TransactionFactory
{
    /**
     * Create and return a new Transaction entity populated from the provided DTO.
     *
     * The initial status is always set to `pending`. The `createdAt` timestamp
     * is set automatically to the current UTC time.
     *
     * @param TransactionDTO $dto Validated transaction data.
     *
     * @return Transaction The newly created and populated Transaction entity.
     */
    public function create(TransactionDTO $dto): Transaction
    {
        $transaction = new Transaction();
        $transaction->setSourceAccountId($dto->sourceAccountId);
        $transaction->setDestinationAccountId($dto->destinationAccountId);
        $transaction->setAmount($dto->amount);
        $transaction->setCurrency($dto->currency);
        $transaction->setStatus('pending');
        $transaction->setCreatedAt(new \DateTimeImmutable());

        return $transaction;
    }
}
