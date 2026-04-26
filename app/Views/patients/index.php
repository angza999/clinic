<div class="row g-4">
    <div class="col-xl-4">
        <div class="card section-card h-100 patient-search-panel">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-1">ลงทะเบียนผู้รับบริการ</h2>
                <div class="small text-muted">กรอกข้อมูลที่จำเป็นก่อน แล้วค่อยเติมรายละเอียดภายหลังได้</div>
            </div>
            <div class="card-body px-4">
                <?php if (has_role('ADMIN')): ?>
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
                                <label class="form-label">อาการสำคัญเบื้องต้น (ถ้ารับคิวทันที)</label>
                                <textarea name="chief_complaint" class="form-control" rows="2" placeholder="เช่น มีไข้ ไอ เจ็บคอ"></textarea>
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
                                            <textarea name="drug_allergy" class="form-control" rows="2"></textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">ที่อยู่ / หมายเหตุ</label>
                                            <textarea name="address" class="form-control mb-3" rows="2"></textarea>
                                            <textarea name="note" class="form-control" rows="2" placeholder="หมายเหตุเพิ่มเติม"></textarea>
                                        </div>
                                    </div>
                                </details>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" name="workflow_action" value="save_and_queue" class="btn btn-primary btn-lg">บันทึกและรับคิวทันที</button>
                            <button type="submit" name="workflow_action" value="save" class="btn btn-outline-secondary">บันทึกข้อมูลไว้ก่อน</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-muted">สิทธิ์ปัจจุบันใช้สำหรับค้นหาและดูประวัติผู้รับบริการ</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card section-card mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                    <div>
                        <h2 class="h5 mb-1">ค้นหาผู้รับบริการ</h2>
                        <div class="small text-muted">ค้นหาด้วย HN ชื่อ เบอร์โทร หรือเลขบัตรประชาชน</div>
                    </div>
                    <form method="get" class="patient-search-form d-flex gap-2 w-100 w-lg-auto">
                        <input type="hidden" name="page" value="patients">
                        <input type="text" name="keyword" class="form-control form-control-lg" placeholder="พิมพ์ HN ชื่อ เบอร์โทร" value="<?= e($keyword) ?>">
                        <button class="btn btn-outline-primary">ค้นหา</button>
                        <?php if ($keyword !== ''): ?>
                            <a href="<?= e(route_url('patients')) ?>" class="btn btn-outline-secondary">ล้าง</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <div class="card-body px-4">
                <?php if ($recentPatients): ?>
                    <div class="mb-4">
                        <div class="small text-muted mb-2">ค้นหาเร็วจากรายการล่าสุด</div>
                        <div class="shortcut-grid">
                            <?php foreach ($recentPatients as $patient): ?>
                                <a href="<?= e(route_url('patients', ['keyword' => $patient['hn']])) ?>" class="btn btn-outline-secondary btn-sm shortcut-btn">
                                    <?= e($patient['hn']) ?> - <?= e($patient['first_name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="patient-result-summary mb-3">
                    <div>
                        <div class="small text-muted">ผลการค้นหา</div>
                        <div class="fw-semibold"><?= e((string) count($patients)) ?> รายการ<?= $keyword !== '' ? ' สำหรับคำค้นหา "' . e($keyword) . '"' : '' ?></div>
                    </div>
                    <div class="small text-muted">เรียงจากรายการล่าสุดก่อน</div>
                </div>

                <div class="patient-card-list">
                    <?php foreach ($patients as $patient): ?>
                        <div class="patient-result-card">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                                <div>
                                    <div class="patient-result-title"><?= e(trim(($patient['title_name'] ?? '') . ' ' . $patient['first_name'] . ' ' . $patient['last_name'])) ?></div>
                                    <div class="patient-meta-row mt-2">
                                        <div><span>HN</span><strong><?= e($patient['hn']) ?></strong></div>
                                        <div><span>โทร</span><strong><?= e($patient['phone'] ?: '-') ?></strong></div>
                                        <div><span>มาครั้งล่าสุด</span><strong><?= thai_date($patient['last_visit_at']) ?></strong></div>
                                    </div>
                                    <div class="small text-muted mt-2">แพ้ยา: <?= e($patient['drug_allergy'] ?: '-') ?> / มารับบริการแล้ว <?= e((string) $patient['visit_count']) ?> ครั้ง</div>
                                </div>
                                <div class="patient-card-actions d-grid gap-2">
                                    <?php if (has_role('ADMIN')): ?>
                                        <form method="post" action="<?= e(route_url('queue-store')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="patient_id" value="<?= e((string) $patient['id']) ?>">
                                            <input type="hidden" name="chief_complaint" value="">
                                            <button type="submit" class="btn btn-primary btn-sm">รับคิวทันที</button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="<?= e(route_url('patient-show', ['id' => $patient['id']])) ?>" class="btn btn-outline-secondary btn-sm">เปิดแฟ้ม</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$patients): ?>
                        <div class="queue-empty-state">ไม่พบข้อมูลผู้รับบริการ</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 mb-1">หยิบใช้เร็ว</h2>
                <div class="small text-muted">สำหรับช่วงที่มีผู้รับบริการต่อเนื่อง สามารถหยิบคิวจากรายการล่าสุดได้ทันที</div>
            </div>
            <div class="card-body px-4">
                <div class="row g-3">
                    <?php foreach ($recentPatients as $patient): ?>
                        <div class="col-md-6">
                            <div class="patient-quick-card h-100">
                                <div class="fw-semibold"><?= e($patient['hn']) ?> - <?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></div>
                                <div class="small text-muted mb-3">โทร <?= e($patient['phone'] ?: '-') ?> / มาแล้ว <?= e((string) $patient['visit_count']) ?> ครั้ง</div>
                                <div class="d-grid gap-2">
                                    <?php if (has_role('ADMIN')): ?>
                                        <form method="post" action="<?= e(route_url('queue-store')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="patient_id" value="<?= e((string) $patient['id']) ?>">
                                            <input type="hidden" name="chief_complaint" value="">
                                            <button type="submit" class="btn btn-outline-primary btn-sm">รับคิวให้คนนี้</button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="<?= e(route_url('patient-show', ['id' => $patient['id']])) ?>" class="btn btn-outline-secondary btn-sm">เปิดแฟ้ม</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$recentPatients): ?>
                        <div class="col-12 text-muted">ยังไม่มีรายการล่าสุด</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
