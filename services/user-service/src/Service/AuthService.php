<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\LoginDTO;
use App\DTO\RegisterDTO;
use App\DTO\UserDTO;
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
     * @param RegisterDTO $dto Validated registration data containing `name`, `email`, and `password`.
     *
     * @throws \RuntimeException When the email address is already registered.
     *
     * @return User The newly created and persisted User entity.
     */
    public function register(RegisterDTO $dto): User
    {
        $existing = $this->userRepository->findByEmail($dto->email);
        if ($existing !== null) {
            throw new \RuntimeException('Email already registered.');
        }

        $hashedPassword = password_hash($dto->password, PASSWORD_BCRYPT);

        $userDto        = new UserDTO(['name' => $dto->name, 'email' => $dto->email]);
        $user           = $this->userFactory->create($userDto, $hashedPassword);

        $this->userRepository->save($user);

        return $user;
    }

    /**
     * Validate credentials and return a signed JWT on success.
     *
     * Looks up the user by email, verifies the password using bcrypt,
     * and issues a signed HS256 JWT containing the user's ID and email.
     *
     * @param LoginDTO $dto Validated login data containing `email` and `password`.
     *
     * @throws AuthenticationException When the email is not found or the password does not match.
     *
     * @return string The signed JWT string.
     */
    public function login(LoginDTO $dto): string
    {
        $user = $this->userRepository->findByEmail($dto->email);

        if ($user === null || !password_verify($dto->password, $user->getPassword())) {
            throw new AuthenticationException('Invalid credentials.');
        }

        return $this->jwtService->encode([
            'sub'   => $user->getId(),
            'email' => $user->getEmail(),
        ]);
    }
}
