<?php
/**
 * License Server — Update Package Proxy
 * GET /api/download?key=LICENSE_KEY&version=1.5.0
 *
 * Validates the license key, then streams the release ZIP from the
 * private GitHub repo using the server-side PAT. The token never
 * leaves this server; clients only ever see the license-server URL.
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../bootstrap.php';

$key     = trim($_GET['key']     ?? '');
$version = trim($_GET['version'] ?? '');

if (!$key || !$version) {
    http_response_code(400);
    exit('Missing parameters.');
}

// ── Validate license ──────────────────────────────────────
$license = ls_row(
    "SELECT * FROM licenses WHERE license_key = :k AND status = 'active' LIMIT 1",
    [':k' => $key]
);

if (!$license) {
    http_response_code(403);
    exit('Invalid or inactive license.');
}

if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
    http_response_code(403);
    exit('License expired.');
}

// ── Look up version ───────────────────────────────────────
$ver = ls_row(
    "SELECT * FROM versions WHERE version = :v LIMIT 1",
    [':v' => $version]
);

if (!$ver || !$ver['download_url']) {
    http_response_code(404);
    exit('Version not found or no download available.');
}

// ── Fetch from GitHub using PAT ───────────────────────────
if (!defined('LS_GITHUB_TOKEN') || !LS_GITHUB_TOKEN) {
    http_response_code(503);
    exit('Update delivery not configured on the license server.');
}

@set_time_limit(300);

$github_url = $ver['download_url'];

$ctx = stream_context_create([
    'http' => [
        'method'          => 'GET',
        'header'          => implode("\r\n", [
            'Authorization: token ' . LS_GITHUB_TOKEN,
            'Accept: application/vnd.github+json',
            'User-Agent: AutoDex-LicenseServer/1.0',
            'X-GitHub-Api-Version: 2022-11-28',
        ]),
        'follow_location' => true,
        'max_redirects'   => 5,
        'timeout'         => 120,
    ],
    'ssl' => [
        'verify_peer'      => true,
        'verify_peer_name' => true,
    ],
]);

$handle = @fopen($github_url, 'rb', false, $ctx);

if (!$handle) {
    http_response_code(502);
    exit('Could not fetch update package from source. Check the GitHub token and URL.');
}

// ── Stream ZIP to client ──────────────────────────────────
$filename = 'autodex-' . preg_replace('/[^a-z0-9.\-]/i', '', $version) . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache');
header('X-Robots-Tag: noindex');

fpassthru($handle);
fclose($handle);
exit;
