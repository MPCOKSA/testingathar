ALTER TABLE evidence ADD COLUMN sort_order INTEGER NOT NULL DEFAULT 0;
UPDATE evidence SET sort_order=id WHERE sort_order=0;
CREATE INDEX IF NOT EXISTS idx_evidence_order ON evidence(portfolio_id,sort_order,id);
