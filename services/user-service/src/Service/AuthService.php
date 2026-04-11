<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Repository\UserRepositoryInterface;
use PhpCommon\Exception\AuthenticationException;
use PhpCommon\Security\JwtService;

/**
 * Service handling user registration and JWT-based authentication.
 *
 * Responsible for creating new user accounts with hashed passwords and
 * validating credentials to issue signed JWTs. Depends on
 * {@see UserRepositoryInterface}, {@see UserFactory}, and {@see JwtService}.
 *
 * @package App\Service
 */
class AuthService
{
    /**
     * The repository used for user data access.
     *
     * @var UserRepositoryInterface
     */
    private UserRepositoryInterface $userRepository;

    /**
     * The factory used to create User entity instances.
     *
     * @var UserFactory
     */
    private UserFactory $userFactory;

    /**
     * The JWT service used to issue signed tokens.
     *
     * @var JwtService
     */
    private JwtService $jwtService;

    /**
     * Construct a new AuthService.
     *
     * @param UserRepositoryInterface $userRepository The user repository interface.
     * @param UserFactory             $userFactory    The user entity factory.
     * @param JwtService              $jwtService     The JWT signing service.
     */
    public function __construct(
        UserRepositoryInterface $userRepository,
        UserFactory $userFactory,
        JwtService $jwtService
    ) {
        $this->userRepository = $userRepository;
        $this->userFactory    = $userFactory;
        $this->jwtService     = $jwtService;
    }

    /**
     * Register a new user with a hashed password.
     *
     * Validates that the email is not already taken, hashes the password
     * using bcrypt, creates the User entity via the factory, and persists it.
     *
     * @param array<string, mixed> $data Must contain `name`, `email`, and `password` keys.
     *
     * @throws \RuntimeException When the email address is already registered.
     *
     * @return User The newly created and persisted User entity.
     */
    public function register(array $data): User
    {
        $existing = $this->userRepository->findByEmail((string) $data['email']);
        if ($existing !== null) {
            throw new \RuntimeException('Email already registered.');
        }

        $hashedPassword = password_hash((string) $data['password'], PASSWORD_BCRYPT);

        /** @var User $user */
        $user = $this->userFactory->create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $hashedPassword,
        ]);

        $this->userRepository->save($user);

        return $user;
    }

    /**
     * Validate credentials and return a signed JWT on success.
     *
     * Looks up the user by email, verifies the password using bcrypt,
     * and issues a signed HS256 JWT containing the user's ID and email.
     *
     * @param array<string, mixed> $data Must contain `email` and `password` keys.
     *
     * @throws AuthenticationException When the email is not found or the password does not match.
     *
     * @return string The signed JWT string.
     */
    public function login(array $data): string
    {
        $user = $this->userRepository->findByEmail((string) $data['email']);

        if ($user === null || !password_verify((string) $data['password'], $user->getPassword())) {
            throw new AuthenticationException('Invalid credentials.');
        }

        return $this->jwtService->encode([
            'sub'   => $user->getId(),
            'email' => $user->getEmail(),
        ]);
    }
}
