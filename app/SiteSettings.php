<?php
declare(strict_types=1);

final class SiteSettings
{
    public static function get(PDO $db, string $key, string $default = ''): string
    {
        $stmt = $db->prepare('SELECT setting_value FROM site_settings WHERE setting_key=:key');
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string)$value;
    }

    public static function all(PDO $db): array
    {
        $rows = $db->query('SELECT setting_key,setting_value FROM site_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        return is_array($rows) ? $rows : [];
    }

    public static function setMany(PDO $db, array $settings): void
    {
        $stmt = $db->prepare('INSERT OR REPLACE INTO site_settings(setting_key,setting_value,updated_at) VALUES(:key,:value,CURRENT_TIMESTAMP)');
        foreach ($settings as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-z0-9_]{2,60}$/', $key)) continue;
            $stmt->execute(['key' => $key, 'value' => (string)$value]);
        }
    }
}
