<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\AccountDTO;
use App\DTO\AmountDTO;
use App\Service\AccountService;
use PhpCommon\Exception\NotFoundException;
use PhpCommon\Exception\ValidationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller exposing HTTP endpoints for account management operations.
 *
 * Handles creation, retrieval by ID, debit, and credit operations on accounts.
 * All responses are JSON. Exceptions are serialised to a consistent
 * error envelope: `{"error": "...", "code": N}`.
 *
 * @package App\Controller
 */
class AccountController extends AbstractController
{
    /**
     * The service handling account business logic.
     *
     * @var AccountService
     */
    private AccountService $accountService;

    /**
     * Construct a new AccountController.
     *
     * @param AccountService $accountService The account service.
     */
    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    /**
     * Create a new account.
     *
     * Accepts a JSON body with `userId`, `balance`, and `currency` fields,
     * validates the input via {@see AccountDTO}, creates the account, and
     * returns the persisted account data with HTTP 201.
     *
     * Request format : POST /account
     *                  Content-Type: application/json
     *                  Body: {"userId": 1, "balance": "1000.00", "currency": "USD"}
     *
     * Response format: HTTP 201
     *                  {"id": 1, "userId": 1, "balance": "1000.00", "currency": "USD", "createdAt": "..."}
     *
     * Error response : HTTP 400 {"error": "Invalid JSON body.", "code": 400}
     *                  HTTP 400 {"errors": ["userId must be a positive integer."], "code": 400}
     *                  HTTP 500 {"error": "...", "code": 500}
     *
     * @Route("/account", name="account_create", methods={"POST"})
     *
     * @param Request $request The incoming HTTP request containing the account data.
     *
     * @return JsonResponse JSON representation of the created account (HTTP 201) or an error envelope.
     */
    #[Route('/account', name: 'account_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!is_array($data)) {
                return new JsonResponse(['error' => 'Invalid JSON body.', 'code' => 400], 400);
            }

            $dto    = new AccountDTO($data);
            $errors = $dto->validate();

            if (!empty($errors)) {
                return new JsonResponse(['errors' => $errors, 'code' => 400], 400);
            }

            $account = $this->accountService->createAccount($dto);

            return new JsonResponse([
                'id'        => $account->getId(),
                'userId'    => $account->getUserId(),
                'balance'   => $account->getBalance(),
                'currency'  => $account->getCurrency(),
                'createdAt' => $account->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ], 201);
        } catch (ValidationException $e) {
            return new JsonResponse(['errors' => $e->getErrors(), 'code' => 400], 400);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 500], 500);
        }
    }

    /**
     * Retrieve a single account by its ID.
     *
     * Looks up the account with the given integer ID and returns its data.
     * Returns HTTP 404 if no account exists with that ID.
     *
     * Request format : GET /account/{id}
     *                  No request body required.
     *
     * Response format: HTTP 200
     *                  {"id": 1, "userId": 1, "balance": "1000.00", "currency": "USD", "createdAt": "..."}
     *
     * Error response : HTTP 404 {"error": "Account with id 1 not found.", "code": 404}
     *                  HTTP 500 {"error": "...", "code": 500}
     *
     * @Route("/account/{id}", name="account_get", methods={"GET"})
     *
     * @param int $id The primary key of the account to retrieve.
     *
     * @return JsonResponse JSON representation of the account (HTTP 200) or an error envelope.
     */
    #[Route('/account/{id}', name: 'account_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getById(int $id): JsonResponse
    {
        try {
            $account = $this->accountService->getAccountById($id);

            return new JsonResponse([
                'id'        => $account->getId(),
                'userId'    => $account->getUserId(),
                'balance'   => $account->getBalance(),
                'currency'  => $account->getCurrency(),
                'createdAt' => $account->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ]);
        } catch (NotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 404], 404);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 500], 500);
        }
    }

    /**
     * Debit (subtract) an amount from an account's balance.
     *
     * Accepts a JSON body with an `amount` field, validates it via {@see AmountDTO},
     * subtracts it from the account balance, and returns the updated account data.
     *
     * Request format : POST /account/{id}/debit
     *                  Content-Type: application/json
     *                  Body: {"amount": "50.00"}
     *
     * Response format: HTTP 200
     *                  {"id": 1, "userId": 1, "balance": "950.00", "currency": "USD", "createdAt": "..."}
     *
     * Error response : HTTP 400 {"error": "Invalid JSON body.", "code": 400}
     *                  HTTP 400 {"errors": ["amount must be a positive numeric value."], "code": 400}
     *                  HTTP 404 {"error": "Account with id 1 not found.", "code": 404}
     *                  HTTP 500 {"error": "...", "code": 500}
     *
     * @Route("/account/{id}/debit", name="account_debit", methods={"POST"})
     *
     * @param Request $request The incoming HTTP request containing the debit amount.
     * @param int     $id      The primary key of the account to debit.
     *
     * @return JsonResponse JSON representation of the updated account (HTTP 200) or an error envelope.
     */
    #[Route('/account/{id}/debit', name: 'account_debit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function debit(Request $request, int $id): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!is_array($data)) {
                return new JsonResponse(['error' => 'Invalid JSON body.', 'code' => 400], 400);
            }

            $dto    = new AmountDTO($data);
            $errors = $dto->validate();

            if (!empty($errors)) {
                return new JsonResponse(['errors' => $errors, 'code' => 400], 400);
            }

            $account = $this->accountService->debit($id, $dto->amount);

            return new JsonResponse([
                'id'        => $account->getId(),
                'userId'    => $account->getUserId(),
                'balance'   => $account->getBalance(),
                'currency'  => $account->getCurrency(),
                'createdAt' => $account->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ]);
        } catch (NotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 404], 404);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 500], 500);
        }
    }

    /**
     * Credit (add) an amount to an account's balance.
     *
     * Accepts a JSON body with an `amount` field, validates it via {@see AmountDTO},
     * adds it to the account balance, and returns the updated account data.
     *
     * Request format : POST /account/{id}/credit
     *                  Content-Type: application/json
     *                  Body: {"amount": "200.00"}
     *
     * Response format: HTTP 200
     *                  {"id": 1, "userId": 1, "balance": "1200.00", "currency": "USD", "createdAt": "..."}
     *
     * Error response : HTTP 400 {"error": "Invalid JSON body.", "code": 400}
     *                  HTTP 400 {"errors": ["amount must be a positive numeric value."], "code": 400}
     *                  HTTP 404 {"error": "Account with id 1 not found.", "code": 404}
     *                  HTTP 500 {"error": "...", "code": 500}
     *
     * @Route("/account/{id}/credit", name="account_credit", methods={"POST"})
     *
     * @param Request $request The incoming HTTP request containing the credit amount.
     * @param int     $id      The primary key of the account to credit.
     *
     * @return JsonResponse JSON representation of the updated account (HTTP 200) or an error envelope.
     */
    #[Route('/account/{id}/credit', name: 'account_credit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function credit(Request $request, int $id): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!is_array($data)) {
                return new JsonResponse(['error' => 'Invalid JSON body.', 'code' => 400], 400);
            }

            $dto    = new AmountDTO($data);
            $errors = $dto->validate();

            if (!empty($errors)) {
                return new JsonResponse(['errors' => $errors, 'code' => 400], 400);
            }

            $account = $this->accountService->credit($id, $dto->amount);

            return new JsonResponse([
                'id'        => $account->getId(),
                'userId'    => $account->getUserId(),
                'balance'   => $account->getBalance(),
                'currency'  => $account->getCurrency(),
                'createdAt' => $account->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ]);
        } catch (NotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 404], 404);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 500], 500);
        }
    }
}
