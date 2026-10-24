ALTER TABLE profiles ADD COLUMN avatar_stored_name TEXT NULL;
ALTER TABLE profiles ADD COLUMN avatar_mime_type TEXT NULL;
ALTER TABLE profiles ADD COLUMN show_avatar INTEGER NOT NULL DEFAULT 0;
