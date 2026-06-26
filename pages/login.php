<?php
require_once dirname(__DIR__) . '/bootstrap.php';

if (ls_logged_in()) { header('Location: ' . ls_url('dashboard')); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    $db_user = ls_setting('admin_username', LS_ADMIN_USER);
    $db_hash = ls_setting('admin_pass_hash', LS_ADMIN_PASS_HASH);
    if ($user === $db_user && password_verify($pass, $db_hash)) {
        // Password correct — generate and send OTP
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['ls_pending_auth'] = $user;
        $_SESSION['ls_otp']          = [
            'code'     => password_hash($otp, PASSWORD_BCRYPT),
            'expires'  => time() + 600,
            'attempts' => 0,
        ];
        session_regenerate_id(true);

        $admin_email = defined('LS_ADMIN_EMAIL') ? LS_ADMIN_EMAIL : '';
        if ($admin_email) {
            $body = '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f5f5f5;padding:40px 0">
                <div style="max-width:420px;margin:0 auto;background:#fff;border-radius:12px;padding:40px;box-shadow:0 4px 24px rgba(0,0,0,.08)">
                    <div style="font-size:1.4rem;font-weight:800;margin-bottom:4px">Auto<span style="color:#d00000">Dex</span></div>
                    <div style="color:#6b7280;font-size:.85rem;margin-bottom:28px">License Server — Two-Factor Authentication</div>
                    <p style="color:#374151;margin-bottom:8px">Your one-time login code is:</p>
                    <div style="font-size:2.5rem;font-weight:800;letter-spacing:10px;color:#000;background:#f3f4f6;border-radius:8px;padding:18px 0;text-align:center;margin-bottom:20px">' . e($otp) . '</div>
                    <p style="color:#6b7280;font-size:.82rem;margin:0">This code expires in <strong>10 minutes</strong>. If you did not attempt to log in, your password may be compromised — change it immediately.</p>
                </div>
            </body></html>';
            ls_send_mail($admin_email, 'AutoDex Login Code: ' . $otp, $body);
        }

        header('Location: ' . ls_url('verify_otp')); exit;
    }
    $error = 'Incorrect username or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In — AutoDex</title>
    <link rel="icon" type="image/svg+xml" href="<?= (defined('LS_BASE') ? LS_BASE : '') ?>/favicon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body   { background: #000; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', system-ui, sans-serif; }
        .card  { width: 100%; max-width: 400px; border: none; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.6); }
        .logo  { font-size: 1.5rem; font-weight: 800; color: #000; letter-spacing: -.4px; }
        .logo span { color: #d00000; }
        .btn-brand { background: #d00000; border-color: #d00000; color: #fff; font-weight: 600; }
        .btn-brand:hover { background: #b30000; border-color: #b30000; color: #fff; }
        .form-control:focus { border-color: #d00000; box-shadow: 0 0 0 3px rgba(208,0,0,.2); }
    </style>
</head>
<body>
<div class="card">
    <div class="card-body p-5">
        <div class="text-center mb-4">
            <div class="logo">Auto<span>Dex</span></div>
            <div class="text-muted small mt-1">Dealership CMS Licensing</div>
            <div class="mt-2" style="font-size:.7rem;color:#9ca3af">v<?= e(ls_current_version()) ?></div>
            <div class="mt-1" style="font-size:.65rem;color:#d1d5db">A property of <strong style="color:#F5A623">EvoDesignCo</strong></div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Username</label>
                <input type="text" name="username" class="form-control"
                       value="<?= e($_POST['username'] ?? '') ?>" autofocus autocomplete="username">
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold small">Password</label>
                <input type="password" name="password" class="form-control" autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-brand w-100">Sign In</button>
        </form>
    </div>
</div>
</body>
</html>
