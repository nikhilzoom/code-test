<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\LedgerController;
use App\Entity\LedgerEntry;
use App\Service\LedgerService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for {@see LedgerController}.
 *
 * {@see LedgerService} is mocked so that only the controller's own logic is exercised.
 *
 * @package App\Tests\Controller
 */
class LedgerControllerTest extends TestCase
{
    /**
     * Mock of the ledger service.
     *
     * @var LedgerService&\PHPUnit\Framework\MockObject\MockObject
     */
    private LedgerService $serviceMock;

    /**
     * The controller under test.
     *
     * @var LedgerController
     */
    private LedgerController $controller;

    /**
     * Set up a fresh service mock and controller before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->serviceMock = $this->createMock(LedgerService::class);
        $this->controller  = new LedgerController($this->serviceMock);
    }

    /**
     * Build a LedgerEntry entity with preset values for use in tests.
     *
     * @param int    $id            The entry ID to set via reflection.
     * @param int    $transactionId The transaction ID.
     * @param int    $accountId     The account ID.
     * @param string $entryType     The entry type ("debit" or "credit").
     * @param string $amount        The monetary amount.
     *
     * @return LedgerEntry A populated LedgerEntry entity.
     */
    private function buildEntry(
        int $id,
        int $transactionId,
        int $accountId,
        string $entryType,
        string $amount
    ): LedgerEntry {
        $entry = new LedgerEntry();
        $entry->setTransactionId($transactionId);
        $entry->setAccountId($accountId);
        $entry->setEntryType($entryType);
        $entry->setAmount($amount);
        $entry->setCreatedAt(new \DateTimeImmutable('2024-01-01T00:00:00+00:00'));

        $ref = new \ReflectionProperty(LedgerEntry::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entry, $id);

        return $entry;
    }

    // -------------------------------------------------------------------------
    // recordEntry()
    // -------------------------------------------------------------------------

    /**
     * Test that recordEntry() returns HTTP 201 with the new entry data on success.
     *
     * @return void
     */
    public function testRecordEntryReturns201OnSuccess(): void
    {
        $entry = $this->buildEntry(1, 1, 2, 'debit', '100.00');

        $this->serviceMock
            ->expects($this->once())
            ->method('recordEntry')
            ->with(['transactionId' => 1, 'accountId' => 2, 'entryType' => 'debit', 'amount' => '100.00'])
            ->willReturn($entry);

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['transactionId' => 1, 'accountId' => 2, 'entryType' => 'debit', 'amount' => '100.00'])
        );

        $response = $this->controller->recordEntry($request);

        $this->assertSame(201, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(1, $body['id']);
        $this->assertSame(1, $body['transactionId']);
        $this->assertSame(2, $body['accountId']);
        $this->assertSame('debit', $body['entryType']);
        $this->assertSame('100.00', $body['amount']);
    }

    /**
     * Test that recordEntry() returns HTTP 400 when the request body is not valid JSON.
     *
     * @return void
     */
    public function testRecordEntryReturns400OnInvalidJson(): void
    {
        $request = new Request([], [], [], [], [], [], 'not-json');

        $response = $this->controller->recordEntry($request);

        $this->assertSame(400, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(400, $body['code']);
        $this->assertArrayHasKey('error', $body);
    }

    /**
     * Test that recordEntry() returns HTTP 500 when the service throws an unexpected exception.
     *
     * @return void
     */
    public function testRecordEntryReturns500OnException(): void
    {
        $this->serviceMock
            ->method('recordEntry')
            ->willThrowException(new \RuntimeException('DB error'));

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['transactionId' => 1, 'accountId' => 2, 'entryType' => 'debit', 'amount' => '100.00'])
        );

        $response = $this->controller->recordEntry($request);

        $this->assertSame(500, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(500, $body['code']);
        $this->assertSame('DB error', $body['error']);
    }

    // -------------------------------------------------------------------------
    // getByAccount()
    // -------------------------------------------------------------------------

    /**
     * Test that getByAccount() returns HTTP 200 with an array of entries on success.
     *
     * @return void
     */
    public function testGetByAccountReturns200OnSuccess(): void
    {
        $entry1 = $this->buildEntry(1, 1, 2, 'debit', '100.00');
        $entry2 = $this->buildEntry(2, 2, 2, 'credit', '50.00');

        $this->serviceMock
            ->expects($this->once())
            ->method('getEntriesByAccount')
            ->with(2)
            ->willReturn([$entry1, $entry2]);

        $response = $this->controller->getByAccount(2);

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertCount(2, $body);
        $this->assertSame(1, $body[0]['id']);
        $this->assertSame(2, $body[1]['id']);
    }

    /**
     * Test that getByAccount() returns HTTP 200 with an empty array when no entries exist.
     *
     * @return void
     */
    public function testGetByAccountReturnsEmptyArray(): void
    {
        $this->serviceMock
            ->expects($this->once())
            ->method('getEntriesByAccount')
            ->with(99)
            ->willReturn([]);

        $response = $this->controller->getByAccount(99);

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame([], $body);
    }

    /**
     * Test that getByAccount() returns HTTP 500 on an unexpected exception.
     *
     * @return void
     */
    public function testGetByAccountReturns500OnException(): void
    {
        $this->serviceMock
            ->method('getEntriesByAccount')
            ->willThrowException(new \RuntimeException('Unexpected error'));

        $response = $this->controller->getByAccount(1);

        $this->assertSame(500, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(500, $body['code']);
    }

    // -------------------------------------------------------------------------
    // getByTransaction()
    // -------------------------------------------------------------------------

    /**
     * Test that getByTransaction() returns HTTP 200 with an array of entries on success.
     *
     * @return void
     */
    public function testGetByTransactionReturns200OnSuccess(): void
    {
        $entry1 = $this->buildEntry(1, 5, 2, 'debit', '200.00');
        $entry2 = $this->buildEntry(2, 5, 3, 'credit', '200.00');

        $this->serviceMock
            ->expects($this->once())
            ->method('getEntriesByTransaction')
            ->with(5)
            ->willReturn([$entry1, $entry2]);

        $response = $this->controller->getByTransaction(5);

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertCount(2, $body);
        $this->assertSame(5, $body[0]['transactionId']);
        $this->assertSame(5, $body[1]['transactionId']);
    }

    /**
     * Test that getByTransaction() returns HTTP 200 with an empty array when no entries exist.
     *
     * @return void
     */
    public function testGetByTransactionReturnsEmptyArray(): void
    {
        $this->serviceMock
            ->expects($this->once())
            ->method('getEntriesByTransaction')
            ->with(99)
            ->willReturn([]);

        $response = $this->controller->getByTransaction(99);

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame([], $body);
    }

    /**
     * Test that getByTransaction() returns HTTP 500 on an unexpected exception.
     *
     * @return void
     */
    public function testGetByTransactionReturns500OnException(): void
    {
        $this->serviceMock
            ->method('getEntriesByTransaction')
            ->willThrowException(new \RuntimeException('Unexpected error'));

        $response = $this->controller->getByTransaction(1);

        $this->assertSame(500, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame(500, $body['code']);
    }
}
