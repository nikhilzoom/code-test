<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\AccountDTO;
use App\Entity\Account;
use App\Factory\AccountFactory;
use App\Repository\AccountRepositoryInterface;
use App\Service\AccountService;
use App\Service\NotificationServiceInterface;
use PhpCommon\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see AccountService}.
 *
 * All dependencies are mocked so that only the service's own logic is exercised.
 *
 * @package App\Tests\Service
 */
class AccountServiceTest extends TestCase
{
    /**
     * Mock of the account repository interface.
     *
     * @var AccountRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private AccountRepositoryInterface $repositoryMock;

    /**
     * Mock of the account factory.
     *
     * @var AccountFactory&\PHPUnit\Framework\MockObject\MockObject
     */
    private AccountFactory $factoryMock;

    /**
     * The service under test (no NotificationService injected).
     *
     * @var AccountService
     */
    private AccountService $service;

    /**
     * Set up fresh mocks and a new service instance before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(AccountRepositoryInterface::class);
        $this->factoryMock    = $this->createMock(AccountFactory::class);
        $this->service        = new AccountService($this->repositoryMock, $this->factoryMock);
    }

    /**
     * Build an Account entity with preset values for use in tests.
     *
     * @param int    $id       The account ID to set via reflection.
     * @param int    $userId   The owner user ID.
     * @param string $balance  The account balance.
     * @param string $currency The currency code.
     *
     * @return Account A populated Account entity.
     */
    private function buildAccount(int $id, int $userId, string $balance, string $currency): Account
    {
        $account = new Account();
        $account->setUserId($userId);
        $account->setBalance($balance);
        $account->setCurrency($currency);
        $account->setCreatedAt(new \DateTimeImmutable());

        $ref = new \ReflectionProperty(Account::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($account, $id);

        return $account;
    }

    /**
     * Build an AccountDTO with preset values.
     *
     * @param int    $userId
     * @param string $balance
     * @param string $currency
     *
     * @return AccountDTO
     */
    private function buildDto(int $userId, string $balance, string $currency): AccountDTO
    {
        return new AccountDTO(['userId' => $userId, 'balance' => $balance, 'currency' => $currency]);
    }

    /**
     * Build an AccountService with a mocked NotificationServiceInterface injected.
     *
     * @param NotificationServiceInterface $notificationService
     *
     * @return AccountService
     */
    private function serviceWithNotification(NotificationServiceInterface $notificationService): AccountService
    {
        return new AccountService(
            $this->repositoryMock,
            $this->factoryMock,
            $notificationService
        );
    }

    // ── Core behaviour ────────────────────────────────────────────────────────

    /**
     * Test that createAccount delegates to the factory and repository, then returns the entity.
     *
     * @return void
     */
    public function testCreateAccountHappyPath(): void
    {
        $dto     = $this->buildDto(1, '1000.00', 'USD');
        $account = $this->buildAccount(1, 1, '1000.00', 'USD');

        $this->factoryMock->expects($this->once())->method('create')->with($dto)->willReturn($account);
        $this->repositoryMock->expects($this->once())->method('save')->with($account);

        $result = $this->service->createAccount($dto);

        $this->assertSame($account, $result);
        $this->assertSame(1, $result->getUserId());
        $this->assertSame('1000.00', $result->getBalance());
        $this->assertSame('USD', $result->getCurrency());
    }

    /**
     * Test that getAccountById returns the account when found.
     *
     * @return void
     */
    public function testGetAccountByIdHappyPath(): void
    {
        $account = $this->buildAccount(5, 2, '500.00', 'EUR');
        $this->repositoryMock->expects($this->once())->method('findById')->with(5)->willReturn($account);

        $this->assertSame($account, $this->service->getAccountById(5));
    }

    /**
     * Test that getAccountById throws NotFoundException when the account does not exist.
     *
     * @return void
     */
    public function testGetAccountByIdThrowsNotFoundException(): void
    {
        $this->repositoryMock->method('findById')->with(99)->willReturn(null);
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Account with id 99 not found.');

        $this->service->getAccountById(99);
    }

    /**
     * Test that debit subtracts the amount from the balance and saves the account.
     *
     * @return void
     */
    public function testDebitHappyPath(): void
    {
        $account = $this->buildAccount(1, 1, '1000.00', 'USD');
        $this->repositoryMock->method('findById')->willReturn($account);
        $this->repositoryMock->expects($this->once())->method('save')->with($account);

        $result = $this->service->debit(1, '200.00');

        $this->assertSame($account, $result);
        $this->assertSame('800', $result->getBalance());
    }

    /**
     * Test that credit adds the amount to the balance and saves the account.
     *
     * @return void
     */
    public function testCreditHappyPath(): void
    {
        $account = $this->buildAccount(1, 1, '1000.00', 'USD');
        $this->repositoryMock->method('findById')->willReturn($account);
        $this->repositoryMock->expects($this->once())->method('save')->with($account);

        $result = $this->service->credit(1, '500.00');

        $this->assertSame($account, $result);
        $this->assertSame('1500', $result->getBalance());
    }

    // ── Notification wiring ───────────────────────────────────────────────────

    /**
     * Test that debit() with null NotificationService completes without error.
     *
     * @return void
     */
    public function testDebitWithNullNotificationServiceCompletesWithoutError(): void
    {
        $account = $this->buildAccount(1, 1, '1000.00', 'USD');
        $this->repositoryMock->method('findById')->willReturn($account);

        $this->assertInstanceOf(Account::class, $this->service->debit(1, '100.00'));
    }

    /**
     * Test that credit() with null NotificationService completes without error.
     *
     * @return void
     */
    public function testCreditWithNullNotificationServiceCompletesWithoutError(): void
    {
        $account = $this->buildAccount(1, 1, '1000.00', 'USD');
        $this->repositoryMock->method('findById')->willReturn($account);

        $this->assertInstanceOf(Account::class, $this->service->credit(1, '100.00'));
    }

    /**
     * Test that debit() calls notifyDebit() on the NotificationService after save().
     *
     * @return void
     */
    public function testDebitCallsNotifyDebitAfterSave(): void
    {
        $account = $this->buildAccount(1, 1, '1000.00', 'USD');
        $this->repositoryMock->method('findById')->willReturn($account);

        $ns = $this->createMock(NotificationServiceInterface::class);
        $ns->expects($this->once())->method('notifyDebit')->with($account, '200.00');

        $this->serviceWithNotification($ns)->debit(1, '200.00');
    }

    /**
     * Test that credit() calls notifyCredit() on the NotificationService after save().
     *
     * @return void
     */
    public function testCreditCallsNotifyCreditAfterSave(): void
    {
        $account = $this->buildAccount(1, 1, '1000.00', 'USD');
        $this->repositoryMock->method('findById')->willReturn($account);

        $ns = $this->createMock(NotificationServiceInterface::class);
        $ns->expects($this->once())->method('notifyCredit')->with($account, '300.00');

        $this->serviceWithNotification($ns)->credit(1, '300.00');
    }

    /**
     * Test that debit() returns the updated Account even when NotificationService throws.
     *
     * @return void
     */
    public function testDebitReturnsAccountWhenNotificationThrows(): void
    {
        $account = $this->buildAccount(1, 1, '1000.00', 'USD');
        $this->repositoryMock->method('findById')->willReturn($account);

        $ns = $this->createMock(NotificationServiceInterface::class);
        $ns->method('notifyDebit')->willThrowException(new \RuntimeException('SMTP down'));

        $this->assertInstanceOf(Account::class, $this->serviceWithNotification($ns)->debit(1, '100.00'));
    }

    /**
     * Test that credit() returns the updated Account even when NotificationService throws.
     *
     * @return void
     */
    public function testCreditReturnsAccountWhenNotificationThrows(): void
    {
        $account = $this->buildAccount(1, 1, '1000.00', 'USD');
        $this->repositoryMock->method('findById')->willReturn($account);

        $ns = $this->createMock(NotificationServiceInterface::class);
        $ns->method('notifyCredit')->willThrowException(new \RuntimeException('Network error'));

        $this->assertInstanceOf(Account::class, $this->serviceWithNotification($ns)->credit(1, '100.00'));
    }

    // ── Notification failure isolation (data-driven) ──────────────────────────

    /**
     * Provides various exception types to simulate notification failures.
     *
     * @return array<string, array{\Throwable}>
     */
    public static function notificationExceptionProvider(): array
    {
        return [
            'RuntimeException'         => [new \RuntimeException('SMTP timeout')],
            'LogicException'           => [new \LogicException('Bad state')],
            'InvalidArgumentException' => [new \InvalidArgumentException('Bad arg')],
            'OverflowException'        => [new \OverflowException('Queue full')],
            'generic Exception'        => [new \Exception('Generic failure')],
        ];
    }

    /**
     * debit() always returns Account regardless of exception type thrown by NotificationService.
     *
     * @dataProvider notificationExceptionProvider
     *
     * @param \Throwable $exception
     *
     * @return void
     */
    public function testDebitIsolatesNotificationFailureForAnyExceptionType(\Throwable $exception): void
    {
        $account = $this->buildAccount(1, 1, '500.00', 'USD');
        $this->repositoryMock->method('findById')->willReturn($account);

        $ns = $this->createMock(NotificationServiceInterface::class);
        $ns->method('notifyDebit')->willThrowException($exception);

        $this->assertInstanceOf(Account::class, $this->serviceWithNotification($ns)->debit(1, '50.00'));
    }

    /**
     * credit() always returns Account regardless of exception type thrown by NotificationService.
     *
     * @dataProvider notificationExceptionProvider
     *
     * @param \Throwable $exception
     *
     * @return void
     */
    public function testCreditIsolatesNotificationFailureForAnyExceptionType(\Throwable $exception): void
    {
        $account = $this->buildAccount(1, 1, '500.00', 'USD');
        $this->repositoryMock->method('findById')->willReturn($account);

        $ns = $this->createMock(NotificationServiceInterface::class);
        $ns->method('notifyCredit')->willThrowException($exception);

        $this->assertInstanceOf(Account::class, $this->serviceWithNotification($ns)->credit(1, '50.00'));
    }
}
