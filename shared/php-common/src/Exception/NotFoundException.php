<?php

declare(strict_types=1);

namespace PhpCommon\Exception;

/**
 * Exception thrown when a requested resource cannot be found in the data store.
 *
 * Services should throw this exception when a repository method returns null
 * for a lookup by identifier. Controllers catch this exception and convert it
 * to an HTTP 404 JSON error response.
 *
 * @see \RuntimeException
 */
class NotFoundException extends \RuntimeException
{
    /**
     * Construct a new NotFoundException.
     *
     * @param string          $message  Human-readable description of what was not found.
     * @param int             $code     Optional exception code (defaults to 0).
     * @param \Throwable|null $previous Optional previous exception for chaining.
     */
    public function __construct(
        string $message = 'Resource not found.',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
