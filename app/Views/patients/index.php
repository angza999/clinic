<?php
$hasPatientKeyword = trim((string) ($keyword ?? '')) !== '';
$visiblePatientList = $hasPatientKeyword ? $patients : $recentPatients;
$canRegister = has_role(['ADMIN', 'NURSE']);
$patientCountLabel = count($visiblePatientList);
?>

<div class="patient-workstation">
    <section class="patient-command-panel">
        <div class="patient-command-main">
            <div class="patient-kicker">New Registration</div>
            <h2>ลงทะเบียนแบบเร็ว</h2>
            <p>กรอกข้อมูลเบื้องต้นเพื่อเปิด Smart Exam ได้ทันที</p>
        </div>

        <?php if ($canRegister): ?>
            <div class="patient-command-actions">
                <button type="button" class="patient-tool-btn" data-smart-card-trigger data-smart-card-url="<?= e(route_url('smart-card-read')) ?>">
                    <i class="bi bi-credit-card-2-front"></i>
                    <span>อ่านบัตรประชาชน</span>
                </button>
                <a class="patient-tool-btn" href="<?= e(route_url('import', ['type' => 'patients'])) ?>">
                    <i class="bi bi-file-earmark-spreadsheet"></i>
                    <span>นำเข้าข้อมูล Excel</span>
                </a>
                <div class="smart-card-state" data-smart-card-state aria-live="polite">
                    <strong>Smart Card</strong>
                    <span>พร้อมเชื่อมต่อ reader</span>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="patient-workstation-grid">
        <div class="patient-list-panel">
            <div class="patient-panel-head">
                <div>
                    <div class="patient-kicker"><?= $hasPatientKeyword ? 'Search Result' : 'Quick Access' ?></div>
                    <h3><?= $hasPatientKeyword ? 'ผลการค้นหา' : 'ผู้รับบริการล่าสุด' ?></h3>
                </div>
                <span class="patient-count-badge"><?= (int) $patientCountLabel ?> รายการ</span>
            </div>
            <form method="get" class="patient-list-search">
                <input type="hidden" name="page" value="patients">
                <div class="patient-search-input">
                    <i class="bi bi-search"></i>
                    <input type="text" name="keyword" class="form-control"
                           placeholder="ค้นหา HN, ชื่อ, เบอร์โทร, เลขบัตรประชาชน"
                           value="<?= e($keyword) ?>">
                </div>
                <button class="btn btn-primary" type="submit">ค้นหา</button>
                <?php if ($keyword !== ''): ?>
                    <a href="<?= e(route_url('patients')) ?>" class="btn btn-outline-secondary">ล้าง</a>
                <?php endif; ?>
            </form>

            <div class="patient-clinical-list">
                <?php foreach ($visiblePatientList as $patient): ?>
                    <?php
                    $patientName = trim(($patient['title_name'] ?? '') . ' ' . $patient['first_name'] . ' ' . $patient['last_name']);
                    $hasAllergy = trim((string) ($patient['drug_allergy'] ?? '')) !== '' && trim((string) ($patient['drug_allergy'] ?? '')) !== '-';
                    $hasChronic = trim((string) ($patient['underlying_disease'] ?? '')) !== '' && trim((string) ($patient['underlying_disease'] ?? '')) !== '-';
                    ?>
                    <article class="patient-row">
                        <div class="patient-row-main">
                            <div class="patient-row-title"><?= e($patientName) ?></div>
                            <div class="patient-row-meta">
                                <span>HN <?= e($patient['hn']) ?></span>
                                <span><?= e($patient['phone'] ?: 'ไม่ระบุเบอร์') ?></span>
                                <span>ล่าสุด <?= thai_date($patient['last_visit_at']) ?></span>
                            </div>
                            <div class="patient-row-flags">
                                <span class="patient-mini-badge muted">มาแล้ว <?= (int) $patient['visit_count'] ?> ครั้ง</span>
                                <?php if ($hasAllergy): ?>
                                    <span class="patient-mini-badge alert">แพ้ยา</span>
                                <?php endif; ?>
                                <?php if ($hasChronic): ?>
                                    <span class="patient-mini-badge chronic">โรคประจำตัว</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="patient-row-actions">
                            <?php if ($canRegister): ?>
                                <form method="post" action="<?= e(route_url('patient-start-treatment')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="patient_id" value="<?= (int) $patient['id'] ?>">
                                    <input type="hidden" name="chief_complaint" value="">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-heart-pulse me-1"></i>เปิดตรวจ
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="<?= e(route_url('patient-show', ['id' => $patient['id']])) ?>" class="btn btn-outline-secondary btn-sm">แฟ้ม</a>
                        </div>
                    </article>
                <?php endforeach; ?>

                <?php if (!$visiblePatientList): ?>
                    <div class="patient-empty-state">
                        <i class="bi bi-person-vcard"></i>
                        <span><?= $hasPatientKeyword ? 'ไม่พบผู้รับบริการที่ตรงกับคำค้น' : 'ยังไม่มีรายการล่าสุด' ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <aside class="patient-intake-panel">
            <div class="patient-panel-head">
                <div>
                    <div class="patient-kicker">New Registration</div>
                    <h3>ลงทะเบียนแบบเร็ว</h3>
                </div>
                <span class="patient-count-badge primary">Intake</span>
            </div>

            <?php if ($canRegister): ?>
                <div class="patient-duplicate-alert" data-patient-duplicate-alert hidden>
                    <div>
                        <i class="bi bi-person-fill-exclamation"></i>
                        <strong>พบแฟ้มผู้รับบริการเดิม</strong>
                        <span data-patient-duplicate-text>กรุณาตรวจสอบก่อนสร้างแฟ้มใหม่</span>
                    </div>
                    <a href="#" class="btn btn-outline-warning btn-sm" data-patient-duplicate-link>ดูข้อมูลเดิม</a>
                </div>
                <form method="post" action="<?= e(route_url('patients-store')) ?>" class="patient-intake-form">
                    <?= csrf_field() ?>
                    <div class="patient-card-photo" data-card-photo-wrap>
                        <div class="patient-card-photo-frame">
                            <img src="" alt="รูปจากบัตรประชาชน" data-card-photo-preview>
                            <span class="patient-card-photo-placeholder" data-card-photo-placeholder>
                                <i class="bi bi-person-badge"></i>
                                รอรูปจากบัตร
                            </span>
                        </div>
                        <span class="patient-photo-check"><i class="bi bi-check-lg"></i></span>
                        <div>
                            <strong>รูปจากบัตรประชาชน</strong>
                            <span data-card-photo-status>กดอ่านบัตร แล้วเสียบบัตรประชาชนเพื่อดึงรูป</span>
                        </div>
                    </div>
                    <input type="hidden" name="card_photo" value="">
                    <section class="patient-intake-section patient-personal-section">
                        <div class="patient-section-title">
                            <span class="patient-section-icon"><i class="bi bi-person-fill"></i></span>
                            <strong>ข้อมูลส่วนตัว</strong>
                        </div>
                    <div class="patient-form-grid patient-primary-grid">
                        <label class="span-2">
                            <span>คำนำหน้า</span>
                            <input type="text" name="title_name" class="form-control" placeholder="นาย">
                        </label>
                        <label class="span-3">
                            <span>ชื่อ</span>
                            <input type="text" name="first_name" class="form-control" required>
                        </label>
                        <label class="span-3">
                            <span>นามสกุล</span>
                            <input type="text" name="last_name" class="form-control" required>
                        </label>
                        <label class="span-2">
                            <span>เพศ</span>
                            <span class="patient-field-icon">
                                <i class="bi bi-gender-ambiguous"></i>
                                <select name="gender" class="form-select">
                                    <option value="">เลือก</option>
                                    <option value="M">ชาย</option>
                                    <option value="F">หญิง</option>
                                    <option value="O">อื่น ๆ</option>
                                </select>
                            </span>
                        </label>
                        <label class="span-2">
                            <span>วันเกิด</span>
                            <span class="patient-field-icon">
                                <i class="bi bi-calendar3"></i>
                                <input type="text" name="birth_date" class="form-control" inputmode="numeric" placeholder="วว/ดด/พ.ศ.">
                            </span>
                        </label>
                        <label class="patient-age-field">
                            <span>อายุ</span>
                            <input type="text" name="calculated_age" class="form-control" placeholder="-" readonly aria-label="อายุคำนวณอัตโนมัติ">
                        </label>
                        <label class="span-3">
                            <span>เบอร์โทร</span>
                            <span class="patient-field-icon">
                                <i class="bi bi-telephone-fill"></i>
                                <input type="text" name="phone" class="form-control">
                            </span>
                        </label>
                    </div>
                    </section>

                    <details class="patient-extra-info patient-intake-section" open>
                        <summary>
                            <span><i class="bi bi-file-earmark-medical-fill"></i>ข้อมูลเพิ่มเติม</span>
                            <small>บัตรประชาชน, แพ้ยา, โรคประจำตัว, ที่อยู่</small>
                        </summary>
                        <div class="patient-form-grid extra">
                            <label>
                                <span>เลขบัตรประชาชน</span>
                                <span class="patient-field-icon">
                                    <i class="bi bi-card-text"></i>
                                    <input type="text" name="citizen_id" class="form-control">
                                </span>
                            </label>
                            <label>
                                <span>โรคประจำตัว (ถ้ามี)</span>
                                <span class="patient-field-icon">
                                    <i class="bi bi-heart-pulse"></i>
                                    <input type="text" name="underlying_disease" class="form-control">
                                </span>
                            </label>
                            <label>
                                <span>แพ้ยา (ถ้ามี)</span>
                                <span class="patient-field-icon textarea">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <textarea name="drug_allergy" class="form-control" rows="2"></textarea>
                                </span>
                            </label>
                            <label class="span-all">
                                <span>ที่อยู่</span>
                                <span class="patient-field-icon textarea">
                                    <i class="bi bi-house-door-fill"></i>
                                    <textarea name="address" class="form-control" rows="2"></textarea>
                                </span>
                            </label>
                            <details class="patient-note-collapse span-all">
                                <summary>หมายเหตุ (ถ้ามี)</summary>
                                <span class="patient-field-icon textarea">
                                    <i class="bi bi-chat-left-text"></i>
                                    <textarea name="note" class="form-control" rows="2" placeholder="เช่น ข้อมูลเพิ่มเติม, สิทธิประกัน, หมายเหตุอื่น ๆ"></textarea>
                                </span>
                            </details>
                        </div>
                    </details>

                    <div class="patient-intake-actions">
                        <button type="submit" name="workflow_action" value="save_and_treat" class="btn btn-primary">
                            <i class="bi bi-person-plus-fill me-1"></i>ลงทะเบียนและเปิด Smart Exam
                        </button>
                        <button type="submit" name="workflow_action" value="save" class="btn btn-outline-secondary">บันทึกแฟ้ม</button>
                    </div>
                    <div class="patient-intake-hint">
                        <i class="bi bi-info-circle"></i>
                        <span>กรอกเฉพาะข้อมูลที่จำเป็น เพื่อความรวดเร็วในการเปิด Smart Exam</span>
                    </div>
                </form>
            <?php else: ?>
                <div class="patient-empty-state">
                    <i class="bi bi-lock"></i>
                    <span>สิทธิ์ปัจจุบันใช้ค้นหาและเปิดแฟ้มผู้รับบริการ</span>
                </div>
            <?php endif; ?>
        </aside>
    </section>
</div>
