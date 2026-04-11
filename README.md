# Fund Transfer System

A PHP/Symfony microservices platform for fund transfers, composed of five independently deployable services orchestrated via Docker Compose, proxied through NGINX, and backed by MySQL and Redis.

Authentication is enforced at the API Gateway using JWT. The User Service issues tokens; all other endpoints require a valid `Authorization: Bearer` header.

---

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) 24+
- Docker Compose — either version works:
  - **v2 plugin** (modern): `docker compose` — built into Docker Desktop and Docker Engine 20.10+
  - **v1 standalone** (legacy): `docker-compose` — separate binary, may not be installed

> All commands in this README use `docker compose` (v2). If you have the v1 standalone binary, replace `docker compose` with `docker-compose` throughout.

No local PHP or Composer installation required — everything runs inside containers.

---

## Build

```bash
# v2 (plugin)
docker compose build --no-cache

# v1 (standalone)
docker-compose build --no-cache
```

---

## Start

```bash
# v2
docker compose up

# v1
docker-compose up
```

On first start, each service automatically runs its Doctrine migrations to create the required database tables.

To run in the background:

```bash
# v2
docker compose up -d

# v1
docker-compose up -d
```

---

## Stop

```bash
# v2
docker compose down

# v1
docker-compose down
```

To also remove the MySQL data volume:

```bash
# v2
docker compose down -v

# v1
docker-compose down -v
```

---

## Service URLs

All services are accessible through NGINX on **port 8000**, routed by path prefix.

| Service             | Base URL                               |
|---------------------|----------------------------------------|
| API Gateway         | http://localhost:8000/api              |
| User Service        | http://localhost:8000/user             |
| Account Service     | http://localhost:8000/account          |
| Transaction Service | http://localhost:8000/transaction      |
| Ledger Service      | http://localhost:8000/ledger           |

---

## Health Check URLs

Health endpoints are public — no token required.

| Service             | Health URL                                   |
|---------------------|----------------------------------------------|
| API Gateway         | http://localhost:8000/api/health             |
| User Service        | http://localhost:8000/user/health            |
| Account Service     | http://localhost:8000/account/health         |
| Transaction Service | http://localhost:8000/transaction/health     |
| Ledger Service      | http://localhost:8000/ledger/health          |

---

## Developer Tools

| Tool        | URL                        | Credentials                                          |
|-------------|----------------------------|------------------------------------------------------|
| Swagger UI  | http://localhost:8081      | —                                                    |
| Adminer     | http://localhost:8080      | Server: `mysql`, User: `root`, Password: `root`      |
| MailHog     | http://localhost:8025      | —                                                    |

### Adminer — available databases

| Database              | Service             |
|-----------------------|---------------------|
| `user_service`        | User Service        |
| `account_service`     | Account Service     |
| `transaction_service` | Transaction Service |
| `ledger_service`      | Ledger Service      |

---

## Authentication

All endpoints except health checks, `/user/register`, and `/user/login` require a valid JWT in the `Authorization` header.

### 1. Register

```bash
curl -X POST http://localhost:8000/user/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Alice Smith","email":"alice@example.com","password":"secret123"}'
```

Response `201`:
```json
{"id": 1, "name": "Alice Smith", "email": "alice@example.com", "createdAt": "..."}
```

### 2. Login — get a token

```bash
curl -X POST http://localhost:8000/user/login \
  -H "Content-Type: application/json" \
  -d '{"email":"alice@example.com","password":"secret123"}'
```

Response `200`:
```json
{"token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."}
```

### 3. Use the token

Pass the token in every subsequent request:

```bash
TOKEN="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."

curl http://localhost:8000/account/1 \
  -H "Authorization: Bearer $TOKEN"
```

Tokens expire after **1 hour**. Login again to get a fresh token.

### Public routes (no token required)

| Method | Path              |
|--------|-------------------|
| POST   | `/user/register`  |
| POST   | `/user/login`     |
| GET    | `*/health`        |

---

## API Quick Reference

All examples below assume `$TOKEN` is set from the login step above.

### Users

```bash
# List all users
curl http://localhost:8000/user \
  -H "Authorization: Bearer $TOKEN"

# Get user by ID
curl http://localhost:8000/user/1 \
  -H "Authorization: Bearer $TOKEN"
```

### Accounts

```bash
# Create an account
curl -X POST http://localhost:8000/account \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"userId":1,"balance":"1000.00","currency":"USD"}'

# Get account by ID
curl http://localhost:8000/account/1 \
  -H "Authorization: Bearer $TOKEN"

# Debit an account (triggers email notification)
curl -X POST http://localhost:8000/account/1/debit \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"amount":"50.00"}'

# Credit an account (triggers email notification)
curl -X POST http://localhost:8000/account/1/credit \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"amount":"200.00"}'
```

### Transactions

```bash
# Initiate a fund transfer
curl -X POST http://localhost:8000/transaction \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"sourceAccountId":1,"destinationAccountId":2,"amount":"100.00","currency":"USD"}'

# Get transaction by ID
curl http://localhost:8000/transaction/1 \
  -H "Authorization: Bearer $TOKEN"
```

### Ledger

```bash
# Record a ledger entry
curl -X POST http://localhost:8000/ledger/entry \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"transactionId":1,"accountId":1,"entryType":"debit","amount":"100.00"}'

# Get entries by account
curl http://localhost:8000/ledger/account/1 \
  -H "Authorization: Bearer $TOKEN"

# Get entries by transaction
curl http://localhost:8000/ledger/transaction/1 \
  -H "Authorization: Bearer $TOKEN"
```

---

## Running Tests

Run PHPUnit for a specific service:

```bash
# v2
docker compose exec user-service vendor/bin/phpunit
docker compose exec account-service vendor/bin/phpunit
docker compose exec transaction-service vendor/bin/phpunit
docker compose exec ledger-service vendor/bin/phpunit
docker compose exec api-gateway vendor/bin/phpunit

# v1
docker-compose exec user-service vendor/bin/phpunit
docker-compose exec account-service vendor/bin/phpunit
docker-compose exec transaction-service vendor/bin/phpunit
docker-compose exec ledger-service vendor/bin/phpunit
docker-compose exec api-gateway vendor/bin/phpunit
```

Run PHPStan static analysis:

```bash
# v2
docker compose exec user-service vendor/bin/phpstan analyse src/
docker compose exec account-service vendor/bin/phpstan analyse src/
docker compose exec transaction-service vendor/bin/phpstan analyse src/
docker compose exec ledger-service vendor/bin/phpstan analyse src/
docker compose exec api-gateway vendor/bin/phpstan analyse src/

# v1
docker-compose exec user-service vendor/bin/phpstan analyse src/
docker-compose exec account-service vendor/bin/phpstan analyse src/
docker-compose exec transaction-service vendor/bin/phpstan analyse src/
docker-compose exec ledger-service vendor/bin/phpstan analyse src/
docker-compose exec api-gateway vendor/bin/phpstan analyse src/
```

---

## Project Structure

```
fund-transfer-system/
├── services/
│   ├── api-gateway/          # API Gateway — JWT validation + request forwarding
│   ├── user-service/         # User management, registration, login, JWT issuance
│   ├── account-service/      # Account & balance management + email notifications
│   ├── transaction-service/  # Fund transfer orchestration
│   └── ledger-service/       # Immutable financial ledger
├── shared/
│   └── php-common/           # Shared interfaces, JwtService, exceptions
├── infrastructure/
│   ├── nginx/                # NGINX reverse proxy configuration
│   ├── mysql/                # MySQL initialisation SQL
│   └── swagger/              # OpenAPI specification (openapi.yaml)
├── docker-compose.yml
└── README.md
```

---

## Architecture

```
Client (HTTP :8000)
       │
       ▼
    NGINX (:8000 → :80)
       │  path-based routing
       ├─ /api/*          ──► API Gateway        (PHP-FPM :9000)
       │                        │
       │                        │  JwtAuthSubscriber validates token
       │                        │  injects X-Authenticated-User-Id
       │                        │  forwards to downstream service
       │                        ▼
       ├─ /user/*         ──► User Service       (PHP-FPM :9000)  ← issues JWTs
       ├─ /account/*      ──► Account Service    (PHP-FPM :9000)  ← sends emails via MailHog
       ├─ /transaction/*  ──► Transaction Service(PHP-FPM :9000)
       └─ /ledger/*       ──► Ledger Service     (PHP-FPM :9000)

Shared infrastructure (internal Docker network):
  MySQL   :3306  — one database per service
  Redis   :6379  — shared cache/session store
  MailHog :1025  — SMTP trap (web UI at :8025)
```

---

## Environment Variables

Each service reads its configuration from environment variables defined in `docker-compose.yml`.

| Variable       | Services                        | Description                              |
|----------------|---------------------------------|------------------------------------------|
| `APP_ENV`      | all                             | Symfony environment (`dev` / `prod`)     |
| `APP_SECRET`   | all                             | Symfony secret key                       |
| `JWT_SECRET`   | `user-service`, `api-gateway`   | Shared HS256 signing secret              |
| `DATABASE_URL` | entity services                 | Doctrine DSN                             |
| `MAILER_DSN`   | `account-service`               | SMTP DSN (`smtp://mailhog:1025` in dev)  |

> **Production note:** Change `JWT_SECRET` to a long random string before deploying. Never commit real secrets to version control.
