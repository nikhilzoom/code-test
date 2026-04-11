<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;
use PhpCommon\Exception\AuthenticationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller exposing HTTP endpoints for user registration and login.
 *
 * These endpoints are public — no JWT is required to access them.
 * On successful login a signed JWT is returned for use on protected endpoints.
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
     * Construct a new AuthController.
     *
     * @param AuthService $authService The authentication service.
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register a new user account.
     *
     * Accepts a JSON body with `name`, `email`, and `password`. Hashes the
     * password and persists the new user. Returns the created user data
     * (password is never included in the response).
     *
     * Request format : POST /user/register
     *                  Content-Type: application/json
     *                  Body: {"name": "Alice", "email": "alice@example.com", "password": "secret123"}
     *
     * Response format: HTTP 201
     *                  {"id": 1, "name": "Alice", "email": "alice@example.com", "createdAt": "..."}
     *
     * Error responses: HTTP 400 {"error": "Invalid JSON body.", "code": 400}
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

        if (!is_array($data) || empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            return new JsonResponse(['error' => 'Invalid JSON body.', 'code' => 400], 400);
        }

        try {
            $user = $this->authService->register($data);

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
     * Validates the provided email and password. On success returns a signed
     * HS256 JWT containing the user's ID and email, valid for 1 hour.
     *
     * Request format : POST /user/login
     *                  Content-Type: application/json
     *                  Body: {"email": "alice@example.com", "password": "secret123"}
     *
     * Response format: HTTP 200
     *                  {"token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."}
     *
     * Error responses: HTTP 400 {"error": "Invalid JSON body.", "code": 400}
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

        if (!is_array($data) || empty($data['email']) || empty($data['password'])) {
            return new JsonResponse(['error' => 'Invalid JSON body.', 'code' => 400], 400);
        }

        try {
            $token = $this->authService->login($data);

            return new JsonResponse(['token' => $token]);
        } catch (AuthenticationException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 401], 401);
        }
    }
}
