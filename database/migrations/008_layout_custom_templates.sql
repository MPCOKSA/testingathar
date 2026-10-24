ALTER TABLE portfolios ADD COLUMN public_layout_mode TEXT NOT NULL DEFAULT 'single' CHECK (public_layout_mode IN ('single','sections'));
CREATE INDEX IF NOT EXISTS idx_portfolios_layout_mode ON portfolios(public_layout_mode);
