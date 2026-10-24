ALTER TABLE portfolios ADD COLUMN share_token TEXT;
ALTER TABLE portfolios ADD COLUMN sharing_enabled INTEGER NOT NULL DEFAULT 0 CHECK (sharing_enabled IN (0,1));
ALTER TABLE portfolios ADD COLUMN show_public_name INTEGER NOT NULL DEFAULT 0 CHECK (show_public_name IN (0,1));
ALTER TABLE portfolios ADD COLUMN quick_share INTEGER NOT NULL DEFAULT 0 CHECK (quick_share IN (0,1));
ALTER TABLE portfolios ADD COLUMN template_id TEXT NOT NULL DEFAULT 'classic-sand';
ALTER TABLE portfolio_sections ADD COLUMN enabled INTEGER NOT NULL DEFAULT 1 CHECK (enabled IN (0,1));

CREATE UNIQUE INDEX IF NOT EXISTS idx_portfolios_share_token ON portfolios(share_token) WHERE share_token IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_portfolios_sharing ON portfolios(sharing_enabled, share_token);

CREATE TABLE IF NOT EXISTS evidence (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    portfolio_id INTEGER NOT NULL,
    section_id INTEGER,
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    original_name TEXT NOT NULL,
    stored_name TEXT NOT NULL UNIQUE,
    mime_type TEXT NOT NULL,
    file_size INTEGER NOT NULL CHECK (file_size >= 0),
    evidence_date TEXT NOT NULL DEFAULT '',
    tags TEXT NOT NULL DEFAULT '',
    competency TEXT NOT NULL DEFAULT '',
    visibility TEXT NOT NULL DEFAULT 'private' CHECK (visibility IN ('private','public')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES portfolio_sections(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_evidence_portfolio_created ON evidence(portfolio_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_evidence_section ON evidence(section_id, id);

CREATE TABLE IF NOT EXISTS activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    portfolio_id INTEGER NOT NULL,
    event_type TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_activity_user_date ON activity_log(user_id, created_at DESC);

CREATE TABLE IF NOT EXISTS user_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    config_json TEXT NOT NULL,
    is_public INTEGER NOT NULL DEFAULT 0 CHECK (is_public IN (0,1)),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS ai_usage (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    task_type TEXT NOT NULL,
    provider TEXT NOT NULL,
    status TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_ai_usage_daily ON ai_usage(user_id, created_at);
