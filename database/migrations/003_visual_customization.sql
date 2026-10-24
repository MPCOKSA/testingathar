ALTER TABLE portfolios ADD COLUMN display_name TEXT NOT NULL DEFAULT '';
ALTER TABLE portfolios ADD COLUMN background_color TEXT NOT NULL DEFAULT '#fbf7ef';
ALTER TABLE portfolios ADD COLUMN accent_color TEXT NOT NULL DEFAULT '#c18a35';
ALTER TABLE portfolios ADD COLUMN font_family TEXT NOT NULL DEFAULT 'system-ui';
ALTER TABLE portfolios ADD COLUMN cover_style TEXT NOT NULL DEFAULT 'clean';
