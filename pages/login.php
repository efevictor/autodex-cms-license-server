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
        $_SESSION['ls_admin'] = $user;
        unset($_SESSION['ls_expiry_checked'], $_SESSION['ls_pass_expired']);
        session_regenerate_id(true);
        header('Location: ' . ls_url('dashboard')); exit;
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
