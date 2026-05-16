<?php
$hasPatientKeyword = trim((string) ($keyword ?? '')) !== '';
$visiblePatientList = $hasPatientKeyword ? $patients : $recentPatients;
?>

<div class="workspace-stack patient-page">
    <section class="card section-card workspace-intro-card patient-hero-card">
        <div class="workspace-intro patient-hero-grid">
            <div>
                <div class="eyebrow">Patient Registration</div>
                <h2>ค้นหาให้เจอก่อน แล้วค่อยเปิด Smart Exam หรือเปิดแฟ้ม</h2>
                <p>ใช้หน้านี้สำหรับค้นหาผู้รับบริการเดิม ลงทะเบียนผู้รับบริการใหม่ และเปิด Smart Exam ได้ทันทีจากจุดเดียวโดยไม่ต้องสลับหลายหน้า</p>
            </div>
            <form method="get" class="patient-search-form search-bar-form patient-search-bar">
                <input type="hidden" name="page" value="patients">
                <input type="text" name="keyword" class="form-control form-control-lg" placeholder="พิมพ์ HN ชื่อ เบอร์โทร หรือเลขบัตร" value="<?= e($keyword) ?>">
                <button class="btn btn-primary btn-lg px-4"><i class="bi bi-search me-1"></i>ค้นหา</button>
                <?php if ($keyword !== ''): ?>
                    <a href="<?= e(route_url('patients')) ?>" class="btn btn-outline-secondary btn-lg">ล้างคำค้น</a>
                <?php endif; ?>
            </form>
        </div>

    </section>

    <div class="patient-workspace-grid">
        <section class="card section-card patient-results-card">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                <div class="panel-heading patient-panel-heading">
                    <div>
                        <div class="eyebrow"><?= $hasPatientKeyword ? 'ผลการค้นหา' : 'Quick Access' ?></div>
                        <h2 class="h5 mb-1"><?= $hasPatientKeyword ? 'รายชื่อผู้รับบริการที่ค้นพบ' : 'คนไข้ล่าสุดที่หยิบใช้บ่อย' ?></h2>
                        <p class="text-muted mb-0"><?= $hasPatientKeyword ? 'เลือกเปิด Smart Exam หากคนไข้มารับบริการวันนี้ หรือเปิดแฟ้มเพื่อตรวจประวัติย้อนหลัง' : 'ยังไม่ต้องค้นหาใหม่ เลือกจากรายชื่อล่าสุดเพื่อเปิด Smart Exam หรือเปิดแฟ้มได้ทันที' ?></p>
                    </div>
                    <span class="soft-badge"><?= e((string) count($visiblePatientList)) ?> รายการ<?= $hasPatientKeyword ? ' สำหรับคำค้นหา "' . e($keyword) . '"' : '' ?></span>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="patient-card-list compact-card-list">
                    <?php foreach ($visiblePatientList as $patient): ?>
                        <article class="patient-result-card professional-card">
                            <div class="patient-result-grid">
                                <div class="patient-result-main">
                                    <div class="patient-result-title"><?= e(trim(($patient['title_name'] ?? '') . ' ' . $patient['first_name'] . ' ' . $patient['last_name'])) ?></div>
                                    <div class="patient-meta-row mt-2">
                                        <div><span>HN</span><strong><?= e($patient['hn']) ?></strong></div>
                                        <div><span>โทร</span><strong><?= e($patient['phone'] ?: '-') ?></strong></div>
                                        <div><span>มาครั้งล่าสุด</span><strong><?= thai_date($patient['last_visit_at']) ?></strong></div>
                                    </div>
                                    <div class="small text-muted mt-2">แพ้ยา: <?= e($patient['drug_allergy'] ?: '-') ?> / รับบริการแล้ว <?= e((string) $patient['visit_count']) ?> ครั้ง</div>
                                </div>
                                <div class="patient-card-actions">
                                    <?php if (has_role(['ADMIN', 'NURSE'])): ?>
                                        <form method="post" action="<?= e(route_url('patient-start-treatment')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="patient_id" value="<?= e((string) $patient['id']) ?>">
                                            <input type="hidden" name="chief_complaint" value="">
                                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-play-circle me-1"></i>เปิด Smart Exam</button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="<?= e(route_url('patient-show', ['id' => $patient['id']])) ?>" class="btn btn-outline-secondary btn-sm">เปิดแฟ้ม</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>

                    <?php if (!$visiblePatientList): ?>
                        <div class="queue-empty-state"><?= $hasPatientKeyword ? 'ไม่พบข้อมูลผู้รับบริการที่ตรงกับคำค้น' : 'ยังไม่มีรายชื่อล่าสุด' ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <aside class="patient-side-column">
            <div class="card section-card patient-search-panel mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <div class="eyebrow">New Registration</div>
                    <h2 class="h5 mb-1">ลงทะเบียนผู้รับบริการใหม่</h2>
                    <p class="text-muted mb-0">กรอกข้อมูลสำคัญก่อน แล้วกดเปิด Smart Exam ได้ทันที ส่วนข้อมูลเพิ่มเติมค่อยเติมภายหลังได้</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <?php if (has_role(['ADMIN', 'NURSE'])): ?>
                        <form method="post" action="<?= e(route_url('patients-store')) ?>">
                            <?= csrf_field() ?>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">คำนำหน้า</label>
                                    <input type="text" name="title_name" class="form-control" placeholder="นาย">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ชื่อ</label>
                                    <input type="text" name="first_name" class="form-control" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">นามสกุล</label>
                                    <input type="text" name="last_name" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">เพศ</label>
                                    <select name="gender" class="form-select">
                                        <option value="">เลือก</option>
                                        <option value="M">ชาย</option>
                                        <option value="F">หญิง</option>
                                        <option value="O">อื่น ๆ</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">วันเกิด</label>
                                    <input type="date" name="birth_date" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">เบอร์โทร</label>
                                    <input type="text" name="phone" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">อาการสำคัญเบื้องต้น</label>
                                    <textarea name="chief_complaint" class="form-control compact-textarea" rows="2" placeholder="เช่น มีไข้ ไอ เจ็บคอ"></textarea>
                                </div>
                                <div class="col-12">
                                    <details class="bg-light rounded-4 p-3">
                                        <summary class="fw-semibold">ข้อมูลเพิ่มเติม</summary>
                                        <div class="row g-3 mt-2">
                                            <div class="col-md-6">
                                                <label class="form-label">เลขบัตรประชาชน</label>
                                                <input type="text" name="citizen_id" class="form-control">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">ผู้ติดต่อฉุกเฉิน</label>
                                                <input type="text" name="emergency_contact_name" class="form-control">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">เบอร์ติดต่อฉุกเฉิน</label>
                                                <input type="text" name="emergency_contact_phone" class="form-control">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">โรคประจำตัว</label>
                                                <input type="text" name="underlying_disease" class="form-control">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">แพ้ยา</label>
                                                <textarea name="drug_allergy" class="form-control compact-textarea" rows="2"></textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">ที่อยู่ / หมายเหตุ</label>
                                                <textarea name="address" class="form-control compact-textarea mb-3" rows="2"></textarea>
                                                <textarea name="note" class="form-control compact-textarea" rows="2" placeholder="หมายเหตุเพิ่มเติม"></textarea>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" name="workflow_action" value="save_and_treat" class="btn btn-primary btn-lg"><i class="bi bi-person-plus-fill me-1"></i>ลงทะเบียนและเปิด Smart Exam</button>
                                <button type="submit" name="workflow_action" value="save" class="btn btn-outline-secondary">บันทึกข้อมูลไว้ก่อน</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-muted">สิทธิ์ปัจจุบันใช้สำหรับค้นหาและดูประวัติผู้รับบริการ</div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</div>
