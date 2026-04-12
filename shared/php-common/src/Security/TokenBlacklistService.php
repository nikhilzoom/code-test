<?php

declare(strict_types=1);

namespace PhpCommon\Security;

/**
 * Service for managing a JWT token blacklist backed by Redis.
 *
 * When a user logs out, their token's JTI (JWT ID) is stored in Redis
 * with a TTL matching the token's remaining lifetime. The API Gateway
 * checks this blacklist on every protected request to reject revoked tokens.
 *
 * @package PhpCommon\Security
 */
class TokenBlacklistService
{
    /**
     * Redis key prefix for blacklisted token entries.
     *
     * @var string
     */
    private const KEY_PREFIX = 'jwt_blacklist:';

    /**
     * The Redis connection instance.
     *
     * @var \Redis
     */
    private \Redis $redis;

    /**
     * Construct a new TokenBlacklistService.
     *
     * @param \Redis $redis The Redis connection instance.
     */
    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Add a token JTI to the blacklist with a TTL.
     *
     * The TTL should match the token's remaining lifetime so that Redis
     * automatically expires the entry when the token would have expired anyway.
     *
     * @param string $jti The JWT ID claim to blacklist.
     * @param int    $ttl Seconds until the blacklist entry expires.
     *
     * @return void
     */
    public function blacklist(string $jti, int $ttl): void
    {
        $this->redis->setex(self::KEY_PREFIX . $jti, $ttl, '1');
    }

    /**
     * Check whether a token JTI is currently blacklisted.
     *
     * @param string $jti The JWT ID claim to check.
     *
     * @return bool True if the token is blacklisted, false otherwise.
     */
    public function isBlacklisted(string $jti): bool
    {
        return $this->redis->exists(self::KEY_PREFIX . $jti) > 0;
    }
}
