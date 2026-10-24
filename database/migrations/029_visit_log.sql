CREATE TABLE IF NOT EXISTS visit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    visitor_ip TEXT NOT NULL DEFAULT '0.0.0.0',
    route TEXT NOT NULL DEFAULT '/',
    user_agent TEXT NOT NULL DEFAULT '',
    is_authenticated INTEGER NOT NULL DEFAULT 0 CHECK (is_authenticated IN (0,1)),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_visit_log_created ON visit_log(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_visit_log_user ON visit_log(user_id,created_at DESC);
CREATE INDEX IF NOT EXISTS idx_visit_log_ip ON visit_log(visitor_ip,created_at DESC);
