# Requirements Document

## Introduction

This feature adds email notification support to the Fund Transfer System's account-service. After every successful debit or credit operation, the system sends a transactional email to the account owner informing them of the transaction amount and their new balance. Emails are delivered via MailHog (an SMTP trap) in development. The notification is fire-and-forget: failures must be logged but must never cause the underlying financial operation to fail.

## Glossary

- **Account_Service**: The Symfony microservice responsible for debit/credit operations on financial accounts.
- **User_Service**: The Symfony microservice that stores user profiles and exposes `GET /user/{id}` returning `{id, name, email, createdAt}`.
- **NotificationService**: The new class inside Account_Service responsible for fetching a user's email and dispatching notification emails.
- **AccountService**: The existing class inside Account_Service that performs debit and credit operations.
- **MailHog**: An SMTP trap server used in development to capture outgoing emails (SMTP port 1025, web UI port 8025).
- **Mailer**: The Symfony Mailer component (`symfony/mailer`) used to send emails via SMTP.
- **HttpClient**: The Symfony HTTP Client component (`symfony/http-client`) used to call User_Service.
- **MAILER_DSN**: The environment variable that configures the SMTP connection string for Symfony Mailer.
- **Debit_Notification**: An email sent after a successful debit operation.
- **Credit_Notification**: An email sent after a successful credit operation.

---

## Requirements

### Requirement 1: MailHog Infrastructure

**User Story:** As a developer, I want a local SMTP trap server in the Docker environment, so that I can inspect outgoing emails without sending real messages.

#### Acceptance Criteria

1. THE docker-compose.yml SHALL include a MailHog service using the `mailhog/mailhog` image.
2. THE MailHog service SHALL expose port 1025 for SMTP and port 8025 for the web UI on the host machine.
3. THE MailHog service SHALL be connected to the `app-network` Docker network so that Account_Service can reach it.

---

### Requirement 2: Mailer Configuration in Account_Service

**User Story:** As a developer, I want Account_Service to be configured to send emails via MailHog, so that transactional emails are captured locally during development.

#### Acceptance Criteria

1. THE Account_Service `composer.json` SHALL declare `symfony/mailer` as a runtime dependency.
2. THE Account_Service `.env` file SHALL contain `MAILER_DSN=smtp://mailhog:1025`.
3. THE `account-service` entry in `docker-compose.yml` SHALL include `MAILER_DSN=smtp://mailhog:1025` in its `environment` block.
4. THE `account-service` entry in `docker-compose.yml` SHALL declare a `depends_on` relationship on the `mailhog` service.

---

### Requirement 3: User Email Resolution

**User Story:** As the system, I want to retrieve the account owner's email address from User_Service before sending a notification, so that the email is addressed to the correct recipient.

#### Acceptance Criteria

1. WHEN NotificationService needs to send an email for a given `userId`, THE NotificationService SHALL issue an HTTP GET request to `http://user-service/user/{userId}`.
2. WHEN User_Service returns a 200 response, THE NotificationService SHALL extract the `email` field from the JSON response body.
3. IF User_Service returns a non-200 response or a network error occurs, THEN THE NotificationService SHALL log the error and abort the email send without throwing an exception.
4. IF the JSON response body does not contain a valid `email` field, THEN THE NotificationService SHALL log the error and abort the email send without throwing an exception.

---

### Requirement 4: Debit Notification Email

**User Story:** As an account owner, I want to receive an email when my account is debited, so that I am informed of the transaction and my new balance.

#### Acceptance Criteria

1. WHEN a debit operation completes successfully, THE NotificationService SHALL send an email to the account owner's address.
2. THE Debit_Notification email SHALL have the subject `Account Debited`.
3. THE Debit_Notification email SHALL have the sender address `noreply@fund-transfer.local`.
4. THE Debit_Notification email body SHALL include the amount debited.
5. THE Debit_Notification email body SHALL include the new account balance after the debit.

---

### Requirement 5: Credit Notification Email

**User Story:** As an account owner, I want to receive an email when my account is credited, so that I am informed of the transaction and my new balance.

#### Acceptance Criteria

1. WHEN a credit operation completes successfully, THE NotificationService SHALL send an email to the account owner's address.
2. THE Credit_Notification email SHALL have the subject `Account Credited`.
3. THE Credit_Notification email SHALL have the sender address `noreply@fund-transfer.local`.
4. THE Credit_Notification email body SHALL include the amount credited.
5. THE Credit_Notification email body SHALL include the new account balance after the credit.

---

### Requirement 6: Fire-and-Forget Error Isolation

**User Story:** As a system operator, I want email notification failures to be isolated from financial operations, so that a mailer or network outage never causes a debit or credit to fail.

#### Acceptance Criteria

1. IF NotificationService throws any exception during email dispatch, THEN THE AccountService SHALL catch the exception, log it, and return the successfully updated Account entity as normal.
2. IF NotificationService throws any exception during user email resolution, THEN THE AccountService SHALL catch the exception, log it, and return the successfully updated Account entity as normal.
3. WHILE a debit or credit operation is in progress, THE AccountService SHALL persist the account balance change before invoking NotificationService.

---

### Requirement 7: NotificationService Integration into AccountService

**User Story:** As a developer, I want NotificationService to be wired into AccountService via constructor injection, so that notifications are sent automatically after every successful debit or credit.

#### Acceptance Criteria

1. THE AccountService SHALL accept a nullable `NotificationService` via constructor injection.
2. WHEN `NotificationService` is `null` (e.g., in tests that do not inject it), THE AccountService SHALL skip notification sending without error.
3. WHEN a debit completes successfully, THE AccountService SHALL call `NotificationService::notifyDebit()` with the updated Account entity and the debited amount.
4. WHEN a credit completes successfully, THE AccountService SHALL call `NotificationService::notifyCredit()` with the updated Account entity and the credited amount.

---

### Requirement 8: Unit Tests for NotificationService

**User Story:** As a developer, I want unit tests for NotificationService, so that I can verify notification logic in isolation without real HTTP calls or SMTP connections.

#### Acceptance Criteria

1. THE test suite SHALL include a unit test verifying that `notifyDebit()` fetches the user email from User_Service and sends an email with subject `Account Debited`.
2. THE test suite SHALL include a unit test verifying that `notifyCredit()` fetches the user email from User_Service and sends an email with subject `Account Credited`.
3. THE test suite SHALL include a unit test verifying that when User_Service returns a non-200 response, no email is sent and no exception is thrown.
4. THE test suite SHALL include a unit test verifying that when Mailer throws an exception, the exception is caught and no exception propagates out of NotificationService.
5. THE test suite SHALL use mocked `HttpClientInterface` and `MailerInterface` dependencies so that no real HTTP or SMTP calls are made.
