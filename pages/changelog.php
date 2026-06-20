<?php
require_once dirname(__DIR__) . '/bootstrap.php';
ls_require_login();

$page_title = 'Changelog';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $version = trim($_POST['version'] ?? '');
    $notes   = trim($_POST['notes']   ?? '');

    if (!preg_match('/^\d+\.\d+(\.\d+)?$/', $version)) $errors[] = 'Version must be in format X.Y or X.Y.Z (e.g. 1.2.0).';
    if (strlen($notes) < 5) $errors[] = 'Please enter release notes (min. 5 characters).';

    if (empty($errors)) {
        ls_run(
            "INSERT INTO versions (version, notes, released_at) VALUES (:v, :n, NOW())",
            [':v' => $version, ':n' => $notes]
        );
        flash_set('success', "Version {$version} added to changelog.");
        header('Location: ' . ls_url('changelog')); exit;
    }
}

$versions = ls_rows("SELECT * FROM versions ORDER BY id DESC");

require __DIR__ . '/../layout/header.php';
?>

<div class="ls-topbar">
    <h1 class="ls-title">Changelog</h1>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
    <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li style="font-size:.88rem"><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Version history -->
    <div class="col-lg-8">
        <div class="ls-card">
            <div class="ls-card-hd"><h6>Version History</h6></div>
            <?php if (empty($versions)): ?>
                <p class="text-center text-muted py-5 small">No versions recorded yet.</p>
            <?php else: ?>
            <div class="p-4">
                <?php foreach ($versions as $i => $v): ?>
                <div class="d-flex gap-3 <?= $i < count($versions) - 1 ? 'mb-4 pb-4 border-bottom' : '' ?>">
                    <div class="flex-shrink-0 text-center" style="width:80px">
                        <div class="fw-bold" style="font-size:.9rem;color:#000"><?= e($v['version']) ?></div>
                        <div class="text-muted" style="font-size:.7rem"><?= date('d M Y', strtotime($v['released_at'])) ?></div>
                        <?php if ($i === 0): ?>
                        <span class="badge-active mt-1 d-inline-block">Latest</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:.85rem;color:#374151;line-height:1.7;white-space:pre-wrap"><?= e($v['notes']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add version -->
    <div class="col-lg-4">
        <div class="ls-card p-4">
            <h6 class="fw-bold mb-3">Add New Version</h6>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Version Number</label>
                    <input type="text" name="version" class="form-control font-monospace"
                           value="<?= e($_POST['version'] ?? '') ?>"
                           placeholder="e.g. 1.2.0">
                    <div class="form-text">Format: X.Y.Z</div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Release Notes</label>
                    <textarea name="notes" class="form-control" rows="6"
                              placeholder="What was added, fixed, or changed in this version…"><?= e($_POST['notes'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-brand w-100">Add Version →</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
