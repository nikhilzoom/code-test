<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Account;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * SMTP implementation of {@see NotificationServiceInterface}.
 *
 * Fetches the account owner's email from user-service via HTTP, then
 * dispatches the notification through Symfony Mailer (MailHog in dev,
 * a real SMTP relay in production).
 *
 * To migrate to a dedicated notification microservice, implement
 * {@see NotificationServiceInterface} in a new class (e.g. HttpNotificationService)
 * and swap the binding in services.yaml — AccountService requires no changes.
 *
 * All failures are caught internally — this implementation is fire-and-forget.
 * Errors are logged but never propagated to the caller.
 *
 * @package App\Service
 */
class SmtpNotificationService implements NotificationServiceInterface
{
    /**
     * The sender address used on all outgoing notification emails.
     *
     * @var string
     */
    private const FROM_ADDRESS = 'noreply@fund-transfer.local';

    /**
     * The base URL of the user-service for email lookups.
     *
     * @var string
     */
    private const USER_SERVICE_URL = 'http://nginx';

    /**
     * HTTP client used to call user-service.
     *
     * @var HttpClientInterface
     */
    private HttpClientInterface $httpClient;

    /**
     * Mailer used to dispatch notification emails.
     *
     * @var MailerInterface
     */
    private MailerInterface $mailer;

    /**
     * Logger for recording notification failures.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Construct a new SmtpNotificationService.
     *
     * @param HttpClientInterface $httpClient HTTP client for user-service calls.
     * @param MailerInterface     $mailer     Symfony Mailer for sending emails.
     * @param LoggerInterface     $logger     PSR logger for error recording.
     */
    public function __construct(
        HttpClientInterface $httpClient,
        MailerInterface $mailer,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->mailer     = $mailer;
        $this->logger     = $logger;
    }

    /**
     * Send a debit notification email to the account owner.
     *
     * Fetches the owner's email from user-service, then sends an email
     * informing them of the debited amount and their new balance.
     * Silently returns if the email cannot be resolved.
     *
     * @param Account $account The account that was debited (with updated balance).
     * @param string  $amount  The amount that was debited as a decimal string.
     *
     * @return void
     */
    public function notifyDebit(Account $account, string $amount): void
    {
        $to = $this->fetchUserEmail($account->getUserId());
        if ($to === null) {
            return;
        }

        $body = sprintf(
            "Your account has been debited.\nAmount: %s %s\nNew balance: %s %s",
            $amount,
            $account->getCurrency(),
            $account->getBalance(),
            $account->getCurrency()
        );

        $this->sendEmail($to, 'Account Debited', $body);
    }

    /**
     * Send a credit notification email to the account owner.
     *
     * Fetches the owner's email from user-service, then sends an email
     * informing them of the credited amount and their new balance.
     * Silently returns if the email cannot be resolved.
     *
     * @param Account $account The account that was credited (with updated balance).
     * @param string  $amount  The amount that was credited as a decimal string.
     *
     * @return void
     */
    public function notifyCredit(Account $account, string $amount): void
    {
        $to = $this->fetchUserEmail($account->getUserId());
        if ($to === null) {
            return;
        }

        $body = sprintf(
            "Your account has been credited.\nAmount: %s %s\nNew balance: %s %s",
            $amount,
            $account->getCurrency(),
            $account->getBalance(),
            $account->getCurrency()
        );

        $this->sendEmail($to, 'Account Credited', $body);
    }

    /**
     * Fetch the email address for a user from user-service.
     *
     * Calls GET http://user-service/user/{userId} and extracts the `email`
     * field from the JSON response. Returns null on any failure (non-200,
     * network error, missing field) and logs the reason.
     *
     * @param int $userId The user ID whose email to fetch.
     *
     * @return string|null The user's email address, or null on failure.
     */
    private function fetchUserEmail(int $userId): ?string
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                self::USER_SERVICE_URL . '/user/' . $userId
            );

            if ($response->getStatusCode() !== 200) {
                $this->logger->error(sprintf(
                    'SmtpNotificationService: user-service returned %d for userId %d',
                    $response->getStatusCode(),
                    $userId
                ));
                return null;
            }

            $data = $response->toArray();

            if (empty($data['email'])) {
                $this->logger->error(sprintf(
                    'SmtpNotificationService: email field missing in user-service response for userId %d',
                    $userId
                ));
                return null;
            }

            return (string) $data['email'];
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'SmtpNotificationService: failed to fetch email for userId %d: %s',
                $userId,
                $e->getMessage()
            ));
            return null;
        }
    }

    /**
     * Construct and send an email via Symfony Mailer.
     *
     * Catches any mailer exception internally and logs it.
     * Never throws.
     *
     * @param string $to      The recipient email address.
     * @param string $subject The email subject line.
     * @param string $body    The plain-text email body.
     *
     * @return void
     */
    private function sendEmail(string $to, string $subject, string $body): void
    {
        try {
            $email = (new Email())
                ->from(self::FROM_ADDRESS)
                ->to($to)
                ->subject($subject)
                ->text($body);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'SmtpNotificationService: failed to send "%s" to %s: %s',
                $subject,
                $to,
                $e->getMessage()
            ));
        }
    }
}
