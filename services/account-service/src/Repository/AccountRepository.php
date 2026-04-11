<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM implementation of {@see AccountRepositoryInterface}.
 *
 * Handles all persistence operations for the {@see Account} entity using
 * Doctrine's EntityManager. This class is the only place in the service
 * that directly interacts with the database for account data.
 *
 * @package App\Repository
 */
class AccountRepository implements AccountRepositoryInterface
{
    /**
     * The Doctrine entity manager used for all persistence operations.
     *
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * Construct a new AccountRepository.
     *
     * @param EntityManagerInterface $entityManager The Doctrine entity manager.
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Find an Account entity by its primary key.
     *
     * @param int $id The primary key of the account to retrieve.
     *
     * @return Account|null The found Account entity, or null if no account exists with the given id.
     */
    public function findById(int $id): ?object
    {
        return $this->entityManager->find(Account::class, $id);
    }

    /**
     * Retrieve all Account entities from the database.
     *
     * @return array<int, Account> An array of all persisted Account entities (may be empty).
     */
    public function findAll(): array
    {
        return $this->entityManager
            ->getRepository(Account::class)
            ->findAll();
    }

    /**
     * Persist a new or updated Account entity to the database.
     *
     * @param object $entity The Account entity instance to save.
     *
     * @return void
     */
    public function save(object $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * Remove an Account entity from the database by its primary key.
     *
     * @param int $id The primary key of the account to delete.
     *
     * @return void
     */
    public function delete(int $id): void
    {
        $account = $this->findById($id);
        if ($account !== null) {
            $this->entityManager->remove($account);
            $this->entityManager->flush();
        }
    }

    /**
     * Find all accounts belonging to a specific user.
     *
     * @param int $userId The ID of the user whose accounts to retrieve.
     *
     * @return array<int, Account> An array of Account entities owned by the given user (may be empty).
     */
    public function findByUserId(int $userId): array
    {
        return $this->entityManager
            ->getRepository(Account::class)
            ->findBy(['userId' => $userId]);
    }
}
