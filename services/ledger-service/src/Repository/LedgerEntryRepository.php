<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LedgerEntry;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM implementation of {@see LedgerEntryRepositoryInterface}.
 *
 * Handles all persistence operations for the {@see LedgerEntry} entity using
 * Doctrine's EntityManager. This class is the only place in the service
 * that directly interacts with the database for ledger entry data.
 *
 * @package App\Repository
 */
class LedgerEntryRepository implements LedgerEntryRepositoryInterface
{
    /**
     * The Doctrine entity manager used for all persistence operations.
     *
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * Construct a new LedgerEntryRepository.
     *
     * @param EntityManagerInterface $entityManager The Doctrine entity manager.
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Find a LedgerEntry entity by its primary key.
     *
     * @param int $id The primary key of the ledger entry to retrieve.
     *
     * @return LedgerEntry|null The found LedgerEntry entity, or null if none exists with the given id.
     */
    public function findById(int $id): ?object
    {
        return $this->entityManager->find(LedgerEntry::class, $id);
    }

    /**
     * Retrieve all LedgerEntry entities from the database.
     *
     * @return array<int, LedgerEntry> An array of all persisted LedgerEntry entities (may be empty).
     */
    public function findAll(): array
    {
        return $this->entityManager
            ->getRepository(LedgerEntry::class)
            ->findAll();
    }

    /**
     * Persist a new or updated LedgerEntry entity to the database.
     *
     * @param object $entity The LedgerEntry entity instance to save.
     *
     * @return void
     */
    public function save(object $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * Remove a LedgerEntry entity from the database by its primary key.
     *
     * @param int $id The primary key of the ledger entry to delete.
     *
     * @return void
     */
    public function delete(int $id): void
    {
        $entry = $this->findById($id);
        if ($entry !== null) {
            $this->entityManager->remove($entry);
            $this->entityManager->flush();
        }
    }

    /**
     * Find all ledger entries associated with a specific account.
     *
     * @param int $accountId The ID of the account whose ledger entries to retrieve.
     *
     * @return array<int, LedgerEntry> An array of LedgerEntry entities for the given account (may be empty).
     */
    public function findByAccountId(int $accountId): array
    {
        return $this->entityManager
            ->getRepository(LedgerEntry::class)
            ->findBy(['accountId' => $accountId]);
    }

    /**
     * Find all ledger entries associated with a specific transaction.
     *
     * @param int $transactionId The ID of the transaction whose ledger entries to retrieve.
     *
     * @return array<int, LedgerEntry> An array of LedgerEntry entities for the given transaction (may be empty).
     */
    public function findByTransactionId(int $transactionId): array
    {
        return $this->entityManager
            ->getRepository(LedgerEntry::class)
            ->findBy(['transactionId' => $transactionId]);
    }
}
