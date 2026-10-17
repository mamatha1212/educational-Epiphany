-- ========================================================
-- EEMT 2027 Database Schema (MySQL & SQLite Compatible)
-- ========================================================

CREATE TABLE IF NOT EXISTS schools_master (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    district VARCHAR(100) NOT NULL,
    mandal VARCHAR(100) NOT NULL,
    school_name VARCHAR(255) NOT NULL,
    unique_code VARCHAR(50) NOT NULL,
    is_registered TINYINT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_sm_district ON schools_master (district);
CREATE INDEX IF NOT EXISTS idx_sm_mandal ON schools_master (mandal);
CREATE INDEX IF NOT EXISTS idx_sm_code ON schools_master (unique_code);

CREATE TABLE IF NOT EXISTS school_registrations_2027 (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    reference_id VARCHAR(50) NOT NULL UNIQUE,
    school_id INTEGER NULL,
    district VARCHAR(100) NOT NULL,
    mandal VARCHAR(100) NOT NULL,
    school_name VARCHAR(255) NOT NULL,
    unique_code VARCHAR(50) NOT NULL,
    contact_person VARCHAR(150) NOT NULL,
    designation VARCHAR(50) NOT NULL,
    mobile VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL,
    ip_address VARCHAR(50) NULL,
    status VARCHAR(30) DEFAULT 'submitted',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_sr_ref ON school_registrations_2027 (reference_id);
CREATE INDEX IF NOT EXISTS idx_sr_dist ON school_registrations_2027 (district);
CREATE INDEX IF NOT EXISTS idx_sr_code ON school_registrations_2027 (unique_code);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(30) NOT NULL,
    assigned_district VARCHAR(100) NULL,
    email VARCHAR(150) NULL,
    mobile VARCHAR(20) NULL,
    is_active TINYINT DEFAULT 1,
    can_view_schools TINYINT DEFAULT 1,
    can_manage_schools TINYINT DEFAULT 0,
    can_view_papers TINYINT DEFAULT 1,
    can_manage_papers TINYINT DEFAULT 0,
    can_view_regs_only TINYINT DEFAULT 0,
    can_view_registrations TINYINT DEFAULT 1,
    can_edit_registrations TINYINT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS question_papers (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    class VARCHAR(20) NOT NULL,
    subject VARCHAR(100) DEFAULT 'General',
    file_path VARCHAR(255) NOT NULL,
    file_size INTEGER DEFAULT 0,
    is_published TINYINT DEFAULT 0,
    published_at DATETIME NULL,
    uploaded_by VARCHAR(100) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
