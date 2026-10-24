CREATE TABLE IF NOT EXISTS template_controls (
    template_id TEXT PRIMARY KEY,
    is_enabled INTEGER NOT NULL DEFAULT 1 CHECK (is_enabled IN (0,1)),
    admin_note TEXT NOT NULL DEFAULT '',
    updated_by INTEGER NULL,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_template_controls_enabled ON template_controls(is_enabled);
