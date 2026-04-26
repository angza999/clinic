<form method="post" action="<?= e(route_url('login')) ?>">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">ชื่อผู้ใช้</label>
        <input type="text" name="username" class="form-control form-control-lg" placeholder="admin" required>
    </div>
    <div class="mb-4">
        <label class="form-label">รหัสผ่าน</label>
        <input type="password" name="password" class="form-control form-control-lg" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn btn-primary btn-lg w-100">เข้าสู่ระบบ</button>
</form>
