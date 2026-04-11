# Requirements Document

## Introduction

A multi-service PHP/Symfony fund transfer system composed of five independently deployable microservices (API Gateway, User Service, Account Service, Transaction Service, Ledger Service), orchestrated via Docker Compose, proxied through NGINX, and backed by MySQL and Redis. Each service exposes a health endpoint and is configured for code quality tooling (PHPStan, PHPUnit).

## Glossary

- **System**: The complete fund-transfer-system platform
- **API_Gateway**: The Symfony service that acts as the single entry point for all external requests, routing to downstream services
- **User_Service**: The Symfony service responsible for user-related operations
- **Account_Service**: The Symfony service responsible for account management operations
- **Transaction_Service**: The Symfony service responsible for fund transfer transaction operations
- **Ledger_Service**: The Symfony service responsible for recording and querying financial ledger entries
- **NGINX**: The reverse proxy that routes incoming HTTP requests to the appropriate service
- **Docker_Compose**: The container orchestration tool used to build and run all services together
- **PHP_FPM**: PHP FastCGI Process Manager used as the runtime for each Symfony service
- **Health_Endpoint**: A `/health` HTTP GET endpoint returning `{"status": "ok"}` to indicate service availability
- **Shared_Common**: The `shared/php-common/` directory containing reusable PHP code shared across services
- **PHPStan**: A static analysis tool for PHP configured at a basic level for each service
- **PHPUnit**: The unit testing framework configured for each service

---

## Requirements

### Requirement 1: Project Structure

**User Story:** As a developer, I want a well-defined folder structure, so that each microservice is isolated and the project is easy to navigate.

#### Acceptance Criteria

1. THE System SHALL contain a root folder named `fund-transfer-system` with the following subfolders: `services/api-gateway/`, `services/user-service/`, `services/account-service/`, `services/transaction-service/`, `services/ledger-service/`, `infrastructure/docker/`, `infrastructure/nginx/`, and `shared/php-common/`.
2. THE System SHALL place each Symfony application exclusively within its corresponding `services/{service-name}/` subfolder.
3. THE System SHALL place all NGINX configuration files within `infrastructure/nginx/`.
4. THE System SHALL place shared reusable PHP code within `shared/php-common/`.

---

### Requirement 2: Symfony Service Initialization

**User Story:** As a developer, I want each service to be a fresh, independently runnable Symfony application, so that services can be developed, tested, and deployed in isolation.

#### Acceptance Criteria

1. THE API_Gateway SHALL be initialized as a Symfony application using the latest stable Symfony version.
2. THE User_Service SHALL be initialized as a Symfony application using the latest stable Symfony version.
3. THE Account_Service SHALL be initialized as a Symfony application using the latest stable Symfony version.
4. THE Transaction_Service SHALL be initialized as a Symfony application using the latest stable Symfony version.
5. THE Ledger_Service SHALL be initialized as a Symfony application using the latest stable Symfony version.
6. WHEN a GET request is made to `/health`, THE API_Gateway SHALL return a JSON response `{"status": "ok"}` with HTTP status 200.
7. WHEN a GET request is made to `/health`, THE User_Service SHALL return a JSON response `{"status": "ok"}` with HTTP status 200.
8. WHEN a GET request is made to `/health`, THE Account_Service SHALL return a JSON response `{"status": "ok"}` with HTTP status 200.
9. WHEN a GET request is made to `/health`, THE Transaction_Service SHALL return a JSON response `{"status": "ok"}` with HTTP status 200.
10. WHEN a GET request is made to `/health`, THE Ledger_Service SHALL return a JSON response `{"status": "ok"}` with HTTP status 200.
11. THE API_Gateway SHALL include `symfony/runtime` and `symfony/framework-bundle` as Composer dependencies.
12. THE User_Service SHALL include `symfony/runtime` and `symfony/framework-bundle` as Composer dependencies.
13. THE Account_Service SHALL include `symfony/runtime` and `symfony/framework-bundle` as Composer dependencies.
14. THE Transaction_Service SHALL include `symfony/runtime` and `symfony/framework-bundle` as Composer dependencies.
15. THE Ledger_Service SHALL include `symfony/runtime` and `symfony/framework-bundle` as Composer dependencies.

---

### Requirement 3: Docker Configuration

**User Story:** As a developer, I want each service containerized with PHP-FPM, so that the environment is consistent and reproducible across machines.

#### Acceptance Criteria

1. THE System SHALL provide a `Dockerfile` for each of the five services using the latest stable PHP-FPM base image.
2. THE System SHALL install the following PHP extensions in each service's Docker image: `pdo`, `pdo_mysql`, `redis`, `intl`, `opcache`.
3. THE System SHALL provide a root-level `docker-compose.yml` defining the following services: `api-gateway`, `user-service`, `account-service`, `transaction-service`, `ledger-service`, `mysql` (latest image), `redis` (latest image), and `nginx`.
4. THE Docker_Compose SHALL configure all services on a shared internal Docker network.
5. THE Docker_Compose SHALL expose the `nginx` service on host port 80.
6. WHEN `docker-compose build` is executed, THE Docker_Compose SHALL build all service images without error.
7. WHEN `docker-compose up` is executed, THE Docker_Compose SHALL start all services and make them reachable on the internal network.

---

### Requirement 4: NGINX Reverse Proxy

**User Story:** As a developer, I want NGINX to route requests to the correct service by path prefix, so that all services are accessible through a single host port.

#### Acceptance Criteria

1. WHEN a request is received with a path beginning with `/api`, THE NGINX SHALL proxy the request to the `api-gateway` service.
2. WHEN a request is received with a path beginning with `/user`, THE NGINX SHALL proxy the request to the `user-service` service.
3. WHEN a request is received with a path beginning with `/account`, THE NGINX SHALL proxy the request to the `account-service` service.
4. WHEN a request is received with a path beginning with `/transaction`, THE NGINX SHALL proxy the request to the `transaction-service` service.
5. WHEN a request is received with a path beginning with `/ledger`, THE NGINX SHALL proxy the request to the `ledger-service` service.
6. THE NGINX SHALL listen on port 80 for all incoming HTTP requests.

---

### Requirement 5: Environment Configuration

**User Story:** As a developer, I want each service to have its own `.env` file with shared infrastructure variables, so that services can connect to MySQL and Redis without hardcoded values.

#### Acceptance Criteria

1. THE System SHALL provide a `.env` file within each service directory.
2. THE API_Gateway `.env` file SHALL contain `DB_HOST=mysql` and `REDIS_HOST=redis`.
3. THE User_Service `.env` file SHALL contain `DB_HOST=mysql` and `REDIS_HOST=redis`.
4. THE Account_Service `.env` file SHALL contain `DB_HOST=mysql` and `REDIS_HOST=redis`.
5. THE Transaction_Service `.env` file SHALL contain `DB_HOST=mysql` and `REDIS_HOST=redis`.
6. THE Ledger_Service `.env` file SHALL contain `DB_HOST=mysql` and `REDIS_HOST=redis`.

---

### Requirement 6: Code Quality Tooling

**User Story:** As a developer, I want PHPStan and PHPUnit configured in each service, so that static analysis and unit tests can be run consistently.

#### Acceptance Criteria

1. THE System SHALL provide a `phpstan.neon` configuration file at the root of each service directory configured at analysis level 1 (basic).
2. THE System SHALL provide a `phpunit.xml` (or `phpunit.xml.dist`) configuration file at the root of each service directory.
3. WHEN PHPStan is executed against a service's source directory, THE System SHALL produce no errors on a freshly initialized Symfony application.
4. WHEN PHPUnit is executed for a service, THE System SHALL run the test suite without configuration errors.

---

### Requirement 7: Documentation

**User Story:** As a developer, I want a README with setup and usage instructions, so that any team member can get the system running quickly.

#### Acceptance Criteria

1. THE System SHALL provide a `README.md` at the root of the project.
2. THE README.md SHALL include the command `docker-compose build` as the build step.
3. THE README.md SHALL include the command `docker-compose up` as the start step.
4. THE README.md SHALL list the URL for each service accessible through NGINX (e.g., `http://localhost/api`, `http://localhost/user`, `http://localhost/account`, `http://localhost/transaction`, `http://localhost/ledger`).
5. THE README.md SHALL include the health check URL for each service (e.g., `http://localhost/api/health`).

---

### Requirement 8: Repository Pattern & Factory Pattern

**User Story:** As a developer, I want all data access and object creation to follow established design patterns, so that the codebase is consistent, testable, and maintainable.

#### Acceptance Criteria

1. THE System SHALL provide a repository interface and a corresponding implementation for each entity in every service.
2. THE System SHALL ensure every service class depends on repository interfaces, not on concrete repository implementations.
3. WHEN a service requires data access, THE System SHALL route all data access logic through the repository layer.
4. WHERE object creation logic is non-trivial or requires abstraction, THE System SHALL use the Factory pattern to instantiate domain objects, DTOs, and complex value objects.

---

### Requirement 9: Code Documentation & Annotations

**User Story:** As a developer, I want all classes, methods, and properties to be fully annotated, so that API documentation can be generated automatically and the codebase is self-documenting.

#### Acceptance Criteria

1. THE System SHALL provide PHPDoc annotations on all classes, methods, and properties across every service.
2. THE System SHALL ensure PHPDoc annotations contain sufficient detail to support automatic API documentation generation using tools such as OpenAPI or Swagger.
3. WHEN a public API endpoint controller method is defined, THE System SHALL include a `@Route` annotation, `@param` and `@return` tags, a description of the endpoint's purpose, and the expected request and response formats.

---

### Requirement 10: Unit Testing

**User Story:** As a developer, I want every feature covered by PHPUnit tests, so that regressions are caught early and code correctness is verifiable.

#### Acceptance Criteria

1. THE System SHALL provide PHPUnit test cases for every feature implemented across all services.
2. THE System SHALL include test cases covering the happy path, edge cases, and error or exception scenarios for each feature.
3. THE System SHALL test all repository and service classes using mocked dependencies.
4. THE System SHALL provide at least one test for every public method across all service and repository classes.
