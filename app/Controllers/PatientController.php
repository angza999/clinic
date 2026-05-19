<?php

namespace App\Controllers;

use App\Core\ClinicWorkflow;
use App\Core\Controller;
use App\Core\NumberGenerator;
use Throwable;

class PatientController extends Controller
{
    public function index(): void
    {
        require_roles(['ADMIN', 'NURSE', 'CASHIER']);

        $keyword = trim((string) ($_GET['keyword'] ?? ''));
        $params = [];
        $sql = 'SELECT patients.*,
                       (SELECT COUNT(*) FROM visits WHERE visits.patient_id = patients.id) AS visit_count,
                       (SELECT MAX(visit_datetime) FROM visits WHERE visits.patient_id = patients.id) AS last_visit_at
                FROM patients
                WHERE patients.is_active = 1';

        if ($keyword !== '') {
            $sql .= ' AND (
                        patients.hn LIKE :keyword
                        OR patients.first_name LIKE :keyword
                        OR patients.last_name LIKE :keyword
                        OR patients.phone LIKE :keyword
                        OR patients.citizen_id LIKE :keyword
                    )';
            $params['keyword'] = '%' . $keyword . '%';
        }

        $sql .= ' ORDER BY patients.id DESC LIMIT 100';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $patients = $stmt->fetchAll();

        $this->render('patients/index', [
            'pageTitle' => 'ผู้รับบริการ',
            'patients' => $patients,
            'keyword' => $keyword,
            'recentPatients' => array_slice($patients, 0, 8),
            'pageStyles' => [$this->versionedAssetUrl('assets/css/patients.css')],
            'pageScripts' => [$this->versionedAssetUrl('assets/js/patients.js')],
        ]);
    }

    public function smartCardRead(): void
    {
        require_roles(['ADMIN', 'NURSE']);
        $this->ensurePatientPhotoSchema();

        header('Content-Type: application/json; charset=utf-8');

        $startedAt = trim((string) ($_GET['started_at'] ?? ''));
        $result = $this->readSmartCardFromLocalService($startedAt);

        if (!$result['success']) {
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return;
        }

        $card = $this->normalizeSmartCardPayload($result['payload']);

        if (($card['citizen_id'] ?? '') === '' && ($card['first_name'] ?? '') === '') {
            echo json_encode([
                'success' => false,
                'message' => 'ได้รับข้อมูลจากเครื่องอ่านบัตรแล้ว แต่ยังอ่านชื่อหรือเลขบัตรไม่ได้ กรุณาถอดบัตรเสียบใหม่อีกครั้ง',
                'attempts' => $result['attempts'],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $existingPatient = null;
        if (!empty($card['citizen_id'])) {
            $stmt = db()->prepare(
                'SELECT id, hn, title_name, first_name, last_name, phone, photo_path
                 FROM patients
                 WHERE citizen_id = :citizen_id AND is_active = 1
                 LIMIT 1'
            );
            $stmt->execute(['citizen_id' => $card['citizen_id']]);
            $existingPatient = $stmt->fetch() ?: null;

            if ($existingPatient && !empty($card['photo'])) {
                $photoPath = $this->storePatientPhoto((string) $card['photo'], (string) $existingPatient['hn']);
                if ($photoPath !== null) {
                    db()->prepare(
                        'UPDATE patients
                         SET photo_path = :photo_path, updated_at = NOW()
                         WHERE id = :id'
                    )->execute([
                        'photo_path' => $photoPath,
                        'id' => (int) $existingPatient['id'],
                    ]);
                    $existingPatient['photo_path'] = $photoPath;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => $existingPatient ? 'อ่านบัตรสำเร็จ และพบแฟ้มผู้รับบริการเดิม' : 'อ่านบัตรสำเร็จ พร้อมเติมข้อมูลลงทะเบียน',
            'card' => $card,
            'existing_patient' => $existingPatient,
            'source' => $result['source'],
        ], JSON_UNESCAPED_UNICODE);
    }

    public function show(): void
    {
        require_roles(['ADMIN', 'NURSE', 'CASHIER']);
        $this->ensurePatientPhotoSchema();

        $patientId = (int) ($_GET['id'] ?? 0);
        $patientStmt = db()->prepare(
            'SELECT patients.*,
                    (SELECT COUNT(*) FROM visits WHERE visits.patient_id = patients.id) AS visit_count,
                    (SELECT MAX(visit_datetime) FROM visits WHERE visits.patient_id = patients.id) AS last_visit_at
             FROM patients
             WHERE patients.id = :id
             LIMIT 1'
        );
        $patientStmt->execute(['id' => $patientId]);
        $patient = $patientStmt->fetch();

        if (!$patient) {
            http_response_code(404);
            exit('Patient not found');
        }

        $visitsStmt = db()->prepare(
            'SELECT visits.id, visits.visit_no, visits.visit_datetime, visits.chief_complaint, visits.nursing_note, visits.advice, visits.followup_date,
                    queue_entries.queue_no, queue_entries.status,
                    visit_vitals.bp_systolic, visit_vitals.bp_diastolic, visit_vitals.temp_c, visit_vitals.pulse_rate, visit_vitals.spo2, visit_vitals.weight_kg,
                    payments.total_amount, payments.receipt_no, payments.paid_at,
                    COALESCE(service_summary.services, "-") AS services_summary,
                    COALESCE(item_summary.items, "-") AS items_summary
             FROM visits
             LEFT JOIN queue_entries ON queue_entries.visit_id = visits.id
             LEFT JOIN visit_vitals ON visit_vitals.visit_id = visits.id
             LEFT JOIN payments ON payments.visit_id = visits.id
             LEFT JOIN (
                SELECT visit_services.visit_id,
                       GROUP_CONCAT(CONCAT(services.service_name, " x", visit_services.qty) ORDER BY visit_services.id SEPARATOR ", ") AS services
                FROM visit_services
                INNER JOIN services ON services.id = visit_services.service_id
                GROUP BY visit_services.visit_id
             ) AS service_summary ON service_summary.visit_id = visits.id
             LEFT JOIN (
                SELECT visit_item_usages.visit_id,
                       GROUP_CONCAT(CONCAT(inventory_items.item_name, " x", visit_item_usages.qty) ORDER BY visit_item_usages.id SEPARATOR ", ") AS items
                FROM visit_item_usages
                INNER JOIN inventory_items ON inventory_items.id = visit_item_usages.item_id
                GROUP BY visit_item_usages.visit_id
             ) AS item_summary ON item_summary.visit_id = visits.id
             WHERE visits.patient_id = :patient_id
             ORDER BY visits.visit_datetime DESC'
        );
        $visitsStmt->execute(['patient_id' => $patientId]);
        $visits = $visitsStmt->fetchAll();

        $appointmentsStmt = db()->prepare(
            'SELECT *
             FROM appointments
             WHERE patient_id = :patient_id
             ORDER BY appointment_date DESC, appointment_time DESC, id DESC
             LIMIT 10'
        );
        $appointmentsStmt->execute(['patient_id' => $patientId]);

        $this->render('patients/show', [
            'pageTitle' => 'แฟ้มประวัติผู้รับบริการ',
            'patient' => $patient,
            'visits' => $visits,
            'appointments' => $appointmentsStmt->fetchAll(),
            'pageStyles' => [$this->versionedAssetUrl('assets/css/patients.css')],
        ]);
    }

    public function photo(): void
    {
        require_roles(['ADMIN', 'NURSE', 'CASHIER']);
        $this->ensurePatientPhotoSchema();

        $patientId = (int) ($_GET['id'] ?? 0);
        if ($patientId <= 0) {
            http_response_code(404);
            exit;
        }

        $stmt = db()->prepare('SELECT photo_path FROM patients WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $patientId]);
        $photoPath = (string) ($stmt->fetchColumn() ?: '');

        if ($photoPath === '' || !str_starts_with($photoPath, 'storage/patient-photos/')) {
            http_response_code(404);
            exit;
        }

        $fullPath = str_replace('\\', '/', BASE_PATH . '/' . $photoPath);
        $photoDir = storage_path('patient-photos');
        if (!is_file($fullPath) || !str_starts_with($fullPath, $photoDir . '/')) {
            http_response_code(404);
            exit;
        }

        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        header('Content-Type: ' . $contentType);
        header('Cache-Control: private, max-age=86400');
        readfile($fullPath);
        exit;
    }

    public function store(): void
    {
        require_roles(['ADMIN', 'NURSE']);
        $this->ensurePatientPhotoSchema();

        $input = [
            'title_name' => trim((string) ($_POST['title_name'] ?? '')),
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'gender' => trim((string) ($_POST['gender'] ?? '')),
            'birth_date' => trim((string) ($_POST['birth_date'] ?? '')),
            'citizen_id' => trim((string) ($_POST['citizen_id'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'emergency_contact_name' => trim((string) ($_POST['emergency_contact_name'] ?? '')),
            'emergency_contact_phone' => trim((string) ($_POST['emergency_contact_phone'] ?? '')),
            'underlying_disease' => trim((string) ($_POST['underlying_disease'] ?? '')),
            'drug_allergy' => trim((string) ($_POST['drug_allergy'] ?? '')),
            'note' => trim((string) ($_POST['note'] ?? '')),
            'card_photo' => trim((string) ($_POST['card_photo'] ?? '')),
        ];
        $input['birth_date'] = $this->normalizePatientBirthDate($input['birth_date']);

        if ($input['first_name'] === '' || $input['last_name'] === '') {
            flash('error', 'กรุณากรอกชื่อและนามสกุลผู้รับบริการ');
            redirect('patients');
        }

        try {
            $hn = NumberGenerator::nextHn();
            $workflowAction = (string) ($_POST['workflow_action'] ?? 'save');
            $chiefComplaint = trim((string) ($_POST['chief_complaint'] ?? ''));
            $photoPath = $this->storePatientPhoto($input['card_photo'], $hn);
            $pdo = db();

            $pdo->prepare(
                'INSERT INTO patients (
                    hn, citizen_id, title_name, first_name, last_name, gender, birth_date, phone, address,
                    emergency_contact_name, emergency_contact_phone, underlying_disease, drug_allergy, note, photo_path,
                    is_active, created_at, updated_at
                ) VALUES (
                    :hn, :citizen_id, :title_name, :first_name, :last_name, :gender, :birth_date, :phone, :address,
                    :emergency_contact_name, :emergency_contact_phone, :underlying_disease, :drug_allergy, :note, :photo_path,
                    1, NOW(), NOW()
                )'
            )->execute([
                'hn' => $hn,
                'citizen_id' => $input['citizen_id'] ?: null,
                'title_name' => $input['title_name'] ?: null,
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'gender' => $input['gender'] ?: null,
                'birth_date' => $input['birth_date'] ?: null,
                'phone' => $input['phone'] ?: null,
                'address' => $input['address'] ?: null,
                'emergency_contact_name' => $input['emergency_contact_name'] ?: null,
                'emergency_contact_phone' => $input['emergency_contact_phone'] ?: null,
                'underlying_disease' => $input['underlying_disease'] ?: null,
                'drug_allergy' => $input['drug_allergy'] ?: null,
                'note' => $input['note'] ?: null,
                'photo_path' => $photoPath,
            ]);

            $patientId = (int) $pdo->lastInsertId();

            if (in_array($workflowAction, ['save_and_treat', 'save_and_queue'], true)) {
                $workflow = ClinicWorkflow::createVisitAndQueue(
                    $patientId,
                    $chiefComplaint,
                    (int) current_user()['id']
                );

                flash('success', "ลงทะเบียนเรียบร้อย HN: {$hn} และเปิด Smart Exam ให้แล้ว");
                redirect('queue-exam', ['id' => $workflow['visit_id']]);
            }

            flash('success', "บันทึกผู้รับบริการเรียบร้อย HN: {$hn}");
        } catch (Throwable $throwable) {
            flash('error', 'ไม่สามารถบันทึกข้อมูลผู้รับบริการได้: ' . $throwable->getMessage());
        }

        redirect('patients');
    }

    public function startTreatment(): void
    {
        require_roles(['ADMIN', 'NURSE']);

        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $chiefComplaint = trim((string) ($_POST['chief_complaint'] ?? ''));

        if ($patientId <= 0) {
            flash('error', 'ไม่พบข้อมูลผู้รับบริการที่ต้องการเริ่มรักษา');
            redirect('patients');
        }

        try {
            $patientStmt = db()->prepare('SELECT id, hn, first_name, last_name FROM patients WHERE id = :id AND is_active = 1 LIMIT 1');
            $patientStmt->execute(['id' => $patientId]);
            $patient = $patientStmt->fetch();

            if (!$patient) {
                flash('error', 'ไม่พบข้อมูลผู้รับบริการที่เลือก');
                redirect('patients');
            }

            $workflow = ClinicWorkflow::createVisitAndQueue(
                $patientId,
                $chiefComplaint,
                (int) current_user()['id']
            );

            flash('success', 'เปิด Smart Exam ให้ ' . $patient['first_name'] . ' ' . $patient['last_name'] . ' เรียบร้อยแล้ว');
            redirect('queue-exam', ['id' => $workflow['visit_id']]);
        } catch (Throwable $throwable) {
            flash('error', 'ไม่สามารถเริ่มรักษาได้: ' . $throwable->getMessage());
            redirect('patients');
        }
    }

    private function readSmartCardFromLocalService(string $startedAt = ''): array
    {
        $endpoints = [
            'http://127.0.0.1:8189/read',
            'http://localhost:8189/read',
            'http://127.0.0.1:8189/json',
            'http://localhost:8189/json',
            'http://127.0.0.1:8189/api/smartcard/read',
            'http://localhost:8189/api/smartcard/read',
            'http://127.0.0.1:8189/api/smartcard/read?readImageFlag=true',
            'http://localhost:8189/api/smartcard/read?readImageFlag=true',
            'http://127.0.0.1:8080/api/smartcard/read',
            'http://localhost:8080/api/smartcard/read',
        ];

        $attempts = [];

        foreach ($endpoints as $endpoint) {
            $response = $this->fetchJson($endpoint);
            $attempts[] = [
                'endpoint' => $endpoint,
                'ok' => $response['ok'],
                'error' => $response['error'],
            ];

            if ($response['ok'] && is_array($response['data']) && $this->isReadableSmartCardResponse($response['data'])) {
                return [
                    'success' => true,
                    'payload' => $response['data'],
                    'source' => $endpoint,
                    'attempts' => $attempts,
                ];
            }
        }

        $mqttResult = $this->readSmartCardFromMqtt();
        $attempts[] = [
            'endpoint' => $mqttResult['source'] ?? 'mqtt://127.0.0.1:10883/moph/ict/mqtt',
            'ok' => $mqttResult['success'],
            'error' => $mqttResult['error'] ?? null,
        ];

        if ($mqttResult['success']) {
            return [
                'success' => true,
                'payload' => $mqttResult['payload'],
                'source' => $mqttResult['source'],
                'attempts' => $attempts,
            ];
        }

        return [
            'success' => false,
            'message' => 'ยังอ่านบัตรไม่ได้ กรุณาเปิดโปรแกรมช่วยอ่านบัตร แล้วกดอ่านบัตรก่อนเสียบบัตรประชาชน',
            'attempts' => $attempts,
        ];
    }

    private function isReadableSmartCardResponse(array $payload): bool
    {
        if (array_key_exists('success', $payload) && $payload['success'] === false) {
            return false;
        }

        $data = $payload['data'] ?? $payload['card'] ?? $payload['result'] ?? $payload;
        if (!is_array($data)) {
            return false;
        }

        $card = $this->normalizeSmartCardPayload($data);

        return ($card['citizen_id'] ?? '') !== '' || ($card['first_name'] ?? '') !== '';
    }

    private function readSmartCardFromMqtt(): array
    {
        $socket = @stream_socket_client(
            'tcp://127.0.0.1:10883',
            $errno,
            $errstr,
            0.8,
            STREAM_CLIENT_CONNECT
        );

        if (!is_resource($socket)) {
            return [
                'success' => false,
                'source' => 'mqtt://127.0.0.1:10883/moph/ict/mqtt',
                'error' => 'mqtt_connect_failed: ' . ($errstr ?: (string) $errno),
            ];
        }

        stream_set_timeout($socket, 1);

        fwrite($socket, $this->mqttConnectPacket('dongmahawan-' . bin2hex(random_bytes(4))));
        $connack = $this->mqttReadPacket($socket);

        if (($connack['type'] ?? 0) !== 2) {
            fclose($socket);
            return [
                'success' => false,
                'source' => 'mqtt://127.0.0.1:10883/moph/ict/mqtt',
                'error' => 'mqtt_connack_failed',
            ];
        }

        fwrite($socket, $this->mqttSubscribePacket('moph/ict/mqtt'));
        $deadline = microtime(true) + 10.0;
        $lastError = 'mqtt_timeout';

        while (microtime(true) < $deadline) {
            $packet = $this->mqttReadPacket($socket);

            if ($packet === null) {
                $lastError = 'mqtt_no_packet';
                continue;
            }

            if (($packet['type'] ?? 0) !== 3) {
                continue;
            }

            $message = $this->mqttPublishMessage($packet['payload'] ?? '');
            if ($message === '') {
                $lastError = 'mqtt_empty_publish';
                continue;
            }

            $decoded = json_decode($message, true);
            if (!is_array($decoded)) {
                $lastError = 'mqtt_invalid_json';
                continue;
            }

            fclose($socket);
            return [
                'success' => true,
                'source' => 'mqtt://127.0.0.1:10883/moph/ict/mqtt',
                'payload' => $decoded,
            ];
        }

        fclose($socket);

        return [
            'success' => false,
            'source' => 'mqtt://127.0.0.1:10883/moph/ict/mqtt',
            'error' => $lastError,
        ];
    }

    private function findMosquittoSubPath(): ?string
    {
        $userProfile = rtrim((string) getenv('USERPROFILE'), '\\/');
        $candidates = [
            $userProfile . '\\Downloads\\ไฟล์สำหรับติดตั้ง smartcard reader\\smartcard-reader\\mosquitto\\mosquitto_sub.exe',
            'C:\\Program Files\\mosquitto\\mosquitto_sub.exe',
            'C:\\Program Files (x86)\\mosquitto\\mosquitto_sub.exe',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function mqttConnectPacket(string $clientId): string
    {
        $variableHeader = "\x00\x04MQTT\x04\x02\x00\x05";
        $payload = pack('n', strlen($clientId)) . $clientId;

        return "\x10" . $this->mqttRemainingLength(strlen($variableHeader . $payload)) . $variableHeader . $payload;
    }

    private function mqttSubscribePacket(string $topic): string
    {
        $payload = "\x00\x01" . pack('n', strlen($topic)) . $topic . "\x00";

        return "\x82" . $this->mqttRemainingLength(strlen($payload)) . $payload;
    }

    private function mqttRemainingLength(int $length): string
    {
        $encoded = '';

        do {
            $byte = $length % 128;
            $length = intdiv($length, 128);
            if ($length > 0) {
                $byte |= 128;
            }
            $encoded .= chr($byte);
        } while ($length > 0);

        return $encoded;
    }

    private function mqttReadPacket(mixed $socket): ?array
    {
        $fixedHeader = fread($socket, 1);
        if ($fixedHeader === false || $fixedHeader === '') {
            return null;
        }

        $multiplier = 1;
        $remainingLength = 0;

        do {
            $encodedByte = fread($socket, 1);
            if ($encodedByte === false || $encodedByte === '') {
                return null;
            }

            $byte = ord($encodedByte);
            $remainingLength += ($byte & 127) * $multiplier;
            $multiplier *= 128;
        } while (($byte & 128) !== 0);

        $payload = '';
        while (strlen($payload) < $remainingLength) {
            $chunk = fread($socket, $remainingLength - strlen($payload));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $payload .= $chunk;
        }

        return [
            'type' => ord($fixedHeader) >> 4,
            'payload' => $payload,
        ];
    }

    private function mqttPublishMessage(string $payload): string
    {
        if (strlen($payload) < 2) {
            return '';
        }

        $topicLength = unpack('n', substr($payload, 0, 2))[1] ?? 0;
        $messageOffset = 2 + (int) $topicLength;

        if (strlen($payload) <= $messageOffset) {
            return '';
        }

        return trim(substr($payload, $messageOffset));
    }

    private function fetchJson(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 1.2,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false || trim($body) === '') {
            return ['ok' => false, 'data' => null, 'error' => 'no_response'];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'data' => null, 'error' => 'invalid_json'];
        }

        return ['ok' => true, 'data' => $decoded, 'error' => null];
    }

    private function normalizeSmartCardPayload(array $payload): array
    {
        $data = $payload['data'] ?? $payload['card'] ?? $payload['result'] ?? $payload;

        if (!is_array($data)) {
            $data = $payload;
        }

        if (isset($payload['raw']) && is_array($payload['raw'])) {
            $rawData = $payload['raw']['data'] ?? $payload['raw']['card'] ?? $payload['raw']['result'] ?? $payload['raw'];
            if (is_array($rawData)) {
                $data = array_merge($rawData, $data);
            }
        }

        $citizenId = preg_replace('/\D+/', '', (string) $this->firstValue($data, [
            'citizen_id', 'citizenId', 'cid', 'pid', 'PID', 'CitizenNo', 'card_id', 'id_card',
        ]));
        $thaiFullName = $this->firstValue($data, [
            'th_fullname', 'ThaiName', 'thai_name', 'fullname_th', 'fullNameTh',
        ]);
        $splitThaiName = $this->splitThaiFullName($thaiFullName);
        $titleName = $this->cleanCardText($this->firstValue($data, [
            'title_name', 'titleName', 'titleTH', 'prefixTH', 'PrefixTH', 'ThaiTitle',
        ]));
        $firstName = $this->cleanCardText($this->firstValue($data, [
            'first_name', 'firstName', 'firstname', 'firstnameTH', 'firstNameTh', 'FirstNameTh', 'fname', 'ThaiFirstName',
        ]));
        $lastName = $this->cleanCardText($this->firstValue($data, [
            'last_name', 'lastName', 'lastname', 'lastnameTH', 'lastNameTh', 'LastNameTh', 'lname', 'ThaiLastName',
        ]));

        return [
            'citizen_id' => $citizenId,
            'title_name' => $titleName !== '' ? $titleName : $splitThaiName['title_name'],
            'first_name' => $firstName !== '' ? $firstName : $splitThaiName['first_name'],
            'last_name' => $lastName !== '' ? $lastName : $splitThaiName['last_name'],
            'gender' => $this->normalizeCardGender($this->firstValue($data, ['gender', 'sex', 'Sex', 'Gender'])),
            'birth_date' => $this->normalizeCardBirthDate($this->firstValue($data, [
                'birth_date', 'birthDate', 'birthdate', 'BirthDate', 'dob',
            ])),
            'address' => $this->cleanCardText($this->firstValue($data, [
                'address', 'addressTH', 'Address', 'addr', 'ThaiAddress',
            ])),
            'photo' => $this->normalizeCardPhoto($this->findCardPhotoValue($payload)),
        ];
    }

    private function splitThaiFullName(string $value): array
    {
        $parts = array_values(array_filter(array_map('trim', explode('#', $value)), static fn ($part) => $part !== ''));

        if (count($parts) >= 3) {
            return [
                'title_name' => $parts[0],
                'first_name' => $parts[1],
                'last_name' => implode(' ', array_slice($parts, 2)),
            ];
        }

        if (count($parts) === 2) {
            return [
                'title_name' => '',
                'first_name' => $parts[0],
                'last_name' => $parts[1],
            ];
        }

        return [
            'title_name' => '',
            'first_name' => '',
            'last_name' => '',
        ];
    }

    private function firstValue(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && trim((string) $data[$key]) !== '') {
                return (string) $data[$key];
            }
        }

        return '';
    }

    private function findCardPhotoValue(mixed $value, int $depth = 0): string
    {
        if ($depth > 5 || $value === null) {
            return '';
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $this->looksLikeImageValue($trimmed) ? $trimmed : '';
        }

        if (!is_array($value)) {
            return '';
        }

        $directKeys = [
            'photo', 'photo_base64', 'photoBase64', 'image', 'image_base64', 'imageBase64',
            'portrait', 'portrait_base64', 'picture', 'picture_base64', 'card_photo',
            'cardPhoto', 'face', 'faceImage', 'jpeg', 'jpg',
        ];

        foreach ($directKeys as $key) {
            if (array_key_exists($key, $value)) {
                $found = $this->findCardPhotoValue($value[$key], $depth + 1);
                if ($found !== '') {
                    return $found;
                }
            }
        }

        foreach ($value as $nested) {
            $found = $this->findCardPhotoValue($nested, $depth + 1);
            if ($found !== '') {
                return $found;
            }
        }

        return '';
    }

    private function looksLikeImageValue(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/i', $value)) {
            return true;
        }

        $compact = preg_replace('/\s+/', '', $value) ?? '';
        if (strlen($compact) < 500 || !preg_match('/^[A-Za-z0-9+\/=]+$/', $compact)) {
            return false;
        }

        return str_starts_with($compact, '/9j/')
            || str_starts_with($compact, 'iVBOR')
            || str_starts_with($compact, 'UklGR');
    }

    private function normalizeCardPhoto(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $compact = preg_replace('/\s+/', '', trim($value)) ?? '';
        if (preg_match('/^data:image\//i', $compact)) {
            return $compact;
        }

        if (str_starts_with($compact, 'iVBOR')) {
            return 'data:image/png;base64,' . $compact;
        }

        if (str_starts_with($compact, 'UklGR')) {
            return 'data:image/webp;base64,' . $compact;
        }

        return 'data:image/jpeg;base64,' . $compact;
    }

    private function storePatientPhoto(string $dataUrl, string $hn): ?string
    {
        if ($dataUrl === '') {
            return null;
        }

        if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,([A-Za-z0-9+\/=\s]+)$/i', $dataUrl, $matches)) {
            return null;
        }

        $mime = strtolower($matches[1]);
        $extension = match ($mime) {
            'png' => 'png',
            'webp' => 'webp',
            default => 'jpg',
        };

        $binary = base64_decode(preg_replace('/\s+/', '', $matches[2]) ?? '', true);
        if ($binary === false || strlen($binary) < 100 || strlen($binary) > 2 * 1024 * 1024) {
            return null;
        }

        $photoDir = storage_path('patient-photos');
        if (!is_dir($photoDir)) {
            mkdir($photoDir, 0777, true);
        }

        $safeHn = preg_replace('/[^A-Za-z0-9_-]+/', '', $hn) ?: 'patient';
        $filename = sprintf('%s-%s.%s', $safeHn, date('YmdHis'), $extension);
        $fullPath = $photoDir . '/' . $filename;

        if (file_put_contents($fullPath, $binary) === false) {
            return null;
        }

        return 'storage/patient-photos/' . $filename;
    }

    private function ensurePatientPhotoSchema(): void
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = \'patients\'
               AND COLUMN_NAME = \'photo_path\''
        );
        $stmt->execute();

        if ((int) $stmt->fetchColumn() === 0) {
            db()->exec('ALTER TABLE patients ADD COLUMN photo_path VARCHAR(255) NULL AFTER note');
        }
    }

    private function versionedAssetUrl(string $path): string
    {
        $publicPath = BASE_PATH . '/public/' . ltrim($path, '/');
        $version = is_file($publicPath) ? (string) filemtime($publicPath) : (string) time();

        return app_url($path) . '?v=' . rawurlencode($version);
    }

    private function cleanCardText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace('#', ' ', $value)) ?? '');
    }

    private function normalizeCardGender(string $value): string
    {
        $value = trim($value);

        return match (true) {
            in_array($value, ['1', 'M', 'm', 'ชาย'], true) => 'M',
            in_array($value, ['2', 'F', 'f', 'หญิง'], true) => 'F',
            default => '',
        };
    }

    private function normalizeCardBirthDate(string $value): string
    {
        $value = preg_replace('/\D+/', '', trim($value));

        if (strlen($value) !== 8) {
            return '';
        }

        $year = (int) substr($value, 0, 4);
        if ($year > 2400) {
            $year -= 543;
        }

        $month = substr($value, 4, 2);
        $day = substr($value, 6, 2);

        if (!checkdate((int) $month, (int) $day, $year)) {
            return '';
        }

        return sprintf('%04d-%s-%s', $year, $month, $day);
    }

    private function normalizePatientBirthDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $day = 0;
        $month = 0;
        $year = 0;
        $normalized = preg_replace('/\s+/', '', $value) ?? '';

        if (preg_match('/^(\d{1,4})[\/.-](\d{1,2})[\/.-](\d{1,4})$/', $normalized, $matches)) {
            if (strlen($matches[1]) === 4) {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
                $day = (int) $matches[3];
            } else {
                $day = (int) $matches[1];
                $month = (int) $matches[2];
                $year = (int) $matches[3];
            }
        } else {
            $digits = preg_replace('/\D+/', '', $normalized) ?? '';
            if (strlen($digits) !== 8) {
                return '';
            }

            $firstFour = (int) substr($digits, 0, 4);
            if ($firstFour >= 1900) {
                $year = $firstFour;
                $month = (int) substr($digits, 4, 2);
                $day = (int) substr($digits, 6, 2);
            } else {
                $day = (int) substr($digits, 0, 2);
                $month = (int) substr($digits, 2, 2);
                $year = (int) substr($digits, 4, 4);
            }
        }

        if ($year > 2400) {
            $year -= 543;
        }

        if (!checkdate($month, $day, $year)) {
            return '';
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
