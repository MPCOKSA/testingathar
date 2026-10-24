CREATE TABLE IF NOT EXISTS user_trials (
    user_id INTEGER PRIMARY KEY,
    starts_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ends_at TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','expired','converted')),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_user_trials_active ON user_trials(status,ends_at);
