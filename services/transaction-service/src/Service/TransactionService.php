<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Transaction;
use App\Factory\TransactionFactory;
use App\Repository\TransactionRepositoryInterface;
use PhpCommon\Exception\NotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service class encapsulating business logic for fund transfer transaction operations.
 *
 * Orchestrates the full transfer flow: creates a pending transaction, debits the source
 * account, credits the destination account, records both ledger entries, and marks the
 * transaction as completed or failed. Depends on {@see TransactionRepositoryInterface}
 * (never the concrete implementation), {@see TransactionFactory}, and Symfony's
 * {@see HttpClientInterface} for inter-service HTTP calls.
 *
 * @package App\Service
 */
class TransactionService
{
    /**
     * The repository used for all transaction data access operations.
     *
     * @var TransactionRepositoryInterface
     */
    private TransactionRepositoryInterface $transactionRepository;

    /**
     * The factory used to create new Transaction entity instances.
     *
     * @var TransactionFactory
     */
    private TransactionFactory $transactionFactory;

    /**
     * The HTTP client used for inter-service communication.
     *
     * @var HttpClientInterface
     */
    private HttpClientInterface $httpClient;

    /**
     * The logger used to record errors during transfer orchestration.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Construct a new TransactionService.
     *
     * @param TransactionRepositoryInterface $transactionRepository The transaction repository interface.
     * @param TransactionFactory             $transactionFactory    The transaction entity factory.
     * @param HttpClientInterface            $httpClient            The HTTP client for inter-service calls.
     * @param LoggerInterface                $logger                The logger for error recording.
     */
    public function __construct(
        TransactionRepositoryInterface $transactionRepository,
        TransactionFactory $transactionFactory,
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->transactionFactory    = $transactionFactory;
        $this->httpClient            = $httpClient;
        $this->logger                = $logger;
    }

    /**
     * Initiate a fund transfer between two accounts.
     *
     * Creates a transaction with status `pending`, then:
     * 1. POSTs to Account Service to debit the source account.
     * 2. POSTs to Account Service to credit the destination account.
     * 3. POSTs to Ledger Service to record the debit ledger entry.
     * 4. POSTs to Ledger Service to record the credit ledger entry.
     *
     * On full success the transaction status is set to `completed` and persisted.
     * On any failure the transaction status is set to `failed`, the error is logged,
     * and the updated transaction is persisted before the exception is re-thrown.
     *
     * Expected keys in $data:
     * - `sourceAccountId`      (int)    The ID of the account to debit.
     * - `destinationAccountId` (int)    The ID of the account to credit.
     * - `amount`               (string) The transfer amount as a decimal string.
     * - `currency`             (string) The ISO 4217 currency code.
     *
     * @param array<string, mixed> $data Associative array containing transfer data.
     *
     * @throws \Throwable When any inter-service call fails; the transaction is marked failed before throwing.
     *
     * @return Transaction The persisted Transaction entity with status `completed` or `failed`.
     */
    public function initiateTransfer(array $data): Transaction
    {
        /** @var Transaction $transaction */
        $transaction = $this->transactionFactory->create(array_merge($data, ['status' => 'pending']));
        $this->transactionRepository->save($transaction);

        try {
            $sourceId      = $transaction->getSourceAccountId();
            $destinationId = $transaction->getDestinationAccountId();
            $amount        = $transaction->getAmount();
            $currency      = $transaction->getCurrency();
            $transactionId = $transaction->getId();

            $this->httpClient->request('POST', sprintf('http://account-service/account/%d/debit', $sourceId), [
                'json' => ['amount' => $amount],
            ]);

            $this->httpClient->request('POST', sprintf('http://account-service/account/%d/credit', $destinationId), [
                'json' => ['amount' => $amount],
            ]);

            $this->httpClient->request('POST', 'http://ledger-service/ledger/entry', [
                'json' => [
                    'transactionId' => $transactionId,
                    'accountId'     => $sourceId,
                    'entryType'     => 'debit',
                    'amount'        => $amount,
                    'currency'      => $currency,
                ],
            ]);

            $this->httpClient->request('POST', 'http://ledger-service/ledger/entry', [
                'json' => [
                    'transactionId' => $transactionId,
                    'accountId'     => $destinationId,
                    'entryType'     => 'credit',
                    'amount'        => $amount,
                    'currency'      => $currency,
                ],
            ]);

            $transaction->setStatus('completed');
            $this->transactionRepository->save($transaction);
        } catch (\Throwable $e) {
            $this->logger->error('Transfer failed: ' . $e->getMessage(), [
                'transactionId' => $transaction->getId(),
                'exception'     => $e,
            ]);

            $transaction->setStatus('failed');
            $this->transactionRepository->save($transaction);

            throw $e;
        }

        return $transaction;
    }

    /**
     * Retrieve a single transaction by its primary key.
     *
     * @param int $id The primary key of the transaction to retrieve.
     *
     * @throws NotFoundException When no transaction exists with the given id.
     *
     * @return Transaction The found Transaction entity.
     */
    public function getTransactionById(int $id): Transaction
    {
        /** @var Transaction|null $transaction */
        $transaction = $this->transactionRepository->findById($id);

        if ($transaction === null) {
            throw new NotFoundException(sprintf('Transaction with id %d not found.', $id));
        }

        return $transaction;
    }
}
