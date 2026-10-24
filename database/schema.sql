PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL COLLATE NOCASE UNIQUE,
    role TEXT NOT NULL CHECK (role IN ('teacher', 'student')),
    password_hash TEXT NOT NULL,
    is_owner INTEGER NOT NULL DEFAULT 0 CHECK (is_owner IN (0,1)),
    onboarding_seen INTEGER NOT NULL DEFAULT 0 CHECK (onboarding_seen IN (0,1)),
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS profiles (
    user_id INTEGER PRIMARY KEY,
    headline TEXT NOT NULL DEFAULT '',
    school_name TEXT NOT NULL DEFAULT '',
    grade_level TEXT NOT NULL DEFAULT '',
    subject TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS portfolios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    portfolio_type TEXT NOT NULL CHECK (portfolio_type IN ('teacher', 'student')),
    title TEXT NOT NULL DEFAULT '',
    introduction TEXT NOT NULL DEFAULT '',
    visibility TEXT NOT NULL DEFAULT 'private' CHECK (visibility IN ('private', 'public')),
    public_view_mode TEXT NOT NULL DEFAULT 'website' CHECK (public_view_mode IN ('website','standard')),
    public_presentation TEXT NOT NULL DEFAULT 'landing' CHECK (public_presentation IN ('landing','cards','pages','editorial','timeline','mosaic','cinema','dashboard','story','studio','minimal','atlas','glass','orbit','museum','journal','steps','split','stack','polaroid','blueprint','paper','neon','nature','aurora','bento','classroom','storyboard','spotlight')),
    public_layout_mode TEXT NOT NULL DEFAULT 'single' CHECK (public_layout_mode IN ('single','sections')),
    public_subjects_mode INTEGER NOT NULL DEFAULT 0 CHECK (public_subjects_mode IN (0,1)),
    section_structure TEXT NOT NULL DEFAULT 'flat' CHECK (section_structure IN ('flat','hierarchical')),
    public_view_count INTEGER NOT NULL DEFAULT 0 CHECK (public_view_count >= 0),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS portfolio_sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    portfolio_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    body TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    visibility TEXT NOT NULL DEFAULT 'private' CHECK (visibility IN ('private', 'public')),
    enabled INTEGER NOT NULL DEFAULT 1 CHECK (enabled IN (0,1)),
    parent_id INTEGER NULL,
    display_mode TEXT NOT NULL DEFAULT 'auto' CHECK (display_mode IN ('auto','page','compact')),
    subject_theme TEXT NOT NULL DEFAULT 'general',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES portfolio_sections(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_portfolio_sections_order
    ON portfolio_sections(portfolio_id, sort_order, id);

CREATE TABLE IF NOT EXISTS portfolio_goals (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL,title TEXT NOT NULL,target_date TEXT NOT NULL DEFAULT '',status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','done')),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS portfolio_reflections (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL,prompt TEXT NOT NULL,body TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);

CREATE TABLE IF NOT EXISTS support_requests (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL,subject TEXT NOT NULL,message TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'new' CHECK (status IN ('new','in_progress','resolved')),owner_note TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);

CREATE TABLE IF NOT EXISTS auth_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT,ip_hash TEXT NOT NULL,email_hash TEXT NOT NULL,success INTEGER NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS security_events (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,event_type TEXT NOT NULL,route TEXT NOT NULL DEFAULT '',ip_hash TEXT NOT NULL,details TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL);


CREATE TABLE IF NOT EXISTS site_settings (setting_key TEXT PRIMARY KEY,setting_value TEXT NOT NULL DEFAULT '',updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS password_reset_requests (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NULL,email_hash TEXT NOT NULL,ip_hash TEXT NOT NULL,code_hash TEXT NOT NULL DEFAULT '',expires_at TEXT NOT NULL,attempts INTEGER NOT NULL DEFAULT 0,used_at TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE INDEX IF NOT EXISTS idx_password_reset_email_created ON password_reset_requests(email_hash,created_at);
CREATE INDEX IF NOT EXISTS idx_password_reset_ip_created ON password_reset_requests(ip_hash,created_at);
