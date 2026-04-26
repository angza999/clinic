<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

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
            flash('error', 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');
            redirect('login');
        }

        if (!Auth::attempt($username, $password)) {
            flash('error', 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
            redirect('login');
        }

        flash('success', 'เข้าสู่ระบบสำเร็จ');
        redirect(default_home_page());
    }

    public function logout(): void
    {
        Auth::logout();
        flash('success', 'ออกจากระบบเรียบร้อย');
        redirect('login');
    }
}
