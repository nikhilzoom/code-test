<?php

declare(strict_types=1);

namespace PhpCommon\RateLimit;

/**
 * Redis-backed sliding window rate limiter.
 *
 * Uses a sorted set per key where each member is a unique request ID and
 * the score is the Unix timestamp in milliseconds. On each request:
 *   1. Remove all entries older than the window.
 *   2. Count remaining entries.
 *   3. If count >= limit, reject.
 *   4. Otherwise add the current request and set the key TTL.
 *
 * All operations are executed atomically via a Lua script to prevent
 * race conditions under concurrent load.
 *
 * @package PhpCommon\RateLimit
 */
class RateLimiterService
{
    /**
     * Redis key prefix for rate limit buckets.
     *
     * @var string
     */
    private const KEY_PREFIX = 'rate_limit:';

    /**
     * Lua script for atomic sliding window check-and-increment.
     *
     * KEYS[1] = rate limit key
     * ARGV[1] = current timestamp in milliseconds
     * ARGV[2] = window size in milliseconds
     * ARGV[3] = max requests allowed in window
     * ARGV[4] = unique request ID (microtime-based)
     *
     * Returns: {current_count, limit, remaining, reset_at_ms}
     *
     * @var string
     */
    private const LUA_SCRIPT = <<<'LUA'
local key      = KEYS[1]
local now      = tonumber(ARGV[1])
local window   = tonumber(ARGV[2])
local limit    = tonumber(ARGV[3])
local req_id   = ARGV[4]
local oldest   = now - window

-- Remove requests outside the sliding window
redis.call('ZREMRANGEBYSCORE', key, '-inf', oldest)

-- Count requests in current window
local count = redis.call('ZCARD', key)

if count >= limit then
    -- Reject — return current count and reset time
    local reset = redis.call('ZRANGE', key, 0, 0, 'WITHSCORES')
    local reset_at = oldest + window
    if #reset > 0 then
        reset_at = tonumber(reset[2]) + window
    end
    return {count, limit, 0, reset_at}
end

-- Allow — record this request
redis.call('ZADD', key, now, req_id)
redis.call('PEXPIRE', key, window)

local remaining = limit - count - 1
return {count + 1, limit, remaining, now + window}
LUA;

    /**
     * The Redis connection instance.
     *
     * @var \Redis
     */
    private \Redis $redis;

    /**
     * Construct a new RateLimiterService.
     *
     * @param \Redis $redis The Redis connection instance.
     */
    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Check and record a request against the rate limit for a given key.
     *
     * @param string $key        Unique identifier for this limit bucket (e.g. "user:42:api").
     * @param int    $limit      Maximum number of requests allowed in the window.
     * @param int    $windowSecs Sliding window size in seconds.
     *
     * @return RateLimitResult The result containing allow/deny decision and metadata.
     */
    public function check(string $key, int $limit, int $windowSecs): RateLimitResult
    {
        $redisKey  = self::KEY_PREFIX . $key;
        $nowMs     = (int) (microtime(true) * 1000);
        $windowMs  = $windowSecs * 1000;
        $requestId = $nowMs . ':' . bin2hex(random_bytes(4));

        /** @var array<int, int> $result */
        $result = $this->redis->eval(
            self::LUA_SCRIPT,
            [$redisKey, (string) $nowMs, (string) $windowMs, (string) $limit, $requestId],
            1
        );

        $count     = (int) $result[0];
        $remaining = (int) $result[2];
        $resetAtMs = (int) $result[3];
        $allowed   = $remaining >= 0;

        return new RateLimitResult(
            allowed: $allowed,
            limit: $limit,
            remaining: max(0, $remaining),
            resetAt: (int) ceil($resetAtMs / 1000),
            windowSecs: $windowSecs
        );
    }
}
