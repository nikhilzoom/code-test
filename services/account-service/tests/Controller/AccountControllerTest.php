<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AccountController;
use App\DTO\AccountDTO;
use App\DTO\AmountDTO;
use App\Entity\Account;
use App\Service\AccountService;
use PhpCommon\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for {@see AccountController}.
 *
 * {@see AccountService} is mocked so that only the controller's own logic is exercised.
 *
 * @package App\Tests\Controller
 */
class AccountControllerTest extends TestCase
{
    /**
     * Mock of the account service.
     *
     * @var AccountService&\PHPUnit\Framework\MockObject\MockObject
     */
    private AccountService $serviceMock;

    /**
     * The controller under test.
     *
     * @var AccountController
     */
    private AccountController $controller;

    /**
     * Set up a fresh service mock and controller before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->serviceMock = $this->createMock(AccountService::class);
        $this->controller  = new AccountController($this->serviceMock);
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
        $account->setCreatedAt(new \DateTimeImmutable('2024-01-01T00:00:00+00:00'));

        $ref = new \ReflectionProperty(Account::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($account, $id);

        return $account;
    }

    // ── create() ─────────────────────────────────────────────────────────────

    /**
     * Test that create() returns HTTP 201 with the new account data on success.
     *
     * @return void
     */
    public function testCreateReturns201OnSuccess(): void
    {
        $account = $this->buildAccount(1, 1, '1000.00', 'USD');

        $this->serviceMock
            ->expects($this->once())
            ->method('createAccount')
            ->with($this->isInstanceOf(AccountDTO::class))
            ->willReturn($account);

        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['userId' => 1, 'balance' => '1000.00', 'currency' => 'USD'])
        );

        $response = $this->controller->create($request);

        $this->assertSame(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame(1, $body['id']);
        $this->assertSame(1, $body['userId']);
        $this->assertSame('1000.00', $body['balance']);
        $this->assertSame('USD', $body['currency']);
    }

    /**
     * Test that create() returns HTTP 400 when the request body is not valid JSON.
     *
     * @return void
     */
    public function testCreateReturns400OnInvalidJson(): void
    {
        $response = $this->controller->create(new Request([], [], [], [], [], [], 'not-json'));

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame(400, $body['code']);
        $this->assertArrayHasKey('error', $body);
    }

    /**
     * Test that create() returns HTTP 400 when DTO validation fails (invalid userId).
     *
     * @return void
     */
    public function testCreateReturns400OnDtoValidationFailure(): void
    {
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['userId' => 0, 'balance' => '1000.00', 'currency' => 'USD'])
        );

        $response = $this->controller->create($request);

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $body);
        $this->assertNotEmpty($body['errors']);
    }

    /**
     * Test that create() returns HTTP 400 when currency is invalid.
     *
     * @return void
     */
    public function testCreateReturns400OnInvalidCurrency(): void
    {
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['userId' => 1, 'balance' => '100.00', 'currency' => 'us'])
        );

        $response = $this->controller->create($request);

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $body);
    }

    /**
     * Test that create() returns HTTP 500 when the service throws an unexpected exception.
     *
     * @return void
     */
    public function testCreateReturns500OnException(): void
    {
        $this->serviceMock->method('createAccount')->willThrowException(new \RuntimeException('DB error'));

        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['userId' => 1, 'balance' => '1000.00', 'currency' => 'USD'])
        );

        $response = $this->controller->create($request);

        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame(500, $body['code']);
        $this->assertSame('DB error', $body['error']);
    }

    // ── getById() ─────────────────────────────────────────────────────────────

    /**
     * Test that getById() returns HTTP 200 with the account data when found.
     *
     * @return void
     */
    public function testGetByIdReturns200OnSuccess(): void
    {
        $account = $this->buildAccount(5, 2, '500.00', 'EUR');
        $this->serviceMock->expects($this->once())->method('getAccountById')->with(5)->willReturn($account);

        $response = $this->controller->getById(5);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame(5, $body['id']);
        $this->assertSame('500.00', $body['balance']);
    }

    /**
     * Test that getById() returns HTTP 404 when the account is not found.
     *
     * @return void
     */
    public function testGetByIdReturns404WhenNotFound(): void
    {
        $this->serviceMock->method('getAccountById')->willThrowException(new NotFoundException('Account with id 99 not found.'));

        $response = $this->controller->getById(99);

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame(404, $body['code']);
    }

    /**
     * Test that getById() returns HTTP 500 on an unexpected exception.
     *
     * @return void
     */
    public function testGetByIdReturns500OnException(): void
    {
        $this->serviceMock->method('getAccountById')->willThrowException(new \RuntimeException('Unexpected'));

        $response = $this->controller->getById(1);

        $this->assertSame(500, $response->getStatusCode());
    }

    // ── debit() ───────────────────────────────────────────────────────────────

    /**
     * Test that debit() returns HTTP 200 with the updated account data on success.
     *
     * @return void
     */
    public function testDebitReturns200OnSuccess(): void
    {
        $account = $this->buildAccount(1, 1, '800', 'USD');
        $this->serviceMock->expects($this->once())->method('debit')->with(1, '200.00')->willReturn($account);

        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['amount' => '200.00'])
        );

        $response = $this->controller->debit($request, 1);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('800', $body['balance']);
    }

    /**
     * Test that debit() returns HTTP 400 when the request body is not valid JSON.
     *
     * @return void
     */
    public function testDebitReturns400OnInvalidJson(): void
    {
        $response = $this->controller->debit(new Request([], [], [], [], [], [], 'not-json'), 1);

        $this->assertSame(400, $response->getStatusCode());
    }

    /**
     * Test that debit() returns HTTP 400 when amount is zero or negative.
     *
     * @return void
     */
    public function testDebitReturns400OnInvalidAmount(): void
    {
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['amount' => '-10.00'])
        );

        $response = $this->controller->debit($request, 1);

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $body);
    }

    /**
     * Test that debit() returns HTTP 404 when the account is not found.
     *
     * @return void
     */
    public function testDebitReturns404WhenNotFound(): void
    {
        $this->serviceMock->method('debit')->willThrowException(new NotFoundException('Account with id 1 not found.'));

        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['amount' => '200.00'])
        );

        $response = $this->controller->debit($request, 1);

        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * Test that debit() returns HTTP 500 on an unexpected exception.
     *
     * @return void
     */
    public function testDebitReturns500OnException(): void
    {
        $this->serviceMock->method('debit')->willThrowException(new \RuntimeException('Unexpected'));

        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['amount' => '200.00'])
        );

        $response = $this->controller->debit($request, 1);

        $this->assertSame(500, $response->getStatusCode());
    }

    // ── credit() ──────────────────────────────────────────────────────────────

    /**
     * Test that credit() returns HTTP 200 with the updated account data on success.
     *
     * @return void
     */
    public function testCreditReturns200OnSuccess(): void
    {
        $account = $this->buildAccount(1, 1, '1500', 'USD');
        $this->serviceMock->expects($this->once())->method('credit')->with(1, '500.00')->willReturn($account);

        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['amount' => '500.00'])
        );

        $response = $this->controller->credit($request, 1);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('1500', $body['balance']);
    }

    /**
     * Test that credit() returns HTTP 400 when the request body is not valid JSON.
     *
     * @return void
     */
    public function testCreditReturns400OnInvalidJson(): void
    {
        $response = $this->controller->credit(new Request([], [], [], [], [], [], 'not-json'), 1);

        $this->assertSame(400, $response->getStatusCode());
    }

    /**
     * Test that credit() returns HTTP 400 when amount is zero.
     *
     * @return void
     */
    public function testCreditReturns400OnZeroAmount(): void
    {
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['amount' => '0'])
        );

        $response = $this->controller->credit($request, 1);

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $body);
    }

    /**
     * Test that credit() returns HTTP 404 when the account is not found.
     *
     * @return void
     */
    public function testCreditReturns404WhenNotFound(): void
    {
        $this->serviceMock->method('credit')->willThrowException(new NotFoundException('Account with id 1 not found.'));

        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['amount' => '500.00'])
        );

        $response = $this->controller->credit($request, 1);

        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * Test that credit() returns HTTP 500 on an unexpected exception.
     *
     * @return void
     */
    public function testCreditReturns500OnException(): void
    {
        $this->serviceMock->method('credit')->willThrowException(new \RuntimeException('Unexpected'));

        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['amount' => '500.00'])
        );

        $response = $this->controller->credit($request, 1);

        $this->assertSame(500, $response->getStatusCode());
    }
}
