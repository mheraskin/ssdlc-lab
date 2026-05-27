# Architecture Coverage Map

This maps every component from Figure 1 of the architecture (Practical Work No. 5) to its
implementation in this project: **C** = implemented in code, **D** = provided by the
deploy target, **M** = mocked/simulated (labelled, with the production equivalent noted).

## Layer topology

```
┌────────────────────────────────────────────────────────────────────────┐
│ Client layer        Web/Mobile client, Admin panel  →  SvelteKit SPA      │
├────────────────────────────────────────────────────────────────────────┤
│ Public zone / DMZ   DNS · CDN · Anti-DDoS · WAF · LB · TLS · API Gateway  │
│                     →  Cloudflare (prod) + SvelteKit BFF (routing/headers) │
├────────────────────────────────────────────────────────────────────────┤
│ Internal app layer  Auth · User · Account · Payment · History · Risk ·    │
│                     Notification · Admin · Message Broker  →  Symfony      │
├────────────────────────────────────────────────────────────────────────┤
│ Protected data      DB cluster · Cache/Session · KMS/Vault · Backups       │
│                     →  PostgreSQL + httpOnly cookie + env secrets + pg_dump │
├────────────────────────────────────────────────────────────────────────┤
│ Audit / DevSecOps   Immutable audit · SIEM · CI/CD SAST/DAST · Integrations │
│                     →  audit_logs trigger + Monolog + GitHub Actions + mocks│
└────────────────────────────────────────────────────────────────────────┘
```

## Component-by-component

| # | Component | Mode | Where / how |
|---|---|---|---|
| 1 | Bank client | C | Seeded `client@example.com` (`ROLE_CLIENT`) |
| 2 | Web / Mobile client | C | SvelteKit SPA (responsive) — `frontend/src/routes/*` |
| 3 | Bank employee | C | Seeded `employee@example.com` (`ROLE_EMPLOYEE`) |
| 4 | Admin panel | C | `frontend/src/routes/admin/*` (guarded by `ROLE_ADMIN`) |
| 5 | HTTPS / TLS | D | Cloudflare + DO terminate TLS; HSTS header set in code |
| 6 | DNS / CDN | D | Cloudflare zone + Pages CDN (`cloudflare/README.md`) |
| 7 | Anti-DDoS | D | Cloudflare always-on DDoS protection |
| 8 | WAF + Firewall | D + C | Cloudflare managed WAF; security headers via `SecurityHeadersSubscriber` + frontend `hooks.server.ts` |
| 9 | Load Balancer / TLS termination | D | Cloudflare + DO App Platform |
| 10 | API Gateway (rate limit, routing) | C + D | SvelteKit BFF proxy `routes/api/[...path]`; Symfony login limiter `rate_limiter.yaml` + `AuthController`; optional `cloudflare/worker-gateway.js` |
| 11 | Auth Service (MFA, tokens) | C | `AuthController`, lexik JWT; `MfaService` emails a random one-time code (`AppMailer` → Postmark/Mailpit) — real MFA, no fixed code |
| 12 | User / Profile Service | C | `User` entity, `GET /api/me` |
| 13 | Account Service | C | `Account` entity, `AccountController` (own accounts only) |
| 14 | Payment Service | C | `PaymentService` (ownership, balance, limits, risk, MFA) |
| 15 | Transaction History | C | `Transaction` entity, `TransactionController` |
| 16 | Fraud / Risk Rules | C | `RiskService` (high-value → MFA, velocity → blocked) |
| 17 | Notification Service | C | `NotificationService` stores a record AND really sends email (`AppMailer` → Postmark/Mailpit); SMS/push channels mocked |
| 18 | Admin Service | C | `AdminController` (`/api/admin/*`, `ROLE_ADMIN`) |
| 19 | Message Broker / Event Queue | C | Symfony Messenger, Doctrine transport (`async`). `PaymentCreatedMessage` is consumed by a **separate `worker` container** (`messenger:consume`) → `PaymentCreatedMessageHandler`; truly decoupled from the API process |
| 20 | DB Cluster (accounts, transactions) | C → D | PostgreSQL container → DO Managed PostgreSQL |
| 21 | Cache / Session Store | C | httpOnly cookie session via BFF (Redis optional in prod) |
| 22 | KMS / Secrets Vault | C + D | Secrets in `.env.local`/env vars, JWT keys git-ignored → DO/Cloudflare encrypted env vars |
| 23 | Backup Storage | C → D | `make db-backup` (`pg_dump`) → DO Managed backups / Spaces |
| 24 | Audit Log (immutable events) | C | `audit_logs` table; PostgreSQL trigger `prevent_audit_log_mutation` blocks UPDATE/DELETE |
| 25 | SIEM / Monitoring | C / M | Monolog `security` channel → JSON to stderr ("SIEM-ready") + admin audit page |
| 26 | CI/CD + Security (SAST, DAST) | C | `.github/workflows/ci.yml`: PHPStan (SAST), composer/npm audit, PHPUnit, svelte-check; ZAP DAST template |
| 27 | External Payment Systems | M | `ExternalPaymentGatewayInterface` + `ExternalPaymentGatewayMock` |
| 28 | SMS / Email / Push Gateway | C / M | **Email is real** via Postmark (`postmark+api://`), captured by Mailpit locally; SMS/push remain mocked DB records |

## Main data flows

**Login.** Browser → BFF `/login` action → `POST /api/login` (rate-limited) → credentials
verified, JWT issued, `login_success`/`login_failed` audited → BFF stores JWT in an
httpOnly cookie → browser only receives the user object.

**Payment.** Browser form → BFF → `POST /api/payments`. `PaymentService` checks ownership
(voter + re-check), account status, amount, balance, then `RiskService`:
- velocity exceeded → transaction `rejected`, `payment_rejected` audited;
- amount ≥ threshold → transaction `pending_mfa` + `MfaChallenge`; a **random code is
  emailed** (Postmark/Mailpit) and `payment_mfa_required` audited → user submits the
  emailed code → `POST /api/payments/{id}/confirm` → `mfa_success`, balance debited,
  `payment_created` audited;
- otherwise → executed immediately.
On completion a `PaymentCreatedMessage` is queued to the `async` transport and processed
by the **separate worker process**, which calls the external gateway (mock), sends the
notification **email**, and writes the `payment_completed` audit event.

## SSDLC stage mapping

- **Requirements** — financial + personal data ⇒ authN, authZ, encryption, audit, backup,
  monitoring are first-class requirements.
- **Design** — DB on the internal network only; critical services separated; external
  access passes through DMZ controls; BFF keeps tokens off the client.
- **Development** — input validation, secrets out of code, no sensitive data in
  errors/logs (no passwords/CVV in audit metadata).
- **Testing** — PHPUnit + svelte-check + SAST + dependency audit in CI; DAST template.
- **Operation** — migrations, audit/SIEM logs, incident review via admin panel, backups
  (`make db-backup` / DO Managed backups).
