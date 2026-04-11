<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM implementation of {@see UserRepositoryInterface}.
 *
 * Handles all persistence operations for the {@see User} entity using
 * Doctrine's EntityManager. This class is the only place in the service
 * that directly interacts with the database for user data.
 *
 * @package App\Repository
 */
class UserRepository implements UserRepositoryInterface
{
    /**
     * The Doctrine entity manager used for all persistence operations.
     *
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * Construct a new UserRepository.
     *
     * @param EntityManagerInterface $entityManager The Doctrine entity manager.
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Find a User entity by its primary key.
     *
     * @param int $id The primary key of the user to retrieve.
     *
     * @return User|null The found User entity, or null if no user exists with the given id.
     */
    public function findById(int $id): ?object
    {
        return $this->entityManager->find(User::class, $id);
    }

    /**
     * Retrieve all User entities from the database.
     *
     * @return array<int, User> An array of all persisted User entities (may be empty).
     */
    public function findAll(): array
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->findAll();
    }

    /**
     * Persist a new or updated User entity to the database.
     *
     * @param object $entity The User entity instance to save.
     *
     * @return void
     */
    public function save(object $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * Remove a User entity from the database by its primary key.
     *
     * @param int $id The primary key of the user to delete.
     *
     * @return void
     */
    public function delete(int $id): void
    {
        $user = $this->findById($id);
        if ($user !== null) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        }
    }

    /**
     * Find a User entity by their email address.
     *
     * @param string $email The email address to search for.
     *
     * @return User|null The matching User entity, or null if none exists with that email.
     */
    public function findByEmail(string $email): ?User
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);
    }
}
