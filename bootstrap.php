<?php
/**
 * License Server — Bootstrap
 * Loaded at the top of every page.
 */

require_once __DIR__ . '/config.php';

// ── Session ───────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(LS_SESSION);
    session_start();
}

// ── Database ──────────────────────────────────────────────
function ls_db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO(
            'mysql:host=' . LS_DB_HOST . ';port=' . LS_DB_PORT . ';dbname=' . LS_DB_NAME . ';charset=utf8mb4',
            LS_DB_USER, LS_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function ls_row(string $sql, array $p = []): ?array
{
    $s = ls_db()->prepare($sql); $s->execute($p); return $s->fetch() ?: null;
}
function ls_rows(string $sql, array $p = []): array
{
    $s = ls_db()->prepare($sql); $s->execute($p); return $s->fetchAll();
}
function ls_val(string $sql, array $p = []): mixed
{
    $s = ls_db()->prepare($sql); $s->execute($p); $r = $s->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null;
}
function ls_run(string $sql, array $p = []): void
{
    ls_db()->prepare($sql)->execute($p);
}

// ── Settings helpers ──────────────────────────────────────
function ls_setting(string $key, string $default = ''): string
{
    try {
        $val = ls_val("SELECT value FROM settings WHERE `key` = :k", [':k' => $key]);
        return $val ?? $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

function ls_set_setting(string $key, string $value): void
{
    ls_run(
        "INSERT INTO settings (`key`, `value`) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE `value` = :v2, updated_at = NOW()",
        [':k' => $key, ':v' => $value, ':v2' => $value]
    );
}

function ls_current_version(): string
{
    try {
        return ls_val("SELECT version FROM versions ORDER BY id DESC LIMIT 1") ?? '1.0.0';
    } catch (\Throwable $e) {
        return '1.0.0';
    }
}

// ── Auth helpers ──────────────────────────────────────────
function ls_logged_in(): bool { return !empty($_SESSION['ls_admin']); }
function ls_require_login(): void
{
    if (!ls_logged_in()) { header('Location: ' . ls_url('login')); exit; }

    // 60-day password expiry — checked once per session
    if (empty($_SESSION['ls_expiry_checked'])) {
        $_SESSION['ls_expiry_checked'] = true;
        $last = ls_setting('last_password_change');
        if ($last && (time() - strtotime($last)) > (60 * 86400)) {
            $_SESSION['ls_pass_expired'] = true;
        }
    }

    // Force to settings until password is changed
    $uri   = $_SERVER['REQUEST_URI'] ?? '';
    $pgGet = $_GET['p'] ?? '';
    if (!empty($_SESSION['ls_pass_expired']) &&
        strpos($uri, '/settings') === false && $pgGet !== 'settings') {
        header('Location: ' . ls_url('settings')); exit;
    }
}

// ── URL helper ────────────────────────────────────────────
function ls_url(string $page = '', array $params = []): string
{
    $page = $page ?: 'dashboard';

    if ($page === 'licenses.view') {
        $id = $params['id'] ?? '';
        unset($params['id']);
        $path = 'licenses/' . $id;
    } elseif ($page === 'licenses.add') {
        $path = 'licenses/add';
    } else {
        $path = $page; // dashboard, login, logout, licenses, settings, changelog
    }

    $base = defined('LS_BASE') ? LS_BASE : '';
    $url = $base . '/' . $path;
    if ($params) { $url .= '?' . http_build_query($params); }
    return $url;
}

// ── Output helpers ────────────────────────────────────────
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function flash_set(string $t, string $m): void { $_SESSION['ls_flash'] = ['type' => $t, 'msg' => $m]; }
function flash_get(): ?array
{
    if (!empty($_SESSION['ls_flash'])) { $f = $_SESSION['ls_flash']; unset($_SESSION['ls_flash']); return $f; }
    return null;
}

// ── Key generator ─────────────────────────────────────────
function generate_license_key(string $prefix = 'ADSK'): string
{
    $seg = fn() => strtoupper(bin2hex(random_bytes(3)));
    $pfx = strtoupper(preg_replace('/[^A-Z0-9]/', '', $prefix)) ?: 'ADSK';
    return "{$pfx}-{$seg()}-{$seg()}-{$seg()}";
}

// ── CSRF ──────────────────────────────────────────────────
function csrf_token(): string
{
    if (empty($_SESSION['ls_csrf'])) $_SESSION['ls_csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['ls_csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void
{
    if (($_POST['_csrf'] ?? '') !== csrf_token()) {
        http_response_code(403); die('Invalid CSRF token.');
    }
}
