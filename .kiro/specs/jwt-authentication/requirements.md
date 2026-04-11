# Requirements Document

## Introduction

Add JWT-based authentication to the Fund Transfer System. The API Gateway becomes the single authentication enforcement point — it validates every inbound JWT before forwarding requests to downstream services. The User Service issues tokens on login and register. Downstream services (Account, Transaction, Ledger) trust requests that arrive from the gateway and do not perform their own token validation.

## Glossary

- **JWT**: JSON Web Token — a signed, self-contained token carrying claims about the authenticated user
- **HS256**: HMAC-SHA256 symmetric signing algorithm used to sign and verify JWTs
- **JWT_SECRET**: A shared secret environment variable used by the User Service to sign tokens and by the API Gateway to verify them
- **API_Gateway**: The Symfony service that acts as the single external entry point; responsible for JWT validation
- **User_Service**: The Symfony service that handles registration, login, and issues JWTs
- **Downstream_Services**: Account Service, Transaction Service, and Ledger Service — they receive only pre-authenticated requests from the gateway
- **Public_Route**: An endpoint that does not require a valid JWT (health checks, register, login)
- **Protected_Route**: Any endpoint that requires a valid JWT in the `Authorization: Bearer` header
- **userId claim**: The `sub` field in the JWT payload containing the authenticated user's integer ID
- **Internal_Header**: `X-Authenticated-User-Id` — a header the gateway injects into forwarded requests carrying the validated userId

---

## Requirements

### Requirement 1: User Registration

**User Story:** As a new user, I want to register with my name, email, and password so that I can obtain a JWT to access the system.

#### Acceptance Criteria

1. THE User_Service SHALL expose `POST /user/register` accepting a JSON body with `name`, `email`, and `password` fields.
2. WHEN a registration request is received, THE User_Service SHALL hash the password using `password_hash()` with `PASSWORD_BCRYPT` before persisting it.
3. THE User_Service SHALL store the hashed password in a `password` column on the `users` table.
4. WHEN registration succeeds, THE User_Service SHALL return HTTP 201 with the created user's `id`, `name`, `email`, and `createdAt` (password is never returned).
5. WHEN the email is already registered, THE User_Service SHALL return HTTP 409 with `{"error": "Email already registered.", "code": 409}`.
6. WHEN required fields are missing or the JSON body is invalid, THE User_Service SHALL return HTTP 400.

---

### Requirement 2: User Login and JWT Issuance

**User Story:** As a registered user, I want to log in with my email and password so that I receive a JWT I can use to call protected endpoints.

#### Acceptance Criteria

1. THE User_Service SHALL expose `POST /user/login` accepting a JSON body with `email` and `password` fields.
2. WHEN login credentials are valid, THE User_Service SHALL return HTTP 200 with a JSON body containing a `token` field holding a signed HS256 JWT.
3. THE JWT payload SHALL contain: `sub` (integer userId), `email` (string), `iat` (issued-at Unix timestamp), `exp` (expiry Unix timestamp, 1 hour from issuance).
4. THE JWT SHALL be signed using the `JWT_SECRET` environment variable shared between User Service and API Gateway.
5. WHEN the email does not exist or the password does not match, THE User_Service SHALL return HTTP 401 with `{"error": "Invalid credentials.", "code": 401}`.
6. WHEN required fields are missing, THE User_Service SHALL return HTTP 400.

---

### Requirement 3: JWT Validation at the API Gateway

**User Story:** As a system operator, I want the API Gateway to validate JWTs on every protected request so that downstream services never receive unauthenticated traffic.

#### Acceptance Criteria

1. THE API_Gateway SHALL intercept every inbound HTTP request via a `kernel.request` event subscriber before routing.
2. FOR every request whose path is not a public route, THE API_Gateway SHALL require an `Authorization: Bearer <token>` header.
3. WHEN the `Authorization` header is absent on a protected route, THE API_Gateway SHALL return HTTP 401 with `{"error": "Authorization header missing.", "code": 401}` and SHALL NOT forward the request.
4. WHEN the JWT signature is invalid, expired, or malformed, THE API_Gateway SHALL return HTTP 401 with `{"error": "Invalid or expired token.", "code": 401}` and SHALL NOT forward the request.
5. WHEN the JWT is valid, THE API_Gateway SHALL extract the `sub` claim (userId) and inject it as the `X-Authenticated-User-Id` request header before forwarding to the downstream service.
6. THE API_Gateway SHALL treat the following routes as public (no JWT required): `POST /user/register`, `POST /user/login`, and any path matching `*/health`.
7. THE API_Gateway SHALL use the `JWT_SECRET` environment variable to verify token signatures.

---

### Requirement 4: userId Propagation to Downstream Services

**User Story:** As a developer, I want downstream services to receive the authenticated userId so that business logic can be scoped to the correct user without re-validating the token.

#### Acceptance Criteria

1. WHEN the API_Gateway forwards a validated request, it SHALL include the `X-Authenticated-User-Id` header containing the integer userId from the JWT `sub` claim.
2. Downstream services (Account, Transaction, Ledger) MAY read `X-Authenticated-User-Id` from the request headers for business logic purposes.
3. Downstream services SHALL NOT perform JWT validation themselves — they trust the gateway.
4. THE API_Gateway SHALL NOT forward the original `Authorization` header to downstream services (strip it before forwarding).

---

### Requirement 5: Password Storage Security

**User Story:** As a security-conscious operator, I want passwords stored securely so that a database breach does not expose plaintext credentials.

#### Acceptance Criteria

1. THE User_Service SHALL never store plaintext passwords.
2. THE User_Service SHALL use PHP's `password_hash()` with `PASSWORD_BCRYPT` for hashing.
3. THE User_Service SHALL use PHP's `password_verify()` for login credential checking.
4. THE User_Service SHALL never return the `password` field in any API response.

---

### Requirement 6: Public Routes

**User Story:** As a developer, I want health check endpoints and auth endpoints to remain publicly accessible so that monitoring and login flows work without a token.

#### Acceptance Criteria

1. THE following routes SHALL be accessible without a JWT: `POST /user/register`, `POST /user/login`, `GET /*/health` (all service health endpoints).
2. THE API_Gateway SHALL not block requests to public routes even if no `Authorization` header is present.

---

### Requirement 7: Shared JWT Library

**User Story:** As a developer, I want JWT signing and verification logic in the shared library so it is not duplicated across services.

#### Acceptance Criteria

1. THE `shared/php-common` library SHALL provide a `JwtService` class with `encode(array $payload): string` and `decode(string $token): object` methods.
2. THE `JwtService` SHALL use `firebase/php-jwt` as its underlying implementation.
3. THE `JwtService` SHALL be constructed with the JWT secret string.
4. WHEN `decode()` is called with an invalid or expired token, THE `JwtService` SHALL throw a `PhpCommon\Exception\AuthenticationException`.
5. THE `shared/php-common/composer.json` SHALL declare `firebase/php-jwt: ^6.10` as a dependency.
6. All five service `composer.json` files SHALL include `firebase/php-jwt: ^6.10` as a direct dependency.

---

### Requirement 8: API Documentation

**User Story:** As a developer, I want the Swagger UI to document the auth endpoints and the Bearer security scheme so I can test the full flow from the UI.

#### Acceptance Criteria

1. THE `infrastructure/swagger/openapi.yaml` SHALL document `POST /user/register` and `POST /user/login` endpoints.
2. THE `openapi.yaml` SHALL define a `BearerAuth` security scheme of type `http` with scheme `bearer` and `bearerFormat: JWT`.
3. All protected endpoints in `openapi.yaml` SHALL reference the `BearerAuth` security scheme.
4. Health endpoints and auth endpoints SHALL be marked with `security: []` (explicitly public).

---

### Requirement 9: Unit Testing

**User Story:** As a developer, I want the new auth components covered by unit tests so regressions are caught early.

#### Acceptance Criteria

1. THE User_Service SHALL have unit tests for `AuthService` covering: successful registration, duplicate email rejection, successful login, invalid password rejection.
2. THE API_Gateway SHALL have unit tests for `JwtAuthSubscriber` covering: valid token passes through with userId header injected, missing header returns 401, invalid token returns 401, public routes bypass validation.
3. All new tests SHALL use `createMock()` for dependencies and follow existing PHPUnit conventions.
