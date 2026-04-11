<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LedgerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller exposing HTTP endpoints for ledger entry operations.
 *
 * Handles recording new ledger entries and querying entries by account or
 * transaction. All responses are JSON. Exceptions are serialised to a consistent
 * error envelope: `{"error": "...", "code": N}`.
 *
 * @package App\Controller
 */
class LedgerController extends AbstractController
{
    /**
     * The service handling ledger business logic.
     *
     * @var LedgerService
     */
    private LedgerService $ledgerService;

    /**
     * Construct a new LedgerController.
     *
     * @param LedgerService $ledgerService The ledger service.
     */
    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Record a new ledger entry.
     *
     * Accepts a JSON body with `transactionId`, `accountId`, `entryType`, and `amount` fields,
     * records the ledger entry, and returns the persisted entry data with HTTP 201.
     *
     * Request format : POST /ledger/entry
     *                  Content-Type: application/json
     *                  Body: {"transactionId": 1, "accountId": 2, "entryType": "debit", "amount": "100.00"}
     *
     * Response format: HTTP 201
     *                  {"id": 1, "transactionId": 1, "accountId": 2, "entryType": "debit", "amount": "100.00", "createdAt": "..."}
     *
     * Error response : HTTP 400 {"error": "Invalid JSON body.", "code": 400}
     *                  HTTP 500 {"error": "...", "code": 500}
     *
     * @Route("/ledger/entry", name="ledger_entry_create", methods={"POST"})
     *
     * @param Request $request The incoming HTTP request containing the ledger entry data.
     *
     * @return JsonResponse JSON representation of the created ledger entry (HTTP 201) or an error envelope.
     */
    #[Route('/ledger/entry', name: 'ledger_entry_create', methods: ['POST'])]
    public function recordEntry(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!is_array($data)) {
                return new JsonResponse(['error' => 'Invalid JSON body.', 'code' => 400], 400);
            }

            $entry = $this->ledgerService->recordEntry($data);

            return new JsonResponse([
                'id'            => $entry->getId(),
                'transactionId' => $entry->getTransactionId(),
                'accountId'     => $entry->getAccountId(),
                'entryType'     => $entry->getEntryType(),
                'amount'        => $entry->getAmount(),
                'createdAt'     => $entry->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ], 201);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 500], 500);
        }
    }

    /**
     * Retrieve all ledger entries for a specific account.
     *
     * Returns all ledger entries (debits and credits) associated with the given account ID.
     * Returns an empty array if no entries exist for the account.
     *
     * Request format : GET /ledger/account/{accountId}
     *                  No request body required.
     *
     * Response format: HTTP 200
     *                  [{"id": 1, "transactionId": 1, "accountId": 2, "entryType": "debit", "amount": "100.00", "createdAt": "..."}, ...]
     *
     * Error response : HTTP 500 {"error": "...", "code": 500}
     *
     * @Route("/ledger/account/{accountId}", name="ledger_entries_by_account", methods={"GET"})
     *
     * @param int $accountId The ID of the account whose ledger entries to retrieve.
     *
     * @return JsonResponse JSON array of ledger entries (HTTP 200) or an error envelope.
     */
    #[Route('/ledger/account/{accountId}', name: 'ledger_entries_by_account', methods: ['GET'], requirements: ['accountId' => '\d+'])]
    public function getByAccount(int $accountId): JsonResponse
    {
        try {
            $entries = $this->ledgerService->getEntriesByAccount($accountId);

            return new JsonResponse(array_map(
                fn ($entry) => [
                    'id'            => $entry->getId(),
                    'transactionId' => $entry->getTransactionId(),
                    'accountId'     => $entry->getAccountId(),
                    'entryType'     => $entry->getEntryType(),
                    'amount'        => $entry->getAmount(),
                    'createdAt'     => $entry->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
                $entries
            ));
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 500], 500);
        }
    }

    /**
     * Retrieve all ledger entries for a specific transaction.
     *
     * Returns all ledger entries (both legs) associated with the given transaction ID.
     * Returns an empty array if no entries exist for the transaction.
     *
     * Request format : GET /ledger/transaction/{transactionId}
     *                  No request body required.
     *
     * Response format: HTTP 200
     *                  [{"id": 1, "transactionId": 1, "accountId": 2, "entryType": "debit", "amount": "100.00", "createdAt": "..."}, ...]
     *
     * Error response : HTTP 500 {"error": "...", "code": 500}
     *
     * @Route("/ledger/transaction/{transactionId}", name="ledger_entries_by_transaction", methods={"GET"})
     *
     * @param int $transactionId The ID of the transaction whose ledger entries to retrieve.
     *
     * @return JsonResponse JSON array of ledger entries (HTTP 200) or an error envelope.
     */
    #[Route('/ledger/transaction/{transactionId}', name: 'ledger_entries_by_transaction', methods: ['GET'], requirements: ['transactionId' => '\d+'])]
    public function getByTransaction(int $transactionId): JsonResponse
    {
        try {
            $entries = $this->ledgerService->getEntriesByTransaction($transactionId);

            return new JsonResponse(array_map(
                fn ($entry) => [
                    'id'            => $entry->getId(),
                    'transactionId' => $entry->getTransactionId(),
                    'accountId'     => $entry->getAccountId(),
                    'entryType'     => $entry->getEntryType(),
                    'amount'        => $entry->getAmount(),
                    'createdAt'     => $entry->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
                $entries
            ));
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => 500], 500);
        }
    }
}
