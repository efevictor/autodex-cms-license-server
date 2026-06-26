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
    $term        = trim($_POST['term'] ?? 'lifetime');
    $expires_raw = trim($_POST['expires_at'] ?? '');
    $custom_key  = trim($_POST['custom_key'] ?? '');
    $domain_raw  = strtolower(trim($_POST['registered_domain'] ?? ''));
    $domain      = $domain_raw ? preg_replace('/^www\./', '', $domain_raw) : null;

    // Resolve expiry date from term selection
    $expires = null;
    if ($term === 'annual') {
        $expires = date('Y-m-d 23:59:59', strtotime('+1 year'));
    } elseif ($term === 'biannual') {
        $expires = date('Y-m-d 23:59:59', strtotime('+2 years'));
    } elseif ($term === 'custom') {
        if ($expires_raw === '') {
            $errors[] = 'Please select a custom expiry date.';
        } else {
            $ts = strtotime($expires_raw);
            if (!$ts || $ts <= time()) {
                $errors[] = 'Custom expiry date must be a valid date in the future.';
            } else {
                $expires = date('Y-m-d 23:59:59', $ts);
            }
        }
    }
    // lifetime → $expires stays null

    if (!$product_id) $errors[] = 'Select a product.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (!in_array($plan, ['standard','extended','developer'], true)) $errors[] = 'Invalid plan.';

    if (empty($errors)) {
        // Get product prefix for key generation (falls back to 'ADSK' for legacy products)
        try {
            $product_prefix = ls_val(
                "SELECT COALESCE(NULLIF(key_prefix,''), 'ADSK') FROM products WHERE id = :pid",
                [':pid' => $product_id]
            ) ?? 'ADSK';
        } catch (\Throwable $e) {
            $product_prefix = 'ADSK';
        }

        $key = $custom_key ?: generate_license_key($product_prefix);

        // Ensure key is unique
        while (ls_val("SELECT id FROM licenses WHERE license_key = :k", [':k' => $key])) {
            $key = generate_license_key($product_prefix);
        }

        try {
            ls_run(
                "INSERT INTO licenses (product_id, license_key, purchase_email, buyer_name, plan, max_domains, activated_domain, expires_at, order_ref, notes)
                 VALUES (:pid, :key, :email, :name, :plan, :maxd, :dom, :exp, :ref, :notes)",
                [
                    ':pid'   => $product_id,
                    ':key'   => $key,
                    ':email' => $email,
                    ':name'  => $name ?: null,
                    ':plan'  => $plan,
                    ':maxd'  => $max_dom,
                    ':dom'   => $domain,
                    ':exp'   => $expires,
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
                        <label class="form-label">License Term</label>
                        <select name="term" id="license_term" class="form-select" onchange="toggleCustomDate(this.value)">
                            <option value="lifetime"  <?= ($_POST['term'] ?? 'lifetime') === 'lifetime'  ? 'selected' : '' ?>>Lifetime (no expiry)</option>
                            <option value="annual"    <?= ($_POST['term'] ?? '') === 'annual'    ? 'selected' : '' ?>>Annual (1 year)</option>
                            <option value="biannual"  <?= ($_POST['term'] ?? '') === 'biannual'  ? 'selected' : '' ?>>Biannual (2 years)</option>
                            <option value="custom"    <?= ($_POST['term'] ?? '') === 'custom'    ? 'selected' : '' ?>>Custom date…</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="custom_date_wrap" style="display:<?= ($_POST['term'] ?? '') === 'custom' ? 'block' : 'none' ?>">
                        <label class="form-label">Custom Expiry Date</label>
                        <input type="date" name="expires_at" id="expires_at" class="form-control"
                               value="<?= e($_POST['expires_at'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Registered Domain <span class="text-muted fw-normal">(optional — locks license to this domain immediately)</span></label>
                        <input type="text" name="registered_domain" class="form-control"
                               value="<?= e($_POST['registered_domain'] ?? '') ?>"
                               placeholder="e.g. dealership.com (no www, no https)">
                        <div class="form-text">If provided, only this domain can ever activate the license.</div>
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
            <script>
            function toggleCustomDate(val) {
                document.getElementById('custom_date_wrap').style.display = val === 'custom' ? 'block' : 'none';
                document.getElementById('expires_at').required = val === 'custom';
            }
            </script>
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
