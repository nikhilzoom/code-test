<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\AccountDTO;
use App\Entity\Account;

/**
 * Factory for creating {@see Account} entity instances from {@see AccountDTO}.
 *
 * Centralises all Account construction logic so that controllers and services
 * never instantiate Account objects directly.
 *
 * @package App\Factory
 */
class AccountFactory
{
    /**
     * Create and return a new Account entity populated from the provided DTO.
     *
     * @param AccountDTO $dto Validated account creation data.
     *
     * @return Account The newly created and populated Account entity.
     */
    public function create(AccountDTO $dto): Account
    {
        $account = new Account();
        $account->setUserId($dto->userId);
        $account->setBalance($dto->balance);
        $account->setCurrency($dto->currency);
        $account->setCreatedAt(new \DateTimeImmutable());

        return $account;
    }
}
