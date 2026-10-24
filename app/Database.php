<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $root = dirname(__DIR__);
        $path = getenv('ATHAR_DB_PATH') ?: $root . '/storage/athar.sqlite';
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the local database directory.');
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        self::migrate($pdo, $root . '/database/migrations');
        self::ensureCompatibility($pdo);

        self::$connection = $pdo;
        return $pdo;
    }

    private static function ensureCompatibility(PDO $pdo): void
    {
        // حماية إضافية لقواعد البيانات التي نُقلت يدويًا أو تحمل schema_migrations قديمًا.
        $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
        $hasActive = false;
        foreach ($columns as $column) $hasActive = $hasActive || (($column['name'] ?? '') === 'is_active');
        if (!$hasActive) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1))");
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_active ON users(is_active)');
        }
        $userColumns = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('last_seen_at', $userColumns, true)) $pdo->exec('ALTER TABLE users ADD COLUMN last_seen_at TEXT NULL');
        $pdo->exec("CREATE TABLE IF NOT EXISTS auth_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT,ip_hash TEXT NOT NULL,email_hash TEXT NOT NULL,success INTEGER NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS security_events (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,event_type TEXT NOT NULL,route TEXT NOT NULL DEFAULT '',ip_hash TEXT NOT NULL,details TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $portfolioColumns = array_column($pdo->query('PRAGMA table_info(portfolios)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('public_presentation', $portfolioColumns, true)) {
            $pdo->exec("ALTER TABLE portfolios ADD COLUMN public_presentation TEXT NOT NULL DEFAULT 'landing' CHECK (public_presentation IN ('landing','cards','pages','editorial','timeline','mosaic','cinema','dashboard','story','studio','minimal','atlas','glass','orbit','museum','journal','steps','split','stack','polaroid','blueprint','paper','neon','nature','aurora','bento','classroom','storyboard','spotlight'))");
        }
        if (!in_array('appearance_mode', $portfolioColumns, true)) $pdo->exec("ALTER TABLE portfolios ADD COLUMN appearance_mode TEXT NOT NULL DEFAULT 'light' CHECK (appearance_mode IN ('light','dark'))");
        if (!in_array('section_structure', $portfolioColumns, true)) {
            $pdo->exec("ALTER TABLE portfolios ADD COLUMN section_structure TEXT NOT NULL DEFAULT 'flat'");
        }
        $evidenceColumns = array_column($pdo->query('PRAGMA table_info(evidence)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if ($evidenceColumns && !in_array('evidence_type', $evidenceColumns, true)) $pdo->exec("ALTER TABLE evidence ADD COLUMN evidence_type TEXT NOT NULL DEFAULT ''");
        $sectionColumns = array_column($pdo->query('PRAGMA table_info(portfolio_sections)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('parent_id', $sectionColumns, true)) {
            $pdo->exec('ALTER TABLE portfolio_sections ADD COLUMN parent_id INTEGER NULL');
        }
        if (!in_array('display_mode', $sectionColumns, true)) {
            $pdo->exec("ALTER TABLE portfolio_sections ADD COLUMN display_mode TEXT NOT NULL DEFAULT 'auto'");
        }
        if (!in_array('subject_theme', $sectionColumns, true)) {
            $pdo->exec("ALTER TABLE portfolio_sections ADD COLUMN subject_theme TEXT NOT NULL DEFAULT 'general'");
        }
        $profileColumns = array_column($pdo->query('PRAGMA table_info(profiles)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('avatar_stored_name', $profileColumns, true)) $pdo->exec('ALTER TABLE profiles ADD COLUMN avatar_stored_name TEXT NULL');
        if (!in_array('avatar_mime_type', $profileColumns, true)) $pdo->exec('ALTER TABLE profiles ADD COLUMN avatar_mime_type TEXT NULL');
        if (!in_array('show_avatar', $profileColumns, true)) $pdo->exec('ALTER TABLE profiles ADD COLUMN show_avatar INTEGER NOT NULL DEFAULT 0');
    }

    private static function migrate(PDO $pdo, string $directory): void
    {
        if (!is_dir($directory)) {
            throw new RuntimeException('The database migrations directory is missing.');
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version INTEGER PRIMARY KEY,
                migration_name TEXT NOT NULL,
                applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $files = glob($directory . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $name = basename($file);
            if (!preg_match('/^(\d{3,})_[a-z0-9_-]+\.sql$/i', $name, $matches)) {
                throw new RuntimeException('Invalid migration filename: ' . $name);
            }

            $version = (int) $matches[1];
            $check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = :version');
            $check->execute(['version' => $version]);
            if ($check->fetchColumn()) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('A database migration could not be read.');
            }

            // بعض النسخ القديمة أُنشئت من schema.sql الذي يحتوي العمود مسبقًا،
            // لذلك نتجاوز ALTER TABLE المكرر ونُسجل الترحيل بدل إسقاط الموقع.
            if ($version === 11) {
                $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($columns as $column) {
                    if (($column['name'] ?? '') === 'is_active') {
                        $sql = '';
                        break;
                    }
                }
            }
            if ($version === 14) {
                $columns = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
                if (in_array('session_version', $columns, true)) {
                    $sql = preg_replace('/ALTER TABLE users ADD COLUMN session_version INTEGER NOT NULL DEFAULT 1;\s*/', '', $sql) ?? $sql;
                }
            }
            if ($version === 16) {
                $columns = array_column($pdo->query('PRAGMA table_info(portfolio_sections)')->fetchAll(PDO::FETCH_ASSOC), 'name');
                if (in_array('subject_theme', $columns, true)) $sql = '';
            }
            if ($version === 17) {
                $columns = array_column($pdo->query('PRAGMA table_info(profiles)')->fetchAll(PDO::FETCH_ASSOC), 'name');
                foreach (['avatar_stored_name','avatar_mime_type','show_avatar'] as $column) {
                    $sql = preg_replace('/ALTER TABLE profiles ADD COLUMN '.preg_quote($column,'/').' [^;]+;\s*/', in_array($column,$columns,true)?'':('$0'), $sql) ?? $sql;
                }
            }
            if ($version === 18) {
                $columns = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
                if (in_array('last_seen_at', $columns, true)) $sql = preg_replace('/ALTER TABLE users ADD COLUMN last_seen_at TEXT NULL;\s*/', '', $sql) ?? $sql;
            }
            if ($version === 25) {
                $columns = array_column($pdo->query('PRAGMA table_info(evidence)')->fetchAll(PDO::FETCH_ASSOC), 'name');
                foreach (['source_url','source_kind'] as $column) {
                    $sql = preg_replace('/ALTER TABLE evidence ADD COLUMN '.preg_quote($column,'/').' [^;]+;\s*/', in_array($column,$columns,true)?'':('$0'), $sql) ?? $sql;
                }
            }
            if ($version === 30) {
                $columns = array_column($pdo->query('PRAGMA table_info(portfolios)')->fetchAll(PDO::FETCH_ASSOC), 'name');
                if (in_array('public_presentation', $columns, true)) $sql = '';
            }

            $pdo->beginTransaction();
            try {
                $pdo->exec($sql);
                $record = $pdo->prepare(
                    'INSERT INTO schema_migrations (version, migration_name) VALUES (:version, :name)'
                );
                $record->execute(['version' => $version, 'name' => $name]);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
        }
    }
}
