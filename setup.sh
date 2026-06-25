#!/bin/bash
# ============================================================
# EnternsTech — one-shot server setup
# Run in cPanel Terminal:  bash setup.sh
#
# BEFORE RUNNING: edit the two values just below (DBUSER, DBPASS).
#   - No space after -p is required by mysql; the script handles that.
#   - Delete this file after it succeeds (it contains your DB password).
# ============================================================

# ---- EDIT THESE TWO LINES ONLY ----
DBUSER="seveleme_enternstech_admin"
DBPASS="Nokia@78674067!!"
# -----------------------------------

DBNAME="seveleme_enternstech"
APPDIR="/home3/seveleme/enternstech_app"

set -e  # stop immediately if any command fails

echo ">>> Moving into app directory..."
cd "$APPDIR"

echo ">>> Activating virtual environment..."
source venv/bin/activate

echo ">>> Confirming dependencies are installed..."
pip install -r requirements.txt --quiet

echo ">>> Loading database migrations in order..."
for f in 001_schema 002_transactions_revenue 003_auth_reset 004_catalog 005_bookings; do
  echo "    - applying migrations/${f}.sql"
  mysql -u "$DBUSER" -p"$DBPASS" "$DBNAME" < "migrations/${f}.sql"
done

echo ">>> Seeding pricing catalog..."
python scripts/seed_catalog.py

echo ">>> Creating admin account (set-password link will print below)..."
python scripts/bootstrap_admin.py

echo ""
echo ">>> DONE. Copy the admin set-password link printed above."
echo ">>> Next: register the app in cPanel Application Manager, then Restart."
echo ">>> SECURITY: delete this file now (it holds your DB password)."
