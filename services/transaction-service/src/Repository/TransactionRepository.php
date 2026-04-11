<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM implementation of {@see TransactionRepositoryInterface}.
 *
 * Handles all persistence operations for the {@see Transaction} entity using
 * Doctrine's EntityManager. This class is the only place in the service
 * that directly interacts with the database for transaction data.
 *
 * @package App\Repository
 */
class TransactionRepository implements TransactionRepositoryInterface
{
    /**
     * The Doctrine entity manager used for all persistence operations.
     *
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * Construct a new TransactionRepository.
     *
     * @param EntityManagerInterface $entityManager The Doctrine entity manager.
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Find a Transaction entity by its primary key.
     *
     * @param int $id The primary key of the transaction to retrieve.
     *
     * @return Transaction|null The found Transaction entity, or null if none exists with the given id.
     */
    public function findById(int $id): ?object
    {
        return $this->entityManager->find(Transaction::class, $id);
    }

    /**
     * Retrieve all Transaction entities from the database.
     *
     * @return array<int, Transaction> An array of all persisted Transaction entities (may be empty).
     */
    public function findAll(): array
    {
        return $this->entityManager
            ->getRepository(Transaction::class)
            ->findAll();
    }

    /**
     * Persist a new or updated Transaction entity to the database.
     *
     * @param object $entity The Transaction entity instance to save.
     *
     * @return void
     */
    public function save(object $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * Remove a Transaction entity from the database by its primary key.
     *
     * @param int $id The primary key of the transaction to delete.
     *
     * @return void
     */
    public function delete(int $id): void
    {
        $transaction = $this->findById($id);
        if ($transaction !== null) {
            $this->entityManager->remove($transaction);
            $this->entityManager->flush();
        }
    }

    /**
     * Find all transactions involving a specific account (as source or destination).
     *
     * Returns transactions where the given account is either the source or the destination.
     *
     * @param int $accountId The ID of the account whose transactions to retrieve.
     *
     * @return array<int, Transaction> An array of Transaction entities involving the given account (may be empty).
     */
    public function findByAccountId(int $accountId): array
    {
        $repo = $this->entityManager->getRepository(Transaction::class);

        $asSources      = $repo->findBy(['sourceAccountId' => $accountId]);
        $asDestinations = $repo->findBy(['destinationAccountId' => $accountId]);

        return array_values(array_unique(array_merge($asSources, $asDestinations), SORT_REGULAR));
    }
}
