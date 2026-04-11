<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\TransactionController;
use App\Entity\Transaction;
use App\Service\TransactionService;
use PhpCommon\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for {@see TransactionController}.
 *
 * {@see TransactionService} is mocked so that only the controller's own logic is exercised.
 *
 * @package App\Tests\Controller
 */
class TransactionControllerTest extends TestCase
{
    /**
     * Mock of the transaction service.
     *
     * @var TransactionService&\PHPUnit\Framework\MockObject\MockObject
     */
    private TransactionService $serviceMock;

    /**
     * The controller under test.
     *
     * @var TransactionController
     */
    private TransactionController $controller;

    /**
     * Set up a fresh service mock and controller before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->serviceMock = $this->createMock(TransactionService::class);
        $this->controller  = new TransactionController($this->serviceMock);
    }

    /**
     * Build a Transaction entity with preset values for use in tests.
     *
     * @param int    $id                   The transaction ID to set via reflection.
     * @param int    $sourceAccountId      The source account ID.
     * @param int    $destinationAccountId The destination account ID.
     * @param string $amount               The transfer amount.
     * @param string $currency             The currency code.
     * @param string $status               The transaction status.
     *
     * @return Transaction A populated Transaction entity.
     */
    private function buildTransaction(
        int $id,
        int $sourceAccountId,
        int $destinationAccountId,
        string $amount,
        string $currency,
        string $status
    ): Transaction {
        $transaction = new Transaction();
        $transaction->setSourceAccountId($sourceAccountId);
        $transaction->setDestinationAccountId($destinationAccountId);
        $transaction->setAmount($amount);
        $transaction->setCurrency($currency);
        $transaction->setStatus($status);
        $transaction->setCreatedAt(new \DateTimeImmutable('2024-01-01T00:00:00+00:00'));

        $ref = new \ReflectionProperty(Transaction::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($transaction, $id);

        return $transaction;
    }

    // -------------------------------------------------------------------------
    // initiate()
    // -------------------------------------------------------------------------

    /**
     * Test that initiate() returns HTTP 201 with the transaction data on success.
     *
     * @return void
     */
    public function testInitiateReturns201OnSuccess(): void
    {
        $transaction = $this->buildTransaction(1, 1, 2, '100.00', 'USD', 'completed');

        $this->serviceMock
            ->expects($this->once())
            ->method('initiateTransfer')
            ->with(['sourceAccountId' => 1, 'destinationAccountId' => 2, 'amount' => '100.00', 'currency' => 'USD'])
            ->willReturn($transaction);

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['sourceAccountId' => 1, 'destinationAccountId' => 2, 'amount' => '100.00', 'currency' => 'USD'])
        );

        $response = $this->controller->initiate($request);

        $this->assertSame(201, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(1, $body['id']);
        $this->assertSame(1, $body['sourceAccountId']);
        $this->assertSame(2, $body['destinationAccountId']);
        $this->assertSame('100.00', $body['amount']);
        $this->assertSame('USD', $body['currency']);
        $this->assertSame('completed', $body['status']);
    }

    /**
     * Test that initiate() returns HTTP 400 when the request body is not valid JSON.
     *
     * @return void
     */
    public function testInitiateReturns400OnInvalidJson(): void
    {
        $request = new Request([], [], [], [], [], [], 'not-json');

        $response = $this->controller->initiate($request);

        $this->assertSame(400, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(400, $body['code']);
        $this->assertArrayHasKey('error', $body);
    }

    /**
     * Test that initiate() returns HTTP 500 when the service throws an exception.
     *
     * @return void
     */
    public function testInitiateReturns500OnException(): void
    {
        $this->serviceMock
            ->method('initiateTransfer')
            ->willThrowException(new \RuntimeException('Transfer failed'));

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['sourceAccountId' => 1, 'destinationAccountId' => 2, 'amount' => '100.00', 'currency' => 'USD'])
        );

        $response = $this->controller->initiate($request);

        $this->assertSame(500, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(500, $body['code']);
        $this->assertSame('Transfer failed', $body['error']);
    }

    // -------------------------------------------------------------------------
    // getById()
    // -------------------------------------------------------------------------

    /**
     * Test that getById() returns HTTP 200 with the transaction data when found.
     *
     * @return void
     */
    public function testGetByIdReturns200OnSuccess(): void
    {
        $transaction = $this->buildTransaction(5, 3, 4, '250.00', 'EUR', 'completed');

        $this->serviceMock
            ->expects($this->once())
            ->method('getTransactionById')
            ->with(5)
            ->willReturn($transaction);

        $response = $this->controller->getById(5);

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(5, $body['id']);
        $this->assertSame(3, $body['sourceAccountId']);
        $this->assertSame(4, $body['destinationAccountId']);
        $this->assertSame('250.00', $body['amount']);
        $this->assertSame('EUR', $body['currency']);
        $this->assertSame('completed', $body['status']);
    }

    /**
     * Test that getById() returns HTTP 404 when the transaction is not found.
     *
     * @return void
     */
    public function testGetByIdReturns404WhenNotFound(): void
    {
        $this->serviceMock
            ->expects($this->once())
            ->method('getTransactionById')
            ->with(99)
            ->willThrowException(new NotFoundException('Transaction with id 99 not found.'));

        $response = $this->controller->getById(99);

        $this->assertSame(404, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(404, $body['code']);
        $this->assertStringContainsString('99', $body['error']);
    }

    /**
     * Test that getById() returns HTTP 500 on an unexpected exception.
     *
     * @return void
     */
    public function testGetByIdReturns500OnException(): void
    {
        $this->serviceMock
            ->method('getTransactionById')
            ->willThrowException(new \RuntimeException('Unexpected error'));

        $response = $this->controller->getById(1);

        $this->assertSame(500, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(500, $body['code']);
    }
}
