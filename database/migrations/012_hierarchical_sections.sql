ALTER TABLE portfolios ADD COLUMN section_structure TEXT NOT NULL DEFAULT 'flat' CHECK (section_structure IN ('flat','hierarchical'));
ALTER TABLE portfolio_sections ADD COLUMN parent_id INTEGER NULL REFERENCES portfolio_sections(id) ON DELETE SET NULL;
ALTER TABLE portfolio_sections ADD COLUMN display_mode TEXT NOT NULL DEFAULT 'auto' CHECK (display_mode IN ('auto','page','compact'));
CREATE INDEX IF NOT EXISTS idx_portfolio_sections_parent ON portfolio_sections(portfolio_id,parent_id,sort_order,id);
