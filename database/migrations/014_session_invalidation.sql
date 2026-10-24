ALTER TABLE users ADD COLUMN session_version INTEGER NOT NULL DEFAULT 1;
CREATE INDEX IF NOT EXISTS idx_users_session_version ON users(id,session_version);
CREATE TABLE IF NOT EXISTS user_bans (
    user_id INTEGER PRIMARY KEY,
    reason TEXT NOT NULL DEFAULT '',
    banned_until TEXT NULL,
    created_by INTEGER NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
);
