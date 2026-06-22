<?php
require_once dirname(__DIR__) . '/bootstrap.php';
ls_require_login();

$page_title = 'Changelog';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $version     = trim($_POST['version']          ?? '');
    $notes       = trim($_POST['notes']            ?? '');
    $dl_url      = trim($_POST['download_url']     ?? '');
    $checksum    = strtolower(trim($_POST['sha256_checksum'] ?? ''));

    if (!preg_match('/^\d+\.\d+(\.\d+)?$/', $version)) $errors[] = 'Version must be in format X.Y or X.Y.Z (e.g. 1.2.0).';
    if (strlen($notes) < 5) $errors[] = 'Please enter release notes (min. 5 characters).';
    if ($dl_url && !filter_var($dl_url, FILTER_VALIDATE_URL)) $errors[] = 'Download URL must be a valid URL.';
    if ($checksum && !preg_match('/^[a-f0-9]{64}$/', $checksum)) $errors[] = 'SHA-256 checksum must be a 64-character hex string.';

    if (empty($errors)) {
        ls_run(
            "INSERT INTO versions (version, notes, download_url, sha256_checksum, released_at) VALUES (:v, :n, :u, :c, NOW())",
            [':v' => $version, ':n' => $notes, ':u' => $dl_url ?: null, ':c' => $checksum ?: null]
        );
        flash_set('success', "Version {$version} published." . ($dl_url ? ' Update delivery is live.' : ' Add a download URL to enable auto-update.'));
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
                    <div style="font-size:.85rem;color:#374151;line-height:1.7;white-space:pre-wrap;margin-bottom:.5rem"><?= e($v['notes']) ?></div>
                    <?php if (!empty($v['download_url'])): ?>
                    <div style="font-size:.75rem">
                        <span style="color:#059669;font-weight:600">✓ Auto-update enabled</span>
                        <span class="text-muted ms-2"><?= e($v['download_url']) ?></span>
                    </div>
                    <?php else: ?>
                    <div style="font-size:.75rem;color:#9ca3af">No download URL — auto-update not available for this version</div>
                    <?php endif; ?>
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
                           placeholder="e.g. 1.5.0">
                    <div class="form-text">Format: X.Y.Z</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Release Notes</label>
                    <textarea name="notes" class="form-control" rows="5"
                              placeholder="What was added, fixed, or changed…"><?= e($_POST['notes'] ?? '') ?></textarea>
                </div>
                <hr class="my-3">
                <p class="text-muted small mb-2">Auto-update delivery (optional — leave blank to skip)</p>
                <div class="mb-3">
                    <label class="form-label">Download URL</label>
                    <input type="url" name="download_url" class="form-control"
                           value="<?= e($_POST['download_url'] ?? '') ?>"
                           placeholder="https://github.com/…/v1.5.0.zip">
                    <div class="form-text">Public ZIP of the release (e.g. GitHub Release asset).</div>
                </div>
                <div class="mb-4">
                    <label class="form-label">SHA-256 Checksum</label>
                    <input type="text" name="sha256_checksum" class="form-control font-monospace"
                           value="<?= e($_POST['sha256_checksum'] ?? '') ?>"
                           placeholder="64-character hex string">
                    <div class="form-text">Run: <code>shasum -a 256 autodex.zip</code></div>
                </div>
                <button type="submit" class="btn btn-brand w-100">Publish Version →</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
