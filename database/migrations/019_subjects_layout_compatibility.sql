ALTER TABLE portfolios ADD COLUMN public_subjects_mode INTEGER NOT NULL DEFAULT 0 CHECK (public_subjects_mode IN (0,1));
