<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\LoginDTO;
use App\DTO\RegisterDTO;
use App\Entity\User;
use App\Factory\UserFactory;
use App\Repository\UserRepositoryInterface;
use App\Service\AuthService;
use PhpCommon\Exception\AuthenticationException;
use PhpCommon\Security\JwtService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see AuthService}.
 *
 * Covers registration (happy path, duplicate email) and login
 * (happy path, wrong password, unknown email).
 *
 * @package App\Tests\Service
 */
class AuthServiceTest extends TestCase
{
    /**
     * @var UserRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private UserRepositoryInterface $userRepository;

    /**
     * @var UserFactory&\PHPUnit\Framework\MockObject\MockObject
     */
    private UserFactory $userFactory;

    /**
     * @var JwtService&\PHPUnit\Framework\MockObject\MockObject
     */
    private JwtService $jwtService;

    /**
     * @var AuthService
     */
    private AuthService $authService;

    /**
     * Set up mocks and the AuthService under test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->userFactory    = $this->createMock(UserFactory::class);
        $this->jwtService     = $this->createMock(JwtService::class);

        $this->authService = new AuthService(
            $this->userRepository,
            $this->userFactory,
            $this->jwtService
        );
    }

    /**
     * Test that register() creates and returns a User on the happy path.
     *
     * @return void
     */
    public function testRegisterHappyPath(): void
    {
        $user = new User();
        $user->setName('Alice');
        $user->setEmail('alice@example.com');
        $user->setCreatedAt(new \DateTimeImmutable());

        $this->userRepository->expects($this->once())->method('findByEmail')->with('alice@example.com')->willReturn(null);
        $this->userFactory->expects($this->once())->method('create')->willReturn($user);
        $this->userRepository->expects($this->once())->method('save')->with($user);

        $dto    = new RegisterDTO(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'secret123']);
        $result = $this->authService->register($dto);

        $this->assertSame($user, $result);
    }

    /**
     * Test that register() throws RuntimeException when the email is already taken.
     *
     * @return void
     */
    public function testRegisterThrowsOnDuplicateEmail(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Email already registered.');

        $existing = new User();
        $existing->setEmail('alice@example.com');
        $this->userRepository->method('findByEmail')->willReturn($existing);

        $dto = new RegisterDTO(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'secret123']);
        $this->authService->register($dto);
    }

    /**
     * Test that login() returns a JWT string on valid credentials.
     *
     * @return void
     */
    public function testLoginHappyPath(): void
    {
        $hash = password_hash('secret123', PASSWORD_BCRYPT);

        $user = new User();
        $user->setId(1);
        $user->setEmail('alice@example.com');
        $user->setPassword($hash);
        $user->setCreatedAt(new \DateTimeImmutable());

        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->jwtService->expects($this->once())->method('encode')->willReturn('signed.jwt.token');

        $dto   = new LoginDTO(['email' => 'alice@example.com', 'password' => 'secret123']);
        $token = $this->authService->login($dto);

        $this->assertSame('signed.jwt.token', $token);
    }

    /**
     * Test that login() throws AuthenticationException on wrong password.
     *
     * @return void
     */
    public function testLoginThrowsOnWrongPassword(): void
    {
        $this->expectException(AuthenticationException::class);

        $hash = password_hash('correct-password', PASSWORD_BCRYPT);
        $user = new User();
        $user->setEmail('alice@example.com');
        $user->setPassword($hash);
        $user->setCreatedAt(new \DateTimeImmutable());

        $this->userRepository->method('findByEmail')->willReturn($user);

        $dto = new LoginDTO(['email' => 'alice@example.com', 'password' => 'wrong-password']);
        $this->authService->login($dto);
    }

    /**
     * Test that login() throws AuthenticationException when email is not found.
     *
     * @return void
     */
    public function testLoginThrowsOnUnknownEmail(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->userRepository->method('findByEmail')->willReturn(null);

        $dto = new LoginDTO(['email' => 'nobody@example.com', 'password' => 'secret123']);
        $this->authService->login($dto);
    }
}
