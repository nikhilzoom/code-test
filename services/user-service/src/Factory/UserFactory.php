<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\UserDTO;
use App\Entity\User;

/**
 * Factory for creating {@see User} entity instances from {@see UserDTO}.
 *
 * Centralises all User construction logic so that controllers and services
 * never instantiate User objects directly.
 *
 * @package App\Factory
 */
class UserFactory
{
    /**
     * Create and return a new User entity populated from the provided DTO.
     *
     * @param UserDTO     $dto      Validated user data.
     * @param string|null $password Optional pre-hashed password string.
     *
     * @return User The newly created and populated User entity.
     */
    public function create(UserDTO $dto, ?string $password = null): User
    {
        $user = new User();
        $user->setName($dto->name);
        $user->setEmail($dto->email);

        if ($password !== null) {
            $user->setPassword($password);
        }

        $user->setCreatedAt(new \DateTimeImmutable());

        return $user;
    }
}
