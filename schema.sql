-- Enterns Tech MySQL schema — matches FastAPI routes exactly
-- Run: mysql -u root -p seveleme_enternstech < schema.sql

USE seveleme_enternstech;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin','mentor','student') NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255),
    status ENUM('active','inactive','pending') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- mentors has its own auto-increment id; user_id is set only after approval
CREATE TABLE IF NOT EXISTS mentors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(30) DEFAULT '',
    linkedin VARCHAR(500) DEFAULT '',
    tech_stack TEXT,
    available_slots VARCHAR(100) DEFAULT '',
    photo_url VARCHAR(500) DEFAULT '',
    rate_per_session DECIMAL(10,2) DEFAULT 500.00,
    status ENUM('pending','approved','rejected','info_requested') DEFAULT 'pending',
    admin_note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- students has its own auto-increment id separate from user_id
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    full_name VARCHAR(255) DEFAULT '',
    email VARCHAR(255) NOT NULL,
    plan_id VARCHAR(50) DEFAULT '',
    sessions_total INT DEFAULT 0,
    sessions_used INT DEFAULT 0,
    status ENUM('active','pending','inactive') DEFAULT 'pending',
    mentor_id INT DEFAULT NULL,
    tech_stack TEXT,
    cv_url VARCHAR(500) DEFAULT '',
    cv_redesign_status VARCHAR(50) DEFAULT 'pending',
    live_project VARCHAR(255) DEFAULT '',
    college VARCHAR(255) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (mentor_id) REFERENCES mentors(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    mentor_id INT NOT NULL,
    scheduled_at DATETIME,
    status ENUM('scheduled','completed','cancelled') DEFAULT 'scheduled',
    rate_applied DECIMAL(10,2) DEFAULT 0,
    mentor_paid TINYINT(1) DEFAULT 0,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (mentor_id) REFERENCES mentors(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    plan_id VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    gateway VARCHAR(50) DEFAULT 'razorpay',
    gateway_order_id VARCHAR(100) DEFAULT '',
    gateway_payment_id VARCHAR(100) DEFAULT '',
    status ENUM('created','paid','failed','manual') DEFAULT 'created',
    student_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) DEFAULT 'mentor_change',
    student_id INT NOT NULL,
    mentor_id INT DEFAULT NULL,
    payload TEXT,
    status ENUM('open','approved','denied') DEFAULT 'open',
    admin_note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (mentor_id) REFERENCES mentors(id) ON DELETE SET NULL
);

-- Psychometric tables (used by existing psy routes)
CREATE TABLE IF NOT EXISTS psy_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_name VARCHAR(255) DEFAULT '',
    candidate_email VARCHAR(255) DEFAULT '',
    region VARCHAR(100) DEFAULT '',
    field VARCHAR(100) DEFAULT '',
    education_level VARCHAR(100) DEFAULT '',
    token VARCHAR(128) UNIQUE NOT NULL,
    status ENUM('pending','completed','expired') DEFAULT 'pending',
    selected_items_json LONGTEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    INDEX idx_token (token)
);

CREATE TABLE IF NOT EXISTS psy_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    section VARCHAR(10) NOT NULL,
    response_json LONGTEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_assessment_section (assessment_id, section),
    FOREIGN KEY (assessment_id) REFERENCES psy_assessments(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS psy_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT UNIQUE NOT NULL,
    strengths_index DECIMAL(5,2),
    strengths_band VARCHAR(20),
    strengths_clusters JSON,
    preference_profile VARCHAR(50),
    learning_index DECIMAL(5,2),
    learning_band VARCHAR(50),
    motivation_top3 JSON,
    engagement_index DECIMAL(5,2),
    engagement_band VARCHAR(20),
    trait_c DECIMAL(5,2),
    trait_e DECIMAL(5,2),
    trait_es DECIMAL(5,2),
    trait_o DECIMAL(5,2),
    trait_a DECIMAL(5,2),
    reasoning_score INT,
    reasoning_band VARCHAR(20),
    overall_band VARCHAR(20),
    recommendation TEXT,
    open_responses JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assessment_id) REFERENCES psy_assessments(id) ON DELETE CASCADE
);

-- Seed admin user (password set via ADMIN_PASSWORD env var — no DB hash needed for admin)
INSERT IGNORE INTO users (id, role, email, status) VALUES
(1, 'admin', 'admin@enternstech.com', 'active');
