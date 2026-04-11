<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Repository\UserRepositoryInterface;
use PhpCommon\Exception\NotFoundException;

/**
 * Service class encapsulating business logic for user operations.
 *
 * Depends on {@see UserRepositoryInterface} (never the concrete implementation)
 * and {@see UserFactory} for entity creation. Throws {@see NotFoundException}
 * when a requested user cannot be found.
 *
 * @package App\Service
 */
class UserService
{
    /**
     * The repository used for all user data access operations.
     *
     * @var UserRepositoryInterface
     */
    private UserRepositoryInterface $userRepository;

    /**
     * The factory used to create new User entity instances.
     *
     * @var UserFactory
     */
    private UserFactory $userFactory;

    /**
     * Construct a new UserService.
     *
     * @param UserRepositoryInterface $userRepository The user repository interface.
     * @param UserFactory             $userFactory    The user entity factory.
     */
    public function __construct(
        UserRepositoryInterface $userRepository,
        UserFactory $userFactory
    ) {
        $this->userRepository = $userRepository;
        $this->userFactory    = $userFactory;
    }

    /**
     * Create a new user from the provided data and persist it.
     *
     * Delegates entity construction to {@see UserFactory::create()} and
     * persists the result via the repository.
     *
     * @param array<string, mixed> $data Associative array with keys `name` and `email`.
     *
     * @return User The newly created and persisted User entity.
     */
    public function createUser(array $data): User
    {
        /** @var User $user */
        $user = $this->userFactory->create($data);
        $this->userRepository->save($user);

        return $user;
    }

    /**
     * Retrieve a single user by their primary key.
     *
     * @param int $id The primary key of the user to retrieve.
     *
     * @throws NotFoundException When no user exists with the given id.
     *
     * @return User The found User entity.
     */
    public function getUserById(int $id): User
    {
        /** @var User|null $user */
        $user = $this->userRepository->findById($id);

        if ($user === null) {
            throw new NotFoundException(sprintf('User with id %d not found.', $id));
        }

        return $user;
    }

    /**
     * Retrieve all users from the data store.
     *
     * @return array<int, User> An array of all User entities (may be empty).
     */
    public function getAllUsers(): array
    {
        return $this->userRepository->findAll();
    }
}
