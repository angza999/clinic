<?php
$isEditing = is_array($editingUser ?? null);
$currentUser = current_user();
?>

<div class="d-grid gap-4">
    <section class="page-hero-card">
        <div class="page-hero-layout">
            <div>
                <div class="page-hero-eyebrow">จัดการผู้ใช้งาน</div>
                <h1 class="page-hero-title">กำหนดสิทธิ์ บัญชี และการใช้งานของเจ้าหน้าที่ในระบบ</h1>
                <p class="page-hero-text">ใช้สำหรับเพิ่มผู้ใช้ใหม่ แก้ไขสิทธิ์ เปิดหรือปิดการใช้งาน และตั้งรหัสผ่านใหม่ให้เจ้าหน้าที่แต่ละบัญชี</p>
            </div>
            <div class="report-summary-grid">
                <div class="report-summary-card">
                    <span>ผู้ใช้ทั้งหมด</span>
                    <strong><?= e((string) ($summary['total_users'] ?? 0)) ?></strong>
                </div>
                <div class="report-summary-card">
                    <span>เปิดใช้งานอยู่</span>
                    <strong><?= e((string) ($summary['active_users'] ?? 0)) ?></strong>
                </div>
                <div class="report-summary-card">
                    <span>บัญชี Admin</span>
                    <strong><?= e((string) ($summary['admin_users'] ?? 0)) ?></strong>
                </div>
                <div class="report-summary-card report-summary-card-accent">
                    <span>เข้าใช้ใน 7 วัน</span>
                    <strong><?= e((string) ($summary['recent_logins'] ?? 0)) ?></strong>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card section-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1"><?= $isEditing ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้ใหม่' ?></h2>
                    <div class="small text-muted">ใช้ฟอร์มนี้สำหรับเพิ่มบัญชีใหม่ แก้ไขสิทธิ์ และเปิดหรือปิดการใช้งาน</div>
                </div>
                <div class="card-body px-4 pb-4">
                    <form method="post" action="<?= e(route_url('users-store')) ?>" class="row g-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= e((string) ($editingUser['id'] ?? 0)) ?>">

                        <div class="col-12">
                            <label class="form-label">ชื่อที่แสดงในระบบ</label>
                            <input type="text" name="full_name" class="form-control" required value="<?= e($editingUser['full_name'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">ชื่อบัญชีเข้าใช้งาน</label>
                            <input type="text" name="username" class="form-control" required value="<?= e($editingUser['username'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">เบอร์โทร</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($editingUser['phone'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">สิทธิ์ผู้ใช้</label>
                            <select name="role_id" class="form-select" required>
                                <option value="">เลือกสิทธิ์</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= e((string) $role['id']) ?>" <?= selected((string) ($editingUser['role_id'] ?? ''), (string) $role['id']) ?>>
                                        <?= e($role['role_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isEditing && (int) ($editingUser['id'] ?? 0) === (int) ($currentUser['id'] ?? 0)): ?>
                                <div class="form-text text-warning">บัญชีที่กำลังใช้งานอยู่ ไม่สามารถเปลี่ยนสิทธิ์จากหน้านี้ได้</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">สถานะ</label>
                            <select name="is_active" class="form-select">
                                <option value="1" <?= selected((string) ($editingUser['is_active'] ?? '1'), '1') ?>>ใช้งาน</option>
                                <option value="0" <?= selected((string) ($editingUser['is_active'] ?? '1'), '0') ?>>ปิดใช้งาน</option>
                            </select>
                        </div>

                        <?php if (!$isEditing): ?>
                            <div class="col-md-6">
                                <label class="form-label">รหัสผ่านเริ่มต้น</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ยืนยันรหัสผ่าน</label>
                                <input type="password" name="password_confirm" class="form-control" required>
                            </div>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="template-panel">
                                    <div class="fw-semibold mb-1">การเปลี่ยนรหัสผ่าน</div>
                                    <div class="small text-muted">หลังบันทึกข้อมูลผู้ใช้แล้ว สามารถตั้งรหัสผ่านใหม่ได้จากส่วนล่างของฟอร์มหรือจากการ์ดรายชื่อด้านขวา</div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary"><?= $isEditing ? 'บันทึกการเปลี่ยนแปลง' : 'เพิ่มผู้ใช้ใหม่' ?></button>
                            <a href="<?= e(route_url('users')) ?>" class="btn btn-outline-secondary">ล้างฟอร์ม</a>
                        </div>
                    </form>

                    <?php if ($isEditing): ?>
                        <hr class="my-4">
                        <h3 class="h6 mb-3">ตั้งรหัสผ่านใหม่: <?= e($editingUser['full_name']) ?></h3>
                        <form method="post" action="<?= e(route_url('users-password')) ?>" class="row g-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= e((string) $editingUser['id']) ?>">
                            <div class="col-md-6">
                                <label class="form-label">รหัสผ่านใหม่</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                                <input type="password" name="new_password_confirm" class="form-control" required>
                            </div>
                            <div class="col-12 d-flex gap-2 flex-wrap align-items-center">
                                <button type="submit" class="btn btn-outline-primary">บันทึกรหัสผ่านใหม่</button>
                                <div class="small text-muted">แนะนำอย่างน้อย 6 ตัวอักษร และไม่ควรใช้รหัสเดาง่าย</div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card section-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <div>
                        <h2 class="h5 mb-1">รายชื่อผู้ใช้งาน</h2>
                        <div class="small text-muted">คลิกแก้ไขเพื่อดึงข้อมูลกลับมาแก้ในฟอร์ม และตั้งรหัสผ่านใหม่ได้จากแต่ละการ์ด</div>
                    </div>
                    <div class="small text-muted">ทั้งหมด <?= e((string) count($users)) ?> บัญชี</div>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="patient-card-list">
                        <?php foreach ($users as $userRow): ?>
                            <?php $isCurrent = (int) $userRow['id'] === (int) ($currentUser['id'] ?? 0); ?>
                            <div class="user-card <?= $isCurrent ? 'user-card-current' : '' ?>">
                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                    <div>
                                        <div class="patient-result-title d-flex align-items-center gap-2 flex-wrap">
                                            <span><?= e($userRow['full_name']) ?></span>
                                            <?php if ($isCurrent): ?>
                                                <span class="badge text-bg-primary">บัญชีที่กำลังใช้งาน</span>
                                            <?php endif; ?>
                                            <?php if ((int) $userRow['is_active'] !== 1): ?>
                                                <span class="badge text-bg-danger">ปิดใช้งาน</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-success">ใช้งาน</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted mb-2">@<?= e($userRow['username']) ?></div>
                                        <div class="patient-meta-row mb-2">
                                            <div><span>สิทธิ์</span><strong><?= e($userRow['role_name']) ?></strong></div>
                                            <div><span>โทร</span><strong><?= e($userRow['phone'] ?: '-') ?></strong></div>
                                            <div><span>เข้าใช้ล่าสุด</span><strong><?= e($userRow['last_login_at'] ? thai_date($userRow['last_login_at']) : '-') ?></strong></div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap user-card-actions">
                                        <a href="<?= e(route_url('users', ['edit' => $userRow['id']])) ?>" class="btn btn-primary btn-sm">แก้ไข</a>
                                        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#password-form-<?= e((string) $userRow['id']) ?>">เปลี่ยนรหัสผ่าน</button>
                                    </div>
                                </div>

                                <div class="collapse mt-3" id="password-form-<?= e((string) $userRow['id']) ?>">
                                    <div class="template-panel">
                                        <form method="post" action="<?= e(route_url('users-password')) ?>" class="row g-3">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= e((string) $userRow['id']) ?>">
                                            <div class="col-md-5">
                                                <label class="form-label">รหัสผ่านใหม่</label>
                                                <input type="password" name="new_password" class="form-control" required>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label">ยืนยันรหัสผ่าน</label>
                                                <input type="password" name="new_password_confirm" class="form-control" required>
                                            </div>
                                            <div class="col-md-2 d-grid align-items-end">
                                                <button type="submit" class="btn btn-outline-primary mt-md-4">บันทึก</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!$users): ?>
                            <div class="queue-empty-state">ยังไม่มีผู้ใช้ในระบบ</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
