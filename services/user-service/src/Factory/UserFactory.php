<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use PhpCommon\Factory\FactoryInterface;

/**
 * Factory for creating {@see User} entity instances from raw data arrays.
 *
 * Centralises all User construction logic so that controllers and services
 * never instantiate User objects directly. Implements the shared
 * {@see FactoryInterface} contract.
 *
 * @package App\Factory
 */
class UserFactory implements FactoryInterface
{
    /**
     * Create and return a new User entity populated from the provided data array.
     *
     * Expected keys in $data:
     * - `name`  (string) The user's full name.
     * - `email` (string) The user's email address.
     *
     * The `createdAt` timestamp is set automatically to the current UTC time.
     *
     * @param array<string, mixed> $data Associative array containing `name` and `email`.
     *
     * @return User The newly created and populated User entity.
     */
    public function create(array $data): object
    {
        $user = new User();
        $user->setName((string) $data['name']);
        $user->setEmail((string) $data['email']);
        if (isset($data['password'])) {
            $user->setPassword((string) $data['password']);
        }
        $user->setCreatedAt(new \DateTimeImmutable());

        return $user;
    }
}
