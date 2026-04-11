<?php

declare(strict_types=1);

namespace PhpCommon\Exception;

/**
 * Exception thrown when authentication fails.
 *
 * Raised by JwtService when a token is invalid, expired, or malformed,
 * and by AuthService when login credentials do not match.
 *
 * @package PhpCommon\Exception
 */
class AuthenticationException extends \RuntimeException
{
}
