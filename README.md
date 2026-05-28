# Банк SSDLC — демо-застосунок

Клієнт-серверний банківський веб-застосунок, побудований за принципами **SSDLC**
(Secure Software Development Life Cycle). Реалізує п'ятирівневу архітектуру з
автентифікацією через JWT, рольовим доступом, MFA для ризикових платежів,
незмінним журналом аудиту та повним конвеєром безпекових перевірок у CI.

Кожен компонент архітектури або реалізований у коді, або покритий конфігурацією
production-розгортання (Cloudflare / DigitalOcean) — усе, що змочковане, явно
позначено як таке.

## Стек

| Рівень | Технологія |
|---|---|
| Frontend + BFF | **SvelteKit** (Svelte 5) — серверний шар як Backend-for-Frontend |
| Backend API | **Symfony 7** (PHP 8.4) — модульний моноліт |
| Брокер повідомлень | **Symfony Messenger** (Doctrine transport) + окремий worker-процес |
| Email | **Postmark** (prod) / **Mailpit** (локально) через Symfony Mailer |
| Моніторинг | **Sentry** (помилки + traces, backend і frontend) + Monolog/stderr (SIEM-ready) |
| База даних | **PostgreSQL 16** |
| Локальна оркестрація | **Docker Compose** (db, backend, worker, frontend, mailpit) |
| Production frontend | **Cloudflare Pages** (+ DNS / WAF / TLS / Anti-DDoS) |
| Production backend | **DigitalOcean App Platform** (api + worker) + **Managed PostgreSQL** |

## Безпекова модель BFF

```
Браузер ──(same-origin, httpOnly cookie)──► SvelteKit-сервер (BFF) ──(Bearer JWT)──► Symfony API ──► PostgreSQL
```

JWT зберігається в **httpOnly, SameSite=Lax** cookie на стороні BFF. Браузер
**ніколи не бачить токен** — XSS не може його викрасти. CORS не експонується
браузеру, бо BFF — це єдина точка, що звертається до API; backend доступний
лише з внутрішньої мережі і виконує роль **API Gateway**.

## Швидкий старт

```bash
make up        # збирає та піднімає db, backend, worker, frontend, mailpit
open http://localhost:5173
```

> `backend/.env` ігнорується git. `make up` створює його з `backend/.env.example`
> на першому запуску. Для запуску поза Docker: `cp backend/.env.example backend/.env`.

- **Застосунок:** http://localhost:5173
- **Mailpit (перехоплені email-листи — MFA-коди, нотифікації):** http://localhost:8025
- **API (тільки для дебагу):** http://localhost:8080/api/health
- **PostgreSQL:** localhost:5432 (`app` / `app` / `ssdlc_bank`)

### Демо-акаунти (пароль `Password123!`)

| Email | Роль | Опис |
|---|---|---|
| `client@example.com` | Клієнт | 3 рахунки (EUR/EUR/USD) + історія операцій |
| `client2@example.com` | Клієнт | другий клієнт (демонструє ізоляцію даних) |
| `employee@example.com` | Співробітник | read-only доступ до чужих рахунків |
| `admin@example.com` | Адміністратор | користувачі, всі платежі, журнал аудиту |
| `victor@example.com` | Клієнт | **заблокований** (вхід відхиляється) |

### Що варто спробувати

1. **Увійдіть** клієнтом → побачите три рахунки + історію.
2. **Створіть невеликий платіж** → виконується миттєво; квитанція надсилається
   **асинхронно через worker** і потрапляє в Mailpit.
3. **Увімкніть справжню MFA** → «Безпека» в меню → «Налаштувати застосунок-автентифікатор».
   Скануйте QR Google Authenticator / 1Password / Authy / Bitwarden, *або* в dev-режимі
   отримайте код командою:
   ```bash
   docker compose exec backend php bin/console app:totp client@example.com
   ```
   Після увімкнення ризикові платежі вимагатимуть код із автентифікатора — це
   **справжній фактор володіння MFA** (пароль + пристрій).
4. **Платіж ≥ 10 000** →
   - Якщо MFA увімкнено: код із автентифікатора (справжня MFA).
   - Інакше: одноразовий код листом (Mailpit) — це *step-up*, не справжня MFA.
5. Платіж понад баланс → відхилено на сервері.
6. Увійдіть **адміністратором** → блокування/розблокування користувачів,
   всі платежі, **незмінний журнал аудиту** (з тегом `factor: 'totp' | 'email_otp'`).

## SSDLC — мапа фаз

| Фаза | Чим представлена |
|---|---|
| **Security Requirements** | Зафіксовано в попередніх лабораторних: вимоги до автентифікації, авторизації, паролів, сесій, валідації введення, логування, MFA для критичних операцій, backup. |
| **Security Design** | Багаторівнева архітектура з DMZ, прикладним рівнем, рівнем даних і аудитом. Реалізована як `backend` + `frontend` із BFF-шаром. |
| **Security Development** | Symfony Security Bundle (argon2id/bcrypt, JWT, rate limiter), `AccountVoter`, `PaymentService`, `RiskService`, `MfaService`, незмінний `audit_logs`, `SecurityHeadersSubscriber`, `ApiExceptionSubscriber`. |
| **Security Testing** | SAST: PHPStan + Semgrep; SCA: `composer audit` + `npm audit`; DAST: OWASP ZAP baseline; **63 unit/functional тести (172 assertions)** на PHPUnit із CI-прогоном на реальній PostgreSQL. |
| **Security Deployment** | Docker Compose локально; FrankenPHP-образ для backend; `.do/app.yaml` для DigitalOcean App Platform; `cloudflare/_headers` + `worker-gateway.js`. |
| **Security Maintenance** | Sentry (підключений, готовий до використання), Monolog із security-каналом, append-only audit log, `make db-backup` через `pg_dump`. |

## Архітектура — реалізація п'яти рівнів

- **Клієнтський рівень** — SvelteKit-додаток із серверним рендерингом. JWT-токен
  ніколи не виходить у браузер: після успішного входу SvelteKit-action кладе
  токен у httpOnly cookie `session`. Будь-який запит до backend йде через
  серверний `backendFetch`, який додає `Authorization` тільки на server-side.
- **Публічна зона / DMZ** — мапиться на **Cloudflare**. У `cloudflare/_headers`
  задано HSTS, X-Content-Type-Options, Referrer-Policy, Permissions-Policy на
  рівні edge; `cloudflare/worker-gateway.js` — edge-проксі з rate-limit і CORS.
- **API Gateway + прикладний рівень** — Symfony як модульний моноліт. Декаплінг
  повільних операцій реалізовано через **Symfony Messenger**: worker-процес у
  docker-compose винесений в окремий контейнер з `messenger:consume` як entrypoint.
- **Захищений рівень даних** — PostgreSQL 16. Доступ лише з backend-у; жодного
  публічного інтерфейсу. Сирий SQL використано рівно один раз — у healthcheck
  (`SELECT 1`). Інше — Doctrine ORM із параметризованими запитами.
- **Рівень аудиту і моніторингу** — розділено на два незалежні канали: бізнес-події
  пишуться в `audit_logs` (захищена тригером від UPDATE/DELETE), технічні
  помилки — в Sentry. Журнали Monolog у JSON у stderr — готові до SIEM.

Повна мапа компонентів — у [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Безпекові механізми

| Контроль | Де реалізовано |
|---|---|
| Хешування паролів | `security.yaml` — `auto` (argon2id з fallback на bcrypt) |
| Автентифікація | Lexik JWT, stateless firewall `^/api` |
| Авторизація (RBAC) | role hierarchy + `access_control` + `AccountVoter` |
| MFA (справжня) | `TotpService` — RFC 6238, AAL2; TOTP-секрет зашифрований at rest через libsodium `crypto_secretbox` (XSalsa20-Poly1305); per-user step counter для захисту від replay у 30с-вікні |
| MFA (step-up) | `MfaService` — bcrypt-хеш одноразового коду в `MfaChallenge`, надсилається листом, обмежений TTL |
| Валідація введення | Symfony Validator на DTO; формат IBAN, додатна сума, обмеження довжини |
| Rate limiting | `rate_limiter.yaml`: sliding window 5 спроб/хв на IP для `/api/login` |
| User enumeration | Узагальнене повідомлення `Invalid credentials.` незалежно від того, чи існує користувач |
| Журнал аудиту | `audit_logs` + `AuditLogger`, фіксований перелік типів подій |
| Незмінність журналу | PostgreSQL-тригер `prevent_audit_log_mutation` — блокує UPDATE/DELETE на рівні БД |
| Захист від IDOR | `AccountVoter` + `denyAccessUnlessGranted(AccountVoter::USE)` + повторна перевірка в `PaymentService` |
| Цілісність платежів | усі перевірки (власник, статус, баланс, ризик) — server-side в `PaymentService`; гроші — в `integer cents`, не float |
| Токен поза браузером | httpOnly cookie на стороні BFF |
| CSP | nonce-based (`kit.csp`): `script-src 'self' 'nonce-…'`; без `unsafe-inline` |
| Security headers | `SecurityHeadersSubscriber` на kernel.response (X-Content-Type-Options, X-Frame-Options, HSTS, CSP) |
| Error hygiene | `ApiExceptionSubscriber` приховує внутрішні деталі у production |
| Секрети поза кодом | `backend/.env` у `.gitignore`; лише `.env.example` із плейсхолдерами; production — секрети DigitalOcean App Platform (KMS-шар) |
| Ізоляція БД | backend-only доступ; жодного публічного інтерфейсу |
| Моніторинг помилок | Sentry на backend (`sentry.yaml`, `send_default_pii: false`) і frontend (`hooks.*.ts`) |
| DevSecOps | GitHub Actions: SAST (PHPStan + Semgrep), `composer`/`npm audit`, тести; DAST (OWASP ZAP) у `dast.yml` |

Перевірити живий стан конфігурації: `GET /api/security/config-check`.

## Структура репозиторію

```
ssdlc-lab/
├── docker-compose.yml      # db, backend, worker, frontend, mailpit
├── Makefile                # up / reset / test / lint / db-backup
├── .do/app.yaml            # DigitalOcean App Platform spec
├── .github/workflows/
│   ├── ci.yml              # PHPStan + Semgrep + audits + tests
│   └── dast.yml            # OWASP ZAP baseline
├── cloudflare/             # _headers, worker-gateway.js
├── docs/                   # ARCHITECTURE.md та інші документи
├── backend/                # Symfony 7 API
│   ├── config/packages/    # security, messenger, monolog, ...
│   ├── migrations/         # схема + тригер audit_logs
│   └── src/                # Controller, Entity, Service, ...
└── frontend/               # SvelteKit BFF + UI
    └── src/                # routes, lib/server, hooks.*
```

Поза git залишаються `vendor`, `node_modules`, `var`, `.svelte-kit`,
реальні `.env`-файли та приватні JWT-ключі.

## Production-розгортання

Локальний стек безпосередньо мапиться на production (конфігурація готова,
живий деплой не активовано):

```
https://bank-demo.example.com      → Cloudflare Pages (SvelteKit)
https://api.bank-demo.example.com  → Cloudflare DNS/WAF/TLS → DigitalOcean App Platform (Symfony)
                                     → DigitalOcean Managed PostgreSQL
```

- Frontend → Cloudflare Pages: [cloudflare/README.md](cloudflare/README.md)
  (`adapter-cloudflare`, `_headers`, опційний `worker-gateway.js`).
- Backend → DigitalOcean: [backend/Dockerfile](backend/Dockerfile) (FrankenPHP) +
  [.do/app.yaml](.do/app.yaml) — компоненти `api` та `worker` + Managed PostgreSQL
  з автоматичними бекапами. Встановіть `MAILER_DSN` на токен Postmark.

## Що реально, а що змочковане

| Можливість | Локально | Production |
|---|---|---|
| **MFA** | **справжній TOTP** (Google Authenticator / `app:totp` CLI) при увімкненні; інакше — email-OTP через Mailpit | справжній TOTP + email-OTP через Postmark |
| **Email-нотифікації** | реально надсилаються в Mailpit | Postmark |
| **Брокер + worker** | реальна Doctrine-черга + worker-контейнер | DO worker (або Redis/RabbitMQ) |
| Зовнішній платіжний шлюз | mock-адаптер | реальний card network / партнерський банк |
| SMS / Push | mock (запис у БД) | SMS/Push provider |
| WAF / DNS / Anti-DDoS / LB / TLS | відсутні | Cloudflare + DigitalOcean |
| SIEM | audit log + JSON stderr | поглинається SIEM-ом |
| KMS / Vault | `.env.local` / env vars | DO / Cloudflare encrypted env vars |
| Кластер БД | один PostgreSQL-контейнер | DigitalOcean Managed PostgreSQL |

## Результати безпекового аудиту

Аудит виконано в класичному форматі: збір інформації → планування → виявлення
вразливостей → тестування на проникнення → звітування.

### SAST + SCA

| Інструмент | Результат |
|---|---|
| PHPStan (level 5) | 0 помилок |
| Semgrep (155 правил, 80 файлів) | 0 знахідок |
| svelte-check | 0 errors, 0 warnings |
| `composer audit` | без security-радників |
| `npm audit` | 4 low (транзитивна `cookie <0.7.0` через `@sveltejs/kit`) |
| PHPUnit (unit + functional) | **63 tests, 172 assertions — PASS** |

### DAST — OWASP ZAP baseline

```
FAIL-NEW: 0
WARN-NEW: 8
PASS:     59
```

Жодного FAIL. Вісім WARN — низького/інформаційного рівня: wildcard у CSP для
Sentry ingest (свідомий), відсутність окремого anti-CSRF-токена (свідоме
рішення — `SameSite=Lax` cookie + origin-check + stateless API), відсутність
деяких заголовків на `/robots.txt` у dev-режимі (у production покрито через
`cloudflare/_headers`), і кілька інформаційних знахідок.

### Ручні перевірки

1. Підстановка чужого `account_id` в URL → **403** (`AccountVoter` спрацював).
2. Шість підряд невдалих логінів з однієї IP → **429** (rate limiter).
3. `DELETE` / `UPDATE` по `audit_logs` з psql → **exception** на рівні тригера БД.
4. Платіж понад поріг без MFA → **status: review** + код у Mailpit (`RiskService`).

## Команди

```bash
make up        # запуск усіх сервісів
make down      # зупинка
make logs      # tail логів
make reset     # очищення БД + перезавантаження демо-даних
make test      # PHPUnit + svelte-check
make lint      # PHPStan + svelte-check
make db-backup # pg_dump у ./backups (стенд-ін для managed backups)
make help      # список усіх targets
```

### Перевірка безпеки вручну

```bash
# Unit + functional тести backend
docker compose exec -T backend php bin/phpunit

# Типобезпека frontend
docker compose exec -T frontend npm run check

# PHPStan
docker compose exec -T backend vendor/bin/phpstan analyse --no-progress --memory-limit=512M

# Semgrep (SAST)
docker run --rm -v "$PWD:/src" -w /src semgrep/semgrep \
  semgrep scan --config p/security-audit --config p/secrets \
    --config p/php --config p/javascript backend/src frontend/src

# SCA: аудит залежностей
docker compose exec -T backend composer audit
docker compose exec -T frontend npm audit

# DAST: OWASP ZAP baseline
docker run --rm --network ssdlc-lab_default ghcr.io/zaproxy/zaproxy:stable \
  zap-baseline.py -t http://frontend:5173 -I

# Sentry: перевірка інтеграції
docker compose exec -T backend php bin/console sentry:test

# TOTP: згенерувати поточний код для dev-тестування MFA
docker compose exec backend php bin/console app:totp client@example.com

# Бекап БД
make db-backup
```

## Sentry — увімкнення

Інтеграція повністю налаштована; потрібен лише DSN:

```bash
SENTRY_DSN=<backend-dsn> PUBLIC_SENTRY_DSN=<frontend-dsn> docker compose up -d
docker compose exec backend php bin/console sentry:test
```

`send_default_pii: false` — Sentry не отримує імена/email-и користувачів
автоматично. Поруч із Sentry події безпеки емітуються як JSON у stderr
(SIEM-ready) і зберігаються в незмінній таблиці `audit_logs`.
