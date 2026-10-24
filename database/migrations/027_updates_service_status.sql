CREATE TABLE IF NOT EXISTS site_updates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    version TEXT NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    kind TEXT NOT NULL DEFAULT 'release' CHECK (kind IN ('release','notice','maintenance')),
    is_published INTEGER NOT NULL DEFAULT 1 CHECK (is_published IN (0,1)),
    created_by INTEGER NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_site_updates_published ON site_updates(is_published, created_at DESC);

CREATE TABLE IF NOT EXISTS service_statuses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_key TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'operational' CHECK (status IN ('operational','maintenance','outage')),
    note TEXT NOT NULL DEFAULT '',
    updated_by INTEGER NULL,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
);
INSERT OR IGNORE INTO service_statuses(service_key,label,status,note) VALUES
 ('platform','منصة أثر','operational','تعمل جميع الوظائف بصورة طبيعية.'),
 ('uploads','رفع الملفات','operational','رفع الملفات متاح حتى 6 ميغابايت.'),
 ('sharing','المشاركة العامة','operational','روابط المشاركة تعمل بصورة طبيعية.'),
 ('assistant','المساعد الذكي','operational','المساعد متاح للمشتركين في الخطط الداعمة.');
