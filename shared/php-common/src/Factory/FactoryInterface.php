<?php

declare(strict_types=1);

namespace PhpCommon\Factory;

/**
 * Base factory interface for creating domain objects from raw data arrays.
 *
 * All service-specific factories must implement this interface to provide
 * a consistent object creation contract across the system.
 */
interface FactoryInterface
{
    /**
     * Create and return a new domain object populated from the provided data array.
     *
     * @param array<string, mixed> $data Associative array of field names to values
     *                                   used to populate the created object.
     *
     * @return object The newly created and populated domain object.
     */
    public function create(array $data): object;
}
