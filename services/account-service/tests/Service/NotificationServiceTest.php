<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Account;
use App\Service\SmtpNotificationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Unit tests for NotificationService.
 *
 * Covers happy paths, error isolation, and data-driven property tests
 * for recipient matching, body content, and non-200 suppression.
 *
 * All HTTP and SMTP dependencies are mocked — no real network calls are made.
 *
 * @package App\Tests\Service
 */
class NotificationServiceTest extends TestCase
{
    /**
     * @var HttpClientInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private HttpClientInterface $httpClient;

    /**
     * @var MailerInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private MailerInterface $mailer;

    /**
     * @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private LoggerInterface $logger;

    /**
     * @var SmtpNotificationService
     */
    private SmtpNotificationService $service;

    /**
     * Set up mocks and the service under test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->mailer     = $this->createMock(MailerInterface::class);
        $this->logger     = $this->createMock(LoggerInterface::class);

        $this->service = new SmtpNotificationService(
            $this->httpClient,
            $this->mailer,
            $this->logger
        );
    }

    /**
     * Build an Account entity with the given values.
     *
     * @param int    $userId
     * @param string $balance
     * @param string $currency
     *
     * @return Account
     */
    private function makeAccount(int $userId, string $balance = '1000.00', string $currency = 'USD'): Account
    {
        $account = new Account();
        $account->setUserId($userId);
        $account->setBalance($balance);
        $account->setCurrency($currency);
        $account->setCreatedAt(new \DateTimeImmutable());

        return $account;
    }

    /**
     * Build a mock HTTP response returning the given status and JSON body.
     *
     * @param int                  $status
     * @param array<string, mixed> $data
     *
     * @return ResponseInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeHttpResponse(int $status, array $data = []): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('toArray')->willReturn($data);

        return $response;
    }

    // ── Debit notification — happy path ───────────────────────────────────────

    /**
     * Test that notifyDebit() sends an email with subject "Account Debited".
     *
     * @return void
     */
    public function testNotifyDebitSendsEmailWithCorrectSubject(): void
    {
        $this->httpClient->method('request')->willReturn(
            $this->makeHttpResponse(200, ['email' => 'alice@example.com'])
        );

        $sentEmail = null;
        $this->mailer->expects($this->once())->method('send')
            ->willReturnCallback(function (Email $e) use (&$sentEmail) { $sentEmail = $e; });

        $this->service->notifyDebit($this->makeAccount(1, '800.00'), '200.00');

        $this->assertSame('Account Debited', $sentEmail->getSubject());
    }

    /**
     * Test that notifyDebit() sends to the email returned by user-service.
     *
     * @return void
     */
    public function testNotifyDebitSendsToCorrectRecipient(): void
    {
        $this->httpClient->method('request')->willReturn(
            $this->makeHttpResponse(200, ['email' => 'alice@example.com'])
        );

        $sentEmail = null;
        $this->mailer->method('send')
            ->willReturnCallback(function (Email $e) use (&$sentEmail) { $sentEmail = $e; });

        $this->service->notifyDebit($this->makeAccount(1, '800.00'), '200.00');

        $this->assertSame('alice@example.com', $sentEmail->getTo()[0]->getAddress());
    }

    /**
     * Test that notifyDebit() email body contains the debited amount.
     *
     * @return void
     */
    public function testNotifyDebitBodyContainsAmount(): void
    {
        $this->httpClient->method('request')->willReturn(
            $this->makeHttpResponse(200, ['email' => 'alice@example.com'])
        );

        $sentEmail = null;
        $this->mailer->method('send')
            ->willReturnCallback(function (Email $e) use (&$sentEmail) { $sentEmail = $e; });

        $this->service->notifyDebit($this->makeAccount(1, '800.00'), '200.00');

        $this->assertStringContainsString('200.00', $sentEmail->getTextBody());
    }

    /**
     * Test that notifyDebit() email body contains the new balance.
     *
     * @return void
     */
    public function testNotifyDebitBodyContainsNewBalance(): void
    {
        $this->httpClient->method('request')->willReturn(
            $this->makeHttpResponse(200, ['email' => 'alice@example.com'])
        );

        $sentEmail = null;
        $this->mailer->method('send')
            ->willReturnCallback(function (Email $e) use (&$sentEmail) { $sentEmail = $e; });

        $this->service->notifyDebit($this->makeAccount(1, '800.00'), '200.00');

        $this->assertStringContainsString('800.00', $sentEmail->getTextBody());
    }

    /**
     * Test that notifyDebit() uses noreply@fund-transfer.local as the from address.
     *
     * @return void
     */
    public function testNotifyDebitUsesCorrectFromAddress(): void
    {
        $this->httpClient->method('request')->willReturn(
            $this->makeHttpResponse(200, ['email' => 'alice@example.com'])
        );

        $sentEmail = null;
        $this->mailer->method('send')
            ->willReturnCallback(function (Email $e) use (&$sentEmail) { $sentEmail = $e; });

        $this->service->notifyDebit($this->makeAccount(1, '800.00'), '200.00');

        $this->assertSame('noreply@fund-transfer.local', $sentEmail->getFrom()[0]->getAddress());
    }

    // ── Credit notification — happy path ──────────────────────────────────────

    /**
     * Test that notifyCredit() sends an email with subject "Account Credited".
     *
     * @return void
     */
    public function testNotifyCreditSendsEmailWithCorrectSubject(): void
    {
        $this->httpClient->method('request')->willReturn(
            $this->makeHttpResponse(200, ['email' => 'bob@example.com'])
        );

        $sentEmail = null;
        $this->mailer->expects($this->once())->method('send')
            ->willReturnCallback(function (Email $e) use (&$sentEmail) { $sentEmail = $e; });

        $this->service->notifyCredit($this->makeAccount(2, '1500.00'), '500.00');

        $this->assertSame('Account Credited', $sentEmail->getSubject());
    }

    /**
     * Test that notifyCredit() email body contains the credited amount and new balance.
     *
     * @return void
     */
    public function testNotifyCreditBodyContainsAmountAndBalance(): void
    {
        $this->httpClient->method('request')->willReturn(
            $this->makeHttpResponse(200, ['email' => 'bob@example.com'])
        );

        $sentEmail = null;
        $this->mailer->method('send')
            ->willReturnCallback(function (Email $e) use (&$sentEmail) { $sentEmail = $e; });

        $this->service->notifyCredit($this->makeAccount(2, '1500.00'), '500.00');

        $this->assertStringContainsString('500.00', $sentEmail->getTextBody());
        $this->assertStringContainsString('1500.00', $sentEmail->getTextBody());
    }

    // ── Error isolation ───────────────────────────────────────────────────────

    /**
     * Test that a non-200 response from user-service suppresses email sending.
     *
     * @return void
     */
    public function testNon200ResponseSuppressesEmail(): void
    {
        $this->httpClient->method('request')->willReturn($this->makeHttpResponse(404));
        $this->mailer->expects($this->never())->method('send');

        $this->service->notifyDebit($this->makeAccount(99), '50.00');
        $this->service->notifyCredit($this->makeAccount(99), '50.00');
    }

    /**
     * Test that a network exception from HttpClient suppresses email and does not throw.
     *
     * @return void
     */
    public function testNetworkExceptionSuppressesEmailAndDoesNotThrow(): void
    {
        $this->httpClient->method('request')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $this->mailer->expects($this->never())->method('send');

        $this->service->notifyDebit($this->makeAccount(1), '50.00');
        $this->service->notifyCredit($this->makeAccount(1), '50.00');

        $this->assertTrue(true);
    }

    /**
     * Test that a missing email field in the response suppresses email sending.
     *
     * @return void
     */
    public function testMissingEmailFieldSuppressesEmail(): void
    {
        $this->httpClient->method('request')->willReturn(
            $this->makeHttpResponse(200, ['id' => 1, 'name' => 'Alice'])
        );

        $this->mailer->expects($this->never())->method('send');

        $this->service->notifyDebit($this->makeAccount(1), '50.00');
    }

    /**
     * Test that a mailer exception is caught and does not propagate.
     *
     * @return void
     */
    public function testMailerExceptionIsCaughtAndDoesNotPropagate(): void
    {
        $this->httpClient->method('request')->willReturn(
            $this->makeHttpResponse(200, ['email' => 'alice@example.com'])
        );
        $this->mailer->method('send')
            ->willThrowException(new \RuntimeException('SMTP connection failed'));

        $this->service->notifyDebit($this->makeAccount(1, '800.00'), '200.00');

        $this->assertTrue(true);
    }

    // ── Property 1: recipient matches user-service email (data-driven) ────────

    /**
     * Provides varied userId / email combinations for recipient property testing.
     *
     * Feature: email-notifications, Property 1: notification recipient matches user-service email
     *
     * @return array<string, array{int, string}>
     */
    public static function userEmailProvider(): array
    {
        return [
            'standard email'          => [1,   'alice@example.com'],
            'subdomain email'         => [2,   'bob@mail.example.org'],
            'plus-address email'      => [3,   'carol+tag@example.com'],
            'numeric local part'      => [4,   '12345@example.com'],
            'hyphenated domain'       => [5,   'dave@my-company.io'],
            'long local part'         => [6,   'very.long.local.part@example.com'],
            'uppercase domain'        => [7,   'eve@EXAMPLE.COM'],
            'high userId'             => [999, 'frank@example.net'],
            'single char local'       => [10,  'g@x.co'],
            'multiple dots in domain' => [11,  'heidi@a.b.c.example.com'],
        ];
    }

    /**
     * Property 1: notifyDebit() sends to the exact email returned by user-service for any userId/email pair.
     *
     * Feature: email-notifications, Property 1: notification recipient matches user-service email
     *
     * @dataProvider userEmailProvider
     *
     * @param int    $userId
     * @param string $email
     *
     * @return void
     */
    public function testNotifyDebitRecipientMatchesUserServiceEmail(int $userId, string $email): void
    {
        $this->httpClient->method('request')->willReturn(
            $this->makeHttpResponse(200, ['email' => $email])
        );

        $sentEmail = null;
        $this->mailer->expects($this->once())->method('send')
            ->willReturnCallback(function (Email $e) use (&$sentEmail) { $sentEmail = $e; });

        $this->service->notifyDebit($this->makeAccount($userId, '500.00'), '100.00');

        $this->assertSame($email, $sentEmail->getTo()[0]->getAddress());
    }

    /**
     * Property 1: notifyCredit() sends to the exact email returned by user-service for any userId/email pair.
     *
     * Feature: email-notifications, Property 1: notification recipient matches user-service email
     *
     * @dataProvider userEmailProvider
     *
     * @param int    $userId
     * @param string $email
     *
     * @return void
     */
    public function testNotifyCreditRecipientMatchesUserServiceEmail(int $userId, string $email): void
    {
        $this->httpClient->method('request')->willReturn(
            $this->makeHttpResponse(200, ['email' => $email])
        );

        $sentEmail = null;
        $this->mailer->expects($this->once())->method('send')
            ->willReturnCallback(function (Email $e) use (&$sentEmail) { $sentEmail = $e; });

        $this->service->notifyCredit($this->makeAccount($userId, '500.00'), '100.00');

        $this->assertSame($email, $sentEmail->getTo()[0]->getAddress());
    }

    // ── Property 2: debit email body contains amount and balance (data-driven) ─

    /**
     * Provides varied amount/balance pairs for debit body content testing.
     *
     * Feature: email-notifications, Property 2: debit email body contains amount and balance
     *
     * @return array<string, array{string, string}>
     */
    public static function debitAmountBalanceProvider(): array
    {
        return [
            'standard debit'      => ['200.00',    '800.00'],
            'small debit'         => ['0.01',      '999.99'],
            'large debit'         => ['50000.00',  '950000.00'],
            'exact balance'       => ['1000.00',   '0.00'],
            'decimal precision'   => ['12.3456',   '987.6544'],
            'round numbers'       => ['500',       '500'],
            'single unit'         => ['1.00',      '99.00'],
            'high precision'      => ['0.0001',    '9999.9999'],
        ];
    }

    /**
     * Property 2: debit email body always contains both the debited amount and the new balance.
     *
     * Feature: email-notifications, Property 2: debit email body contains amount and balance
     *
     * @dataProvider debitAmountBalanceProvider
     *
     * @param string $amount  The amount debited.
     * @param string $balance The resulting balance on the account.
     *
     * @return void
     */
    public function testDebitEmailBodyContainsAmountAndBalance(string $amount, string $balance): void
    {
        $this->httpClient->method('request')->willReturn(
            $this->makeHttpResponse(200, ['email' => 'test@example.com'])
        );

        $sentEmail = null;
        $this->mailer->method('send')
            ->willReturnCallback(function (Email $e) use (&$sentEmail) { $sentEmail = $e; });

        $this->service->notifyDebit($this->makeAccount(1, $balance), $amount);

        $body = $sentEmail->getTextBody();
        $this->assertStringContainsString($amount, $body,
            "Debit email body should contain amount '{$amount}'");
        $this->assertStringContainsString($balance, $body,
            "Debit email body should contain balance '{$balance}'");
    }

    // ── Property 3: credit email body contains amount and balance (data-driven) ─

    /**
     * Provides varied amount/balance pairs for credit body content testing.
     *
     * Feature: email-notifications, Property 3: credit email body contains amount and balance
     *
     * @return array<string, array{string, string}>
     */
    public static function creditAmountBalanceProvider(): array
    {
        return [
            'standard credit'     => ['500.00',   '1500.00'],
            'small credit'        => ['0.01',      '100.01'],
            'large credit'        => ['100000.00', '200000.00'],
            'zero balance start'  => ['250.00',    '250.00'],
            'decimal precision'   => ['33.3333',   '133.3333'],
            'round numbers'       => ['1000',      '2000'],
            'single cent'         => ['0.01',      '0.01'],
            'high value'          => ['999999.99', '1999999.99'],
        ];
    }

    /**
     * Property 3: credit email body always contains both the credited amount and the new balance,
     * and the subject is exactly "Account Credited".
     *
     * Feature: email-notifications, Property 3: credit email body contains amount and balance
     *
     * @dataProvider creditAmountBalanceProvider
     *
     * @param string $amount  The amount credited.
     * @param string $balance The resulting balance on the account.
     *
     * @return void
     */
    public function testCreditEmailBodyContainsAmountAndBalance(string $amount, string $balance): void
    {
        $this->httpClient->method('request')->willReturn(
            $this->makeHttpResponse(200, ['email' => 'test@example.com'])
        );

        $sentEmail = null;
        $this->mailer->method('send')
            ->willReturnCallback(function (Email $e) use (&$sentEmail) { $sentEmail = $e; });

        $this->service->notifyCredit($this->makeAccount(1, $balance), $amount);

        $body = $sentEmail->getTextBody();
        $this->assertStringContainsString($amount, $body,
            "Credit email body should contain amount '{$amount}'");
        $this->assertStringContainsString($balance, $body,
            "Credit email body should contain balance '{$balance}'");
        $this->assertSame('Account Credited', $sentEmail->getSubject());
    }

    // ── Property 4: non-200 status suppresses email (data-driven) ────────────

    /**
     * Provides HTTP error status codes that should all suppress email sending.
     *
     * Feature: email-notifications, Property 4: non-200 user-service response suppresses email send
     *
     * @return array<string, array{int}>
     */
    public static function errorStatusCodeProvider(): array
    {
        return [
            'Bad Request 400'           => [400],
            'Unauthorized 401'          => [401],
            'Forbidden 403'             => [403],
            'Not Found 404'             => [404],
            'Method Not Allowed 405'    => [405],
            'Conflict 409'              => [409],
            'Gone 410'                  => [410],
            'Unprocessable Entity 422'  => [422],
            'Too Many Requests 429'     => [429],
            'Internal Server Error 500' => [500],
            'Bad Gateway 502'           => [502],
            'Service Unavailable 503'   => [503],
            'Gateway Timeout 504'       => [504],
        ];
    }

    /**
     * Property 4: notifyDebit() never sends email when user-service returns any non-200 status.
     *
     * Feature: email-notifications, Property 4: non-200 user-service response suppresses email send
     *
     * @dataProvider errorStatusCodeProvider
     *
     * @param int $statusCode
     *
     * @return void
     */
    public function testNotifyDebitSuppressesEmailForNon200Status(int $statusCode): void
    {
        $this->httpClient->method('request')->willReturn(
            $this->makeHttpResponse($statusCode)
        );

        $this->mailer->expects($this->never())->method('send');

        $this->service->notifyDebit($this->makeAccount(1), '50.00');

        $this->assertTrue(true, "No exception should propagate for status {$statusCode}");
    }

    /**
     * Property 4: notifyCredit() never sends email when user-service returns any non-200 status.
     *
     * Feature: email-notifications, Property 4: non-200 user-service response suppresses email send
     *
     * @dataProvider errorStatusCodeProvider
     *
     * @param int $statusCode
     *
     * @return void
     */
    public function testNotifyCreditSuppressesEmailForNon200Status(int $statusCode): void
    {
        $this->httpClient->method('request')->willReturn(
            $this->makeHttpResponse($statusCode)
        );

        $this->mailer->expects($this->never())->method('send');

        $this->service->notifyCredit($this->makeAccount(1), '50.00');

        $this->assertTrue(true, "No exception should propagate for status {$statusCode}");
    }
}
