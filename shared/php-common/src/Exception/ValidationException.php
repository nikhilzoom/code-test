<?php

declare(strict_types=1);

namespace PhpCommon\Exception;

/**
 * Exception thrown when DTO validation fails.
 *
 * Carries a list of human-readable validation error messages so that
 * controllers can return a structured 400 response without coupling
 * validation logic to the HTTP layer.
 *
 * @package PhpCommon\Exception
 */
class ValidationException extends \RuntimeException
{
    /**
     * List of validation error messages.
     *
     * @var array<string>
     */
    private array $errors;

    /**
     * Construct a new ValidationException.
     *
     * @param array<string> $errors List of human-readable validation error messages.
     */
    public function __construct(array $errors)
    {
        parent::__construct(implode('; ', $errors));
        $this->errors = $errors;
    }

    /**
     * Return all validation error messages.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
