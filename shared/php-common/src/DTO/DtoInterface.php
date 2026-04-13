<?php

declare(strict_types=1);

namespace PhpCommon\DTO;

/**
 * Contract for all Data Transfer Objects that support self-validation.
 *
 * Every DTO that carries request input must implement this interface so that
 * controllers can call {@see validate()} before passing the DTO to the service
 * layer. Validation errors are returned as an array of human-readable strings;
 * an empty array means the DTO is valid.
 *
 * @package PhpCommon\DTO
 */
interface DtoInterface
{
    /**
     * Validate the DTO's field values.
     *
     * Returns an empty array when all fields are valid.
     * Returns one or more human-readable error strings when validation fails.
     *
     * @return array<string> List of validation error messages, empty if valid.
     */
    public function validate(): array;
}
