# Design Document

## JWT Authentication — Fund Transfer System

---

## Overview

Authentication is centralised at the API Gateway. The User Service owns credential management and token issuance. All other services are shielded behind the gateway and receive only pre-validated requests with the authenticated userId injected as a trusted header.

```
                    ┌──────────────────────────────────────────────┐
                    │               Docker Network                  │
                    │                                               │
  HTTP :80          │  ┌─────────────────────────────────────────┐ │
 ────────────────►  │  │           NGINX (:80)                   │ │
                    │  └──────────────┬──────────────────────────┘ │
                    │                 │ FastCGI                     │
                    │  ┌──────────────▼──────────────────────────┐ │
                    │  │         API Gateway (:9000)              │ │
                    │  │                                          │ │
                    │  │  JwtAuthSubscriber (kernel.request)      │ │
                    │  │    ├─ public route? → pass through       │ │
                    │  │    ├─ no token?     → 401                │ │
                    │  │    ├─ bad token?    → 401                │ │
                    │  │    └─ valid token   → inject             │ │
                    │  │                      X-Authenticated-    │ │
                    │  │                      User-Id: {userId}   │ │
                    │  │                      forward request      │ │
                    │  └──────────────┬──────────────────────────┘ │
                    │                 │ HTTP (HttpClient)           │
                    │    ┌────────────┼────────────┐               │
                    │    ▼            ▼            ▼               │
                    │  user-svc  account-svc  txn-svc  ledger-svc  │
                    │  (issues   (trusts      (trusts   (trusts     │
                    │   tokens)   gateway)     gateway)  gateway)   │
                    └──────────────────────────────────────────────┘
```

---

## Authentication Flow

### Registration

```
POST /user/register
  │
  ▼ API Gateway
  JwtAuthSubscriber → public route → pass through
  │
  ▼ User Service
  AuthController::register()
    → validate fields
    → check email uniqueness (UserRepository::findByEmail)
    → hash password (password_hash / BCRYPT)
    → UserFactory::create() → UserRepository::save()
    → return 201 {id, name, email, createdAt}
```

### Login

```
POST /user/login
  │
  ▼ API Gateway
  JwtAuthSubscriber → public route → pass through
  │
  ▼ User Service
  AuthController::login()
    → validate fields
    → UserRepository::findByEmail()
    → password_verify()
    → JwtService::encode({sub: userId, email, iat, exp})
    → return 200 {token: "eyJ..."}
```

### Protected Request

```
GET /account/1
Authorization: Bearer eyJ...
  │
  ▼ API Gateway
  JwtAuthSubscriber
    → extract Bearer token
    → JwtService::decode(token)   ← verifies signature + expiry
    → extract sub (userId)
    → strip Authorization header
    → inject X-Authenticated-User-Id: {userId}
    → ProxyService::forward() → account-service
  │
  ▼ Account Service
  AccountController::getById()
    → reads X-Authenticated-User-Id if needed
    → returns account data
```

---

## Components

### shared/php-common — new classes

#### `PhpCommon\Security\JwtService`

```php
class JwtService
{
    public function __construct(private string $secret) {}

    /** Signs and returns a JWT string */
    public function encode(array $payload): string;

    /** Decodes and verifies a JWT; throws AuthenticationException on failure */
    public function decode(string $token): object;
}
```

- Uses `Firebase\JWT\JWT::encode()` / `JWT::decode()` with algorithm `HS256`
- `encode()` merges caller-supplied payload with `iat` (now) and `exp` (now + 3600)
- `decode()` catches `Firebase\JWT\*Exception` and rethrows as `AuthenticationException`

#### `PhpCommon\Exception\AuthenticationException`

```php
class AuthenticationException extends \RuntimeException {}
```

---

### User Service — new / modified classes

#### `User` entity — add `password` column

```php
#[ORM\Column(type: 'string', length: 255)]
private string $password;
```

New getter/setter: `getPassword(): string`, `setPassword(string $password): void`

#### `AuthService` (new)

```php
class AuthService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserFactory $userFactory,
        private JwtService $jwtService
    ) {}

    public function register(array $data): User;   // hashes password, saves user
    public function login(array $data): string;    // verifies password, returns JWT
}
```

- `register()`: checks for duplicate email → throws `\RuntimeException('Email already registered.')` → hashes password → delegates to `UserFactory` + `UserRepository`
- `login()`: finds user by email → `password_verify()` → on failure throws `AuthenticationException` → `JwtService::encode(['sub' => $user->getId(), 'email' => $user->getEmail()])`

#### `AuthController` (new)

```
POST /user/register  → AuthController::register()
POST /user/login     → AuthController::login()
```

Both endpoints are public (no JWT required — the gateway whitelists them).

#### `UserFactory` — update `create()` to accept optional `password`

The factory sets `password` only when the key is present in `$data`, so existing tests remain unaffected.

---

### API Gateway — new / modified classes

#### `JwtAuthSubscriber` (new)

Implements `EventSubscriberInterface`, listens on `KernelEvents::REQUEST` at priority 10 (before routing).

```php
class JwtAuthSubscriber implements EventSubscriberInterface
{
    private const PUBLIC_ROUTES = [
        ['method' => 'POST', 'prefix' => '/user/register'],
        ['method' => 'POST', 'prefix' => '/user/login'],
    ];

    public function __construct(private JwtService $jwtService) {}

    public function onKernelRequest(RequestEvent $event): void;

    public static function getSubscribedEvents(): array;
}
```

Logic in `onKernelRequest()`:
1. Skip sub-requests (`!$event->isMainRequest()`)
2. Check if path matches a public route or ends with `/health` → return early
3. Extract `Authorization` header → if missing, set 401 response and return
4. Strip `Bearer ` prefix → call `JwtService::decode()`
5. On `AuthenticationException` → set 401 response and return
6. Extract `sub` claim → set `X-Authenticated-User-Id` on the request
7. Remove `Authorization` header from the request (so it is not forwarded downstream)

#### `ProxyService` — forward `X-Authenticated-User-Id`

The existing `ProxyService::forward()` already copies all request headers. Since the subscriber injects `X-Authenticated-User-Id` onto the Symfony `Request` object before forwarding, no changes are needed to `ProxyService` — it will naturally include the header.

#### `JwtService` wiring in API Gateway

Registered as a Symfony service via `config/services.yaml`:

```yaml
PhpCommon\Security\JwtService:
    arguments:
        $secret: '%env(JWT_SECRET)%'
```

---

### Migration — add `password` column to `users` table

New migration file in `services/user-service/migrations/`:

```sql
ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL AFTER email;
```

---

## Directory Changes

```
shared/php-common/src/
  Security/
    JwtService.php              ← NEW
  Exception/
    AuthenticationException.php ← NEW
    NotFoundException.php       (existing)

services/user-service/src/
  Controller/
    AuthController.php          ← NEW
    UserController.php          (existing, unchanged)
  Service/
    AuthService.php             ← NEW
    UserService.php             (existing, unchanged)
  Entity/
    User.php                    ← MODIFIED (add password field)
  Factory/
    UserFactory.php             ← MODIFIED (handle password key)
  migrations/
    Version20260411000002.php   ← NEW (add password column)

services/api-gateway/src/
  EventSubscriber/
    JwtAuthSubscriber.php       ← NEW
  Service/
    ProxyService.php            (existing, no changes needed)
  config/
    services.yaml               ← MODIFIED (wire JwtService)

infrastructure/swagger/
  openapi.yaml                  ← MODIFIED (add auth endpoints + BearerAuth)
```

---

## Environment Variables

Add `JWT_SECRET` to `docker-compose.yml` for `user-service` and `api-gateway`:

```yaml
JWT_SECRET: "super-secret-jwt-key-change-in-production"
```

The same value must be set on both services — user-service signs with it, api-gateway verifies with it.

---

## JWT Payload Structure

```json
{
  "sub": 1,
  "email": "alice@example.com",
  "iat": 1712800000,
  "exp": 1712803600
}
```

| Claim   | Type    | Description                        |
|---------|---------|------------------------------------|
| `sub`   | integer | User ID (primary key)              |
| `email` | string  | User's email address               |
| `iat`   | integer | Issued-at Unix timestamp           |
| `exp`   | integer | Expiry Unix timestamp (iat + 3600) |

---

## Public Routes (no JWT required)

| Method | Path pattern     | Reason              |
|--------|------------------|---------------------|
| POST   | `/user/register` | Registration flow   |
| POST   | `/user/login`    | Login flow          |
| GET    | `*/health`       | Monitoring/liveness |

---

## Error Responses

| Scenario                        | HTTP | Body                                              |
|---------------------------------|------|---------------------------------------------------|
| Missing Authorization header    | 401  | `{"error":"Authorization header missing.","code":401}` |
| Invalid / expired token         | 401  | `{"error":"Invalid or expired token.","code":401}` |
| Invalid credentials (login)     | 401  | `{"error":"Invalid credentials.","code":401}`     |
| Duplicate email (register)      | 409  | `{"error":"Email already registered.","code":409}` |
| Missing fields                  | 400  | `{"error":"Invalid JSON body.","code":400}`       |

---

## Correctness Properties

### Property A: All protected routes require a valid JWT

For any HTTP request to a non-public path, the API Gateway SHALL return 401 if the `Authorization: Bearer` header is absent or the token is invalid/expired. No request with a missing or invalid token SHALL reach a downstream service.

### Property B: JWT sub claim matches a real user

The `sub` claim in every issued JWT SHALL equal the `id` of an existing `User` entity in the `user_service` database at the time of issuance.

### Property C: Passwords are never stored or returned in plaintext

No API response from any service SHALL contain a `password` field. The `users` table SHALL never contain a plaintext password string.

### Property D: Public routes are always accessible without a token

`POST /user/register`, `POST /user/login`, and all `*/health` endpoints SHALL return non-401 responses regardless of whether an `Authorization` header is present.

---

## Testing Strategy

### Unit tests

| Class                | Tests                                                                 |
|----------------------|-----------------------------------------------------------------------|
| `JwtService`         | encode produces valid JWT, decode returns payload, decode throws on bad token |
| `AuthService`        | register happy path, duplicate email throws, login happy path, wrong password throws |
| `AuthController`     | register 201, register 409, register 400, login 200, login 401, login 400 |
| `JwtAuthSubscriber`  | valid token → userId header injected, missing header → 401, bad token → 401, public route → passes through, health route → passes through |

### Integration (manual / docker)

```bash
# Register
curl -X POST http://localhost/user/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Alice","email":"alice@example.com","password":"secret123"}'

# Login → copy token
curl -X POST http://localhost/user/login \
  -H "Content-Type: application/json" \
  -d '{"email":"alice@example.com","password":"secret123"}'

# Use token
curl http://localhost/account/1 \
  -H "Authorization: Bearer <token>"

# Without token → 401
curl http://localhost/account/1
```
