<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
ls_require_login();

$id = (int)($_GET['id'] ?? 0);
$license = ls_row("SELECT l.*, p.name AS product_name FROM licenses l JOIN products p ON p.id = l.product_id WHERE l.id = :id", [':id' => $id]);
if (!$license) { flash_set('error', 'License not found.'); header('Location: ' . ls_url('licenses')); exit; }

$page_title = 'License: ' . $license['license_key'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'suspend') {
        ls_run("UPDATE licenses SET status='suspended' WHERE id=:id", [':id' => $id]);
        _ls_log($id, $license['activated_domain'] ?? '-', $license['purchase_email'], 'admin_suspended');
        flash_set('success', 'License suspended.');
    } elseif ($action === 'activate') {
        ls_run("UPDATE licenses SET status='active' WHERE id=:id", [':id' => $id]);
        _ls_log($id, $license['activated_domain'] ?? '-', $license['purchase_email'], 'admin_reactivated');
        flash_set('success', 'License reactivated.');
    } elseif ($action === 'deactivate_domain') {
        ls_run("UPDATE licenses SET activated_domain=NULL, extra_domains=NULL, activated_at=NULL, activations=0 WHERE id=:id", [':id' => $id]);
        _ls_log($id, $license['activated_domain'] ?? '-', $license['purchase_email'], 'domain_released');
        flash_set('success', 'Domain binding cleared. Buyer can now install on a new domain.');
    } elseif ($action === 'refund') {
        ls_run("UPDATE licenses SET status='refunded' WHERE id=:id", [':id' => $id]);
        _ls_log($id, $license['activated_domain'] ?? '-', $license['purchase_email'], 'admin_refunded');
        flash_set('success', 'License marked as refunded and revoked.');
    } elseif ($action === 'update_notes') {
        ls_run("UPDATE licenses SET notes=:n WHERE id=:id", [':n' => trim($_POST['notes'] ?? ''), ':id' => $id]);
        flash_set('success', 'Notes updated.');
    } elseif ($action === 'change_plan') {
        $new_plan = trim($_POST['new_plan'] ?? '');
        $valid_plans = ['standard', 'extended', 'developer'];
        if (!in_array($new_plan, $valid_plans, true)) {
            flash_set('error', 'Invalid plan selected.');
        } elseif ($new_plan === $license['plan']) {
            flash_set('error', 'License is already on that plan.');
        } else {
            $plan_defaults = ['standard' => 1, 'extended' => 5, 'developer' => 50];
            $new_max = $plan_defaults[$new_plan];

            // Clear extra domains when downgrading to standard (they'd exceed the new limit)
            $clear_extras = ($new_plan === 'standard' && !empty($license['extra_domains']));

            if ($clear_extras) {
                ls_run(
                    "UPDATE licenses SET plan=:plan, max_domains=:maxd, extra_domains=NULL WHERE id=:id",
                    [':plan' => $new_plan, ':maxd' => $new_max, ':id' => $id]
                );
            } else {
                ls_run(
                    "UPDATE licenses SET plan=:plan, max_domains=:maxd WHERE id=:id",
                    [':plan' => $new_plan, ':maxd' => $new_max, ':id' => $id]
                );
            }

            $old_plan = $license['plan'];
            $direction = array_search($new_plan, $valid_plans) > array_search($old_plan, $valid_plans) ? 'upgraded' : 'downgraded';
            _ls_log($id, $license['activated_domain'] ?? '-', $license['purchase_email'], "plan_{$direction}_{$old_plan}_to_{$new_plan}");
            flash_set('success', ucfirst($direction) . " plan from " . ucfirst($old_plan) . " to " . ucfirst($new_plan) . "." . ($clear_extras ? ' Extra domain bindings were cleared.' : ''));
        }
    }

    header('Location: ' . ls_url('licenses.view', ['id' => $id])); exit;
}

function _ls_log(int $license_id, string $domain, string $email, string $event): void
{
    ls_run(
        "INSERT INTO activation_logs (license_id, domain, email, event, ip_address, created_at)
         VALUES (:lid, :dom, :email, :evt, :ip, NOW())",
        [
            ':lid'   => $license_id,
            ':dom'   => $domain,
            ':email' => $email,
            ':evt'   => $event,
            ':ip'    => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ]
    );
}

// Reload after any POST
$license = ls_row("SELECT l.*, p.name AS product_name FROM licenses l JOIN products p ON p.id = l.product_id WHERE l.id = :id", [':id' => $id]);
$logs    = ls_rows("SELECT * FROM activation_logs WHERE license_id = :id ORDER BY created_at DESC LIMIT 30", [':id' => $id]);

require __DIR__ . '/../../layout/header.php';

$extra_domains = json_decode($license['extra_domains'] ?? '[]', true) ?: [];
$status_badge  = ['active' => 'badge-active', 'suspended' => 'badge-suspended', 'expired' => 'badge-expired', 'refunded' => 'badge-refunded'];
?>

<div class="ls-topbar">
    <div>
        <a href="<?= ls_url('licenses') ?>" class="text-muted small text-decoration-none">← All Licenses</a>
        <h1 class="ls-title mt-1 d-flex align-items-center gap-3">
            <span class="lic-key" style="font-size:1rem"><?= e($license['license_key']) ?></span>
            <span class="<?= $status_badge[$license['status']] ?? 'badge-expired' ?>"><?= ucfirst(e($license['status'])) ?></span>
        </h1>
    </div>
    <button onclick="navigator.clipboard.writeText('<?= e($license['license_key']) ?>');this.textContent='Copied!'"
            class="btn btn-outline-secondary btn-sm">Copy Key</button>
</div>

<div class="row g-4">
    <!-- Left: details + actions -->
    <div class="col-lg-7">

        <!-- Details card -->
        <div class="ls-card mb-4">
            <div class="ls-card-hd"><h6>License Details</h6></div>
            <div class="p-4">
                <dl class="row mb-0" style="font-size:.85rem">
                    <dt class="col-sm-4 text-muted fw-normal">Buyer</dt>
                    <dd class="col-sm-8 fw-semibold"><?= e($license['buyer_name'] ?: 'N/A') ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Email</dt>
                    <dd class="col-sm-8"><a href="mailto:<?= e($license['purchase_email']) ?>"><?= e($license['purchase_email']) ?></a></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Product</dt>
                    <dd class="col-sm-8"><?= e($license['product_name']) ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Plan</dt>
                    <dd class="col-sm-8"><?= ucfirst(e($license['plan'])) ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Primary Domain</dt>
                    <dd class="col-sm-8">
                        <?= $license['activated_domain'] ? '<strong>' . e($license['activated_domain']) . '</strong>' : '<span class="text-muted">Not activated yet</span>' ?>
                    </dd>

                    <?php if (!empty($extra_domains)): ?>
                    <dt class="col-sm-4 text-muted fw-normal">Extra Domains</dt>
                    <dd class="col-sm-8"><?= implode(', ', array_map('e', $extra_domains)) ?></dd>
                    <?php endif; ?>

                    <dt class="col-sm-4 text-muted fw-normal">Max Domains</dt>
                    <dd class="col-sm-8"><?= (int)$license['max_domains'] ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Total Activations</dt>
                    <dd class="col-sm-8"><?= (int)$license['activations'] ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Activated On</dt>
                    <dd class="col-sm-8"><?= $license['activated_at'] ? date('d M Y, H:i', strtotime($license['activated_at'])) : '—' ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Expires</dt>
                    <dd class="col-sm-8"><?= $license['expires_at'] ? date('d M Y', strtotime($license['expires_at'])) : 'Lifetime' ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Order Ref</dt>
                    <dd class="col-sm-8 text-muted"><?= e($license['order_ref'] ?: '—') ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Issued</dt>
                    <dd class="col-sm-8 text-muted"><?= date('d M Y, H:i', strtotime($license['created_at'])) ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Last Check</dt>
                    <dd class="col-sm-8 text-muted"><?= $license['last_check_at'] ? date('d M Y, H:i', strtotime($license['last_check_at'])) : 'Never' ?></dd>
                </dl>
            </div>
        </div>

        <!-- Activation log -->
        <div class="ls-card">
            <div class="ls-card-hd"><h6>Activation History</h6></div>
            <?php if (empty($logs)): ?>
                <p class="text-center text-muted py-4 small">No activation events recorded yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="ls-table">
                    <thead><tr><th>Event</th><th>Domain</th><th>IP</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark border" style="font-size:.7rem"><?= e($log['event']) ?></span></td>
                            <td class="small"><?= e($log['domain']) ?></td>
                            <td class="small text-muted"><?= e($log['ip_address'] ?? '—') ?></td>
                            <td class="small text-muted"><?= date('d M Y, H:i', strtotime($log['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: actions + notes -->
    <div class="col-lg-5">

        <!-- Actions -->
        <div class="ls-card mb-4 p-4">
            <h6 class="fw-bold mb-3">Actions</h6>
            <div class="d-grid gap-2">

                <?php if ($license['status'] === 'active'): ?>
                <form method="POST" onsubmit="return confirm('Suspend this license? The CMS will stop working for this buyer.')">
                    <?= csrf_field() ?><input type="hidden" name="action" value="suspend">
                    <button class="btn btn-outline-danger w-100">
                        <i class="ph ph-pause-circle me-1"></i> Suspend License
                    </button>
                </form>
                <?php elseif ($license['status'] === 'suspended'): ?>
                <form method="POST">
                    <?= csrf_field() ?><input type="hidden" name="action" value="activate">
                    <button class="btn btn-outline-success w-100">
                        <i class="ph ph-play-circle me-1"></i> Reactivate License
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($license['activated_domain']): ?>
                <form method="POST" onsubmit="return confirm('This will allow the buyer to install on a NEW domain. Continue?')">
                    <?= csrf_field() ?><input type="hidden" name="action" value="deactivate_domain">
                    <button class="btn btn-outline-warning w-100">
                        <i class="ph ph-link-break me-1"></i> Release Domain Binding
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($license['status'] !== 'refunded'): ?>
                <form method="POST" onsubmit="return confirm('Mark as REFUNDED? This will permanently revoke the license.')">
                    <?= csrf_field() ?><input type="hidden" name="action" value="refund">
                    <button class="btn btn-outline-secondary w-100">
                        <i class="ph ph-arrow-u-up-left me-1"></i> Mark as Refunded
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Change Plan -->
        <div class="ls-card mb-4 p-4">
            <h6 class="fw-bold mb-3">Change Plan</h6>
            <form method="POST" onsubmit="return confirm('Change plan for this license? This takes effect immediately.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_plan">
                <div class="mb-3">
                    <label class="form-label small text-muted">Current Plan</label>
                    <div class="fw-semibold"><?= ucfirst(e($license['plan'])) ?> <span class="text-muted fw-normal small">(<?= (int)$license['max_domains'] ?> domain<?= $license['max_domains'] != 1 ? 's' : '' ?>)</span></div>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-muted" for="new_plan">New Plan</label>
                    <select name="new_plan" id="new_plan" class="form-select form-select-sm">
                        <option value="standard"  <?= $license['plan'] === 'standard'  ? 'selected' : '' ?>>Standard — 1 domain</option>
                        <option value="extended"  <?= $license['plan'] === 'extended'  ? 'selected' : '' ?>>Extended — up to 5 domains</option>
                        <option value="developer" <?= $license['plan'] === 'developer' ? 'selected' : '' ?>>Developer — up to 50 domains</option>
                    </select>
                    <div class="form-text small">Max domains will be set automatically. Downgrading to Standard clears extra domain bindings.</div>
                </div>
                <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                    <i class="ph ph-arrows-down-up me-1"></i> Apply Plan Change
                </button>
            </form>
        </div>

        <!-- Notes -->
        <div class="ls-card p-4">
            <h6 class="fw-bold mb-3">Internal Notes</h6>
            <form method="POST">
                <?= csrf_field() ?><input type="hidden" name="action" value="update_notes">
                <textarea name="notes" class="form-control mb-3" rows="5"
                          placeholder="Special terms, refund notes, buyer requests…"><?= e($license['notes'] ?? '') ?></textarea>
                <button type="submit" class="btn btn-brand btn-sm w-100">Save Notes</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layout/footer.php'; ?>
