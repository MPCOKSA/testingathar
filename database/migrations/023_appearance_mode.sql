ALTER TABLE portfolios ADD COLUMN appearance_mode TEXT NOT NULL DEFAULT 'light' CHECK (appearance_mode IN ('light','dark'));
