<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
ls_require_login();

$page_title = 'All Licenses';

// Filters
$search  = trim($_GET['q']      ?? '');
$status  = trim($_GET['status'] ?? '');
$plan    = trim($_GET['plan']   ?? '');

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(l.license_key LIKE :q OR l.purchase_email LIKE :q OR l.buyer_name LIKE :q OR l.activated_domain LIKE :q)';
    $params[':q'] = "%{$search}%";
}
if ($status && in_array($status, ['active','suspended','expired','refunded'], true)) {
    $where[]  = 'l.status = :status';
    $params[':status'] = $status;
}
if ($plan && in_array($plan, ['standard','extended','developer'], true)) {
    $where[]  = 'l.plan = :plan';
    $params[':plan'] = $plan;
}

$where_sql = implode(' AND ', $where);
$total     = (int)ls_val("SELECT COUNT(*) FROM licenses l WHERE {$where_sql}", $params);

$per_page  = 20;
$pg        = max(1, (int)($_GET['pg'] ?? 1));
$offset    = ($pg - 1) * $per_page;
$total_pgs = max(1, (int)ceil($total / $per_page));

$licenses = ls_rows(
    "SELECT l.*, p.name AS product_name
     FROM licenses l
     JOIN products p ON p.id = l.product_id
     WHERE {$where_sql}
     ORDER BY l.created_at DESC
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);

require __DIR__ . '/../../layout/header.php';
?>

<div class="ls-topbar">
    <h1 class="ls-title">All Licenses <span class="text-muted fw-normal" style="font-size:1rem">(<?= number_format($total) ?>)</span></h1>
    <a href="<?= ls_url('licenses.add') ?>" class="btn btn-brand btn-sm">
        <i class="ph ph-plus me-1"></i> Issue License
    </a>
</div>

<!-- Filters -->
<form method="get" action="<?= ls_url('licenses') ?>" class="ls-card p-3 mb-4 d-flex flex-wrap gap-2 align-items-end">
    <div>
        <label class="form-label mb-1">Search</label>
        <input type="text" name="q" class="form-control form-control-sm" style="width:220px"
               placeholder="Email, key, or domain…" value="<?= e($search) ?>">
    </div>
    <div>
        <label class="form-label mb-1">Status</label>
        <select name="status" class="form-select form-select-sm" style="width:140px">
            <option value="">All Statuses</option>
            <?php foreach (['active','suspended','expired','refunded'] as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label mb-1">Plan</label>
        <select name="plan" class="form-select form-select-sm" style="width:140px">
            <option value="">All Plans</option>
            <?php foreach (['standard','extended','developer'] as $pl): ?>
            <option value="<?= $pl ?>" <?= $plan === $pl ? 'selected' : '' ?>><?= ucfirst($pl) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-brand btn-sm">Filter</button>
    <?php if ($search || $status || $plan): ?>
    <a href="<?= ls_url('licenses') ?>" class="btn btn-outline-secondary btn-sm">Clear</a>
    <?php endif; ?>
</form>

<!-- Table -->
<div class="ls-card">
    <div class="table-responsive">
        <table class="ls-table">
            <thead>
                <tr>
                    <th>License Key</th>
                    <th>Buyer</th>
                    <th>Plan</th>
                    <th>Domain</th>
                    <th>Status</th>
                    <th>Expires</th>
                    <th>Issued</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($licenses)): ?>
                <tr><td colspan="8" class="text-center text-muted py-5">No licenses found.</td></tr>
            <?php else: foreach ($licenses as $l): ?>
                <tr>
                    <td><span class="lic-key"><?= e($l['license_key']) ?></span></td>
                    <td>
                        <div class="fw-semibold small"><?= e($l['buyer_name'] ?: '—') ?></div>
                        <div class="text-muted" style="font-size:.74rem"><?= e($l['purchase_email']) ?></div>
                    </td>
                    <td><span class="badge bg-light text-dark border" style="font-size:.7rem"><?= ucfirst(e($l['plan'])) ?></span></td>
                    <td class="small text-muted"><?= $l['activated_domain'] ? e($l['activated_domain']) : '—' ?></td>
                    <td><span class="badge-<?= e($l['status']) ?>"><?= ucfirst(e($l['status'])) ?></span></td>
                    <?php $exp_ts = $l['expires_at'] ? strtotime($l['expires_at']) : false; ?>
                    <td class="small text-muted"><?= ($exp_ts && $exp_ts > 0) ? date('d M Y', $exp_ts) : 'Lifetime' ?></td>
                    <td class="small text-muted"><?= date('d M Y', strtotime($l['created_at'])) ?></td>
                    <td>
                        <a href="<?= ls_url('licenses.view', ['id' => $l['id']]) ?>"
                           class="btn btn-outline-secondary btn-sm">View</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pgs > 1): ?>
    <div class="px-3 py-3 border-top d-flex align-items-center justify-content-between">
        <div class="text-muted small">Page <?= $pg ?> of <?= $total_pgs ?></div>
        <div class="d-flex gap-1">
            <?php for ($i = max(1, $pg - 2); $i <= min($total_pgs, $pg + 2); $i++): ?>
            <a href="<?= ls_url('licenses', ['pg' => $i, 'q' => $search, 'status' => $status, 'plan' => $plan]) ?>"
               class="btn btn-sm <?= $i === $pg ? 'btn-brand' : 'btn-outline-secondary' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../layout/footer.php'; ?>
