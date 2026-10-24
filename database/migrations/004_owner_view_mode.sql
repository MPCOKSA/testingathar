ALTER TABLE users ADD COLUMN is_owner INTEGER NOT NULL DEFAULT 0 CHECK (is_owner IN (0,1));
ALTER TABLE portfolios ADD COLUMN public_view_mode TEXT NOT NULL DEFAULT 'website' CHECK (public_view_mode IN ('website','standard'));
CREATE INDEX IF NOT EXISTS idx_users_owner ON users(is_owner);
UPDATE users SET is_owner = 1 WHERE id = (SELECT MIN(id) FROM users) AND NOT EXISTS (SELECT 1 FROM users WHERE is_owner = 1);
