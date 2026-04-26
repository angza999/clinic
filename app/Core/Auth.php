<?php

declare(strict_types=1);

namespace App\Core;

class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $stmt = db()->prepare(
            'SELECT users.*, roles.role_code, roles.role_name
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.username = :username AND users.is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        $storedHash = (string) $user['password_hash'];
        $passwordOk = str_starts_with($storedHash, '$2') || str_starts_with($storedHash, '$argon')
            ? password_verify($password, $storedHash)
            : hash_equals($storedHash, $password);

        if (!$passwordOk) {
            return false;
        }

        $_SESSION['auth_user'] = [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'role_code' => $user['role_code'],
            'role_name' => $user['role_name'],
        ];

        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute([
            'id' => $user['id'],
        ]);

        return true;
    }

    public static function user(): ?array
    {
        return $_SESSION['auth_user'] ?? null;
    }

    public static function check(): bool
    {
        return !empty($_SESSION['auth_user']);
    }

    public static function logout(): void
    {
        unset($_SESSION['auth_user']);
        session_regenerate_id(true);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            flash('error', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
            redirect('login');
        }
    }

    public static function requireRole(string|array $roles): void
    {
        self::requireLogin();

        if (!self::hasRole($roles)) {
            http_response_code(403);
            exit('You do not have permission to access this page.');
        }
    }

    public static function hasRole(string|array $roles): bool
    {
        $user = self::user();

        if (!$user) {
            return false;
        }

        $roles = (array) $roles;
        return in_array($user['role_code'], $roles, true);
    }
}
