<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TransactionService;
use PhpCommon\Exception\NotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller exposing HTTP endpoints for fund transfer transaction operations.
 *
 * Handles initiating new transfers and retrieving existing transactions by ID.
 * All responses are JSON. Exceptions are serialised to a consistent
 * error envelope: `{"error": "...", "code": N}`.
 *
 * @package App\Controller
 */
class TransactionController extends AbstractController
{
    /**
     * The service handling transaction business logic.
     *
     * @var TransactionService
     */
    private TransactionService $transactionService;

    /**
     * Construct a new TransactionController.
     *
     * @param TransactionService $transactionService The transaction service.
     */
    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Initiate a new fund transfer between two accounts.
     *
     * Accepts a JSON body with `sourceAccountId`, `destinationAccountId`, `amount`,
     * and `currency` fields. Creates a pending transaction, debits the source account,
     * credits the destination account, records both ledger entries, and returns the
     * completed transaction. Returns HTTP 500 with status `failed` if any step fails.
     *
     * Request format : POST /transaction
     *                  Content-Type: application/json
     *                  Body: {"sourceAccountId": 1, "destinationAccountId": 2, "amount": "100.00", "currency": "USD"}
     *
     * Response format: HTTP 201
     *                  {"id": 1, "sourceAccountId": 1, "destinationAccountId": 2,
     *                   "amount": "100.00", "currency": "USD", "status": "completed", "createdAt": "..."}
     *
     * Error response : HTTP 400 {"error": "Invalid JSON body.", "code": 400}
     *                  HTTP 500 {"error": "...", "code": 500}
     *
     * @Route("/transaction", name="transaction_initiate", methods={"POST"})
     *
     * @param Request $request The incoming HTTP request containing the transfer data.
     *
     * @return JsonResponse JSON representation of the created transaction (HTTP 201) or an error envelope.
     */
    #[Route('/transaction', name: 'transaction_initiate', methods: ['POST'])]
    public function initiate(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!is_array($data)) {
                return new JsonResponse(['error' => 'Invalid JSON body.', 'code' => 400], 400);
            }

            $transaction = $this->transactionService->initiateTransfer($data);

            return new JsonResponse([
                'id'                   => $transaction->getId(),
                'sourceAccountId'      => $transaction->getSourceAccountId(),
                'destinationAccountId' => $transaction->getDestinationAccountId(),
                'amount'               => $transaction->getAmount(),
                'currency'             => $transaction->getCurrency(),
                'status'               => $transaction->getStatus(),
                'createdAt'            => $transaction->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ], 201);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 500], 500);
        }
    }

    /**
     * Retrieve a single transaction by its ID.
     *
     * Looks up the transaction with the given integer ID and returns its data.
     * Returns HTTP 404 if no transaction exists with that ID.
     *
     * Request format : GET /transaction/{id}
     *                  No request body required.
     *
     * Response format: HTTP 200
     *                  {"id": 1, "sourceAccountId": 1, "destinationAccountId": 2,
     *                   "amount": "100.00", "currency": "USD", "status": "completed", "createdAt": "..."}
     *
     * Error response : HTTP 404 {"error": "Transaction with id 1 not found.", "code": 404}
     *                  HTTP 500 {"error": "...", "code": 500}
     *
     * @Route("/transaction/{id}", name="transaction_get", methods={"GET"})
     *
     * @param int $id The primary key of the transaction to retrieve.
     *
     * @return JsonResponse JSON representation of the transaction (HTTP 200) or an error envelope.
     */
    #[Route('/transaction/{id}', name: 'transaction_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getById(int $id): JsonResponse
    {
        try {
            $transaction = $this->transactionService->getTransactionById($id);

            return new JsonResponse([
                'id'                   => $transaction->getId(),
                'sourceAccountId'      => $transaction->getSourceAccountId(),
                'destinationAccountId' => $transaction->getDestinationAccountId(),
                'amount'               => $transaction->getAmount(),
                'currency'             => $transaction->getCurrency(),
                'status'               => $transaction->getStatus(),
                'createdAt'            => $transaction->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ]);
        } catch (NotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 404], 404);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 500], 500);
        }
    }
}
