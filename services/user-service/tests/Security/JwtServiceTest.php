<?php

declare(strict_types=1);

namespace App\Tests\Security;

use PhpCommon\Exception\AuthenticationException;
use PhpCommon\Security\JwtService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for JwtService.
 *
 * Verifies that tokens are correctly encoded and decoded, and that
 * invalid or expired tokens raise AuthenticationException.
 *
 * @package App\Tests\Security
 */
class JwtServiceTest extends TestCase
{
    /**
     * @var JwtService
     */
    private JwtService $jwtService;

    /**
     * Set up a JwtService instance with a known test secret.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->jwtService = new JwtService('test-secret-key');
    }

    /**
     * Test that encode() returns a non-empty JWT string.
     *
     * @return void
     */
    public function testEncodeReturnsNonEmptyString(): void
    {
        $token = $this->jwtService->encode(['sub' => 1, 'email' => 'alice@example.com']);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        // JWT has three dot-separated parts
        $this->assertCount(3, explode('.', $token));
    }

    /**
     * Test that decode() returns the correct payload for a valid token.
     *
     * @return void
     */
    public function testDecodeReturnsCorrectPayload(): void
    {
        $token   = $this->jwtService->encode(['sub' => 42, 'email' => 'bob@example.com']);
        $payload = $this->jwtService->decode($token);

        $this->assertSame(42, (int) $payload->sub);
        $this->assertSame('bob@example.com', $payload->email);
    }

    /**
     * Test that decode() throws AuthenticationException for a tampered token.
     *
     * @return void
     */
    public function testDecodeThrowsOnTamperedToken(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->jwtService->decode('eyJhbGciOiJIUzI1NiJ9.tampered.signature');
    }

    /**
     * Test that decode() throws AuthenticationException for a completely invalid string.
     *
     * @return void
     */
    public function testDecodeThrowsOnInvalidToken(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->jwtService->decode('not-a-jwt-at-all');
    }

    /**
     * Test that a token signed with a different secret fails verification.
     *
     * @return void
     */
    public function testDecodeThrowsOnWrongSecret(): void
    {
        $this->expectException(AuthenticationException::class);

        $otherService = new JwtService('different-secret');
        $token        = $otherService->encode(['sub' => 1]);

        $this->jwtService->decode($token);
    }
}
