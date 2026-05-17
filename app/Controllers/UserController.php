<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use RuntimeException;
use Throwable;

class UserController extends Controller
{
    public function index(): void
    {
        require_roles(['ADMIN']);

        $roles = db()->query(
            'SELECT id, role_code, role_name
             FROM roles
             ORDER BY id ASC'
        )->fetchAll();

        $users = db()->query(
            'SELECT users.*, roles.role_code, roles.role_name
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             ORDER BY users.is_active DESC, users.role_id ASC, users.full_name ASC'
        )->fetchAll();

        $editingUser = null;
        $editId = (int) ($_GET['edit'] ?? 0);

        if ($editId > 0) {
            $stmt = db()->prepare(
                'SELECT users.*, roles.role_code, roles.role_name
                 FROM users
                 INNER JOIN roles ON roles.id = users.role_id
                 WHERE users.id = :id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $editId]);
            $editingUser = $stmt->fetch() ?: null;
        }

        $summary = [
            'total_users' => count($users),
            'active_users' => count(array_filter($users, static fn(array $user): bool => (int) $user['is_active'] === 1)),
            'admin_users' => count(array_filter($users, static fn(array $user): bool => $user['role_code'] === 'ADMIN')),
            'recent_logins' => count(array_filter($users, static fn(array $user): bool => !empty($user['last_login_at']) && strtotime((string) $user['last_login_at']) >= strtotime('-7 days'))),
        ];

        $this->render('users/index', [
            'pageTitle' => 'จัดการผู้ใช้งาน',
            'roles' => $roles,
            'users' => $users,
            'editingUser' => $editingUser,
            'summary' => $summary,
            'userAuditLogs' => $this->recentUserAuditLogs(),
        ]);
    }

    public function store(): void
    {
        require_roles(['ADMIN']);

        $userId = (int) ($_POST['user_id'] ?? 0);
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $isActive = (int) ($_POST['is_active'] ?? 0) === 1 ? 1 : 0;
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if ($fullName === '' || $username === '' || $roleId <= 0) {
            flash('error', 'กรุณากรอกชื่อผู้ใช้ ชื่อบัญชี และสิทธิ์ให้ครบถ้วน');
            redirect('users', $userId > 0 ? ['edit' => $userId] : []);
        }

        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            flash('error', 'ชื่อบัญชีต้องมีอย่างน้อย 3 ตัวอักษร และใช้ได้เฉพาะ A-Z, a-z, 0-9, จุด, ขีดล่าง หรือขีดกลาง');
            redirect('users', $userId > 0 ? ['edit' => $userId] : []);
        }

        $roleStmt = db()->prepare('SELECT id, role_code FROM roles WHERE id = :id LIMIT 1');
        $roleStmt->execute(['id' => $roleId]);
        $role = $roleStmt->fetch();

        if (!$role) {
            flash('error', 'ไม่พบสิทธิ์ผู้ใช้ที่เลือก');
            redirect('users', $userId > 0 ? ['edit' => $userId] : []);
        }

        try {
            $currentUser = current_user();
            $existingUser = $userId > 0 ? $this->findUser($userId) : null;

            if ($userId > 0 && !$existingUser) {
                throw new RuntimeException('ไม่พบผู้ใช้ที่ต้องการแก้ไข');
            }

            $duplicateStmt = db()->prepare(
                'SELECT id
                 FROM users
                 WHERE username = :username
                   AND (:current_id = 0 OR id <> :target_id)
                 LIMIT 1'
            );
            $duplicateStmt->execute([
                'username' => $username,
                'current_id' => $userId,
                'target_id' => $userId,
            ]);

            if ($duplicateStmt->fetch()) {
                throw new RuntimeException('ชื่อบัญชีนี้ถูกใช้งานแล้ว');
            }

            if ($userId === 0) {
                if ($password === '' || $passwordConfirm === '') {
                    throw new RuntimeException('กรุณากำหนดรหัสผ่านเริ่มต้นสำหรับผู้ใช้ใหม่');
                }

                if ($password !== $passwordConfirm) {
                    throw new RuntimeException('รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน');
                }

                if (strlen($password) < 6) {
                    throw new RuntimeException('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
                }

                db()->prepare(
                    'INSERT INTO users (role_id, full_name, username, password_hash, phone, is_active, created_at, updated_at)
                     VALUES (:role_id, :full_name, :username, :password_hash, :phone, :is_active, NOW(), NOW())'
                )->execute([
                    'role_id' => $roleId,
                    'full_name' => $fullName,
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'phone' => $phone ?: null,
                    'is_active' => $isActive,
                ]);

                flash('success', 'เพิ่มผู้ใช้ใหม่เรียบร้อยแล้ว');
                $createdUserId = (int) db()->lastInsertId();
                $this->writeUserAudit('USER_CREATED', $createdUserId, [
                    'username' => $username,
                    'full_name' => $fullName,
                    'role_id' => $roleId,
                    'role_code' => $role['role_code'],
                    'is_active' => $isActive,
                ]);

                redirect('users');
            }

            if ($currentUser && (int) $currentUser['id'] === $userId) {
                if ($isActive !== 1) {
                    throw new RuntimeException('ไม่สามารถปิดการใช้งานบัญชีที่กำลังล็อกอินอยู่ได้');
                }

                if ((int) $existingUser['role_id'] !== $roleId) {
                    throw new RuntimeException('ไม่สามารถเปลี่ยนสิทธิ์ของบัญชีที่กำลังใช้งานอยู่จากหน้านี้ได้');
                }
            }

            if ($existingUser['role_code'] === 'ADMIN' && ($role['role_code'] !== 'ADMIN' || $isActive !== 1)) {
                $activeAdminCount = $this->activeAdminCount();
                if ($activeAdminCount <= 1) {
                    throw new RuntimeException('ระบบต้องมีผู้ใช้ที่เป็น Admin และเปิดใช้งานอยู่อย่างน้อย 1 บัญชี');
                }
            }

            db()->prepare(
                'UPDATE users
                 SET role_id = :role_id,
                     full_name = :full_name,
                     username = :username,
                     phone = :phone,
                     is_active = :is_active,
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'id' => $userId,
                'role_id' => $roleId,
                'full_name' => $fullName,
                'username' => $username,
                'phone' => $phone ?: null,
                'is_active' => $isActive,
            ]);

            flash('success', 'บันทึกข้อมูลผู้ใช้เรียบร้อยแล้ว');
            $this->writeUserAudit('USER_UPDATED', $userId, [
                'before' => $this->auditUserSnapshot($existingUser),
                'after' => [
                    'username' => $username,
                    'full_name' => $fullName,
                    'role_id' => $roleId,
                    'role_code' => $role['role_code'],
                    'phone' => $phone ?: null,
                    'is_active' => $isActive,
                ],
            ]);
        } catch (Throwable $throwable) {
            flash('error', 'ไม่สามารถบันทึกข้อมูลผู้ใช้ได้: ' . $throwable->getMessage());
        }

        redirect('users', ['edit' => $userId]);
    }

    public function changePassword(): void
    {
        require_roles(['ADMIN']);

        $userId = (int) ($_POST['user_id'] ?? 0);
        $password = (string) ($_POST['new_password'] ?? '');
        $passwordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

        try {
            if ($userId <= 0) {
                throw new RuntimeException('ไม่พบผู้ใช้ที่ต้องการตั้งรหัสผ่านใหม่');
            }

            $user = $this->findUser($userId);
            if (!$user) {
                throw new RuntimeException('ไม่พบผู้ใช้ที่ต้องการตั้งรหัสผ่านใหม่');
            }

            if ($password === '' || $passwordConfirm === '') {
                throw new RuntimeException('กรุณากรอกรหัสผ่านใหม่และยืนยันรหัสผ่านให้ครบ');
            }

            if ($password !== $passwordConfirm) {
                throw new RuntimeException('รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน');
            }

            if (strlen($password) < 6) {
                throw new RuntimeException('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
            }

            db()->prepare(
                'UPDATE users
                 SET password_hash = :password_hash,
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'id' => $userId,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            $this->writeUserAudit('USER_PASSWORD_CHANGED', $userId, [
                'username' => $user['username'],
                'full_name' => $user['full_name'],
            ]);

            flash('success', 'ตั้งรหัสผ่านใหม่ให้ ' . $user['full_name'] . ' เรียบร้อยแล้ว');
        } catch (Throwable $throwable) {
            flash('error', 'ไม่สามารถตั้งรหัสผ่านใหม่ได้: ' . $throwable->getMessage());
        }

        redirect('users', ['edit' => $userId]);
    }

    private function findUser(int $userId): array|null
    {
        $stmt = db()->prepare(
            'SELECT users.*, roles.role_code, roles.role_name
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    private function activeAdminCount(): int
    {
        $stmt = db()->query(
            'SELECT COUNT(*) AS total_admin
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE roles.role_code = "ADMIN" AND users.is_active = 1'
        );

        return (int) ($stmt->fetch()['total_admin'] ?? 0);
    }

    private function recentUserAuditLogs(): array
    {
        $stmt = db()->query(
            'SELECT audit_logs.*, users.full_name AS actor_name, users.username AS actor_username
             FROM audit_logs
             LEFT JOIN users ON users.id = audit_logs.user_id
             WHERE audit_logs.action IN (
                "USER_CREATED", "USER_UPDATED", "USER_PASSWORD_CHANGED",
                "LOGIN_SUCCESS", "LOGIN_FAILED", "LOGOUT"
             )
             ORDER BY audit_logs.created_at DESC, audit_logs.id DESC
             LIMIT 12'
        );

        return $stmt->fetchAll();
    }

    private function writeUserAudit(string $action, int $targetUserId, array $detail): void
    {
        try {
            $actor = current_user();
            db()->prepare(
                'INSERT INTO audit_logs (user_id, action, table_name, record_id, detail_json, created_at)
                 VALUES (:user_id, :action, "users", :record_id, :detail_json, NOW())'
            )->execute([
                'user_id' => $actor['id'] ?? null,
                'action' => $action,
                'record_id' => $targetUserId,
                'detail_json' => json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable) {
            // User management should still complete if audit logging is temporarily unavailable.
        }
    }

    private function auditUserSnapshot(array $user): array
    {
        return [
            'username' => $user['username'] ?? null,
            'full_name' => $user['full_name'] ?? null,
            'role_id' => isset($user['role_id']) ? (int) $user['role_id'] : null,
            'role_code' => $user['role_code'] ?? null,
            'phone' => $user['phone'] ?? null,
            'is_active' => isset($user['is_active']) ? (int) $user['is_active'] : null,
        ];
    }
}
