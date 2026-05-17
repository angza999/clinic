<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use Throwable;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('dashboard');
        }

        $this->render('auth/login', [
            'pageTitle' => 'เข้าสู่ระบบ',
        ], 'layouts/guest');
    }

    public function login(): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $this->writeAuthAudit('LOGIN_FAILED', null, [
                'username' => $username,
                'reason' => 'missing_credentials',
            ]);

            flash('error', 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');
            redirect('login');
        }

        if (!Auth::attempt($username, $password)) {
            $this->writeAuthAudit('LOGIN_FAILED', null, [
                'username' => $username,
                'reason' => 'invalid_credentials',
            ]);

            flash('error', 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
            redirect('login');
        }

        $user = current_user();
        $this->writeAuthAudit('LOGIN_SUCCESS', (int) ($user['id'] ?? 0), [
            'username' => $user['username'] ?? $username,
            'full_name' => $user['full_name'] ?? null,
            'role_code' => $user['role_code'] ?? null,
        ]);

        flash('success', 'เข้าสู่ระบบสำเร็จ');
        redirect(default_home_page());
    }

    public function logout(): void
    {
        $user = current_user();
        if ($user) {
            $this->writeAuthAudit('LOGOUT', (int) $user['id'], [
                'username' => $user['username'] ?? null,
                'full_name' => $user['full_name'] ?? null,
                'role_code' => $user['role_code'] ?? null,
            ]);
        }

        Auth::logout();
        flash('success', 'ออกจากระบบเรียบร้อย');
        redirect('login');
    }

    private function writeAuthAudit(string $action, ?int $userId, array $detail): void
    {
        try {
            $detail += [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ];

            db()->prepare(
                'INSERT INTO audit_logs (user_id, action, table_name, record_id, detail_json, created_at)
                 VALUES (:user_id, :action, "users", :record_id, :detail_json, NOW())'
            )->execute([
                'user_id' => $userId ?: null,
                'action' => $action,
                'record_id' => $userId ?: null,
                'detail_json' => json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable) {
            // Authentication should not fail just because audit logging is temporarily unavailable.
        }
    }
}
