# Design Document

## Fund Transfer System

---

## Overview

The Fund Transfer System is a PHP/Symfony microservices platform composed of five independently deployable services, orchestrated via Docker Compose, proxied through NGINX, and backed by MySQL and Redis. The system is designed for isolation, testability, and maintainability — each service owns its domain, exposes a health endpoint, and communicates with peers over the internal Docker network via HTTP.

The five services are:

| Service | Path Prefix | Responsibility |
|---|---|---|
| API Gateway | `/api` | Single external entry point; routes and forwards requests to downstream services |
| User Service | `/user` | User registration, lookup, and management |
| Account Service | `/account` | Account creation and balance management |
| Transaction Service | `/transaction` | Initiating and tracking fund transfers |
| Ledger Service | `/ledger` | Recording and querying immutable financial ledger entries |

All services share a common PHP library at `shared/php-common/` for cross-cutting concerns (base interfaces, DTOs, utilities).

---

## Architecture

### High-Level Architecture

```
                        ┌─────────────────────────────────────────────────────┐
                        │                  Docker Network                      │
                        │                                                       │
  HTTP :80              │  ┌──────────┐    ┌─────────────────────────────────┐ │
 ──────────────────────►│  │  NGINX   │───►│         API Gateway             │ │
  /api/*                │  │  :80     │    │  services/api-gateway/  :9000   │ │
  /user/*               │  │          │    └─────────────────────────────────┘ │
  /account/*            │  │          │                    │                   │
  /transaction/*        │  │          │    ┌───────────────▼─────────────────┐ │
  /ledger/*             │  │          │───►│         User Service            │ │
                        │  │          │    │  services/user-service/  :9001  │ │
                        │  │          │    └─────────────────────────────────┘ │
                        │  │          │                                         │
                        │  │          │    ┌─────────────────────────────────┐ │
                        │  │          │───►│        Account Service          │ │
                        │  │          │    │ services/account-service/ :9002 │ │
                        │  │          │    └─────────────────────────────────┘ │
                        │  │          │                                         │
                        │  │          │    ┌─────────────────────────────────┐ │
                        │  │          │───►│      Transaction Service        │ │
                        │  │          │    │services/transaction-service/:9003│ │
                        │  │          │    └─────────────────────────────────┘ │
                        │  │          │                                         │
                        │  │          │    ┌─────────────────────────────────┐ │
                        │  └──────────┘───►│        Ledger Service           │ │
                        │                  │ services/ledger-service/  :9004 │ │
                        │                  └─────────────────────────────────┘ │
                        │                                                       │
                        │  ┌──────────────────┐   ┌──────────────────────────┐ │
                        │  │  MySQL (latest)  │   │    Redis (latest)        │ │
                        │  │  :3306           │   │    :6379                 │ │
                        │  └──────────────────┘   └──────────────────────────┘ │
                        └─────────────────────────────────────────────────────┘
```

### Request Flow

```
Client
  │
  ▼
NGINX (:80)
  │  path-based routing
  ├─ /api/*          ──► API Gateway  (PHP-FPM :9000)
  ├─ /user/*         ──► User Service (PHP-FPM :9001)
  ├─ /account/*      ──► Account Service (PHP-FPM :9002)
  ├─ /transaction/*  ──► Transaction Service (PHP-FPM :9003)
  └─ /ledger/*       ──► Ledger Service (PHP-FPM :9004)
```

The API Gateway may additionally fan out to downstream services over the internal Docker network using HTTP client calls (e.g., Symfony HttpClient). Direct service-to-service calls (e.g., Transaction Service calling Ledger Service) follow the same pattern.

---

## Components and Interfaces

### Shared: `shared/php-common/`

Contains base interfaces and utilities reused across all services.

```
shared/php-common/
├── src/
│   ├── Repository/
│   │   └── RepositoryInterface.php       # Base CRUD interface
│   ├── Factory/
│   │   └── FactoryInterface.php          # Base factory interface
│   ├── DTO/
│   │   └── HealthResponseDTO.php         # Shared health response DTO
│   └── Exception/
│       └── NotFoundException.php
└── composer.json
```

**`RepositoryInterface.php`** — generic base:
```php
interface RepositoryInterface
{
    public function findById(int $id): ?object;
    public function findAll(): array;
    public function save(object $entity): void;
    public function delete(int $id): void;
}
```

**`FactoryInterface.php`** — generic base:
```php
interface FactoryInterface
{
    public function create(array $data): object;
}
```

---

### API Gateway (`services/api-gateway/`)

Acts as the single external entry point. Validates incoming requests, forwards them to the appropriate downstream service, and returns the response.

#### Layer Breakdown

| Layer | Class | Responsibility |
|---|---|---|
| Controller | `HealthController` | `GET /health` → `{"status":"ok"}` |
| Controller | `ProxyController` | Forwards requests to downstream services |
| Service | `ProxyService` | Encapsulates HttpClient routing logic |
| Factory | `RequestFactory` | Builds outbound `Request` objects |
| DTO | `HealthResponseDTO` | Typed health response |

#### Directory Structure

```
services/api-gateway/
├── src/
│   ├── Controller/
│   │   ├── HealthController.php
│   │   └── ProxyController.php
│   ├── Service/
│   │   └── ProxyService.php
│   ├── Factory/
│   │   └── RequestFactory.php
│   └── DTO/
│       └── HealthResponseDTO.php
├── tests/
│   ├── Controller/
│   │   ├── HealthControllerTest.php
│   │   └── ProxyControllerTest.php
│   └── Service/
│       └── ProxyServiceTest.php
├── config/
├── .env
├── composer.json
├── Dockerfile
├── phpstan.neon
└── phpunit.xml
```

---

### User Service (`services/user-service/`)

Manages user entities: creation, retrieval, and listing.

#### Layer Breakdown

| Layer | Class | Responsibility |
|---|---|---|
| Controller | `HealthController` | `GET /health` |
| Controller | `UserController` | CRUD endpoints for users |
| Service | `UserService` | Business logic for user operations |
| Repository Interface | `UserRepositoryInterface` | Contract for user data access |
| Repository | `UserRepository` | Doctrine/MySQL implementation |
| Factory | `UserFactory` | Creates `User` entities from raw data |
| Entity | `User` | Doctrine ORM entity |
| DTO | `UserDTO` | Request/response data transfer object |

#### Directory Structure

```
services/user-service/
├── src/
│   ├── Controller/
│   │   ├── HealthController.php
│   │   └── UserController.php
│   ├── Service/
│   │   └── UserService.php
│   ├── Repository/
│   │   ├── UserRepositoryInterface.php
│   │   └── UserRepository.php
│   ├── Factory/
│   │   └── UserFactory.php
│   ├── Entity/
│   │   └── User.php
│   └── DTO/
│       └── UserDTO.php
├── tests/
│   ├── Controller/
│   │   └── UserControllerTest.php
│   ├── Service/
│   │   └── UserServiceTest.php
│   └── Repository/
│       └── UserRepositoryTest.php
├── config/
├── .env
├── composer.json
├── Dockerfile
├── phpstan.neon
└── phpunit.xml
```

---

### Account Service (`services/account-service/`)

Manages financial accounts: creation, balance queries, and updates.

#### Layer Breakdown

| Layer | Class | Responsibility |
|---|---|---|
| Controller | `HealthController` | `GET /health` |
| Controller | `AccountController` | CRUD + balance endpoints |
| Service | `AccountService` | Business logic for account operations |
| Repository Interface | `AccountRepositoryInterface` | Contract for account data access |
| Repository | `AccountRepository` | Doctrine/MySQL implementation |
| Factory | `AccountFactory` | Creates `Account` entities |
| Entity | `Account` | Doctrine ORM entity |
| DTO | `AccountDTO` | Request/response DTO |

#### Directory Structure

```
services/account-service/
├── src/
│   ├── Controller/
│   │   ├── HealthController.php
│   │   └── AccountController.php
│   ├── Service/
│   │   └── AccountService.php
│   ├── Repository/
│   │   ├── AccountRepositoryInterface.php
│   │   └── AccountRepository.php
│   ├── Factory/
│   │   └── AccountFactory.php
│   ├── Entity/
│   │   └── Account.php
│   └── DTO/
│       └── AccountDTO.php
├── tests/
│   ├── Controller/
│   │   └── AccountControllerTest.php
│   ├── Service/
│   │   └── AccountServiceTest.php
│   └── Repository/
│       └── AccountRepositoryTest.php
├── config/
├── .env
├── composer.json
├── Dockerfile
├── phpstan.neon
└── phpunit.xml
```

---

### Transaction Service (`services/transaction-service/`)

Initiates and tracks fund transfer transactions. Calls Account Service to validate/update balances and calls Ledger Service to record entries.

#### Layer Breakdown

| Layer | Class | Responsibility |
|---|---|---|
| Controller | `HealthController` | `GET /health` |
| Controller | `TransactionController` | Initiate and query transactions |
| Service | `TransactionService` | Orchestrates transfer logic |
| Repository Interface | `TransactionRepositoryInterface` | Contract for transaction data access |
| Repository | `TransactionRepository` | Doctrine/MySQL implementation |
| Factory | `TransactionFactory` | Creates `Transaction` entities |
| Entity | `Transaction` | Doctrine ORM entity |
| DTO | `TransactionDTO` | Request/response DTO |

#### Directory Structure

```
services/transaction-service/
├── src/
│   ├── Controller/
│   │   ├── HealthController.php
│   │   └── TransactionController.php
│   ├── Service/
│   │   └── TransactionService.php
│   ├── Repository/
│   │   ├── TransactionRepositoryInterface.php
│   │   └── TransactionRepository.php
│   ├── Factory/
│   │   └── TransactionFactory.php
│   ├── Entity/
│   │   └── Transaction.php
│   └── DTO/
│       └── TransactionDTO.php
├── tests/
│   ├── Controller/
│   │   └── TransactionControllerTest.php
│   ├── Service/
│   │   └── TransactionServiceTest.php
│   └── Repository/
│       └── TransactionRepositoryTest.php
├── config/
├── .env
├── composer.json
├── Dockerfile
├── phpstan.neon
└── phpunit.xml
```

---

### Ledger Service (`services/ledger-service/`)

Records immutable financial ledger entries (debit/credit) and supports querying by account.

#### Layer Breakdown

| Layer | Class | Responsibility |
|---|---|---|
| Controller | `HealthController` | `GET /health` |
| Controller | `LedgerController` | Record and query ledger entries |
| Service | `LedgerService` | Business logic for ledger operations |
| Repository Interface | `LedgerEntryRepositoryInterface` | Contract for ledger data access |
| Repository | `LedgerEntryRepository` | Doctrine/MySQL implementation |
| Factory | `LedgerEntryFactory` | Creates `LedgerEntry` entities |
| Entity | `LedgerEntry` | Doctrine ORM entity |
| DTO | `LedgerEntryDTO` | Request/response DTO |

#### Directory Structure

```
services/ledger-service/
├── src/
│   ├── Controller/
│   │   ├── HealthController.php
│   │   └── LedgerController.php
│   ├── Service/
│   │   └── LedgerService.php
│   ├── Repository/
│   │   ├── LedgerEntryRepositoryInterface.php
│   │   └── LedgerEntryRepository.php
│   ├── Factory/
│   │   └── LedgerEntryFactory.php
│   ├── Entity/
│   │   └── LedgerEntry.php
│   └── DTO/
│       └── LedgerEntryDTO.php
├── tests/
│   ├── Controller/
│   │   └── LedgerControllerTest.php
│   ├── Service/
│   │   └── LedgerServiceTest.php
│   └── Repository/
│       └── LedgerEntryRepositoryTest.php
├── config/
├── .env
├── composer.json
├── Dockerfile
├── phpstan.neon
└── phpunit.xml
```

---

## Data Models

### User Entity (`user-service`)

```php
/**
 * @ORM\Entity
 * @ORM\Table(name="users")
 */
class User
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private int $id;

    /** @ORM\Column(type="string", length=100) */
    private string $name;

    /** @ORM\Column(type="string", length=255, unique=true) */
    private string $email;

    /** @ORM\Column(type="datetime_immutable") */
    private \DateTimeImmutable $createdAt;
}
```

**MySQL table: `users`**

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| name | VARCHAR(100) | |
| email | VARCHAR(255) UNIQUE | |
| created_at | DATETIME | |

---

### Account Entity (`account-service`)

```php
/**
 * @ORM\Entity
 * @ORM\Table(name="accounts")
 */
class Account
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private int $id;

    /** @ORM\Column(type="integer") */
    private int $userId;

    /** @ORM\Column(type="decimal", precision=15, scale=2) */
    private string $balance;

    /** @ORM\Column(type="string", length=3) */
    private string $currency;

    /** @ORM\Column(type="datetime_immutable") */
    private \DateTimeImmutable $createdAt;
}
```

**MySQL table: `accounts`**

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| user_id | INT | FK reference (logical, not enforced cross-service) |
| balance | DECIMAL(15,2) | |
| currency | CHAR(3) | ISO 4217 |
| created_at | DATETIME | |

---

### Transaction Entity (`transaction-service`)

```php
/**
 * @ORM\Entity
 * @ORM\Table(name="transactions")
 */
class Transaction
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private int $id;

    /** @ORM\Column(type="integer") */
    private int $sourceAccountId;

    /** @ORM\Column(type="integer") */
    private int $destinationAccountId;

    /** @ORM\Column(type="decimal", precision=15, scale=2) */
    private string $amount;

    /** @ORM\Column(type="string", length=3) */
    private string $currency;

    /** @ORM\Column(type="string", length=20) */
    private string $status;  // pending | completed | failed

    /** @ORM\Column(type="datetime_immutable") */
    private \DateTimeImmutable $createdAt;
}
```

**MySQL table: `transactions`**

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| source_account_id | INT | |
| destination_account_id | INT | |
| amount | DECIMAL(15,2) | |
| currency | CHAR(3) | |
| status | VARCHAR(20) | pending / completed / failed |
| created_at | DATETIME | |

---

### LedgerEntry Entity (`ledger-service`)

```php
/**
 * @ORM\Entity
 * @ORM\Table(name="ledger_entries")
 */
class LedgerEntry
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private int $id;

    /** @ORM\Column(type="integer") */
    private int $transactionId;

    /** @ORM\Column(type="integer") */
    private int $accountId;

    /** @ORM\Column(type="string", length=6) */
    private string $entryType;  // debit | credit

    /** @ORM\Column(type="decimal", precision=15, scale=2) */
    private string $amount;

    /** @ORM\Column(type="datetime_immutable") */
    private \DateTimeImmutable $createdAt;
}
```

**MySQL table: `ledger_entries`**

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| transaction_id | INT | Logical FK to transactions |
| account_id | INT | Logical FK to accounts |
| entry_type | VARCHAR(6) | debit / credit |
| amount | DECIMAL(15,2) | |
| created_at | DATETIME | |

---

### Inter-Service Communication

Services communicate synchronously over the internal Docker network using Symfony's `HttpClient` component. There are no message queues in this initial design.

```
Transaction Service
  │
  ├─ POST http://account-service/account/{id}/debit   (deduct from source)
  ├─ POST http://account-service/account/{id}/credit  (add to destination)
  └─ POST http://ledger-service/ledger/entry          (record both legs)
```

The API Gateway uses `HttpClient` to forward requests to the appropriate downstream service based on the path prefix, stripping the prefix before forwarding.

**Design Decision**: Synchronous HTTP was chosen over async messaging (e.g., RabbitMQ) to keep the initial scaffold simple and dependency-free. The repository and service layers are designed so that an async adapter can be substituted later without changing business logic.

---

## Infrastructure

### Root Project Structure

```
fund-transfer-system/
├── services/
│   ├── api-gateway/
│   ├── user-service/
│   ├── account-service/
│   ├── transaction-service/
│   └── ledger-service/
├── infrastructure/
│   ├── docker/
│   └── nginx/
│       └── default.conf
├── shared/
│   └── php-common/
│       ├── src/
│       └── composer.json
├── docker-compose.yml
└── README.md
```

### NGINX Configuration (`infrastructure/nginx/default.conf`)

```nginx
server {
    listen 80;

    location /api {
        proxy_pass http://api-gateway:9000;
    }
    location /user {
        proxy_pass http://user-service:9001;
    }
    location /account {
        proxy_pass http://account-service:9002;
    }
    location /transaction {
        proxy_pass http://transaction-service:9003;
    }
    location /ledger {
        proxy_pass http://ledger-service:9004;
    }
}
```

### Dockerfile Pattern (per service)

```dockerfile
FROM php:8.3-fpm

RUN docker-php-ext-install pdo pdo_mysql intl opcache \
 && pecl install redis && docker-php-ext-enable redis

WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader

EXPOSE 9000
CMD ["php-fpm"]
```

### docker-compose.yml (outline)

```yaml
services:
  nginx:
    image: nginx:latest
    ports: ["80:80"]
    volumes:
      - ./infrastructure/nginx/default.conf:/etc/nginx/conf.d/default.conf
    networks: [app-network]

  api-gateway:
    build: ./services/api-gateway
    networks: [app-network]

  user-service:
    build: ./services/user-service
    networks: [app-network]

  account-service:
    build: ./services/account-service
    networks: [app-network]

  transaction-service:
    build: ./services/transaction-service
    networks: [app-network]

  ledger-service:
    build: ./services/ledger-service
    networks: [app-network]

  mysql:
    image: mysql:latest
    environment:
      MYSQL_ROOT_PASSWORD: root
    networks: [app-network]

  redis:
    image: redis:latest
    networks: [app-network]

networks:
  app-network:
    driver: bridge
```

---

## Key Design Decisions

1. **Repository Interface Segregation**: Every service defines its own repository interface (e.g., `UserRepositoryInterface`). Service classes depend only on the interface, never the concrete Doctrine implementation. This makes unit testing trivial — mock the interface, inject it, done.

2. **Factory for Entity Creation**: All entity instantiation goes through a Factory (e.g., `UserFactory::create(array $data): User`). This centralises validation and construction logic, and keeps controllers thin.

3. **PHPDoc on Everything**: Every class, method, and property carries a PHPDoc block. Controller methods include `@Route`, `@param`, `@return`, and a description. This is enforced by PHPStan level 1 and supports `nelmio/api-doc-bundle` or OpenAPI generation.

4. **PHPStan Level 1**: Level 1 catches the most impactful issues (undefined variables, wrong types on obvious calls) without requiring full generics annotations. It is the right starting point for a new project.

5. **Shared Library via Composer Path Repository**: `shared/php-common/` is included in each service's `composer.json` as a path repository, keeping shared code DRY without publishing to Packagist.

6. **No Cross-Service Database Joins**: Each service owns its own MySQL schema (separate database or separate tables with a service prefix). Foreign key relationships across services are logical only — enforced at the application layer, not the database layer.

7. **Health Endpoint Uniformity**: Every service exposes `GET /health` returning `{"status":"ok"}` with HTTP 200. This is the contract used by Docker health checks and monitoring tools.


---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

The following properties were derived from the acceptance criteria. Infrastructure-level criteria (Docker build, NGINX routing, .env files) are classified as smoke or integration tests and do not yield property-based tests. The properties below target the structural and architectural invariants that hold across all entities, services, and test classes in the system.

---

### Property 1: Every entity has companion repository interface, repository implementation, and factory

*For any* entity class found in a service's `Entity/` directory, there SHALL exist a corresponding repository interface (named `{Entity}RepositoryInterface`), a concrete repository implementation (named `{Entity}Repository`), and a factory class (named `{Entity}Factory`) in the same service.

**Validates: Requirements 8.1, 8.4**

---

### Property 2: Service classes depend only on repository interfaces

*For any* service class in a service's `Service/` directory, all constructor-injected dependencies that are repositories SHALL be typed against an interface (i.e., the type hint ends in `Interface` or resolves to a PHP `interface` declaration), never against a concrete repository class.

**Validates: Requirements 8.2, 8.3**

---

### Property 3: All public classes, methods, and properties carry PHPDoc blocks

*For any* public class, public method, or public property declared in any service's `src/` directory, a PHPDoc comment block (`/** ... */`) SHALL be present immediately before the declaration.

**Validates: Requirements 9.1**

---

### Property 4: Controller route methods include required PHPDoc tags

*For any* controller method annotated with `@Route` (or the `#[Route]` attribute), the associated PHPDoc block SHALL contain at minimum one `@param` tag (or note that no parameters exist), one `@return` tag, and a prose description of the endpoint's purpose.

**Validates: Requirements 9.3**

---

### Property 5: Every service and repository class has a test file that uses mocks and covers all public methods

*For any* class in a service's `Service/` or `Repository/` directory, there SHALL exist a corresponding `*Test.php` file in the service's `tests/` directory. That test file SHALL contain at least one call to `createMock()` or `getMockBuilder()`, and SHALL contain at least one test method (prefixed `test` or annotated `@test`) for every public method of the class under test.

**Validates: Requirements 10.1, 10.3, 10.4**

---

## Error Handling

### HTTP Error Responses

All controllers return JSON error responses in a consistent envelope:

```json
{
  "error": "Human-readable message",
  "code": 404
}
```

| Scenario | HTTP Status | Notes |
|---|---|---|
| Entity not found | 404 | Repository returns `null`, service throws `NotFoundException` |
| Validation failure | 422 | Invalid request body fields |
| Downstream service unreachable | 502 | API Gateway / Transaction Service HttpClient failure |
| Unexpected server error | 500 | Caught by Symfony's exception listener |

### Service-Level Error Handling

- Repository methods return `null` for not-found cases; services convert `null` to a thrown `NotFoundException` from `shared/php-common/`.
- Transaction Service wraps the account debit + credit + ledger record sequence in a try/catch. On any failure, the transaction status is set to `failed` and the error is logged. Partial state (e.g., debit succeeded but credit failed) is surfaced as a `failed` transaction — compensating transactions are out of scope for this initial design.
- All exceptions are caught at the controller layer and serialized to the JSON error envelope above.

### Health Endpoint

The `/health` endpoint never returns an error status. It is intentionally simple — if the PHP-FPM process is alive, it returns `{"status":"ok"}`. Infrastructure-level health (DB connectivity) is out of scope for this endpoint.

---

## Testing Strategy

### Dual Testing Approach

The system uses two complementary layers of testing:

1. **Unit tests (PHPUnit)** — verify specific behavior with mocked dependencies. Every service class, repository class, and controller has a corresponding test class.
2. **Property-based structural tests (PHPUnit + reflection)** — verify architectural invariants (Properties 1–5 above) across all classes in all services. These use PHP Reflection API to enumerate classes and assert structural rules hold universally.

### Unit Testing

- Framework: **PHPUnit** (configured via `phpunit.xml` per service)
- All repository and service dependencies are mocked using `createMock()` or `getMockBuilder()`
- Each test class covers: happy path, edge cases (empty collections, zero amounts), and error/exception scenarios
- Minimum: one test method per public method on every service and repository class

### Property-Based Structural Tests

PHPUnit is used to implement the structural properties (1–5) via reflection-based assertions. These are not randomized input tests — they are universal assertions over the set of all classes in the codebase.

Each property test:
- Discovers all relevant classes via `RecursiveDirectoryIterator` over `src/`
- Asserts the invariant holds for every discovered class
- Is tagged with a comment referencing the design property

Example tag format:
```php
// Feature: fund-transfer-system, Property 1: Every entity has companion repository interface, repository implementation, and factory
```

Minimum 1 assertion per discovered class (which scales with the number of entities/services added).

### Static Analysis

- **PHPStan level 1** configured via `phpstan.neon` in each service root
- Run as: `vendor/bin/phpstan analyse src/`
- Must produce zero errors on a freshly initialized service

### Integration Tests (Manual / CI)

The following are verified as integration tests (not unit tests):

| Scenario | Method |
|---|---|
| `docker-compose build` succeeds | CI pipeline |
| All services start and respond on internal network | `docker-compose up` + curl |
| NGINX routes `/api/*`, `/user/*`, etc. to correct service | curl against `http://localhost/{prefix}/health` |
| PHPStan exits 0 for each service | CI step per service |
| PHPUnit exits 0 for each service | CI step per service |

### Test Configuration Files

Each service includes:
- `phpunit.xml` — bootstrap, test suite directory (`tests/`), coverage source (`src/`)
- `phpstan.neon` — level 1, paths: `[src]`
