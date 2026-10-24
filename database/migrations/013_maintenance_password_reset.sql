CREATE TABLE IF NOT EXISTS site_settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT NOT NULL DEFAULT '',
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
INSERT OR IGNORE INTO site_settings(setting_key,setting_value) VALUES
    ('maintenance_enabled','0'),
    ('maintenance_message',''),
    ('mail_transport','smtp'),
    ('mail_smtp_host',''),
    ('mail_smtp_port','587'),
    ('mail_smtp_encryption','tls'),
    ('mail_smtp_username',''),
    ('mail_smtp_password',''),
    ('mail_from_email',''),
    ('mail_from_name','أثر');

CREATE TABLE IF NOT EXISTS password_reset_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    email_hash TEXT NOT NULL,
    ip_hash TEXT NOT NULL,
    code_hash TEXT NOT NULL DEFAULT '',
    expires_at TEXT NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    used_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_password_reset_email_created ON password_reset_requests(email_hash,created_at);
CREATE INDEX IF NOT EXISTS idx_password_reset_ip_created ON password_reset_requests(ip_hash,created_at);
