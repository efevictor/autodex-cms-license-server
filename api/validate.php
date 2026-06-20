<?php
/**
 * AutoDesk CMS — License Validation API
 * Host this on YOUR server at: https://licenses.yourproduct.com/api/validate
 *
 * This endpoint receives a POST request from the CMS installer and
 * returns whether the license key is valid for the given domain.
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['valid' => false, 'message' => 'Method not allowed.']));
}

// Parse JSON body
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    exit(json_encode(['valid' => false, 'message' => 'Invalid request body.']));
}

$email   = strtolower(trim($body['email']   ?? ''));
$key     = trim($body['key']     ?? '');
$domain  = strtolower(trim($body['domain']  ?? ''));
$product = trim($body['product'] ?? '');

// Basic input validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($key) < 10 || empty($domain)) {
    exit(json_encode(['valid' => false, 'message' => 'Missing or invalid parameters.']));
}

// ── Database connection (your license server DB) ──────────
require __DIR__ . '/../config.php'; // defines LS_DB_HOST, LS_DB_NAME, LS_DB_USER, LS_DB_PASS

try {
    $pdo = new PDO(
        "mysql:host=" . LS_DB_HOST . ";dbname=" . LS_DB_NAME . ";charset=utf8mb4",
        LS_DB_USER, LS_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit(json_encode(['valid' => false, 'message' => 'License server error. Please try again.']));
}

// ── Look up the license ───────────────────────────────────
$stmt = $pdo->prepare("
    SELECT l.*, p.slug AS product_slug
    FROM licenses l
    JOIN products p ON p.id = l.product_id
    WHERE l.license_key = :key
      AND l.purchase_email = :email
    LIMIT 1
");
$stmt->execute([':key' => $key, ':email' => $email]);
$license = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$license) {
    exit(json_encode(['valid' => false, 'message' => 'License key not found or email does not match.']));
}

// ── Check product match ───────────────────────────────────
if ($product && $license['product_slug'] !== $product) {
    exit(json_encode(['valid' => false, 'message' => 'This license key is not valid for ' . htmlspecialchars($product) . '.']));
}

// ── Check status ──────────────────────────────────────────
if ($license['status'] !== 'active') {
    $msgs = [
        'suspended'  => 'This license has been suspended. Please contact support.',
        'refunded'   => 'This license has been refunded and is no longer valid.',
        'expired'    => 'This license has expired. Please renew to continue.',
    ];
    exit(json_encode(['valid' => false, 'message' => $msgs[$license['status']] ?? 'License is not active.']));
}

// ── Check expiry ──────────────────────────────────────────
if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
    $pdo->prepare("UPDATE licenses SET status='expired' WHERE id=:id")->execute([':id' => $license['id']]);
    exit(json_encode(['valid' => false, 'message' => 'This license expired on ' . date('d M Y', strtotime($license['expires_at'])) . '.']));
}

// ── Domain binding ────────────────────────────────────────
$clean_domain = preg_replace('/^www\./', '', $domain);

if (empty($license['activated_domain'])) {
    // First activation — bind to this domain
    $pdo->prepare("
        UPDATE licenses
        SET activated_domain = :domain, activated_at = NOW(), activations = activations + 1
        WHERE id = :id
    ")->execute([':domain' => $clean_domain, ':id' => $license['id']]);

    log_activation($pdo, $license['id'], $clean_domain, $email, 'activated');

} else {
    $bound = preg_replace('/^www\./', '', $license['activated_domain']);

    if ($bound !== $clean_domain) {
        // Already bound to a different domain
        // Allow if within the allowed_domains count (for multi-site licenses)
        $extra = json_decode($license['extra_domains'] ?? '[]', true) ?: [];

        if (!in_array($clean_domain, $extra, true)) {
            if (count($extra) < (int)($license['max_domains'] - 1)) {
                // Add this domain as an additional allowed domain
                $extra[] = $clean_domain;
                $pdo->prepare("UPDATE licenses SET extra_domains=:d, activations=activations+1 WHERE id=:id")
                    ->execute([':d' => json_encode($extra), ':id' => $license['id']]);
                log_activation($pdo, $license['id'], $clean_domain, $email, 'additional_domain');
            } else {
                exit(json_encode([
                    'valid'   => false,
                    'message' => 'This license is already activated on ' . htmlspecialchars($bound) . '. '
                               . 'To use it on a different domain, deactivate it from your account first.',
                ]));
            }
        }
    }
}

// ── Return success ────────────────────────────────────────
$pdo->prepare("UPDATE licenses SET last_check_at = NOW() WHERE id = :id")->execute([':id' => $license['id']]);

exit(json_encode([
    'valid'      => true,
    'plan'       => $license['plan'] ?? 'standard',
    'expires_at' => $license['expires_at'],
    'domain'     => $clean_domain,
]));


// ── Helper: log activation event ─────────────────────────
function log_activation(PDO $pdo, int $license_id, string $domain, string $email, string $event): void
{
    $pdo->prepare("
        INSERT INTO activation_logs (license_id, domain, email, event, ip_address, created_at)
        VALUES (:lid, :dom, :email, :evt, :ip, NOW())
    ")->execute([
        ':lid'   => $license_id,
        ':dom'   => $domain,
        ':email' => $email,
        ':evt'   => $event,
        ':ip'    => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
    ]);
}
