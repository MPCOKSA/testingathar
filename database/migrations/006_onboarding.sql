ALTER TABLE users ADD COLUMN onboarding_seen INTEGER NOT NULL DEFAULT 0 CHECK (onboarding_seen IN (0,1));
