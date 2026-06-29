# CLAUDE.md — Enterns Tech Portal (Python)

## Stack & hosting constraints

| Item | Value |
|------|-------|
| Runtime | Python 3.9, FastAPI + PyMySQL + Jinja2 |
| WSGI | Phusion Passenger on Bluehost shared hosting |
| Entry point | `passenger_wsgi.py` |
| **Hard limits** | **NO Redis, NO long-lived background processes, NO APScheduler / Celery / threading** — Passenger kills workers unpredictably |

## Project layout

```text
EnternsTech_Python/
├── app/
│   ├── main.py              FastAPI app, middleware registration, top-level routes
│   ├── auth.py              JWT helpers, bcrypt, get_current_user, require_role
│   ├── config.py            pydantic-settings Settings (loaded from .env at startup)
│   ├── database.py          fetchone / fetchall / execute / execute_many (PyMySQL)
│   ├── email_service.py     SMTP helpers: send_mail, send_booking_confirmation, …
│   ├── middleware/
│   │   └── csrf.py          Double-submit cookie CSRF (G3)
│   ├── routes/
│   │   ├── auth.py          /login  /logout  /forgot-password  /reset  /set-password
│   │   ├── mentee.py        /mentee/*  (dashboard, booking, invoice, reviews)
│   │   ├── mentor.py        /mentor/*  (availability, sessions, earnings)
│   │   ├── admin.py         /admin/*   (overview, mentees, mentors, payments, …)
│   │   ├── payments.py      /payments/*  (Razorpay order + webhook)
│   │   ├── partner.py       /partner/apply
│   │   ├── cron.py          /internal/cron/*  (token-protected, Passenger-safe)
│   │   └── psychometric.py  /psychometric/*
│   └── services/
│       ├── rate_limiter.py  DB-backed rate limiter (G1)
│       ├── escrow.py        lock / release / refund escrow funds per session
│       ├── reviews.py       submit, maybe_release, release_due, _lazy_release_due
│       ├── scheduling.py    cancel_session, reschedule
│       ├── capacity.py      attach mentor, capacity checks
│       ├── invoice.py       build_invoice
│       ├── catalog.py       get_catalog, get_plan
│       ├── matching.py      filtered_mentors, fee_breakdown (7.5 % each side)
│       ├── meeting.py       jitsi_url
│       ├── privacy.py       student_view_of_mentor (strips contact info)
│       ├── tz.py            to_utc, fmt_local, valid_tz, COMMON_TZS
│       └── psy_*.py         psychometric resolver + scorer
├── templates/               Jinja2 templates; dark-theme --enp-* CSS variables
│   ├── admin/layout.html    Admin sidebar layout (CSRF meta tag + JS auto-inject)
│   └── student/             Mentee-facing templates
├── migrations/              SQL files numbered 001–NNN; apply in order
│   └── 007_security.sql     rate_limits table
├── tests/
│   ├── conftest.py          Sets APP_ENV / SECRET_KEY / CRON_SECRET before imports
│   ├── test_csrf.py         CSRF middleware tests (no DB needed)
│   ├── test_rate_limiter.py Rate limiter unit tests (DB mocked)
│   └── test_cron.py         Cron endpoint tests (DB mocked)
├── requirements.txt         Runtime dependencies
├── requirements-dev.txt     pytest
└── .env                     Not committed — DB creds, SECRET_KEY, CRON_SECRET, …
```

## Mandatory conventions

Apply to **every `.py` file you touch**:

1. `from __future__ import annotations` — first non-comment line
2. DB access **only** via `fetchone` / `fetchall` / `execute` / `execute_many` from `app.database` — never raw pymysql calls in routes or services
3. Money is **paise (int)** everywhere; format for display only at the template layer
4. Datetimes are **naive UTC** in the DB; convert to local only via `app.services.tz` helpers
5. Platform fee: 7.5 % each side (`settings.PLATFORM_FEE_MENTEE_PCT` / `_MENTOR_PCT`)
6. Privacy: never expose cross-role contact info (mentee email/phone hidden from mentor and vice-versa — use `privacy.student_view_of_mentor`)
7. No comments explaining *what* the code does; only add a comment when the *why* is non-obvious

## Database

`app/database.py` — lazy connections (a new connection opens and closes per call; no pool):

| Helper | Returns |
|--------|---------|
| `fetchone(sql, params)` | `dict \| None` |
| `fetchall(sql, params)` | `list[dict]` |
| `execute(sql, params)` | `int` — lastrowid for INSERT, rowcount for UPDATE/DELETE |
| `execute_many(sql, params_list)` | `int` — rowcount |

### Migrations

```bash
mysql -u <user> -p <db> < migrations/007_security.sql
# Apply each numbered file in order on the live DB.
```

### Key tables

`users`, `students` ← mentees live here, `mentors`, `sessions`, `payments`,
`password_tokens`, `reviews`, `escrow`, `requests`, `rate_limits`, `app_settings`

> The app code uses the role name `"student"` in JWTs and the `students` table,
> but the UI and URLs say "mentee".  Do not rename the DB column or JWT field.

## Auth

- JWT stored in httponly cookie **`enp_token`**; roles: `admin`, `student`, `mentor`
- `get_current_user(request)` → JWT payload dict or `None`
- `require_role(role)` → FastAPI dependency; redirects to `/login` on failure
- Password tokens in `password_tokens` table — 64-char hex, sha256-hashed at rest

## Security invariants (Phase 1 / G1-G3)

| Invariant | Where |
|-----------|-------|
| CSRF | `CSRFMiddleware` (double-submit cookie) — exempt: `/api/`, `/internal/`, `/health`, `/static/`, `application/json` |
| Rate limit | `app.services.rate_limiter` keyed on **(email, IP)** — login 5/30 min, forgot 3/60 min |
| Invoice IDOR | `payments.student_id == mentee.id` checked from DB row before `build_invoice()` |

## Cron (G7)

`GET /internal/cron/release-reviews?secret=CRON_SECRET` calls `reviews_svc.release_due()`.

Add `CRON_SECRET` to `.env`.  Register in Bluehost cPanel → Cron Jobs:

```bash
curl -s -H "X-Cron-Secret: YOUR_SECRET" \
     "https://yourdomain.com/internal/cron/release-reviews" >/dev/null 2>&1
```

Recommended frequency: every 6 hours.  An empty `CRON_SECRET` always returns 403.

## Running locally

```bash
cp .env.example .env   # fill in DB creds, SECRET_KEY, etc.
python -m uvicorn app.main:app --reload
```

## Running tests

```bash
pip install -r requirements-dev.txt
pytest tests/ -v
# No real DB or SMTP needed — tests mock app.database helpers.
```

## Out of scope / future work

- G9 referral redemption — net-new feature, spec separately
- Phase 3: mentor payout flow, concurrency-safe slot booking, DB transactions
- Phase 4: migrate `payments.amount` float → paise int (BACKUP FIRST gate)
- Phase 5: pagination on admin lists, admin audit trail
