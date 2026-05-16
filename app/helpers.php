<?php

use App\Core\Auth;
use App\Core\Database;

function config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS['config'] ?? [];

    if ($key === null) {
        return $config;
    }

    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function system_settings(): array
{
    static $settings = null;

    if ($settings !== null) {
        return $settings;
    }

    try {
        $row = db()->query('SELECT * FROM system_settings ORDER BY id ASC LIMIT 1')->fetch();
        $settings = is_array($row) ? $row : [];
    } catch (Throwable $throwable) {
        $settings = [];
    }

    return $settings;
}

function system_setting(string $key, mixed $default = null): mixed
{
    $settings = system_settings();

    if (array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== '') {
        return $settings[$key];
    }

    return match ($key) {
        'clinic_name' => config('app.name', $default),
        'receipt_footer', 'queue_note' => config('app.receipt_footer', $default),
        default => $default,
    };
}

function db(): PDO
{
    return Database::connection();
}

function app_url(string $path = ''): string
{
    $baseUrl = rtrim((string) config('app.base_url', ''), '/');
    $path = ltrim($path, '/');

    return $path === '' ? $baseUrl : $baseUrl . '/' . $path;
}

function route_url(string $page, array $params = []): string
{
    $query = array_merge(['page' => $page], $params);

    return app_url('index.php?' . http_build_query($query));
}

function redirect(string $page, array $params = []): never
{
    header('Location: ' . route_url($page, $params));
    exit;
}

function redirect_raw(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function remember_old_input(array $input): void
{
    $_SESSION['_old'] = $input;
}

function clear_old_input(): void
{
    unset($_SESSION['_old']);
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if (!is_post()) {
        return;
    }

    $token = $_POST['_csrf'] ?? '';

    if (!hash_equals(csrf_token(), (string) $token)) {
        http_response_code(419);
        exit('CSRF token mismatch');
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function view(string $template, array $data = [], string $layout = 'layouts/app'): void
{
    extract($data, EXTR_SKIP);
    $viewPath = BASE_PATH . '/app/Views/' . $template . '.php';

    if (!is_file($viewPath)) {
        throw new RuntimeException("View not found: {$template}");
    }

    ob_start();
    require $viewPath;
    $content = ob_get_clean();

    require BASE_PATH . '/app/Views/' . $layout . '.php';
}

function current_user(): ?array
{
    return Auth::user();
}

function auth_check(): bool
{
    return Auth::check();
}

function current_page(): string
{
    return (string) ($_GET['page'] ?? (auth_check() ? default_home_page() : 'login'));
}

function default_home_page(): string
{
    $user = current_user();

    if (!$user) {
        return 'login';
    }

    return match ($user['role_code'] ?? '') {
        'CASHIER' => 'payments',
        'ADMIN', 'NURSE' => 'queue',
        default => 'dashboard',
    };
}

function has_role(string|array $roles): bool
{
    return Auth::hasRole($roles);
}

function require_login(): void
{
    Auth::requireLogin();
}

function require_roles(string|array $roles): void
{
    Auth::requireRole($roles);
}

function selected(?string $value, ?string $expected): string
{
    return (string) $value === (string) $expected ? 'selected' : '';
}

function checked(mixed $value, mixed $expected): string
{
    return (string) $value === (string) $expected ? 'checked' : '';
}

function format_money(float|int|string|null $amount): string
{
    return number_format((float) $amount, 2);
}

function thai_date(?string $datetime): string
{
    if (empty($datetime)) {
        return '-';
    }

    return date('d/m/Y H:i', strtotime($datetime));
}

function thai_date_only(?string $date): string
{
    if (empty($date)) {
        return '-';
    }

    return date('d/m/Y', strtotime($date));
}

function queue_status_meta(string $status): array
{
    return match ($status) {
        'WAITING' => ['label' => 'รอรับบริการ', 'class' => 'warning text-dark'],
        'IN_SERVICE' => ['label' => 'กำลังตรวจ', 'class' => 'info text-dark'],
        'WAITING_PAYMENT' => ['label' => 'รอชำระเงิน', 'class' => 'secondary'],
        'COMPLETED' => ['label' => 'เสร็จสิ้น', 'class' => 'success'],
        'CANCELLED' => ['label' => 'ยกเลิกเคส', 'class' => 'danger'],
        default => ['label' => $status, 'class' => 'light text-dark'],
    };
}

function storage_path(string $path = ''): string
{
    $fullPath = BASE_PATH . '/storage' . ($path ? '/' . ltrim($path, '/') : '');
    return str_replace('\\', '/', $fullPath);
}

function can_transition_queue_status(string $fromStatus, string $toStatus): bool
{
    $allowedTransitions = [
        'WAITING' => ['IN_SERVICE', 'CANCELLED'],
        'IN_SERVICE' => ['WAITING_PAYMENT', 'COMPLETED', 'CANCELLED'],
        'WAITING_PAYMENT' => ['IN_SERVICE', 'COMPLETED', 'CANCELLED'],
        'COMPLETED' => [],
        'CANCELLED' => [],
    ];

    return in_array($toStatus, $allowedTransitions[$fromStatus] ?? [], true);
}

function is_visit_editable_status(?string $status): bool
{
    return (string) $status === 'IN_SERVICE';
}
