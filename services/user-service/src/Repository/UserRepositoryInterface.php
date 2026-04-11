<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use PhpCommon\Repository\RepositoryInterface;

/**
 * Repository interface for User entity data access operations.
 *
 * Extends the base {@see RepositoryInterface} with user-specific query methods.
 * All service classes must depend on this interface, never on the concrete implementation.
 *
 * @package App\Repository
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a user by their email address.
     *
     * @param string $email The email address to search for.
     *
     * @return User|null The matching User entity, or null if none exists with that email.
     */
    public function findByEmail(string $email): ?User;
}
