<?php
/**
 * License Server — Auto-publish Version API
 * POST /api/publish_version.php
 * Called by GitHub Actions after a release is created.
 *
 * Body (JSON): { secret, version, notes, download_url, checksum }
 */

header('Content-Type: application/json');

require __DIR__ . '/../config.php';
require __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'message' => 'Method not allowed.']));
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'message' => 'Invalid JSON body.']));
}

// ── Authenticate ──────────────────────────────────────────
if (!defined('LS_PUBLISH_SECRET') || !LS_PUBLISH_SECRET) {
    http_response_code(503);
    exit(json_encode(['ok' => false, 'message' => 'Publish secret not configured on server.']));
}

if (!hash_equals(LS_PUBLISH_SECRET, $body['secret'] ?? '')) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'message' => 'Invalid secret.']));
}

// ── Validate fields ───────────────────────────────────────
$version  = trim($body['version']      ?? '');
$notes    = trim($body['notes']        ?? '');
$dl_url   = trim($body['download_url'] ?? '');
$checksum = strtolower(trim($body['checksum'] ?? ''));

if (!preg_match('/^\d+\.\d+(\.\d+)?$/', $version)) {
    http_response_code(422);
    exit(json_encode(['ok' => false, 'message' => 'Invalid version format.']));
}

// ── Check for duplicate ───────────────────────────────────
$existing = ls_val("SELECT id FROM versions WHERE version = :v", [':v' => $version]);
if ($existing) {
    http_response_code(409);
    exit(json_encode(['ok' => false, 'message' => "Version {$version} already exists."]));
}

// ── Insert ────────────────────────────────────────────────
ls_run(
    "INSERT INTO versions (version, notes, download_url, sha256_checksum, released_at)
     VALUES (:v, :n, :u, :c, NOW())",
    [':v' => $version, ':n' => $notes, ':u' => $dl_url ?: null, ':c' => $checksum ?: null]
);

exit(json_encode([
    'ok'      => true,
    'message' => "Version {$version} published successfully.",
    'version' => $version,
]));
