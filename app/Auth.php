<?php
declare(strict_types=1);

final class Auth
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function createUser(string $name, string $email, string $role, string $password): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO users (full_name, email, role, password_hash)
             VALUES (:name, :email, :role, :password_hash)'
        );
        $statement->execute([
            'name' => $name,
            'email' => mb_strtolower($email),
            'role' => $role,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $userId = (int) $this->db->lastInsertId();

        $ownerCount = (int) $this->db->query('SELECT COUNT(*) FROM users WHERE is_owner=1')->fetchColumn();
        if ($ownerCount === 0) {
            $owner = $this->db->prepare('UPDATE users SET is_owner=1 WHERE id=:id');
            $owner->execute(['id'=>$userId]);
        }

        $profile = $this->db->prepare('INSERT INTO profiles (user_id) VALUES (:user_id)');
        $profile->execute(['user_id' => $userId]);

        return $userId;
    }

    public function signIn(string $email, string $password): bool
    {
        $statement = $this->db->prepare('SELECT * FROM users WHERE email = :email COLLATE NOCASE AND is_active=1 AND NOT EXISTS (SELECT 1 FROM user_bans b WHERE b.user_id=users.id AND (b.banned_until IS NULL OR b.banned_until>CURRENT_TIMESTAMP))');
        $statement->execute(['email' => trim($email)]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $update = $this->db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
            $update->execute([
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $user['id'],
            ]);
        }

        $this->signInById((int) $user['id']);
        return true;
    }

    public function signInById(int $userId): void
    {
        session_regenerate_id(true);
        unset($_SESSION['csrf_token']);
        $stmt=$this->db->prepare('SELECT session_version FROM users WHERE id=:id AND is_active=1');
        $stmt->execute(['id'=>$userId]);
        $_SESSION['user_id']=$userId;
        $_SESSION['user_session_version']=(int)$stmt->fetchColumn();
        $_SESSION['security_last_activity']=time();
    }

    public function user(): ?array
    {
        $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$userId) {
            return null;
        }

        $statement = $this->db->prepare(
            'SELECT id, full_name, email, role, is_owner, onboarding_seen, is_active, session_version, created_at FROM users WHERE id = :id AND is_active=1 AND NOT EXISTS (SELECT 1 FROM user_bans b WHERE b.user_id=users.id AND (b.banned_until IS NULL OR b.banned_until>CURRENT_TIMESTAMP))'
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();

        if (!$user) {
            unset($_SESSION['user_id'],$_SESSION['user_session_version'],$_SESSION['csrf_token']);
            return null;
        }
        if ((int)($user['session_version']??0)!==(int)($_SESSION['user_session_version']??-1)) {
            unset($_SESSION['user_id'],$_SESSION['user_session_version'],$_SESSION['csrf_token']);
            session_regenerate_id(true);
            return null;
        }
        return $user;
    }

    public function signOut(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
}
