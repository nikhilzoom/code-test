<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\LoginDTO;
use App\DTO\RegisterDTO;
use App\Service\AuthService;
use PhpCommon\Exception\AuthenticationException;
use PhpCommon\Security\JwtService;
use PhpCommon\Security\TokenBlacklistService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller exposing HTTP endpoints for user registration, login, and logout.
 *
 * Register and login are public — no JWT is required to access them.
 * Logout requires a valid JWT and blacklists its JTI in Redis so it cannot
 * be reused even before it expires.
 *
 * @package App\Controller
 */
class AuthController extends AbstractController
{
    /**
     * The service handling registration and login business logic.
     *
     * @var AuthService
     */
    private AuthService $authService;

    /**
     * The JWT service used to decode tokens on logout.
     *
     * @var JwtService
     */
    private JwtService $jwtService;

    /**
     * The token blacklist service backed by Redis.
     *
     * @var TokenBlacklistService
     */
    private TokenBlacklistService $blacklistService;

    /**
     * Construct a new AuthController.
     *
     * @param AuthService           $authService      The authentication service.
     * @param JwtService            $jwtService       The JWT service for decoding tokens.
     * @param TokenBlacklistService $blacklistService The Redis-backed token blacklist.
     */
    public function __construct(
        AuthService $authService,
        JwtService $jwtService,
        TokenBlacklistService $blacklistService
    ) {
        $this->authService      = $authService;
        $this->jwtService       = $jwtService;
        $this->blacklistService = $blacklistService;
    }

    /**
     * Register a new user account.
     *
     * Accepts a JSON body with `name`, `email`, and `password`. Validates the
     * input via {@see RegisterDTO}, hashes the password, and persists the new user.
     * Returns the created user data (password is never included in the response).
     *
     * Request format : POST /user/register
     *                  Content-Type: application/json
     *                  Body: {"name": "Alice", "email": "alice@example.com", "password": "secret123"}
     *
     * Response format: HTTP 201
     *                  {"id": 1, "name": "Alice", "email": "alice@example.com", "createdAt": "..."}
     *
     * Error responses: HTTP 400 {"error": "Invalid JSON body.", "code": 400}
     *                  HTTP 400 {"errors": ["password must be at least 6 characters."], "code": 400}
     *                  HTTP 409 {"error": "Email already registered.", "code": 409}
     *                  HTTP 500 {"error": "...", "code": 500}
     *
     * @Route("/user/register", name="user_register", methods={"POST"})
     *
     * @param Request $request The incoming HTTP request containing registration data.
     *
     * @return JsonResponse The created user (HTTP 201) or an error envelope.
     */
    #[Route('/user/register', name: 'user_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON body.', 'code' => 400], 400);
        }

        $dto    = new RegisterDTO($data);
        $errors = $dto->validate();

        if (!empty($errors)) {
            return new JsonResponse(['errors' => $errors, 'code' => 400], 400);
        }

        try {
            $user = $this->authService->register($dto);

            return new JsonResponse([
                'id'        => $user->getId(),
                'name'      => $user->getName(),
                'email'     => $user->getEmail(),
                'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ], 201);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'Email already registered.') {
                return new JsonResponse(['error' => $e->getMessage(), 'code' => 409], 409);
            }

            return new JsonResponse(['error' => $e->getMessage(), 'code' => 500], 500);
        }
    }

    /**
     * Authenticate a user and return a signed JWT.
     *
     * Validates the provided email and password via {@see LoginDTO}. On success
     * returns a signed HS256 JWT containing the user's ID and email, valid for 1 hour.
     *
     * Request format : POST /user/login
     *                  Content-Type: application/json
     *                  Body: {"email": "alice@example.com", "password": "secret123"}
     *
     * Response format: HTTP 200
     *                  {"token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."}
     *
     * Error responses: HTTP 400 {"error": "Invalid JSON body.", "code": 400}
     *                  HTTP 400 {"errors": ["email must be a valid email address."], "code": 400}
     *                  HTTP 401 {"error": "Invalid credentials.", "code": 401}
     *
     * @Route("/user/login", name="user_login", methods={"POST"})
     *
     * @param Request $request The incoming HTTP request containing login credentials.
     *
     * @return JsonResponse A JWT token (HTTP 200) or an error envelope.
     */
    #[Route('/user/login', name: 'user_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON body.', 'code' => 400], 400);
        }

        $dto    = new LoginDTO($data);
        $errors = $dto->validate();

        if (!empty($errors)) {
            return new JsonResponse(['errors' => $errors, 'code' => 400], 400);
        }

        try {
            $token = $this->authService->login($dto);

            return new JsonResponse(['token' => $token]);
        } catch (AuthenticationException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 401], 401);
        }
    }

    /**
     * Logout the authenticated user by blacklisting their current JWT.
     *
     * Decodes the Bearer token, extracts the JTI and expiry, then stores
     * the JTI in Redis with a TTL equal to the token's remaining lifetime.
     * Any subsequent request using this token will be rejected with 401.
     *
     * Request format : POST /user/logout
     *                  Authorization: Bearer <token>
     *
     * Response format: HTTP 200 {"message": "Logged out successfully."}
     *
     * Error responses: HTTP 401 {"error": "Authorization header missing.", "code": 401}
     *
     * @Route("/user/logout", name="user_logout", methods={"POST"})
     *
     * @param Request $request The incoming HTTP request with Authorization header.
     *
     * @return JsonResponse Success message (HTTP 200) or an error envelope.
     */
    #[Route('/user/logout', name: 'user_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization');

        if ($authHeader === null || !str_starts_with($authHeader, 'Bearer ')) {
            return new JsonResponse(['error' => 'Authorization header missing.', 'code' => 401], 401);
        }

        $token = substr($authHeader, 7);

        try {
            $payload = $this->jwtService->decode($token);
        } catch (AuthenticationException $e) {
            return new JsonResponse(['error' => 'Invalid or expired token.', 'code' => 401], 401);
        }

        $jti = (string) ($payload->jti ?? '');
        $exp = (int) ($payload->exp ?? 0);

        if ($jti !== '') {
            $ttl = max(1, $exp - time());
            $this->blacklistService->blacklist($jti, $ttl);
        }

        return new JsonResponse(['message' => 'Logged out successfully.']);
    }
}
