<?php
require_once dirname(__DIR__) . '/bootstrap.php';

// Must have passed password check first
if (empty($_SESSION['ls_pending_auth']) || empty($_SESSION['ls_otp'])) {
    header('Location: ' . ls_url('login')); exit;
}
// Already fully logged in
if (ls_logged_in()) { header('Location: ' . ls_url('dashboard')); exit; }

$error   = '';
$success = '';
$otp_data = &$_SESSION['ls_otp'];

// Handle resend
if (isset($_GET['resend'])) {
    $new_otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otp_data = [
        'code'     => password_hash($new_otp, PASSWORD_BCRYPT),
        'expires'  => time() + 600,
        'attempts' => 0,
    ];
    $admin_email = defined('LS_ADMIN_EMAIL') ? LS_ADMIN_EMAIL : '';
    if ($admin_email) {
        $body = '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f5f5f5;padding:40px 0">
            <div style="max-width:420px;margin:0 auto;background:#fff;border-radius:12px;padding:40px;box-shadow:0 4px 24px rgba(0,0,0,.08)">
                <div style="font-size:1.4rem;font-weight:800;margin-bottom:4px">Auto<span style="color:#d00000">Dex</span></div>
                <div style="color:#6b7280;font-size:.85rem;margin-bottom:28px">License Server — New Code</div>
                <p style="color:#374151;margin-bottom:8px">Your new one-time login code is:</p>
                <div style="font-size:2.5rem;font-weight:800;letter-spacing:10px;color:#000;background:#f3f4f6;border-radius:8px;padding:18px 0;text-align:center;margin-bottom:20px">' . e($new_otp) . '</div>
                <p style="color:#6b7280;font-size:.82rem;margin:0">This code expires in <strong>10 minutes</strong>.</p>
            </div>
        </body></html>';
        ls_send_mail($admin_email, 'AutoDex Login Code: ' . $new_otp, $body);
    }
    $success = 'A new code has been sent to your email.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $typed = trim(str_replace(' ', '', $_POST['otp'] ?? ''));

    // Check expiry
    if (time() > $otp_data['expires']) {
        unset($_SESSION['ls_otp'], $_SESSION['ls_pending_auth']);
        header('Location: ' . ls_url('login') . '?err=otp_expired'); exit;
    }

    // Check attempts
    if ($otp_data['attempts'] >= 5) {
        unset($_SESSION['ls_otp'], $_SESSION['ls_pending_auth']);
        header('Location: ' . ls_url('login') . '?err=otp_blocked'); exit;
    }

    if (password_verify($typed, $otp_data['code'])) {
        // Success — grant full session
        $user = $_SESSION['ls_pending_auth'];
        unset($_SESSION['ls_otp'], $_SESSION['ls_pending_auth'], $_SESSION['ls_expiry_checked'], $_SESSION['ls_pass_expired']);
        $_SESSION['ls_admin'] = $user;
        session_regenerate_id(true);
        header('Location: ' . ls_url('dashboard')); exit;
    }

    $otp_data['attempts']++;
    $remaining = 5 - $otp_data['attempts'];
    $error = 'Incorrect code. ' . ($remaining > 0 ? "{$remaining} attempt" . ($remaining !== 1 ? 's' : '') . " remaining." : 'Account locked — please sign in again.');

    if ($remaining <= 0) {
        unset($_SESSION['ls_otp'], $_SESSION['ls_pending_auth']);
        header('Location: ' . ls_url('login') . '?err=otp_blocked'); exit;
    }
}

$masked_email = '';
if (defined('LS_ADMIN_EMAIL') && LS_ADMIN_EMAIL) {
    [$local, $domain] = explode('@', LS_ADMIN_EMAIL, 2);
    $masked_email = substr($local, 0, 2) . str_repeat('*', max(2, strlen($local) - 2)) . '@' . $domain;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Login — AutoDex</title>
    <link rel="icon" type="image/svg+xml" href="<?= (defined('LS_BASE') ? LS_BASE : '') ?>/favicon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body   { background:#000; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:'Segoe UI',system-ui,sans-serif; }
        .card  { width:100%; max-width:420px; border:none; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.6); }
        .logo  { font-size:1.5rem; font-weight:800; color:#000; letter-spacing:-.4px; }
        .logo span { color:#d00000; }
        .btn-brand { background:#d00000; border-color:#d00000; color:#fff; font-weight:600; }
        .btn-brand:hover { background:#b30000; border-color:#b30000; color:#fff; }
        .otp-input { font-size:1.8rem; font-weight:700; letter-spacing:8px; text-align:center; border-radius:10px; border:2px solid #e5e7eb; padding:14px; }
        .otp-input:focus { border-color:#d00000; box-shadow:0 0 0 3px rgba(208,0,0,.2); outline:none; }
    </style>
</head>
<body>
<div class="card">
    <div class="card-body p-5">
        <div class="text-center mb-4">
            <div class="logo">Auto<span>Dex</span></div>
            <div class="text-muted small mt-1">Two-Factor Authentication</div>
        </div>

        <p class="text-center text-muted small mb-4">
            A 6-digit code was sent to
            <?php if ($masked_email): ?>
                <strong><?= e($masked_email) ?></strong>
            <?php else: ?>
                your registered email
            <?php endif; ?>.
            It expires in 10 minutes.
        </p>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small text-center"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success py-2 small text-center"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['err'])): ?>
            <?php $err_msgs = ['otp_expired' => 'Your code expired. Please sign in again.', 'otp_blocked' => 'Too many failed attempts. Please sign in again.']; ?>
            <div class="alert alert-danger py-2 small text-center"><?= e($err_msgs[$_GET['err']] ?? 'Please sign in again.') ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-4">
                <input type="text" name="otp" class="form-control otp-input"
                       maxlength="6" placeholder="——————"
                       inputmode="numeric" pattern="[0-9]{6}"
                       autofocus>
            </div>
            <button type="submit" class="btn btn-brand w-100 mb-3">Verify →</button>
        </form>

        <div class="text-center">
            <a href="<?= ls_url('verify_otp') ?>?resend=1" class="text-muted small">Didn't receive it? Resend code</a>
        </div>
        <div class="text-center mt-2">
            <a href="<?= ls_url('login') ?>" class="text-muted small">← Back to sign in</a>
        </div>
    </div>
</div>
</body>
</html>
