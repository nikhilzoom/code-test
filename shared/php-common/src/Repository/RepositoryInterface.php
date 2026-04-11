<?php

declare(strict_types=1);

namespace PhpCommon\Repository;

/**
 * Base repository interface defining generic CRUD operations.
 *
 * All service-specific repository interfaces must extend this interface
 * to ensure a consistent data access contract across the system.
 */
interface RepositoryInterface
{
    /**
     * Find a single entity by its primary identifier.
     *
     * @param int $id The primary key of the entity to retrieve.
     *
     * @return object|null The found entity, or null if no entity exists with the given id.
     */
    public function findById(int $id): ?object;

    /**
     * Retrieve all entities managed by this repository.
     *
     * @return array<int, object> An array of all persisted entities (may be empty).
     */
    public function findAll(): array;

    /**
     * Persist a new or updated entity to the data store.
     *
     * @param object $entity The entity instance to save.
     *
     * @return void
     */
    public function save(object $entity): void;

    /**
     * Remove an entity from the data store by its primary identifier.
     *
     * @param int $id The primary key of the entity to delete.
     *
     * @return void
     */
    public function delete(int $id): void;
}
