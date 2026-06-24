# Enterns Tech Portal

A Python **FastAPI** web application for IT career mentoring — student enrolment, mentor management, Razorpay payments, psychometric assessments, and a full admin dashboard.

Deployed on **Vercel** (serverless Python), database on **Bluehost MySQL**.

---

## Tech stack

| Layer | Technology |
| --- | --- |
| Backend | FastAPI (Python 3.11+) |
| Templates | Jinja2 (server-side rendering) |
| Database | MySQL 8.0+ via PyMySQL |
| Payments | Razorpay |
| Email | SMTP (Gmail App Password) |
| Auth | JWT tokens (python-jose + bcrypt) |
| Hosting | Vercel (Python serverless) |
| Database host | Bluehost cPanel MySQL (remote access) |
| CI | GitHub Actions (import check on push) |

---

## Project structure

```
EnternsTech_Python/
├── api/
│   └── index.py              ← Vercel entry point — imports FastAPI app
├── app/
│   ├── main.py               ← App factory, router registration, static mount
│   ├── config.py             ← Pydantic settings (reads .env)
│   ├── database.py           ← PyMySQL helpers (fetchone, fetchall, execute)
│   ├── auth.py               ← JWT creation/validation, bcrypt hashing
│   ├── email_service.py      ← SMTP email templates
│   └── routes/
│       ├── auth.py           ← Login, logout, password reset
│       ├── admin.py          ← Full admin dashboard (students, mentors, payments …)
│       ├── student.py        ← Student dashboard, mentor matching, sessions
│       ├── mentor.py         ← Mentor dashboard
│       ├── payments.py       ← Razorpay order creation + webhook
│       ├── partner.py        ← Mentor application form
│       └── psychometric.py   ← Candidate assessment flow
│   └── services/
│       ├── psy_resolver.py   ← Builds per-candidate question paper
│       └── psy_scorer.py     ← Scoring engine (Likert, Big Five, MCQ)
├── templates/
│   ├── base.html             ← Shared nav layout
│   ├── home.html             ← Landing page
│   ├── login.html
│   ├── admin/                ← Admin dashboard templates
│   ├── student/
│   ├── mentor/
│   ├── partner/
│   └── psy/                  ← Candidate assessment UI
├── public/
│   ├── css/portal.css        ← Dark-theme design system
│   └── css/psychometric.css
├── migrations/
│   ├── 001_schema.sql        ← All core tables
│   └── 002_transactions_revenue.sql  ← app_settings + manual_revenue
├── .env                      ← Local secrets (never committed)
├── .env.example              ← Template — copy to .env and fill in
├── vercel.json               ← Vercel deployment config
├── requirements.txt          ← Python dependencies
└── .github/workflows/
    └── deploy.yml            ← CI — checks imports on every push
```

---

## Quick start (local development)

### 1. Prerequisites

- Python 3.11+ — download from [python.org](https://python.org) (tick "Add to PATH")
- A MySQL database (Bluehost cPanel — see below)

### 2. Clone and set up

```bash
# Clone the repo
git clone https://github.com/<you>/EnternsTech_Python.git
cd EnternsTech_Python

# Create and activate virtual environment (Windows)
py -m venv .venv
.venv\Scripts\Activate.ps1

# Install dependencies
pip install -r requirements.txt
```

### 3. Configure environment

Copy `.env.example` to `.env` (already done) and fill in your values:

```env
DB_HOST=box1234.bluehost.com   # your Bluehost server hostname
DB_NAME=u12345678_enternstech  # full name with cPanel prefix
DB_USER=u12345678_entuser
DB_PASS=your_password
SECRET_KEY=<run: py -c "import secrets; print(secrets.token_hex(32))">
ADMIN_PASSWORD=your_admin_password
RAZORPAY_KEY_ID=rzp_test_XXXX
RAZORPAY_KEY_SECRET=XXXX
SMTP_USER=your@gmail.com
SMTP_PASS=xxxx xxxx xxxx xxxx  # Gmail App Password
APP_BASE_URL=http://localhost:8000
APP_ENV=development
```

### 4. Run the server

```bash
uvicorn app.main:app --reload --port 8000
```

Open [http://localhost:8000](http://localhost:8000). Admin at `/admin`, login at `/login`.

---

## Bluehost MySQL setup

1. **cPanel → MySQL Databases** → create database `enternstech` (becomes `u12345678_enternstech`)
2. Create a user and grant ALL PRIVILEGES on that database
3. **cPanel → Remote MySQL** → add `%` as an access host (required for Vercel's dynamic IPs)
4. Note your server hostname from cPanel → General Information (e.g. `box1234.bluehost.com`)
5. **cPanel → phpMyAdmin** → select the database → SQL tab → paste and run `migrations/001_schema.sql`, then `migrations/002_transactions_revenue.sql`

See the [full step-by-step guide](https://claude.ai/code/artifact/7861decf-0865-47a8-963b-9647d85a7a81) for screenshots and troubleshooting.

---

## Deploy to Vercel

Vercel auto-deploys from GitHub on every push to `main`. One-time setup:

1. Push code to GitHub
2. Go to [vercel.com](https://vercel.com) → New Project → Import your GitHub repo
3. Vercel detects `vercel.json` automatically — leave Framework as "Other"
4. Before clicking Deploy, add all environment variables from `.env` in the Vercel UI
5. Set `APP_BASE_URL` to your Vercel URL (e.g. `https://enternstech.vercel.app`)
6. Set `APP_ENV=production` and use your live Razorpay keys
7. Click Deploy

After the first deploy, every `git push origin main` triggers an automatic redeploy.

---

## Environment variables reference

| Variable | Description | Example |
| --- | --- | --- |
| `DB_HOST` | Bluehost server hostname | `box1234.bluehost.com` |
| `DB_PORT` | MySQL port | `3306` |
| `DB_NAME` | Database name (with prefix) | `u12345678_enternstech` |
| `DB_USER` | DB username (with prefix) | `u12345678_entuser` |
| `DB_PASS` | DB password | — |
| `SECRET_KEY` | JWT signing key (32 hex bytes) | `3e7d86…` |
| `ADMIN_PASSWORD` | Admin portal login password | — |
| `RAZORPAY_KEY_ID` | Razorpay public key | `rzp_live_XXX` |
| `RAZORPAY_KEY_SECRET` | Razorpay secret key | — |
| `SMTP_HOST` | SMTP server | `smtp.gmail.com` |
| `SMTP_PORT` | SMTP port | `587` |
| `SMTP_USER` | SMTP username / Gmail address | — |
| `SMTP_PASS` | Gmail App Password | — |
| `SMTP_FROM_NAME` | Sender display name | `Enterns Tech` |
| `SMTP_FROM_EMAIL` | Sender email address | `noreply@enternstech.com` |
| `ADMIN_EMAIL` | Admin notification address | `admin@enternstech.com` |
| `MENTOR_EMAIL` | Mentor team address | `mentor@enternstech.com` |
| `APP_BASE_URL` | Full public URL of the app | `https://enternstech.vercel.app` |
| `APP_ENV` | `development` or `production` | `production` |

---

## Key routes

| Path | What it does |
| --- | --- |
| `/` | Home / landing page |
| `/login` | Login (admin, mentor, student) |
| `/admin` | Admin dashboard overview |
| `/admin/students` | Student management |
| `/admin/mentors` | Mentor management + applications |
| `/admin/payments` | Razorpay payment log |
| `/admin/sessions` | Session scheduling |
| `/admin/assessments` | Psychometric assessment management |
| `/admin/manual-revenue` | Manual / offline revenue tracking |
| `/student/dashboard` | Student dashboard |
| `/mentor/dashboard` | Mentor dashboard |
| `/apply` | Mentor application form |
| `/assessment` | Candidate psychometric assessment (`?t=TOKEN`) |
| `/health` | Health check — returns `{"status":"ok"}` |

---

© 2026 Enterns Tech — www.enternstech.com
