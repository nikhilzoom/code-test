<?php

declare(strict_types=1);

namespace PhpCommon\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PhpCommon\Exception\AuthenticationException;

/**
 * Service for encoding and decoding JSON Web Tokens using HS256.
 *
 * Wraps firebase/php-jwt to provide a clean interface for JWT operations
 * across all services. The User Service uses this to issue tokens; the
 * API Gateway uses it to verify them.
 *
 * @package PhpCommon\Security
 */
class JwtService
{
    /**
     * The signing algorithm used for all tokens.
     *
     * @var string
     */
    private const ALGORITHM = 'HS256';

    /**
     * Token lifetime in seconds (1 hour).
     *
     * @var int
     */
    private const TTL = 3600;

    /**
     * The secret key used to sign and verify tokens.
     *
     * @var string
     */
    private string $secret;

    /**
     * Construct a new JwtService.
     *
     * @param string $secret The HMAC secret key shared between issuer and verifier.
     */
    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * Encode a payload into a signed JWT string.
     *
     * Automatically adds `iat` (issued-at) and `exp` (expiry = iat + 3600)
     * claims to the provided payload before signing.
     *
     * @param array<string, mixed> $payload Associative array of claims to include in the token.
     *                                      Must contain at minimum `sub` (integer user ID).
     *
     * @return string The signed JWT string.
     */
    public function encode(array $payload): string
    {
        $now = time();

        $payload['iat'] = $now;
        $payload['exp'] = $now + self::TTL;

        return JWT::encode($payload, $this->secret, self::ALGORITHM);
    }

    /**
     * Decode and verify a JWT string, returning its payload as an object.
     *
     * Verifies the signature and expiry. Throws AuthenticationException
     * for any invalid, expired, or malformed token.
     *
     * @param string $token The JWT string to decode and verify.
     *
     * @throws AuthenticationException When the token is invalid, expired, or malformed.
     *
     * @return object The decoded JWT payload as a stdClass object.
     */
    public function decode(string $token): object
    {
        try {
            return JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (\Throwable $e) {
            throw new AuthenticationException('Invalid or expired token.', 0, $e);
        }
    }
}
