-- ============================================================
-- Enterns Tech Portal — MySQL Schema
-- Run once on a fresh database.
-- Compatible with: MySQL 8.0+, PlanetScale, Aiven, Railway
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ── users ───────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(255)  NOT NULL,
  password_hash VARCHAR(255)  NOT NULL DEFAULT '',
  role          ENUM('admin','mentor','student') NOT NULL DEFAULT 'student',
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── mentors ──────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mentors (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name        VARCHAR(200)  NOT NULL DEFAULT '',
  email            VARCHAR(255)  NOT NULL DEFAULT '',
  phone            VARCHAR(30)   NOT NULL DEFAULT '',
  linkedin         VARCHAR(500)  NOT NULL DEFAULT '',
  photo_url        VARCHAR(500)  NOT NULL DEFAULT '',
  tech_stack       TEXT,
  available_slots  VARCHAR(100)  NOT NULL DEFAULT '',
  rate_per_session DECIMAL(10,2) NOT NULL DEFAULT 0,
  extra_fields     JSON,
  admin_note       TEXT,
  status           ENUM('pending','approved','rejected','info_requested') NOT NULL DEFAULT 'pending',
  user_id          BIGINT UNSIGNED DEFAULT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status),
  KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── students ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS students (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name          VARCHAR(200) NOT NULL DEFAULT '',
  email              VARCHAR(255) NOT NULL DEFAULT '',
  phone              VARCHAR(30)  NOT NULL DEFAULT '',
  college            VARCHAR(200) NOT NULL DEFAULT '',
  tech_stack         TEXT,
  cv_url             VARCHAR(500) NOT NULL DEFAULT '',
  live_project       VARCHAR(500) NOT NULL DEFAULT '',
  plan_id            VARCHAR(30)  NOT NULL DEFAULT '',
  sessions_total     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  sessions_used      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  mentor_id          BIGINT UNSIGNED DEFAULT NULL,
  cv_redesign_status ENUM('pending','in_progress','done') NOT NULL DEFAULT 'pending',
  status             ENUM('pending','active','inactive') NOT NULL DEFAULT 'pending',
  user_id            BIGINT UNSIGNED DEFAULT NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email),
  KEY idx_mentor_id (mentor_id),
  KEY idx_status (status),
  KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── payments ──────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payments (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id         BIGINT UNSIGNED DEFAULT NULL,
  email              VARCHAR(255) NOT NULL DEFAULT '',
  plan_id            VARCHAR(30)  NOT NULL DEFAULT '',
  amount             DECIMAL(12,2) NOT NULL DEFAULT 0,
  currency           VARCHAR(5)   NOT NULL DEFAULT 'INR',
  gateway            ENUM('razorpay','manual','paypal') NOT NULL DEFAULT 'razorpay',
  gateway_order_id   VARCHAR(100) NOT NULL DEFAULT '',
  gateway_payment_id VARCHAR(100) NOT NULL DEFAULT '',
  status             ENUM('created','paid','failed','refunded') NOT NULL DEFAULT 'created',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_student_id (student_id),
  KEY idx_status (status),
  KEY idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── sessions ──────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sessions (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id    BIGINT UNSIGNED NOT NULL,
  mentor_id     BIGINT UNSIGNED NOT NULL,
  scheduled_at  DATETIME NOT NULL,
  duration_min  SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  status        ENUM('planned','completed','cancelled') NOT NULL DEFAULT 'planned',
  mentor_paid   TINYINT(1) NOT NULL DEFAULT 0,
  rate_applied  DECIMAL(10,2) NOT NULL DEFAULT 0,
  notes         TEXT,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_student_id (student_id),
  KEY idx_mentor_id  (mentor_id),
  KEY idx_status     (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── requests ──────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS requests (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  type        VARCHAR(50)   NOT NULL DEFAULT 'mentor_change',
  student_id  BIGINT UNSIGNED NOT NULL,
  mentor_id   BIGINT UNSIGNED DEFAULT NULL,
  payload     TEXT,
  status      ENUM('open','approved','denied') NOT NULL DEFAULT 'open',
  admin_note  TEXT,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_student_id (student_id),
  KEY idx_status     (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── feedback ──────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS feedback (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id  BIGINT UNSIGNED NOT NULL,
  from_role   ENUM('student','mentor') NOT NULL,
  from_id     BIGINT UNSIGNED NOT NULL,
  about_id    BIGINT UNSIGNED NOT NULL,
  rating      TINYINT UNSIGNED NOT NULL DEFAULT 5,
  comments    TEXT,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── psy_items ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS psy_items (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id          VARCHAR(20)   NOT NULL DEFAULT '',
  section          TINYINT UNSIGNED NOT NULL DEFAULT 0,
  type             VARCHAR(20)   NOT NULL DEFAULT '',
  region           VARCHAR(20)   NOT NULL DEFAULT 'ALL',
  edu_min          VARCHAR(10)   NOT NULL DEFAULT 'ALL',
  edu_max          VARCHAR(10)   NOT NULL DEFAULT 'ALL',
  field            VARCHAR(30)   NOT NULL DEFAULT 'ALL',
  difficulty       TINYINT UNSIGNED DEFAULT NULL,
  reverse_scored   VARCHAR(1)    NOT NULL DEFAULT 'N',
  trait_or_cluster VARCHAR(30)   NOT NULL DEFAULT '',
  question_text    TEXT          NOT NULL,
  option_a         VARCHAR(500)  NOT NULL DEFAULT '',
  option_b         VARCHAR(500)  NOT NULL DEFAULT '',
  option_c         VARCHAR(500)  NOT NULL DEFAULT '',
  option_d         VARCHAR(500)  NOT NULL DEFAULT '',
  correct          VARCHAR(500)  NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_item_id (item_id),
  KEY idx_section (section),
  KEY idx_region  (region),
  KEY idx_field   (field)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── psy_assessments ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS psy_assessments (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  token           VARCHAR(64)   NOT NULL DEFAULT '',
  candidate_name  VARCHAR(200)  NOT NULL DEFAULT '',
  candidate_email VARCHAR(200)  NOT NULL DEFAULT '',
  candidate_phone VARCHAR(30)   NOT NULL DEFAULT '',
  region          VARCHAR(10)   NOT NULL DEFAULT 'UK',
  region_source   VARCHAR(20)   NOT NULL DEFAULT 'admin',
  education_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
  field           VARCHAR(30)   NOT NULL DEFAULT '',
  created_by      BIGINT UNSIGNED DEFAULT NULL,
  payment_ref     VARCHAR(200)  NOT NULL DEFAULT '',
  status          VARCHAR(20)   NOT NULL DEFAULT 'pending',
  expires_at      DATETIME      NOT NULL,
  selected_items  LONGTEXT      DEFAULT NULL,
  defaulted       TINYINT(1)    NOT NULL DEFAULT 0,
  razorpay_auto   TINYINT(1)    NOT NULL DEFAULT 0,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token (token),
  KEY idx_status (status),
  KEY idx_region (region),
  KEY idx_field  (field)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── psy_responses ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS psy_responses (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  assessment_id BIGINT UNSIGNED NOT NULL,
  item_id       VARCHAR(20)   NOT NULL DEFAULT '',
  section       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  answer_value  LONGTEXT      NOT NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assessment_item (assessment_id, item_id),
  KEY idx_assessment_id (assessment_id),
  KEY idx_section (section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── psy_scores ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS psy_scores (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  assessment_id       BIGINT UNSIGNED NOT NULL,
  strengths_index     DECIMAL(5,2)  DEFAULT NULL,
  strengths_clusters  LONGTEXT      DEFAULT NULL,
  preference_profile  VARCHAR(500)  NOT NULL DEFAULT '',
  learning_index      DECIMAL(5,2)  DEFAULT NULL,
  motivation_top3     LONGTEXT      DEFAULT NULL,
  engagement_index    DECIMAL(5,2)  DEFAULT NULL,
  trait_c             DECIMAL(5,2)  DEFAULT NULL,
  trait_e             DECIMAL(5,2)  DEFAULT NULL,
  trait_es            DECIMAL(5,2)  DEFAULT NULL,
  trait_o             DECIMAL(5,2)  DEFAULT NULL,
  trait_a             DECIMAL(5,2)  DEFAULT NULL,
  reasoning_score     TINYINT UNSIGNED DEFAULT NULL,
  reasoning_band      VARCHAR(20)   NOT NULL DEFAULT '',
  open_responses      LONGTEXT      DEFAULT NULL,
  overall_band        VARCHAR(20)   NOT NULL DEFAULT '',
  recommendation      TEXT          DEFAULT NULL,
  computed_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assessment_id (assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── psy_rate_limits ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS psy_rate_limits (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  token        VARCHAR(64) NOT NULL DEFAULT '',
  attempted_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_token (token),
  KEY idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
