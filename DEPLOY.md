# Deployment Guide — Enterns Tech Python App

## Stack
- **Python** 3.11+ / FastAPI
- **Database**: MySQL (PlanetScale / Aiven / Railway / Bluehost cPanel)
- **Payments**: Razorpay
- **Hosting**: Vercel (frontend + API)
- **DNS**: Bluehost → Vercel

---

## Step 1 — Set up MySQL Database

### Option A: PlanetScale (recommended for Vercel)
1. Create account at planetscale.com
2. Create database `enternstech`
3. Get connection string → extract host, user, password
4. Enable "allow public connections" or use connection string directly

### Option B: Aiven MySQL
1. Create account at aiven.io
2. Create MySQL service → copy host/port/user/pass/dbname
3. SSL is required — add `ssl={"ca": "/path/to/ca.pem"}` to PyMySQL connect call

### Option C: Bluehost cPanel MySQL (if staying on Bluehost)
1. cPanel → MySQL Databases → Create database + user + grant all privileges
2. Enable "Remote MySQL" in cPanel → add Vercel's IP ranges
3. Vercel outbound IPs: check Vercel docs for the current list

### Run Schema Migration
```bash
# Connect to your MySQL and run:
mysql -h YOUR_HOST -u YOUR_USER -p YOUR_DB < migrations/001_schema.sql
```

---

## Step 2 — Migrate Data from WordPress

```bash
pip install -r requirements.txt

# Set WordPress DB credentials
export WP_DB_HOST=your-wp-mysql-host
export WP_DB_NAME=your_wp_database
export WP_DB_USER=your_wp_user
export WP_DB_PASS=your_wp_password
export WP_DB_PREFIX=wp_   # or whatever prefix you used

# Set new DB credentials (same as .env)
export DB_HOST=your-new-db-host
export DB_NAME=enternstech
export DB_USER=your_user
export DB_PASS=your_password

# Run migration
python scripts/seed_from_wordpress.py --all
```

---

## Step 3 — Configure Environment Variables

Copy `.env.example` to `.env` and fill in all values.

**Critical values:**
```
SECRET_KEY=<run: python -c "import secrets; print(secrets.token_hex(32))">
ADMIN_PASSWORD=<your admin portal password>
RAZORPAY_KEY_ID=rzp_live_...
RAZORPAY_KEY_SECRET=...
SMTP_USER=your@gmail.com
SMTP_PASS=<Gmail App Password>
APP_BASE_URL=https://yourdomain.com
```

---

## Step 4 — Deploy to Vercel

### Install Vercel CLI
```bash
npm i -g vercel
```

### Deploy
```bash
cd /path/to/EnternsTech
vercel login
vercel --prod
```

### Add Environment Variables in Vercel Dashboard
1. Go to vercel.com → your project → Settings → Environment Variables
2. Add all variables from `.env.example`
3. **Never commit `.env` to git**

---

## Step 5 — Connect Domain (Bluehost DNS → Vercel)

### Method A: Change Nameservers (recommended)
1. **Vercel dashboard** → your project → Settings → Domains → Add domain
2. Note the Vercel nameservers shown (e.g. `ns1.vercel-dns.com`, `ns2.vercel-dns.com`)
3. **Bluehost** → Domains → Nameservers → Change to Vercel's nameservers
4. Wait 24–48 hours for DNS propagation

### Method B: Add CNAME/A records (keep Bluehost DNS)
1. **Vercel dashboard** → Domains → Add your domain → copy the A record value (e.g. `76.76.21.21`)
2. **Bluehost** → cPanel → Zone Editor → Add A record:
   - Name: `@` (or your domain)
   - Value: `76.76.21.21`
3. For `www` subdomain: Add CNAME `www` → `cname.vercel-dns.com`

---

## Step 6 — Test the Deployment

| URL | Expected |
|-----|----------|
| `https://yourdomain.com` | Home page with plans |
| `https://yourdomain.com/login` | Login page |
| `https://yourdomain.com/admin` | Admin portal (login: admin + your ADMIN_PASSWORD) |
| `https://yourdomain.com/partner` | Mentor application form |
| `https://yourdomain.com/health` | `{"status":"ok"}` |

### Admin Login
- Username: `admin` (or `admin@enternstech.com`)
- Password: value of `ADMIN_PASSWORD` in your env

### Student Login
- After a student pays (or you manually activate), they receive a set-password email
- They log in at `/login` with their email + chosen password

### Mentor Login
- After admin approves, mentor gets a set-password email
- They log in at `/login` → redirected to `/mentor`

---

## Local Development

```bash
pip install -r requirements.txt
cp .env.example .env  # fill in values

# Run locally
uvicorn app.main:app --reload --port 8000
```

Open http://localhost:8000

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| `ModuleNotFoundError` | Run `pip install -r requirements.txt` |
| DB connection refused | Check DB_HOST, DB_PORT, firewall/remote access settings |
| Email not sending | Check SMTP_USER/PASS, ensure Gmail "App Password" not account password |
| Razorpay error | Verify KEY_ID and KEY_SECRET in env, check test vs live keys |
| 404 on Vercel | Check `vercel.json` routes, ensure `api/index.py` exists |
| Static files 404 | Ensure `public/` folder is committed to git |
