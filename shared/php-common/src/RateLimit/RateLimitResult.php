<?php

declare(strict_types=1);

namespace PhpCommon\RateLimit;

/**
 * Value object representing the result of a rate limit check.
 *
 * Carries the allow/deny decision along with standard rate limit
 * metadata suitable for inclusion in HTTP response headers.
 *
 * @package PhpCommon\RateLimit
 */
class RateLimitResult
{
    /**
     * Construct a new RateLimitResult.
     *
     * @param bool $allowed     Whether the request is allowed.
     * @param int  $limit       The maximum number of requests in the window.
     * @param int  $remaining   Requests remaining in the current window.
     * @param int  $resetAt     Unix timestamp when the window resets.
     * @param int  $windowSecs  The window size in seconds.
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly int  $limit,
        public readonly int  $remaining,
        public readonly int  $resetAt,
        public readonly int  $windowSecs,
    ) {
    }
}
