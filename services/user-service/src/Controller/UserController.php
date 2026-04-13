<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\UserDTO;
use App\Service\UserService;
use PhpCommon\Exception\NotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller exposing HTTP endpoints for user management operations.
 *
 * Handles creation, retrieval by ID, and listing of all users.
 * All responses are JSON. Exceptions are serialised to a consistent
 * error envelope: `{"error": "...", "code": N}`.
 *
 * @package App\Controller
 */
class UserController extends AbstractController
{
    /**
     * The service handling user business logic.
     *
     * @var UserService
     */
    private UserService $userService;

    /**
     * Construct a new UserController.
     *
     * @param UserService $userService The user service.
     */
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Create a new user.
     *
     * Accepts a JSON body with `name` and `email` fields, validates the input
     * via {@see UserDTO}, creates the user, and returns the persisted user data
     * with HTTP 201.
     *
     * Request format : POST /user
     *                  Content-Type: application/json
     *                  Body: {"name": "Alice", "email": "alice@example.com"}
     *
     * Response format: HTTP 201
     *                  {"id": 1, "name": "Alice", "email": "alice@example.com", "createdAt": "..."}
     *
     * Error response : HTTP 400 {"error": "Invalid JSON body.", "code": 400}
     *                  HTTP 400 {"errors": ["email must be a valid email address."], "code": 400}
     *                  HTTP 500 {"error": "...", "code": 500}
     *
     * @Route("/user", name="user_create", methods={"POST"})
     *
     * @param Request $request The incoming HTTP request containing the user data.
     *
     * @return JsonResponse JSON representation of the created user (HTTP 201) or an error envelope.
     */
    #[Route('/user', name: 'user_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!is_array($data)) {
                return new JsonResponse(['error' => 'Invalid JSON body.', 'code' => 400], 400);
            }

            $dto    = new UserDTO($data);
            $errors = $dto->validate();

            if (!empty($errors)) {
                return new JsonResponse(['errors' => $errors, 'code' => 400], 400);
            }

            $user = $this->userService->createUser($dto);

            return new JsonResponse([
                'id'        => $user->getId(),
                'name'      => $user->getName(),
                'email'     => $user->getEmail(),
                'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ], 201);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 500], 500);
        }
    }

    /**
     * Retrieve a single user by their ID.
     *
     * Looks up the user with the given integer ID and returns their data.
     * Returns HTTP 404 if no user exists with that ID.
     *
     * Request format : GET /user/{id}
     *                  No request body required.
     *
     * Response format: HTTP 200
     *                  {"id": 1, "name": "Alice", "email": "alice@example.com", "createdAt": "..."}
     *
     * Error response : HTTP 404 {"error": "User with id 1 not found.", "code": 404}
     *                  HTTP 500 {"error": "...", "code": 500}
     *
     * @Route("/user/{id}", name="user_get", methods={"GET"})
     *
     * @param int $id The primary key of the user to retrieve.
     *
     * @return JsonResponse JSON representation of the user (HTTP 200) or an error envelope.
     */
    #[Route('/user/{id}', name: 'user_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getById(int $id): JsonResponse
    {
        try {
            $user = $this->userService->getUserById($id);

            return new JsonResponse([
                'id'        => $user->getId(),
                'name'      => $user->getName(),
                'email'     => $user->getEmail(),
                'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ]);
        } catch (NotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 404], 404);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 500], 500);
        }
    }

    /**
     * List all users.
     *
     * Returns an array of all registered users. Returns an empty array when
     * no users exist.
     *
     * Request format : GET /user
     *                  No request body required.
     *
     * Response format: HTTP 200
     *                  [{"id": 1, "name": "Alice", "email": "alice@example.com", "createdAt": "..."}, ...]
     *
     * Error response : HTTP 500 {"error": "...", "code": 500}
     *
     * @Route("/user", name="user_list", methods={"GET"})
     *
     * @param void No parameters required.
     *
     * @return JsonResponse JSON array of all users (HTTP 200) or an error envelope.
     */
    #[Route('/user', name: 'user_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        try {
            $users = $this->userService->getAllUsers();

            $payload = array_map(static function ($user) {
                return [
                    'id'        => $user->getId(),
                    'name'      => $user->getName(),
                    'email'     => $user->getEmail(),
                    'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ];
            }, $users);

            return new JsonResponse($payload);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 500], 500);
        }
    }
}
