-- ============================================================
-- Migration 005 — Session booking (mentor slots + booking metadata)
-- Run after 004_catalog.sql
-- ============================================================

SET NAMES utf8mb4;

-- Mentor publishes weekly recurring availability as a JSON array.
-- e.g. [{"day":"MO","start":"18:00","end":"19:00"},...]
ALTER TABLE mentors
  ADD COLUMN IF NOT EXISTS slots_json LONGTEXT NULL;

-- Extra columns on sessions for the booking model
ALTER TABLE sessions
  ADD COLUMN IF NOT EXISTS booked_by   ENUM('student','mentor','admin') NOT NULL DEFAULT 'admin',
  ADD COLUMN IF NOT EXISTS topic       VARCHAR(255) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS meeting_link VARCHAR(500) NOT NULL DEFAULT '';

-- Also change 'planned' → allow 'scheduled' status (v1 booking creates 'scheduled' rows)
ALTER TABLE sessions
  MODIFY COLUMN status ENUM('planned','scheduled','completed','cancelled') NOT NULL DEFAULT 'planned';
