<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use PhpCommon\Repository\RepositoryInterface;

/**
 * Repository interface for Account entity data access operations.
 *
 * Extends the base {@see RepositoryInterface} with account-specific query methods.
 * All service classes must depend on this interface, never on the concrete implementation.
 *
 * @package App\Repository
 */
interface AccountRepositoryInterface extends RepositoryInterface
{
    /**
     * Find all accounts belonging to a specific user.
     *
     * @param int $userId The ID of the user whose accounts to retrieve.
     *
     * @return array<int, Account> An array of Account entities owned by the given user (may be empty).
     */
    public function findByUserId(int $userId): array;
}
