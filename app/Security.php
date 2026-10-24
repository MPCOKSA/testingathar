<?php
declare(strict_types=1);

final class Security
{
    private const LOGIN_WINDOW = 900;
    private const MAX_PAIR_FAILURES = 7;
    private const MAX_IP_FAILURES = 24;
    private const MAX_EMAIL_FAILURES = 12;
    private static ?string $applicationSecret = null;

    public static function clientIp(): string
    {
        // لا نثق بترويسات X-Forwarded-For التي يستطيع العميل تزويرها.
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    private static function secret(): string
    {
        if (self::$applicationSecret !== null) return self::$applicationSecret;
        $configured = (string)(getenv('ATHAR_SECURITY_SALT') ?: '');
        if (strlen($configured) >= 32) return self::$applicationSecret = $configured;

        $dir = dirname(__DIR__) . '/storage';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('تعذر إنشاء مساحة حفظ مفتاح الأمان.');
        }
        $path = $dir . '/.security-key';
        if (is_link($path)) throw new RuntimeException('ملف مفتاح الأمان غير صالح.');
        if (!is_file($path)) {
            $handle = @fopen($path, 'x+b');
            if ($handle) {
                $encoded = base64_encode(random_bytes(32));
                if (fwrite($handle, $encoded) !== strlen($encoded)) { fclose($handle); @unlink($path); throw new RuntimeException('تعذر حفظ مفتاح الأمان.'); }
                fflush($handle); @chmod($path, 0600); fclose($handle);
            }
        }
        for ($attempt = 0; $attempt < 5; $attempt++) {
            if (is_file($path) && !is_link($path) && filesize($path) <= 128) {
                $raw = @file_get_contents($path);
                $decoded = is_string($raw) ? base64_decode(trim($raw), true) : false;
                if (is_string($decoded) && strlen($decoded) === 32) return self::$applicationSecret = $decoded;
            }
            usleep(20000);
        }
        throw new RuntimeException('تعذر قراءة مفتاح الأمان؛ تحقق من صلاحيات مجلد storage.');
    }

    public static function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, self::secret());
    }

    public static function loginAllowed(PDO $db, string $email): bool
    {
        try {
            $ip = self::fingerprint(self::clientIp());
            $emailHash = self::fingerprint(mb_strtolower(trim($email)));
            $stmt = $db->prepare("SELECT SUM(CASE WHEN ip_hash=:ip THEN 1 ELSE 0 END) AS ip_count,SUM(CASE WHEN email_hash=:email THEN 1 ELSE 0 END) AS email_count,SUM(CASE WHEN ip_hash=:ip AND email_hash=:email THEN 1 ELSE 0 END) AS pair_count FROM auth_attempts WHERE success=0 AND created_at >= datetime('now','-15 minutes')");
            $stmt->execute(['ip'=>$ip,'email'=>$emailHash]); $counts=$stmt->fetch() ?: [];
            return (int)($counts['ip_count']??0) < self::MAX_IP_FAILURES
                && (int)($counts['email_count']??0) < self::MAX_EMAIL_FAILURES
                && (int)($counts['pair_count']??0) < self::MAX_PAIR_FAILURES;
        } catch (Throwable) { return false; }
    }

    public static function registrationAllowed(PDO $db, string $email): bool
    {
        try {
            $ip = self::fingerprint(self::clientIp()); $emailHash = self::fingerprint(mb_strtolower(trim($email)));
            $stmt=$db->prepare("SELECT SUM(CASE WHEN ip_hash=:ip THEN 1 ELSE 0 END) AS ip_count,SUM(CASE WHEN email_hash=:email THEN 1 ELSE 0 END) AS email_count FROM auth_attempts WHERE success=0 AND created_at>=datetime('now','-1 hour')");
            $stmt->execute(['ip'=>$ip,'email'=>$emailHash]); $row=$stmt->fetch()?:[];
            return (int)($row['ip_count']??0)<12 && (int)($row['email_count']??0)<5;
        } catch (Throwable) { return false; }
    }

    public static function recordLogin(PDO $db, string $email, bool $success): void
    {
        try {
            $stmt = $db->prepare('INSERT INTO auth_attempts (ip_hash,email_hash,success) VALUES (:ip,:email,:success)');
            $stmt->execute(['ip'=>self::fingerprint(self::clientIp()),'email'=>self::fingerprint(mb_strtolower(trim($email))),'success'=>$success?1:0]);
            $db->exec("DELETE FROM auth_attempts WHERE created_at < datetime('now','-2 days')");
        } catch (Throwable) { /* السجل الأمني لا يعطل الموقع */ }
    }

    public static function event(PDO $db, ?int $userId, string $type, string $route, string $details = ''): void
    {
        try {
            $stmt=$db->prepare('INSERT INTO security_events (user_id,event_type,route,ip_hash,details) VALUES (:user,:type,:route,:ip,:details)');
            $stmt->execute(['user'=>$userId,'type'=>mb_substr($type,0,60),'route'=>mb_substr($route,0,180),'ip'=>self::fingerprint(self::clientIp()),'details'=>mb_substr($details,0,500)]);
        } catch (Throwable) { /* لا نكتب عناوين IP الخام أو أسرار المستخدم */ }
    }

    public static function invalidateUserSessions(PDO $db, int $userId): void
    {
        $stmt=$db->prepare('UPDATE users SET session_version=session_version+1 WHERE id=:id');
        $stmt->execute(['id'=>$userId]);
    }

    public static function hardenSession(): void
    {
        $now=time(); $last=(int)($_SESSION['security_last_activity']??0);
        if ($last>0 && $now-$last>7200) { $_SESSION=[]; session_regenerate_id(true); }
        $agent=hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??''));
        if (!isset($_SESSION['security_fingerprint'])) $_SESSION['security_fingerprint']=$agent;
        if (!hash_equals((string)$_SESSION['security_fingerprint'],$agent)) { $_SESSION=[]; session_regenerate_id(true); $_SESSION['security_fingerprint']=$agent; }
        $_SESSION['security_last_activity']=$now;
        if (!isset($_SESSION['security_rotated_at']) || $now-(int)$_SESSION['security_rotated_at']>900) { session_regenerate_id(true); $_SESSION['security_rotated_at']=$now; }
    }
}
