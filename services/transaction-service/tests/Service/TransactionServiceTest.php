<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Transaction;
use App\Factory\TransactionFactory;
use App\Repository\TransactionRepositoryInterface;
use App\Service\TransactionService;
use PhpCommon\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Unit tests for {@see TransactionService}.
 *
 * All dependencies are mocked so that only the service's own logic is exercised.
 *
 * @package App\Tests\Service
 */
class TransactionServiceTest extends TestCase
{
    /**
     * Mock of the transaction repository interface.
     *
     * @var TransactionRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private TransactionRepositoryInterface $repositoryMock;

    /**
     * Mock of the transaction factory.
     *
     * @var TransactionFactory&\PHPUnit\Framework\MockObject\MockObject
     */
    private TransactionFactory $factoryMock;

    /**
     * Mock of the HTTP client.
     *
     * @var HttpClientInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private HttpClientInterface $httpClientMock;

    /**
     * Mock of the logger.
     *
     * @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private LoggerInterface $loggerMock;

    /**
     * The service under test.
     *
     * @var TransactionService
     */
    private TransactionService $service;

    /**
     * Set up fresh mocks and a new service instance before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(TransactionRepositoryInterface::class);
        $this->factoryMock    = $this->createMock(TransactionFactory::class);
        $this->httpClientMock = $this->createMock(HttpClientInterface::class);
        $this->loggerMock     = $this->createMock(LoggerInterface::class);

        $this->service = new TransactionService(
            $this->repositoryMock,
            $this->factoryMock,
            $this->httpClientMock,
            $this->loggerMock
        );
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
        $transaction->setCreatedAt(new \DateTimeImmutable());

        $ref = new \ReflectionProperty(Transaction::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($transaction, $id);

        return $transaction;
    }

    /**
     * Test that initiateTransfer creates a pending transaction, calls all downstream services,
     * and returns the transaction with status 'completed' on the happy path.
     *
     * @return void
     */
    public function testInitiateTransferHappyPath(): void
    {
        $data = [
            'sourceAccountId'      => 1,
            'destinationAccountId' => 2,
            'amount'               => '100.00',
            'currency'             => 'USD',
        ];

        $transaction = $this->buildTransaction(1, 1, 2, '100.00', 'USD', 'pending');

        $this->factoryMock
            ->expects($this->once())
            ->method('create')
            ->willReturn($transaction);

        $this->repositoryMock
            ->expects($this->exactly(2))
            ->method('save')
            ->with($transaction);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('getContent')->willReturn(
            json_encode(['id' => 1, 'balance' => '1000.00', 'currency' => 'USD'])
        );

        $this->httpClientMock
            ->expects($this->exactly(6))
            ->method('request')
            ->willReturn($responseMock);

        $result = $this->service->initiateTransfer($data);

        $this->assertSame($transaction, $result);
        $this->assertSame('completed', $result->getStatus());
    }

    /**
     * Test that initiateTransfer sets status to 'failed', logs the error, and re-throws
     * when an HTTP client call fails.
     *
     * @return void
     */
    public function testInitiateTransferFailurePath(): void
    {
        $data = [
            'sourceAccountId'      => 1,
            'destinationAccountId' => 2,
            'amount'               => '100.00',
            'currency'             => 'USD',
        ];

        $transaction = $this->buildTransaction(1, 1, 2, '100.00', 'USD', 'pending');

        $this->factoryMock
            ->expects($this->once())
            ->method('create')
            ->willReturn($transaction);

        $this->repositoryMock
            ->expects($this->exactly(2))
            ->method('save')
            ->with($transaction);

        $preflightResponse = $this->createMock(ResponseInterface::class);
        $preflightResponse->method('getStatusCode')->willReturn(200);
        $preflightResponse->method('getContent')->willReturn(
            json_encode(['id' => 1, 'balance' => '1000.00', 'currency' => 'USD'])
        );

        $this->httpClientMock
            ->expects($this->atLeastOnce())
            ->method('request')
            ->willReturnCallback(function (string $method, string $url) use ($preflightResponse): ResponseInterface {
                if ($method === 'GET') {
                    return $preflightResponse;
                }
                throw new \RuntimeException('Connection refused');
            });

        $this->loggerMock
            ->expects($this->once())
            ->method('error');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection refused');

        try {
            $this->service->initiateTransfer($data);
        } finally {
            $this->assertSame('failed', $transaction->getStatus());
        }
    }

    /**
     * Test that initiateTransfer throws InvalidArgumentException when the destination account
     * does not exist (account service returns HTTP 404).
     *
     * @return void
     */
    public function testInitiateTransferThrowsWhenReceiverAccountNotFound(): void
    {
        $data = [
            'sourceAccountId'      => 1,
            'destinationAccountId' => 99,
            'amount'               => '100.00',
            'currency'             => 'USD',
        ];

        $notFoundResponse = $this->createMock(ResponseInterface::class);
        $notFoundResponse->method('getStatusCode')->willReturn(404);

        $this->httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'http://nginx/account/99')
            ->willReturn($notFoundResponse);

        $this->factoryMock->expects($this->never())->method('create');
        $this->repositoryMock->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Destination account with id 99 not found.');

        $this->service->initiateTransfer($data);
    }

    /**
     * Test that initiateTransfer throws InvalidArgumentException when the source account
     * has insufficient balance to cover the transfer amount.
     *
     * @return void
     */
    public function testInitiateTransferThrowsWhenInsufficientBalance(): void
    {
        $data = [
            'sourceAccountId'      => 1,
            'destinationAccountId' => 2,
            'amount'               => '500.00',
            'currency'             => 'USD',
        ];

        $receiverResponse = $this->createMock(ResponseInterface::class);
        $receiverResponse->method('getStatusCode')->willReturn(200);

        $senderResponse = $this->createMock(ResponseInterface::class);
        $senderResponse->method('getStatusCode')->willReturn(200);
        $senderResponse->method('getContent')->willReturn(
            json_encode(['id' => 1, 'balance' => '100.00', 'currency' => 'USD'])
        );

        $this->httpClientMock
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($receiverResponse, $senderResponse);

        $this->factoryMock->expects($this->never())->method('create');
        $this->repositoryMock->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient balance. Available: 100.00, Required: 500.00.');

        $this->service->initiateTransfer($data);
    }

    /**
     * Test that initiateTransfer throws InvalidArgumentException when source and destination
     * account IDs are the same, without creating any transaction.
     *
     * @return void
     */
    public function testInitiateTransferThrowsWhenSameAccount(): void
    {
        $data = [
            'sourceAccountId'      => 5,
            'destinationAccountId' => 5,
            'amount'               => '100.00',
            'currency'             => 'USD',
        ];

        $this->factoryMock->expects($this->never())->method('create');
        $this->repositoryMock->expects($this->never())->method('save');
        $this->httpClientMock->expects($this->never())->method('request');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Source and destination accounts must not be the same.');

        $this->service->initiateTransfer($data);
    }

    /**
     * Test that getTransactionById returns the transaction when found.
     *
     * @return void
     */
    public function testGetTransactionByIdHappyPath(): void
    {
        $transaction = $this->buildTransaction(7, 1, 2, '50.00', 'EUR', 'completed');

        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with(7)
            ->willReturn($transaction);

        $result = $this->service->getTransactionById(7);

        $this->assertSame($transaction, $result);
    }

    /**
     * Test that getTransactionById throws NotFoundException when the transaction does not exist.
     *
     * @return void
     */
    public function testGetTransactionByIdThrowsNotFoundException(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with(99)
            ->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Transaction with id 99 not found.');

        $this->service->getTransactionById(99);
    }
}
