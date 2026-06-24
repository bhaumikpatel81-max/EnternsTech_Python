"""
One-time (idempotent) admin bootstrap script.

Usage:
    python scripts/bootstrap_admin.py

What it does:
  1. Finds or creates a users row for admin@enternstech.com with role='admin'.
  2. Invalidates any previously issued unused set-password tokens for that user.
  3. Generates a fresh single-use set-password token (valid SET_TOKEN_EXPIRY_MINUTES).
  4. Prints the link to stdout (works even if SMTP is not yet configured).
  5. Attempts to email the link to admin@enternstech.com.

Run again at any time to issue a fresh link (e.g. if the admin is locked out).
"""

import hashlib
import os
import secrets
import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path

# Allow running from any directory
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from app.config import settings
from app.database import fetchone, execute


def main() -> None:
    admin_email = settings.ADMIN_EMAIL  # admin@enternstech.com

    user = fetchone("SELECT * FROM users WHERE email=%s LIMIT 1", (admin_email,))
    if user:
        uid = int(user["id"])
        if user.get("role") != "admin":
            execute("UPDATE users SET role='admin' WHERE id=%s", (uid,))
        print(f"[bootstrap_admin] Found admin user (id={uid}, email={admin_email})")
    else:
        uid = execute(
            "INSERT INTO users (email, password_hash, role, status) VALUES (%s, NULL, 'admin', 'pending')",
            (admin_email,),
        )
        print(f"[bootstrap_admin] Created admin user (id={uid}, email={admin_email})")

    # Invalidate any previous unused set tokens for this user
    invalidated = execute(
        "UPDATE password_tokens SET used=1 WHERE user_id=%s AND purpose='set' AND used=0",
        (uid,),
    )
    if invalidated:
        print(f"[bootstrap_admin] Invalidated {invalidated} old set-password token(s)")

    # Generate fresh single-use token
    raw_token = secrets.token_hex(32)
    token_hash = hashlib.sha256(raw_token.encode()).hexdigest()
    expires = datetime.now(timezone.utc) + timedelta(minutes=settings.SET_TOKEN_EXPIRY_MINUTES)

    execute(
        """INSERT INTO password_tokens (user_id, token_hash, purpose, expires_at)
           VALUES (%s, %s, 'set', %s)""",
        (uid, token_hash, expires.replace(tzinfo=None)),
    )

    set_pw_url = f"{settings.APP_BASE_URL.rstrip('/')}/set-password?token={raw_token}"
    expiry_hours = settings.SET_TOKEN_EXPIRY_MINUTES // 60

    print(f"\n{'='*60}")
    print(f"Set-password link (valid {expiry_hours}h):")
    print(f"  {set_pw_url}")
    print(f"{'='*60}\n")

    # Try to email the link (will silently fail if SMTP not configured yet)
    try:
        from app import email_service
        body = f"""
<h2>Admin Portal — Set Your Password</h2>
<p>Use the link below to set your password for the Enterns Tech admin portal.</p>
<p style="margin:24px 0">
  <a href="{set_pw_url}"
     style="background:#22D3EE;color:#05080f;padding:12px 28px;border-radius:8px;
            text-decoration:none;font-weight:700;display:inline-block">
     Set Your Password &rarr;
  </a>
</p>
<p style="color:#94a3b8;font-size:13px">
  This link expires in {expiry_hours} hours and can only be used once.
</p>
<p style="color:#94a3b8;font-size:13px">
  If the button doesn't work, copy this link:<br>{set_pw_url}
</p>
"""
        ok = email_service.send_mail(
            admin_email,
            "Enterns Tech Admin — Set your password",
            body,
            bcc_admin=False,
        )
        if ok:
            print(f"[bootstrap_admin] Link also emailed to {admin_email}")
        else:
            print(f"[bootstrap_admin] Email failed (SMTP issue?) — use the printed link above")
    except Exception as exc:
        print(f"[bootstrap_admin] Email skipped ({exc}) — use the printed link above")


if __name__ == "__main__":
    main()
