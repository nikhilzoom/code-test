<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Account;
use App\Factory\AccountFactory;
use App\Repository\AccountRepositoryInterface;
use PhpCommon\Exception\NotFoundException;
use Psr\Log\LoggerInterface;

/**
 * Service class encapsulating business logic for account operations.
 *
 * Depends on {@see AccountRepositoryInterface} (never the concrete implementation)
 * and {@see AccountFactory} for entity creation. Throws {@see NotFoundException}
 * when a requested account cannot be found.
 *
 * @package App\Service
 */
class AccountService
{
    /**
     * The repository used for all account data access operations.
     *
     * @var AccountRepositoryInterface
     */
    private AccountRepositoryInterface $accountRepository;

    /**
     * The factory used to create new Account entity instances.
     *
     * @var AccountFactory
     */
    private AccountFactory $accountFactory;

    /**
     * The notification service for sending debit/credit emails.
     * Nullable — when null, notifications are silently skipped.
     *
     * @var NotificationServiceInterface|null
     */
    private ?NotificationServiceInterface $notificationService;

    /**
     * Logger for recording notification failures.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Construct a new AccountService.
     *
     * @param AccountRepositoryInterface      $accountRepository   The account repository interface.
     * @param AccountFactory                  $accountFactory      The account entity factory.
     * @param NotificationServiceInterface|null $notificationService Optional notification service.
     * @param LoggerInterface|null            $logger              PSR logger for error recording.
     */
    public function __construct(
        AccountRepositoryInterface $accountRepository,
        AccountFactory $accountFactory,
        ?NotificationServiceInterface $notificationService = null,
        ?LoggerInterface $logger = null
    ) {
        $this->accountRepository   = $accountRepository;
        $this->accountFactory      = $accountFactory;
        $this->notificationService = $notificationService;
        $this->logger              = $logger ?? new \Psr\Log\NullLogger();
    }

    /**
     * Create a new account from the provided data and persist it.
     *
     * Delegates entity construction to {@see AccountFactory::create()} and
     * persists the result via the repository.
     *
     * @param array<string, mixed> $data Associative array with keys `userId`, `balance`, and `currency`.
     *
     * @return Account The newly created and persisted Account entity.
     */
    public function createAccount(array $data): Account
    {
        /** @var Account $account */
        $account = $this->accountFactory->create($data);
        $this->accountRepository->save($account);

        return $account;
    }

    /**
     * Retrieve a single account by its primary key.
     *
     * @param int $id The primary key of the account to retrieve.
     *
     * @throws NotFoundException When no account exists with the given id.
     *
     * @return Account The found Account entity.
     */
    public function getAccountById(int $id): Account
    {
        /** @var Account|null $account */
        $account = $this->accountRepository->findById($id);

        if ($account === null) {
            throw new NotFoundException(sprintf('Account with id %d not found.', $id));
        }

        return $account;
    }

    /**
     * Debit (subtract) an amount from the account balance.
     *
     * Retrieves the account, subtracts the given amount from the current balance
     * using arbitrary-precision arithmetic, and persists the updated entity.
     *
     * @param int    $id     The primary key of the account to debit.
     * @param string $amount The amount to subtract as a decimal string.
     *
     * @throws NotFoundException When no account exists with the given id.
     * @throws \RuntimeException When the account balance is insufficient to cover the debit amount.
     *
     * @return Account The updated Account entity with the new balance.
     */
    public function debit(int $id, string $amount): Account
    {
        $account = $this->getAccountById($id);

        if (((float) $account->getBalance()) < ((float) $amount)) {
            throw new \RuntimeException(
                sprintf('Insufficient balance. Available: %s, Required: %s.', $account->getBalance(), $amount)
            );
        }

        $newBalance = (string) (((float) $account->getBalance()) - ((float) $amount));
        $account->setBalance($newBalance);
        $this->accountRepository->save($account);

        try {
            $this->notificationService?->notifyDebit($account, $amount);
        } catch (\Throwable $e) {
            $this->logger->error('AccountService: debit notification failed: ' . $e->getMessage());
        }

        return $account;
    }

    /**
     * Credit (add) an amount to the account balance.
     *
     * Retrieves the account, adds the given amount to the current balance
     * using arbitrary-precision arithmetic, and persists the updated entity.
     *
     * @param int    $id     The primary key of the account to credit.
     * @param string $amount The amount to add as a decimal string.
     *
     * @throws NotFoundException When no account exists with the given id.
     *
     * @return Account The updated Account entity with the new balance.
     */
    public function credit(int $id, string $amount): Account
    {
        $account    = $this->getAccountById($id);
        $newBalance = (string) (((float) $account->getBalance()) + ((float) $amount));
        $account->setBalance($newBalance);
        $this->accountRepository->save($account);

        try {
            $this->notificationService?->notifyCredit($account, $amount);
        } catch (\Throwable $e) {
            $this->logger->error('AccountService: credit notification failed: ' . $e->getMessage());
        }

        return $account;
    }
}
