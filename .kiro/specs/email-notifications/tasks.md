# Implementation Plan: Email Notifications

## Overview

Implement fire-and-forget transactional email notifications in `account-service`. After every successful debit or credit, the system fetches the account owner's email from `user-service` and sends a notification via Symfony Mailer through MailHog. A new `NotificationService` is introduced and wired into the existing `AccountService` via nullable constructor injection.

## Tasks

- [x] 1. Add MailHog to docker-compose and configure account-service environment
  - Add a `mailhog` service to `docker-compose.yml` using image `mailhog/mailhog`, ports `1025:1025` and `8025:8025`, connected to `app-network`
  - Add `MAILER_DSN: smtp://mailhog:1025` to the `account-service` environment block in `docker-compose.yml`
  - Add `depends_on: mailhog` to the `account-service` service in `docker-compose.yml`
  - Add `MAILER_DSN=smtp://mailhog:1025` to `services/account-service/.env`
  - _Requirements: 1.1, 1.2, 1.3, 2.2, 2.3, 2.4_

- [x] 2. Add symfony/mailer dependency to account-service
  - Add `"symfony/mailer": "^7.0"` to the `require` block in `services/account-service/composer.json`
  - Add `"symfony/http-client": "^7.0"` to the `require` block if not already present via the shared lib
  - Register `Symfony\Component\Mailer\MailerInterface` and `Symfony\Contracts\HttpClient\HttpClientInterface` for autowiring in `services/account-service/config/packages/framework.yaml` (add `mailer.dsn: '%env(MAILER_DSN)%'` under a `mailer:` key)
  - _Requirements: 2.1_

- [x] 3. Implement NotificationService
  - Create `services/account-service/src/Service/NotificationService.php`
  - Constructor-inject `HttpClientInterface $httpClient`, `MailerInterface $mailer`, and `LoggerInterface $logger`
  - Implement private `fetchUserEmail(int $userId): ?string` — calls `GET http://user-service/user/{userId}`, returns the `email` field on 200, logs and returns `null` on any non-200 or exception
  - Implement private `sendEmail(string $to, string $subject, string $body): void` — constructs a `Symfony\Component\Mime\Email` with from `noreply@fund-transfer.local`, catches and logs any `MailerInterface::send()` exception
  - Implement public `notifyDebit(Account $account, string $amount): void` — calls `fetchUserEmail()`, returns early if null, calls `sendEmail()` with subject `Account Debited` and body containing amount and new balance
  - Implement public `notifyCredit(Account $account, string $amount): void` — calls `fetchUserEmail()`, returns early if null, calls `sendEmail()` with subject `Account Credited` and body containing amount and new balance
  - Add full PHPDoc to all methods and properties
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 4.1, 4.2, 4.3, 4.4, 4.5, 5.1, 5.2, 5.3, 5.4, 5.5_

- [x] 3.1 Write property test for notification recipient (Property 1)
  - Create `services/account-service/tests/Service/NotificationServiceTest.php`
  - Use eris (or equivalent PBT library) to generate random `userId` integers and random email strings
  - For each generated pair, mock `HttpClientInterface` to return a 200 response with that email, mock `MailerInterface`, call `notifyDebit()` and `notifyCredit()`, assert `MailerInterface::send()` was called with the generated email as recipient
  - Minimum 100 iterations
  - Tag: `Feature: email-notifications, Property 1: notification recipient matches user-service email`
  - _Requirements: 3.1, 3.2, 4.1, 5.1_

- [x] 3.2 Write property test for debit email body content (Property 2)
  - In `NotificationServiceTest`, generate random amount and balance strings
  - Mock `HttpClientInterface` to return a valid email, mock `MailerInterface` to capture the sent `Email` object
  - Call `notifyDebit()`, assert the email body contains both the amount and balance substrings
  - Minimum 100 iterations
  - Tag: `Feature: email-notifications, Property 2: debit email body contains amount and balance`
  - _Requirements: 4.4, 4.5_

- [x] 3.3 Write property test for credit email body content (Property 3)
  - In `NotificationServiceTest`, generate random amount and balance strings
  - Same approach as 3.2 but call `notifyCredit()` and assert subject is `Account Credited`
  - Minimum 100 iterations
  - Tag: `Feature: email-notifications, Property 3: credit email body contains amount and balance`
  - _Requirements: 5.4, 5.5_

- [x] 3.4 Write property test for non-200 suppresses email (Property 4)
  - In `NotificationServiceTest`, generate random HTTP status codes in range 400–599
  - Mock `HttpClientInterface` to return that status code, assert `MailerInterface::send()` is never called and no exception propagates
  - Minimum 100 iterations
  - Tag: `Feature: email-notifications, Property 4: non-200 user-service response suppresses email send`
  - _Requirements: 3.3_

- [x] 3.5 Write unit tests for NotificationService edge cases
  - Test: network exception from `HttpClientInterface` → no email sent, no exception thrown
  - Test: response JSON missing `email` field → no email sent, no exception thrown
  - Test: `MailerInterface::send()` throws → exception caught, no propagation
  - Test: happy path debit subject is exactly `Account Debited`
  - Test: happy path credit subject is exactly `Account Credited`
  - Test: from address is `noreply@fund-transfer.local`
  - _Requirements: 3.3, 3.4, 4.2, 4.3, 5.2, 5.3, 8.1, 8.2, 8.3, 8.4, 8.5_

- [x] 4. Checkpoint — Ensure all NotificationService tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Wire NotificationService into AccountService
  - Modify `services/account-service/src/Service/AccountService.php` constructor to accept `?NotificationService $notificationService = null` as a third parameter and store it as `$this->notificationService`
  - Add `LoggerInterface $logger` as a fourth constructor parameter (for logging notification failures) and store it
  - Update PHPDoc for the constructor
  - In `debit()`, after `$this->accountRepository->save($account)`, add a try/catch block that calls `$this->notificationService?->notifyDebit($account, $amount)` and logs any `\Throwable` via `$this->logger->error()`
  - In `credit()`, after `$this->accountRepository->save($account)`, add a try/catch block that calls `$this->notificationService?->notifyCredit($account, $amount)` and logs any `\Throwable` via `$this->logger->error()`
  - _Requirements: 6.1, 6.2, 6.3, 7.1, 7.2, 7.3, 7.4_

- [x] 5.1 Write property test for AccountService notification failure isolation (Property 5)
  - In `AccountServiceTest`, generate random exception classes/messages
  - Mock `NotificationService` to throw the generated exception, mock repository to return a valid account
  - Call `debit()` and `credit()`, assert the updated `Account` is returned and no exception propagates
  - Minimum 100 iterations
  - Tag: `Feature: email-notifications, Property 5: AccountService notification failure does not propagate`
  - _Requirements: 6.1, 6.2_

- [x] 5.2 Write property test for AccountService correct notification arguments (Property 6)
  - In `AccountServiceTest`, generate random account balances and amount strings
  - Mock `NotificationService`, call `debit()` and `credit()`, assert `notifyDebit()` / `notifyCredit()` is called with the post-operation `Account` and the original amount
  - Minimum 100 iterations
  - Tag: `Feature: email-notifications, Property 6: AccountService calls correct notification method with correct arguments`
  - _Requirements: 7.3, 7.4_

- [x] 5.3 Write unit tests for AccountService notification wiring
  - Test: `debit()` with null `NotificationService` completes without error
  - Test: `credit()` with null `NotificationService` completes without error
  - Test: `debit()` calls `notifyDebit()` after `save()` (verify call order via mock)
  - Test: `credit()` calls `notifyCredit()` after `save()` (verify call order via mock)
  - _Requirements: 7.1, 7.2, 6.3_

- [x] 6. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP
- Property tests require adding `eris/eris` (or equivalent) to `require-dev` in `composer.json`
- The nullsafe operator `?->` handles the null `NotificationService` case without an explicit null check
- MailHog web UI is available at `http://localhost:8025` after `docker compose up` for manual verification
- All new classes must follow the project PHPDoc standards (all properties, constructor params, and public methods documented)
