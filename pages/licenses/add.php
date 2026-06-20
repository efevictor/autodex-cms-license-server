<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
ls_require_login();

$page_title = 'Issue License';
$products   = ls_rows("SELECT * FROM products ORDER BY name");
$errors     = [];
$generated_key = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $product_id = (int)($_POST['product_id'] ?? 0);
    $email      = strtolower(trim($_POST['purchase_email'] ?? ''));
    $name       = trim($_POST['buyer_name'] ?? '');
    $plan       = trim($_POST['plan']       ?? 'standard');
    $max_dom    = max(1, (int)($_POST['max_domains'] ?? 1));
    $order_ref  = trim($_POST['order_ref'] ?? '');
    $notes      = trim($_POST['notes']     ?? '');
    $expires    = trim($_POST['expires_at'] ?? '');
    $custom_key = trim($_POST['custom_key'] ?? '');

    if (!$product_id) $errors[] = 'Select a product.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (!in_array($plan, ['standard','extended','developer'], true)) $errors[] = 'Invalid plan.';

    if (empty($errors)) {
        $key = $custom_key ?: generate_license_key();

        // Ensure key is unique
        while (ls_val("SELECT id FROM licenses WHERE license_key = :k", [':k' => $key])) {
            $key = generate_license_key();
        }

        try {
            ls_run(
                "INSERT INTO licenses (product_id, license_key, purchase_email, buyer_name, plan, max_domains, expires_at, order_ref, notes)
                 VALUES (:pid, :key, :email, :name, :plan, :maxd, :exp, :ref, :notes)",
                [
                    ':pid'   => $product_id,
                    ':key'   => $key,
                    ':email' => $email,
                    ':name'  => $name ?: null,
                    ':plan'  => $plan,
                    ':maxd'  => $max_dom,
                    ':exp'   => $expires ?: null,
                    ':ref'   => $order_ref ?: null,
                    ':notes' => $notes ?: null,
                ]
            );
            $generated_key = $key;
            flash_set('success', "License {$key} issued to {$email}.");
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . e($e->getMessage());
        }
    }
}

require __DIR__ . '/../../layout/header.php';
?>

<div class="ls-topbar">
    <div>
        <a href="<?= ls_url('licenses') ?>" class="text-muted small text-decoration-none">← All Licenses</a>
        <h1 class="ls-title mt-1">Issue New License</h1>
    </div>
</div>

<?php if ($generated_key): ?>
<div class="alert alert-success d-flex gap-3 align-items-start mb-4" style="border-radius:12px">
    <span style="font-size:1.5rem">🎉</span>
    <div>
        <strong>License issued successfully!</strong><br>
        <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
            <span class="lic-key" id="genKey"><?= e($generated_key) ?></span>
            <button class="btn btn-sm btn-outline-success" onclick="navigator.clipboard.writeText('<?= e($generated_key) ?>');this.textContent='Copied!'">Copy</button>
        </div>
        <div class="small text-muted mt-2">Send this key to the buyer along with their purchase confirmation email.</div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
    <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li style="font-size:.88rem"><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="ls-card p-4">
            <form method="POST">
                <?= csrf_field() ?>

                <h6 class="fw-bold mb-3" style="color:#6c757d;font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Buyer Details</h6>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <label class="form-label">Purchase Email <span class="text-danger">*</span></label>
                        <input type="email" name="purchase_email" class="form-control"
                               value="<?= e($_POST['purchase_email'] ?? '') ?>"
                               placeholder="buyer@example.com" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Buyer Name</label>
                        <input type="text" name="buyer_name" class="form-control"
                               value="<?= e($_POST['buyer_name'] ?? '') ?>"
                               placeholder="Full name (optional)">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Order Reference</label>
                        <input type="text" name="order_ref" class="form-control"
                               value="<?= e($_POST['order_ref'] ?? '') ?>"
                               placeholder="Gumroad/Stripe order ID">
                    </div>
                </div>

                <h6 class="fw-bold mb-3" style="color:#6c757d;font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">License Settings</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select product…</option>
                            <?php foreach ($products as $pr): ?>
                            <option value="<?= $pr['id'] ?>" <?= (int)($_POST['product_id'] ?? 0) === (int)$pr['id'] ? 'selected' : '' ?>>
                                <?= e($pr['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Plan</label>
                        <select name="plan" class="form-select">
                            <option value="standard"  <?= ($_POST['plan'] ?? 'standard') === 'standard'  ? 'selected' : '' ?>>Standard (1 domain)</option>
                            <option value="extended"  <?= ($_POST['plan'] ?? '') === 'extended'  ? 'selected' : '' ?>>Extended (multi-site)</option>
                            <option value="developer" <?= ($_POST['plan'] ?? '') === 'developer' ? 'selected' : '' ?>>Developer (unlimited)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Max Domains</label>
                        <input type="number" name="max_domains" class="form-control" min="1" max="50"
                               value="<?= e($_POST['max_domains'] ?? '1') ?>">
                        <div class="form-text">1 for Standard, more for Extended/Developer.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Expires On</label>
                        <input type="date" name="expires_at" class="form-control"
                               value="<?= e($_POST['expires_at'] ?? '') ?>">
                        <div class="form-text">Leave blank for a lifetime license.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Custom Key <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" name="custom_key" class="form-control font-monospace"
                               value="<?= e($_POST['custom_key'] ?? '') ?>"
                               placeholder="Auto-generated if left blank (e.g. ADSK-XXXXXX-XXXXXX-XXXXXX)">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Internal Notes</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Refund policy, special terms, etc."><?= e($_POST['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-brand w-100">Issue License →</button>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="ls-card p-4">
            <h6 class="fw-bold mb-3">Plan Guide</h6>
            <?php
            $plans = [
                ['Standard',  '1 domain', 'One buyer, one live site.', '#d1fae5', '#065f46'],
                ['Extended',  'Up to 5 domains', 'Agency buying for multiple client sites.', '#eff6ff', '#1e40af'],
                ['Developer', 'Unlimited', 'Freelancer or agency power user.', '#f5f3ff', '#5b21b6'],
            ];
            foreach ($plans as [$name, $scope, $desc, $bg, $color]):
            ?>
            <div class="p-3 rounded-3 mb-2" style="background:<?= $bg ?>">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <strong style="color:<?= $color ?>;font-size:.85rem"><?= $name ?></strong>
                    <span style="font-size:.72rem;color:<?= $color ?>;font-weight:600"><?= $scope ?></span>
                </div>
                <div style="font-size:.75rem;color:#4b5563"><?= $desc ?></div>
            </div>
            <?php endforeach; ?>

            <hr>
            <h6 class="fw-bold mb-2 mt-3">Key Format</h6>
            <div class="lic-key mb-2">ADSK-XXXXXX-XXXXXX-XXXXXX</div>
            <div class="text-muted small">Auto-generated keys are cryptographically random and unique. Use a custom key only if required by your sales platform.</div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layout/footer.php'; ?>
