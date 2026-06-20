<?php
require_once dirname(__DIR__) . '/bootstrap.php';
ls_require_login();

$page_title = 'Settings';
$errors = [];
$success = '';

$current_username = ls_setting('admin_username', LS_ADMIN_USER);
$last_change      = ls_setting('last_password_change');
$days_since       = $last_change ? (int)floor((time() - strtotime($last_change)) / 86400) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $current_pass  = $_POST['current_password']  ?? '';
    $new_username  = trim($_POST['new_username']  ?? '');
    $new_pass      = $_POST['new_password']       ?? '';
    $confirm_pass  = $_POST['confirm_password']   ?? '';

    // Verify current password before any change
    $current_hash = ls_setting('admin_pass_hash', LS_ADMIN_PASS_HASH);
    if (!password_verify($current_pass, $current_hash)) {
        $errors[] = 'Current password is incorrect.';
    }

    if (empty($errors)) {
        $changed = false;

        if ($new_username !== '' && $new_username !== $current_username) {
            if (strlen($new_username) < 3) {
                $errors[] = 'Username must be at least 3 characters.';
            } else {
                ls_set_setting('admin_username', $new_username);
                $_SESSION['ls_admin'] = $new_username;
                $current_username = $new_username;
                $changed = true;
            }
        }

        if ($new_pass !== '') {
            if (strlen($new_pass) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            } elseif ($new_pass !== $confirm_pass) {
                $errors[] = 'New passwords do not match.';
            } else {
                $hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
                ls_set_setting('admin_pass_hash', $hash);
                ls_set_setting('last_password_change', date('Y-m-d H:i:s'));
                unset($_SESSION['ls_pass_expired'], $_SESSION['ls_expiry_checked']);
                $days_since = 0;
                $changed = true;
            }
        }

        if (empty($errors)) {
            $success = $changed ? 'Settings updated successfully.' : 'No changes were made.';
        }
    }
}

require __DIR__ . '/../layout/header.php';
?>

<div class="ls-topbar">
    <h1 class="ls-title">Settings</h1>
</div>

<?php if (!empty($_SESSION['ls_pass_expired'])): ?>
<div class="alert alert-danger mb-4">
    <strong>Password expired.</strong> Your password has not been changed in over 60 days. Please update it below to continue.
</div>
<?php elseif ($days_since !== null && $days_since >= 45): ?>
<div class="alert alert-warning mb-4">
    <strong>Password expiry warning.</strong> Your password was last changed <?= $days_since ?> days ago. It will be required to change it after 60 days.
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success mb-4"><?= e($success) ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
    <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li style="font-size:.88rem"><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="ls-card p-4">
            <h6 class="fw-bold mb-4">Account Credentials</h6>
            <form method="POST">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label">Current Username</label>
                    <input type="text" class="form-control" value="<?= e($current_username) ?>" disabled>
                </div>
                <div class="mb-4">
                    <label class="form-label">New Username <span class="text-muted fw-normal small">(leave blank to keep current)</span></label>
                    <input type="text" name="new_username" class="form-control"
                           value="<?= e($_POST['new_username'] ?? '') ?>"
                           placeholder="<?= e($current_username) ?>" autocomplete="username">
                </div>

                <hr class="my-4">

                <div class="mb-3">
                    <label class="form-label">Current Password <span class="text-danger">*</span></label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password <span class="text-muted fw-normal small">(leave blank to keep current)</span></label>
                    <input type="password" name="new_password" class="form-control" autocomplete="new-password"
                           placeholder="Min. 8 characters">
                </div>
                <div class="mb-4">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-brand w-100">Save Changes</button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="ls-card p-4">
            <h6 class="fw-bold mb-3">Password Policy</h6>
            <dl class="row mb-0" style="font-size:.85rem">
                <dt class="col-sm-6 text-muted fw-normal">Last Changed</dt>
                <dd class="col-sm-6 fw-semibold">
                    <?php if ($last_change): ?>
                        <?= date('d M Y', strtotime($last_change)) ?>
                        <span class="text-muted fw-normal">(<?= $days_since ?> day<?= $days_since !== 1 ? 's' : '' ?> ago)</span>
                    <?php else: ?>
                        <span class="text-muted">Never recorded</span>
                    <?php endif; ?>
                </dd>

                <dt class="col-sm-6 text-muted fw-normal">Status</dt>
                <dd class="col-sm-6">
                    <?php if ($days_since === null || $days_since < 45): ?>
                        <span class="badge-active">Good</span>
                    <?php elseif ($days_since < 60): ?>
                        <span class="badge" style="background:#fef3c7;color:#92400e;font-size:.68rem;font-weight:700;padding:.25rem .6rem;border-radius:20px">Expiring Soon</span>
                    <?php else: ?>
                        <span class="badge-suspended">Expired</span>
                    <?php endif; ?>
                </dd>

                <dt class="col-sm-6 text-muted fw-normal">Policy</dt>
                <dd class="col-sm-6 text-muted">Change required every 60 days</dd>
            </dl>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
