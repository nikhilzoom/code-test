# Implementation Plan: JWT Authentication

## Overview

Implement JWT-based authentication centralised at the API Gateway. The User Service issues tokens; the gateway validates them on every protected request and injects the authenticated userId as a trusted header before forwarding to downstream services.

## Tasks

- [x] 1. Add JWT dependencies to shared library and all services
  - [x] 1.1 Update `shared/php-common/composer.json` to require `firebase/php-jwt: ^6.10`
  - [x] 1.2 Add `firebase/php-jwt: ^6.10` to `require` in `services/user-service/composer.json`
  - [x] 1.3 Add `firebase/php-jwt: ^6.10` to `require` in `services/api-gateway/composer.json`
  - [x] 1.4 Add `firebase/php-jwt: ^6.10` to `require` in `services/account-service/composer.json`
  - [x] 1.5 Add `firebase/php-jwt: ^6.10` to `require` in `services/transaction-service/composer.json`
  - [x] 1.6 Add `firebase/php-jwt: ^6.10` to `require` in `services/ledger-service/composer.json`
  - _Requirements: 7.5, 7.6_

- [x] 2. Add shared JWT classes to `shared/php-common`
  - [x] 2.1 Create `shared/php-common/src/Exception/AuthenticationException.php`
    - Extend `\RuntimeException`; full PHPDoc
    - _Requirements: 7.4_
  - [x] 2.2 Create `shared/php-common/src/Security/JwtService.php`
    - Constructor accepts `string $secret`
    - `encode(array $payload): string` — merges `iat` and `exp` (now + 3600), calls `Firebase\JWT\JWT::encode()` with `HS256`
    - `decode(string $token): object` — calls `Firebase\JWT\JWT::decode()`, catches all Firebase JWT exceptions, rethrows as `AuthenticationException`
    - Full PHPDoc on class and all public methods
    - _Requirements: 7.1, 7.2, 7.3, 7.4_

- [x] 3. Add `password` field to User entity and migration
  - [x] 3.1 Modify `services/user-service/src/Entity/User.php`
    - Add `private string $password` with `#[ORM\Column(type: 'string', length: 255)]`
    - Add `getPassword(): string` and `setPassword(string $password): void` with full PHPDoc
    - _Requirements: 1.3, 5.1, 5.2_
  - [x] 3.2 Modify `services/user-service/src/Factory/UserFactory.php`
    - In `create()`, if `password` key exists in `$data`, call `$user->setPassword((string) $data['password'])`
    - _Requirements: 1.2_
  - [x] 3.3 Create `services/user-service/migrations/Version20260411000002.php`
    - `up()`: `ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL DEFAULT '' AFTER email`
    - `down()`: `ALTER TABLE users DROP COLUMN password`
    - Full PHPDoc
    - _Requirements: 1.3_

- [x] 4. Implement `AuthService` in User Service
  - Create `services/user-service/src/Service/AuthService.php`
  - Constructor-inject `UserRepositoryInterface`, `UserFactory`, and `JwtService` (from shared lib)
  - `register(array $data): User`:
    - Validate `name`, `email`, `password` keys present
    - Call `UserRepositoryInterface::findByEmail()` — if found throw `\RuntimeException('Email already registered.')`
    - Hash password: `password_hash($data['password'], PASSWORD_BCRYPT)`
    - Call `UserFactory::create(['name'=>..., 'email'=>..., 'password'=>$hash])`
    - Call `UserRepositoryInterface::save()`, return User
  - `login(array $data): string`:
    - Validate `email`, `password` keys present
    - Call `UserRepositoryInterface::findByEmail()` — if null throw `AuthenticationException('Invalid credentials.')`
    - Call `password_verify()` — if false throw `AuthenticationException('Invalid credentials.')`
    - Call `JwtService::encode(['sub' => $user->getId(), 'email' => $user->getEmail()])`, return token string
  - Full PHPDoc on class and all public methods
  - _Requirements: 1.1–1.6, 2.1–2.6, 5.1–5.4_

- [x] 5. Implement `AuthController` in User Service
  - Create `services/user-service/src/Controller/AuthController.php`
  - Constructor-inject `AuthService`
  - `POST /user/register` → `register(Request $request): JsonResponse`
    - Decode JSON body; return 400 on invalid JSON
    - Call `AuthService::register()`
    - On `\RuntimeException` with message 'Email already registered.' → return 409
    - On success → return 201 `{id, name, email, createdAt}` (no password field)
  - `POST /user/login` → `login(Request $request): JsonResponse`
    - Decode JSON body; return 400 on invalid JSON
    - Call `AuthService::login()`
    - On `AuthenticationException` → return 401
    - On success → return 200 `{"token": "eyJ..."}`
  - Full PHPDoc with `@Route`, `@param`, `@return`, description, request/response format on each action
  - _Requirements: 1.1, 1.4, 1.5, 1.6, 2.1, 2.2, 2.5, 2.6_

- [x] 6. Wire `JwtService` in User Service
  - Create or update `services/user-service/config/services.yaml`
  - Register `PhpCommon\Security\JwtService` with argument `$secret: '%env(JWT_SECRET)%'`
  - Add `JWT_SECRET` to `services/user-service/.env` with a placeholder value
  - _Requirements: 2.4, 7.3_

- [x] 7. Implement `JwtAuthSubscriber` in API Gateway
  - Create `services/api-gateway/src/EventSubscriber/JwtAuthSubscriber.php`
  - Implement `EventSubscriberInterface`; subscribe to `KernelEvents::REQUEST` at priority 10
  - Constructor-inject `JwtService`
  - Public routes (no auth): `POST /user/register`, `POST /user/login`, any path ending in `/health`
  - `onKernelRequest(RequestEvent $event)`:
    1. Skip if not main request
    2. If public route → return early
    3. Extract `Authorization` header; if missing → set `JsonResponse({'error':'Authorization header missing.','code':401}, 401)` on event and return
    4. Strip `Bearer ` prefix; call `JwtService::decode()`
    5. On `AuthenticationException` → set `JsonResponse({'error':'Invalid or expired token.','code':401}, 401)` on event and return
    6. Extract `sub` from decoded payload → set `X-Authenticated-User-Id` header on request
    7. Remove `Authorization` header from request
  - Full PHPDoc on class and all public methods
  - _Requirements: 3.1–3.7, 4.1, 4.3, 4.4_

- [x] 8. Wire `JwtService` and `JwtAuthSubscriber` in API Gateway
  - Create or update `services/api-gateway/config/services.yaml`
  - Register `PhpCommon\Security\JwtService` with argument `$secret: '%env(JWT_SECRET)%'`
  - Register `App\EventSubscriber\JwtAuthSubscriber` with tag `kernel.event_subscriber`
  - Add `JWT_SECRET` to `services/api-gateway/.env` with the same placeholder value as user-service
  - _Requirements: 3.7, 7.3_

- [x] 9. Update `docker-compose.yml`
  - Add `JWT_SECRET: "super-secret-jwt-key-change-in-production"` to the `environment` block of `user-service` and `api-gateway`
  - _Requirements: 2.4, 3.7_

- [x] 10. Write unit tests for `JwtService`
  - Create `shared/php-common/tests/Security/JwtServiceTest.php` (or place in user-service tests)
  - Test: `encode()` returns a non-empty string, `decode()` returns object with correct `sub`, `decode()` throws `AuthenticationException` on tampered token, `decode()` throws `AuthenticationException` on expired token
  - _Requirements: 9.3_

- [x] 11. Write unit tests for `AuthService`
  - Create `services/user-service/tests/Service/AuthServiceTest.php`
  - Mock `UserRepositoryInterface`, `UserFactory`, `JwtService`
  - Test: `register()` happy path returns User, `register()` throws on duplicate email, `login()` happy path returns token string, `login()` throws `AuthenticationException` on wrong password, `login()` throws `AuthenticationException` on unknown email
  - _Requirements: 9.1_

- [x] 12. Write unit tests for `AuthController`
  - Create `services/user-service/tests/Controller/AuthControllerTest.php`
  - Mock `AuthService`
  - Test: register 201, register 409 on duplicate, register 400 on bad JSON, login 200 with token, login 401 on bad credentials, login 400 on bad JSON
  - _Requirements: 9.1_

- [x] 13. Write unit tests for `JwtAuthSubscriber`
  - Create `services/api-gateway/tests/EventSubscriber/JwtAuthSubscriberTest.php`
  - Mock `JwtService`
  - Test: valid token → request has `X-Authenticated-User-Id` header and no `Authorization` header, missing header → event response is 401, invalid token → event response is 401, public route (register) → passes through, public route (login) → passes through, health route → passes through
  - _Requirements: 9.2_

- [x] 14. Update `infrastructure/swagger/openapi.yaml`
  - Add `POST /user/register` and `POST /user/login` path entries with request/response schemas
  - Add `RegisterRequest` schema (`name`, `email`, `password`) and `LoginRequest` schema (`email`, `password`) and `TokenResponse` schema (`token`)
  - Add `BearerAuth` security scheme under `components/securitySchemes`
  - Add `security: [{BearerAuth: []}]` to all protected endpoints
  - Add `security: []` to health endpoints, `/user/register`, and `/user/login`
  - _Requirements: 8.1–8.4_

## Notes

- `JWT_SECRET` must be identical in `user-service` and `api-gateway` — they share the same signing key
- Downstream services (account, transaction, ledger) require no changes for auth — they trust the gateway
- The `password` field must never appear in any JSON response; controllers must explicitly omit it
- The migration adds `password` as `NOT NULL DEFAULT ''` to avoid breaking existing rows; in production a proper data migration would be required
- Service-to-service calls (Transaction → Account → Ledger) bypass NGINX and go directly over the Docker network, so they are not subject to gateway JWT validation
