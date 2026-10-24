ALTER TABLE users ADD COLUMN last_seen_at TEXT NULL;
CREATE TABLE IF NOT EXISTS admin_notifications (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL,
 title TEXT NOT NULL,
 message TEXT NOT NULL,
 type TEXT NOT NULL DEFAULT 'info',
 created_by INTEGER NOT NULL,
 seen_at TEXT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_admin_notifications_user ON admin_notifications(user_id,seen_at,id);
CREATE INDEX IF NOT EXISTS idx_users_last_seen ON users(last_seen_at);
