"""
Migration helper: export psy_items from the existing WordPress MySQL database
and import into the new Python app database.

Usage:
  pip install pymysql python-dotenv
  python scripts/seed_from_wordpress.py

Set these env vars (or update the WP_ constants below):
  WP_DB_HOST, WP_DB_NAME, WP_DB_USER, WP_DB_PASS, WP_DB_PREFIX
"""
import os
import sys
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from dotenv import load_dotenv
load_dotenv()

import pymysql
import pymysql.cursors

WP_DB_HOST   = os.getenv("WP_DB_HOST",   "localhost")
WP_DB_PORT   = int(os.getenv("WP_DB_PORT", "3306"))
WP_DB_NAME   = os.getenv("WP_DB_NAME",   "wordpress")
WP_DB_USER   = os.getenv("WP_DB_USER",   "root")
WP_DB_PASS   = os.getenv("WP_DB_PASS",   "")
WP_DB_PREFIX = os.getenv("WP_DB_PREFIX", "wp_")

NEW_DB_HOST = os.getenv("DB_HOST",   "localhost")
NEW_DB_PORT = int(os.getenv("DB_PORT", "3306"))
NEW_DB_NAME = os.getenv("DB_NAME",   "enternstech")
NEW_DB_USER = os.getenv("DB_USER",   "root")
NEW_DB_PASS = os.getenv("DB_PASS",   "")


def connect(host, port, name, user, password):
    return pymysql.connect(
        host=host, port=port, database=name, user=user, password=password,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
    )


def migrate_psy_items():
    print("Connecting to WordPress database…")
    src = connect(WP_DB_HOST, WP_DB_PORT, WP_DB_NAME, WP_DB_USER, WP_DB_PASS)

    print("Connecting to new database…")
    dst = connect(NEW_DB_HOST, NEW_DB_PORT, NEW_DB_NAME, NEW_DB_USER, NEW_DB_PASS)

    with src.cursor() as cur:
        cur.execute(f"SELECT * FROM {WP_DB_PREFIX}psy_items ORDER BY id")
        items = cur.fetchall()
    print(f"Found {len(items)} question items in WordPress DB.")

    inserted = 0
    skipped  = 0
    with dst.cursor() as cur:
        for item in items:
            try:
                cur.execute(
                    """INSERT INTO psy_items
                       (item_id, section, type, region, edu_min, edu_max, field,
                        difficulty, reverse_scored, trait_or_cluster, question_text,
                        option_a, option_b, option_c, option_d, correct)
                       VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                       ON DUPLICATE KEY UPDATE question_text=VALUES(question_text)""",
                    (
                        item["item_id"], item["section"], item["type"],
                        item["region"], item["edu_min"], item["edu_max"], item["field"],
                        item["difficulty"], item["reverse_scored"], item["trait_or_cluster"],
                        item["question_text"], item["option_a"], item["option_b"],
                        item["option_c"], item["option_d"], item["correct"],
                    ),
                )
                inserted += 1
            except Exception as e:
                print(f"  Skip {item['item_id']}: {e}")
                skipped += 1
        dst.commit()

    src.close(); dst.close()
    print(f"Done. Imported: {inserted}, Skipped: {skipped}")


def migrate_students():
    print("\nMigrating students…")
    src = connect(WP_DB_HOST, WP_DB_PORT, WP_DB_NAME, WP_DB_USER, WP_DB_PASS)
    dst = connect(NEW_DB_HOST, NEW_DB_PORT, NEW_DB_NAME, NEW_DB_USER, NEW_DB_PASS)

    with src.cursor() as cur:
        cur.execute(f"SELECT * FROM {WP_DB_PREFIX}enp_students ORDER BY id")
        students = cur.fetchall()
    print(f"Found {len(students)} students.")

    from app.auth import hash_password
    inserted = 0
    with dst.cursor() as cur:
        for s in students:
            email = s.get("email", "")
            if not email:
                continue
            # Create user if not exists
            cur.execute("SELECT id FROM users WHERE email=%s", (email,))
            user = cur.fetchone()
            if not user:
                cur.execute(
                    "INSERT INTO users (email, password_hash, role) VALUES (%s, %s, 'student')",
                    (email, hash_password("ChangeMe123!")),
                )
                uid = cur.lastrowid
            else:
                uid = user["id"]

            cur.execute(
                """INSERT INTO students
                   (full_name, email, phone, college, tech_stack, cv_url, live_project,
                    plan_id, sessions_total, sessions_used, cv_redesign_status, status, user_id, created_at)
                   VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                   ON DUPLICATE KEY UPDATE plan_id=VALUES(plan_id), status=VALUES(status)""",
                (
                    s.get("full_name",""), email, s.get("phone",""), s.get("college",""),
                    s.get("tech_stack",""), s.get("cv_url",""), s.get("live_project",""),
                    s.get("plan_id",""), s.get("sessions_total",0), s.get("sessions_used",0),
                    s.get("cv_redesign_status","pending"), s.get("status","pending"),
                    uid, s.get("created_at"),
                ),
            )
            inserted += 1
        dst.commit()

    src.close(); dst.close()
    print(f"Done. Migrated {inserted} students.")


def migrate_mentors():
    print("\nMigrating mentors…")
    src = connect(WP_DB_HOST, WP_DB_PORT, WP_DB_NAME, WP_DB_USER, WP_DB_PASS)
    dst = connect(NEW_DB_HOST, NEW_DB_PORT, NEW_DB_NAME, NEW_DB_USER, NEW_DB_PASS)

    with src.cursor() as cur:
        cur.execute(f"SELECT * FROM {WP_DB_PREFIX}enp_mentors ORDER BY id")
        mentors = cur.fetchall()
    print(f"Found {len(mentors)} mentors.")

    from app.auth import hash_password
    inserted = 0
    with dst.cursor() as cur:
        for m in mentors:
            email = m.get("email","")
            if not email:
                continue
            uid = None
            if m.get("status") == "approved":
                cur.execute("SELECT id FROM users WHERE email=%s", (email,))
                user = cur.fetchone()
                if not user:
                    cur.execute(
                        "INSERT INTO users (email, password_hash, role) VALUES (%s,%s,'mentor')",
                        (email, hash_password("ChangeMe123!")),
                    )
                    uid = cur.lastrowid
                else:
                    uid = user["id"]

            cur.execute(
                """INSERT INTO mentors
                   (full_name, email, phone, linkedin, photo_url, tech_stack,
                    available_slots, rate_per_session, status, user_id, created_at)
                   VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                   ON DUPLICATE KEY UPDATE status=VALUES(status)""",
                (
                    m.get("full_name",""), email, m.get("phone",""),
                    m.get("linkedin",""), m.get("photo_url",""), m.get("tech_stack",""),
                    m.get("available_slots",""), m.get("rate_per_session",0),
                    m.get("status","pending"), uid, m.get("created_at"),
                ),
            )
            inserted += 1
        dst.commit()

    src.close(); dst.close()
    print(f"Done. Migrated {inserted} mentors.")


if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description="Migrate data from WordPress to new DB")
    parser.add_argument("--all",      action="store_true", help="Migrate everything")
    parser.add_argument("--psy",      action="store_true", help="Migrate psy_items only")
    parser.add_argument("--students", action="store_true", help="Migrate students only")
    parser.add_argument("--mentors",  action="store_true", help="Migrate mentors only")
    args = parser.parse_args()

    if args.all or args.psy:      migrate_psy_items()
    if args.all or args.students: migrate_students()
    if args.all or args.mentors:  migrate_mentors()
    if not any(vars(args).values()):
        print("Use --all, --psy, --students, or --mentors")
