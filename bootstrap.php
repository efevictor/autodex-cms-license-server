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

// ── Auth helpers ──────────────────────────────────────────
function ls_logged_in(): bool  { return !empty($_SESSION['ls_admin']); }
function ls_require_login(): void
{
    if (!ls_logged_in()) { header('Location: ' . ls_url('login')); exit; }
}

// ── URL helper ────────────────────────────────────────────
function ls_url(string $page = '', array $params = []): string
{
    $url = 'index.php' . ($page ? '?p=' . $page : '?p=dashboard');
    foreach ($params as $k => $v) $url .= '&' . urlencode($k) . '=' . urlencode($v);
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
function generate_license_key(): string
{
    $seg = fn() => strtoupper(bin2hex(random_bytes(3)));
    return "ADSK-{$seg()}-{$seg()}-{$seg()}";
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
