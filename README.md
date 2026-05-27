# SSDLC Banking Demo

A minimal but complete **client-server banking application** that implements the full
five-layer architecture from Practical Work No. 5 following **SSDLC** (Secure Software
Development Life Cycle) principles. Every component from the architecture is either
implemented in code or satisfied by the production deploy target (Cloudflare /
DigitalOcean) — and anything mocked is clearly labelled.

## Stack

| Layer | Technology |
|---|---|
| Frontend + BFF | **SvelteKit** (Svelte 5) — runs a server layer acting as a Backend-For-Frontend |
| Backend API | **Symfony 7** (PHP 8.4) — modular monolith REST API |
| Message broker | **Symfony Messenger** (Doctrine transport) consumed by a **separate worker** process |
| Email | **Postmark** (prod) / **Mailpit** (local) via Symfony Mailer — real emailed MFA + notifications |
| Monitoring | **Sentry** (errors + tracing, backend & frontend) + Monolog/stderr SIEM-ready logs |
| Database | **PostgreSQL 16** |
| Local orchestration | **Docker Compose** (db, backend, worker, frontend, mailpit) |
| Production frontend | **Cloudflare Pages** (+ Cloudflare DNS / WAF / TLS / Anti-DDoS) |
| Production backend | **DigitalOcean App Platform** (api + worker) + **Managed PostgreSQL** |

## The BFF security model

```
Browser ──(same-origin, httpOnly cookie)──► SvelteKit server (BFF) ──(Bearer JWT)──► Symfony API ──► PostgreSQL
```

The JWT is stored in an **httpOnly, SameSite=Lax** cookie that the BFF holds. The browser
never sees the token (XSS cannot steal it), CORS is never exposed to the browser, and the
SvelteKit server is the only thing that talks to the API — it plays the role of the
**API Gateway** layer. The backend is reachable only on the internal network.

## Quick start

```bash
make up        # build + start db, backend, frontend (first run installs deps)
# then open:
open http://localhost:5173
```

(or `docker compose up -d --build` if you don't have `make`).

> `backend/.env` is git-ignored. `make up` creates it automatically from
> `backend/.env.example` on first run; for non-Docker use, `cp backend/.env.example backend/.env`.

- **App (use this):** http://localhost:5173
- **Mailpit (captured emails — MFA codes & receipts):** http://localhost:8025
- **API (direct debugging only):** http://localhost:8080/api/health
- **PostgreSQL:** localhost:5432 (`app` / `app` / `ssdlc_bank`)

### Demo accounts (password `Password123!`)

| Email | Role | Notes |
|---|---|---|
| `client@example.com` | Client | 3 accounts (EUR/EUR/USD) + transaction history |
| `client2@example.com` | Client | a second client (proves isolation) |
| `employee@example.com` | Employee | client-level access |
| `admin@example.com` | Admin | users, all payments, audit logs |
| `victor@example.com` | Client | **blocked** account (login is refused) |

### What to try

1. **Log in** as the client → dashboard with three accounts + history.
2. **New payment** of a small amount → completes immediately; the receipt email is sent
   **asynchronously by the worker** (view it at http://localhost:8025).
3. **New payment ≥ 10,000** → triggers **MFA**: a random code is **emailed**. Open
   [Mailpit](http://localhost:8025), copy the code, and confirm.
4. Try a payment that's too large → rejected (insufficient balance) server-side.
5. Log in as **admin** → review **users** (block/unblock), **payments**, and the
   **immutable audit log**.

## Architecture coverage

Every component of the architecture is accounted for. See
**[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)** for the full component-by-component map.
Highlights:

- **Auth Service** — JWT (lexik), password hashing, **real emailed MFA** for risky payments
  (random code via Postmark / Mailpit).
- **RBAC** — `ROLE_CLIENT` / `ROLE_EMPLOYEE` / `ROLE_ADMIN`, role hierarchy + an account
  ownership voter.
- **Payment Service** — server-side ownership, balance, limit and risk checks.
- **Fraud/Risk** — high-value → MFA, velocity (too many payments/minute) → blocked.
- **Message Broker** — Symfony Messenger (Doctrine transport) consumed by a **dedicated
  `worker` container**; after a payment it fans out to notification email + external
  gateway + completion audit, decoupled from the request.
- **Audit Log** — append-only `audit_logs` table, made **immutable by a PostgreSQL
  trigger** that blocks UPDATE/DELETE; also emitted as JSON for SIEM ingestion.
- **API Gateway** — BFF routing + Symfony login rate-limiter (+ optional Cloudflare Worker).
- **WAF-lite** — security headers on both tiers (Cloudflare strengthens these in prod).

## Security controls (SSDLC)

| Control | Where |
|---|---|
| Password hashing | `security.yaml` (`auto` = argon2id/bcrypt) |
| Authentication (tokens) | lexik JWT, stateless `^/api` firewall |
| Authorization (RBAC) | role hierarchy, `access_control`, `AccountVoter` |
| MFA (real) | `MfaService` emails a random code (Postmark/Mailpit) to confirm risky payments |
| Input validation | Symfony Validator on request DTOs |
| Login rate limiting | `rate_limiter.yaml` + `AuthController` |
| Audit logging | `audit_logs` + `AuditLogger` |
| Audit immutability | DB trigger (`prevent_audit_log_mutation`) |
| Token never in browser | BFF httpOnly cookie |
| Content-Security-Policy | strict nonce-based CSP (`kit.csp`): `script-src 'self' 'nonce-…'`, no `unsafe-inline` for scripts |
| Secrets out of code | `backend/.env` is **git-ignored** — only `backend/.env.example` is committed; JWT keys git-ignored; prod uses encrypted env vars |
| Payment integrity | all checks server-side in `PaymentService` |
| Error hygiene | `ApiExceptionSubscriber` hides internals in prod |
| Error monitoring | **Sentry** on backend (`sentry.yaml`, no PII) and frontend (`hooks.*.ts`), wired through Monolog — inert until a DSN is set |
| DB isolation | backend-only access; no public DB |
| DevSecOps | GitHub Actions: SAST (PHPStan + **Semgrep**), `composer`/`npm audit`, tests; **DAST** via OWASP ZAP (`dast.yml`) |

Check the live posture at `GET /api/security/config-check`.

## Production deployment

The local stack maps directly to production (configuration is provided, not deployed):

```
https://bank-demo.example.com      → Cloudflare Pages (SvelteKit)
https://api.bank-demo.example.com  → Cloudflare DNS/WAF/TLS → DigitalOcean App Platform (Symfony)
                                     → DigitalOcean Managed PostgreSQL
```

- Frontend → Cloudflare Pages: see **[cloudflare/README.md](cloudflare/README.md)**
  (swap to `adapter-cloudflare`; `_headers` + optional `worker-gateway.js`).
- Backend → DigitalOcean: **[backend/Dockerfile](backend/Dockerfile)** (FrankenPHP) +
  **[.do/app.yaml](.do/app.yaml)** — App Platform `api` **and** `worker` components +
  Managed PostgreSQL (automated backups). Set `MAILER_DSN` to your Postmark token.

## What is real vs mocked (and how it maps to production)

| Capability | Locally | Production |
|---|---|---|
| **MFA** | **real email** (random code via Mailpit) | **real email** via Postmark (same code path) |
| **Email notifications** | **really sent** to Mailpit | Postmark |
| **Message broker / worker** | **real** Doctrine queue + worker container | DO worker component (or Redis/RabbitMQ) |
| External payment gateway | mock adapter | real card network / partner bank API |
| SMS / Push notifications | mock (DB record) | real SMS/Push provider |
| WAF / DNS / Anti-DDoS / LB / TLS | n/a | Cloudflare + DigitalOcean |
| SIEM | audit logs + JSON stderr logs | ingested by a SIEM |
| KMS / Vault | `.env.local` / env vars | DO / Cloudflare encrypted env vars |
| DB cluster | one PostgreSQL container | DigitalOcean Managed PostgreSQL |

## Project layout

```
backend/    Symfony API (Controller / Entity / Repository / Service / Security / Message)
frontend/   SvelteKit BFF + SPA (routes, lib/server BFF helpers, components)
docs/       ARCHITECTURE.md — full coverage map
cloudflare/ _headers, optional API-gateway Worker, deploy notes
.do/        DigitalOcean App Platform spec
.github/    CI workflow (SAST / audit / tests / DAST template)
docker-compose.yml, Makefile
```

## Commands

```bash
make up        # start everything
make down      # stop
make logs      # tail logs
make reset     # clean DB + re-seed demo data
make test      # PHPUnit + svelte-check
make lint      # PHPStan + svelte-check
make db-backup # pg_dump to ./backups (stands in for managed backups)
make help      # list all targets
```

## SSDLC: security testing & maintenance

**Security testing**
- **SAST** — PHPStan (PHP) + **Semgrep** (`p/security-audit`, `p/secrets`, `p/php`,
  `p/javascript`) across backend and frontend, in `ci.yml` on every push/PR.
- **SCA (dependencies)** — `composer audit` + `npm audit` in CI.
- **DAST** — OWASP **ZAP baseline** against the running BFF in `.github/workflows/dast.yml`
  (run from the Actions tab or weekly): brings the stack up, scans `http://localhost:5173`.
- **Tests** — **49 PHPUnit tests** (unit + functional) plus `svelte-check`; run with `make test`.
  - *Unit*: risk rules, MFA (code hashing / expiry / attempt-lockout), input-validation
    constraints, the account-ownership voter, and no-secret-leakage in API serialization.
  - *Functional* (boot the kernel against an isolated test DB, transaction-rolled-back per
    test via `dama/doctrine-test-bundle`): login / blocked users / **rate-limiting**,
    account & transaction **isolation (IDOR)**, payments (debit, **cross-account 403**,
    over-balance, validation, and the **full emailed-MFA flow** read from the captured
    message), **admin RBAC**, security headers, JSON error handling, and **audit-log
    immutability** at the DB level.

**Maintenance**
- **Logging & alerting** — **Sentry** captures errors + traces on both tiers (wired through
  Monolog on the backend). It is fully configured and **only needs a DSN**:
  ```bash
  SENTRY_DSN=<backend-dsn> PUBLIC_SENTRY_DSN=<frontend-dsn> docker compose up -d
  ```
  Verify the backend wiring any time with `docker compose exec backend php bin/console sentry:test`.
  No PII is sent (`send_default_pii: false`). Alongside Sentry, security events are emitted
  as JSON to stderr (SIEM-ready) and stored in the immutable `audit_logs` table.
- **Auditing** — append-only `audit_logs`, enforced immutable by a PostgreSQL trigger.
- **Backups** — `make db-backup` (`pg_dump`) locally; DigitalOcean Managed PostgreSQL
  provides automated daily backups + point-in-time recovery in production.
