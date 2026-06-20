<?php
require_once dirname(__DIR__) . '/bootstrap.php';

if (ls_logged_in()) { header('Location: ' . ls_url('dashboard')); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($user === LS_ADMIN_USER && password_verify($pass, LS_ADMIN_PASS_HASH)) {
        $_SESSION['ls_admin'] = $user;
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
    <title>Sign In — License Manager</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body   { background: #1a1e3a; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', system-ui, sans-serif; }
        .card  { width: 100%; max-width: 400px; border: none; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.4); }
        .logo  { font-size: 1.5rem; font-weight: 800; color: #1a1e3a; letter-spacing: -.4px; }
        .logo span { color: #E07B22; }
        .btn-brand { background: #E07B22; border-color: #E07B22; color: #fff; font-weight: 600; }
        .btn-brand:hover { background: #c56b1a; border-color: #c56b1a; color: #fff; }
        .form-control:focus { border-color: #E07B22; box-shadow: 0 0 0 3px rgba(224,123,34,.2); }
    </style>
</head>
<body>
<div class="card">
    <div class="card-body p-5">
        <div class="text-center mb-4">
            <div class="logo">Auto<span>Desk</span></div>
            <div class="text-muted small mt-1">License Manager — Admin</div>
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
