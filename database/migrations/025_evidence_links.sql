ALTER TABLE evidence ADD COLUMN source_url TEXT NOT NULL DEFAULT '';
ALTER TABLE evidence ADD COLUMN source_kind TEXT NOT NULL DEFAULT 'file' CHECK (source_kind IN ('file','url','drive'));
CREATE INDEX IF NOT EXISTS idx_evidence_source_kind ON evidence(source_kind);
