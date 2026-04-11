<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Account;
use PhpCommon\Factory\FactoryInterface;

/**
 * Factory for creating {@see Account} entity instances from raw data arrays.
 *
 * Centralises all Account construction logic so that controllers and services
 * never instantiate Account objects directly. Implements the shared
 * {@see FactoryInterface} contract.
 *
 * @package App\Factory
 */
class AccountFactory implements FactoryInterface
{
    /**
     * Create and return a new Account entity populated from the provided data array.
     *
     * Expected keys in $data:
     * - `userId`   (int)    The ID of the user who owns the account.
     * - `balance`  (string) The initial balance as a decimal string.
     * - `currency` (string) The ISO 4217 currency code (3 characters).
     *
     * The `createdAt` timestamp is set automatically to the current UTC time.
     *
     * @param array<string, mixed> $data Associative array containing `userId`, `balance`, and `currency`.
     *
     * @return Account The newly created and populated Account entity.
     */
    public function create(array $data): object
    {
        $account = new Account();
        $account->setUserId((int) $data['userId']);
        $account->setBalance((string) $data['balance']);
        $account->setCurrency((string) $data['currency']);
        $account->setCreatedAt(new \DateTimeImmutable());

        return $account;
    }
}
