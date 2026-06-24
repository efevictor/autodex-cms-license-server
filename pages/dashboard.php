<?php
require_once dirname(__DIR__) . '/bootstrap.php';
ls_require_login();

$page_title = 'Dashboard';

$stats = [
    'total'     => ls_val("SELECT COUNT(*) FROM licenses"),
    'active'    => ls_val("SELECT COUNT(*) FROM licenses WHERE status='active'"),
    'suspended' => ls_val("SELECT COUNT(*) FROM licenses WHERE status='suspended'"),
    'activated' => ls_val("SELECT COUNT(*) FROM licenses WHERE activated_domain IS NOT NULL"),
];

$revenue = ls_val("SELECT SUM(p.price) FROM licenses l JOIN products p ON p.id = l.product_id WHERE l.status != 'refunded'") ?? 0;

$recent_licenses = ls_rows("
    SELECT l.*, p.name AS product_name
    FROM licenses l
    JOIN products p ON p.id = l.product_id
    ORDER BY l.created_at DESC
    LIMIT 8
");

$recent_activations = ls_rows("
    SELECT a.*, l.license_key, l.purchase_email
    FROM activation_logs a
    JOIN licenses l ON l.id = a.license_id
    ORDER BY a.created_at DESC
    LIMIT 8
");

// Version distribution across active installs
$latest_version        = ls_val("SELECT version FROM versions ORDER BY id DESC LIMIT 1") ?? '';
$total_active_installs = (int)ls_val("SELECT COUNT(*) FROM licenses WHERE status='active' AND activated_domain IS NOT NULL");
$up_to_date_count      = $latest_version
    ? (int)ls_val("SELECT COUNT(*) FROM licenses WHERE status='active' AND activated_domain IS NOT NULL AND cms_version = :v", [':v' => $latest_version])
    : 0;
$outdated_count        = (int)ls_val("SELECT COUNT(*) FROM licenses WHERE status='active' AND activated_domain IS NOT NULL AND cms_version IS NOT NULL AND cms_version != :v", [':v' => $latest_version ?: '']);
$unknown_count         = $total_active_installs - $up_to_date_count - $outdated_count;
$version_dist          = ls_rows("
    SELECT
        COALESCE(NULLIF(cms_version,''), '(unknown)') AS version,
        COUNT(*) AS installs
    FROM licenses
    WHERE status = 'active' AND activated_domain IS NOT NULL
    GROUP BY cms_version
    ORDER BY installs DESC
");

require __DIR__ . '/../layout/header.php';
?>

<div class="ls-topbar">
    <h1 class="ls-title">Dashboard</h1>
    <a href="<?= ls_url('licenses.add') ?>" class="btn btn-brand btn-sm">
        <i class="ph ph-plus me-1"></i> Issue License
    </a>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <?php
    $stat_cards = [
        ['Total Licenses',     $stats['total'],     '#eff6ff', '#3b82f6', 'ph-key'],
        ['Active',             $stats['active'],    '#d1fae5', '#059669', 'ph-check-circle'],
        ['Activated Domains',  $stats['activated'], '#fef3c7', '#d97706', 'ph-globe'],
        ['Suspended',          $stats['suspended'], '#fee2e2', '#dc2626', 'ph-prohibit'],
    ];
    foreach ($stat_cards as [$lbl, $val, $bg, $color, $icon]):
    ?>
    <div class="col-6 col-lg-3">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon" style="background:<?= $bg ?>; color:<?= $color ?>">
                <i class="ph <?= $icon ?>"></i>
            </div>
            <div>
                <div class="stat-val"><?= number_format((int)$val) ?></div>
                <div class="stat-lbl"><?= $lbl ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Recent licenses -->
    <div class="col-lg-7">
        <div class="ls-card">
            <div class="ls-card-hd">
                <h6>Recent Licenses</h6>
                <a href="<?= ls_url('licenses') ?>" class="text-muted small">View all →</a>
            </div>
            <div class="table-responsive">
                <table class="ls-table">
                    <thead>
                        <tr><th>Key</th><th>Email</th><th>Domain</th><th>Status</th><th>Issued</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recent_licenses)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No licenses yet.</td></tr>
                    <?php else: foreach ($recent_licenses as $l): ?>
                        <tr>
                            <td>
                                <a href="<?= ls_url('licenses.view', ['id' => $l['id']]) ?>" class="lic-key text-decoration-none">
                                    <?= e($l['license_key']) ?>
                                </a>
                            </td>
                            <td class="text-muted"><?= e($l['purchase_email']) ?></td>
                            <td class="text-muted small"><?= $l['activated_domain'] ? e($l['activated_domain']) : '<span class="text-muted">—</span>' ?></td>
                            <td><span class="badge-<?= e($l['status']) ?>"><?= ucfirst(e($l['status'])) ?></span></td>
                            <td class="text-muted small"><?= date('d M Y', strtotime($l['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent activations -->
    <div class="col-lg-5">
        <div class="ls-card">
            <div class="ls-card-hd">
                <h6>Recent Activations</h6>
            </div>
            <div style="max-height:380px; overflow-y:auto">
                <?php if (empty($recent_activations)): ?>
                    <p class="text-muted text-center py-4 small">No activations yet.</p>
                <?php else: foreach ($recent_activations as $a): ?>
                <div class="d-flex align-items-start gap-2 px-3 py-2 border-bottom" style="border-color:#f0f2f5 !important">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mt-1"
                         style="width:28px;height:28px;background:#eff6ff;color:#3b82f6;font-size:.7rem">
                        <i class="ph ph-globe"></i>
                    </div>
                    <div style="min-width:0">
                        <div class="fw-semibold small text-truncate"><?= e($a['domain']) ?></div>
                        <div class="text-muted" style="font-size:.72rem"><?= e($a['purchase_email']) ?> · <?= date('d M, H:i', strtotime($a['created_at'])) ?></div>
                    </div>
                    <span class="ms-auto badge-active" style="white-space:nowrap;font-size:.65rem"><?= e($a['event']) ?></span>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Version distribution -->
<div class="row g-4 mt-0">
    <div class="col-12">
        <div class="ls-card">
            <div class="ls-card-hd">
                <h6>CMS Installation Status</h6>
                <?php if ($latest_version): ?>
                <span class="text-muted small">Latest release: <strong><?= e($latest_version) ?></strong></span>
                <?php endif; ?>
            </div>
            <div class="p-4">
                <?php if ($total_active_installs === 0): ?>
                    <p class="text-muted text-center py-3 small mb-0">No active installations yet.</p>
                <?php else: ?>

                <!-- Summary sentence -->
                <div class="mb-4" style="font-size:1.05rem">
                    <?php if ($latest_version): ?>
                        <span class="fw-bold" style="font-size:1.5rem;color:#059669"><?= $up_to_date_count ?></span>
                        <span class="text-muted"> out of </span>
                        <span class="fw-bold" style="font-size:1.5rem"><?= $total_active_installs ?></span>
                        <span class="text-muted"> active installations are up to date.</span>
                    <?php else: ?>
                        <span class="fw-bold" style="font-size:1.5rem"><?= $total_active_installs ?></span>
                        <span class="text-muted"> active installation<?= $total_active_installs !== 1 ? 's' : '' ?>.</span>
                    <?php endif; ?>
                </div>

                <!-- Progress bar -->
                <?php if ($latest_version && $total_active_installs > 0):
                    $up_pct  = round($up_to_date_count  / $total_active_installs * 100);
                    $out_pct = round($outdated_count     / $total_active_installs * 100);
                    $unk_pct = 100 - $up_pct - $out_pct;
                ?>
                <div style="height:10px;border-radius:6px;overflow:hidden;display:flex;margin-bottom:1rem">
                    <div style="width:<?= $up_pct ?>%;background:#059669" title="Up to date"></div>
                    <div style="width:<?= $out_pct ?>%;background:#f59e0b" title="Outdated"></div>
                    <div style="width:<?= $unk_pct ?>%;background:#e5e7eb" title="Unknown"></div>
                </div>
                <?php endif; ?>

                <!-- Breakdown -->
                <div class="d-flex gap-4 flex-wrap" style="font-size:.85rem">
                    <div>
                        <span class="badge-active me-1"><?= $up_to_date_count ?></span>
                        <span class="text-muted">Up to date</span>
                    </div>
                    <?php if ($outdated_count > 0): ?>
                    <div>
                        <span class="badge-suspended me-1"><?= $outdated_count ?></span>
                        <span class="text-muted">Outdated</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($unknown_count > 0): ?>
                    <div>
                        <span class="badge bg-light text-muted border me-1"><?= $unknown_count ?></span>
                        <span class="text-muted">Not yet reported</span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($version_dist)): ?>
                <!-- Per-version breakdown -->
                <table class="ls-table mt-4">
                    <thead>
                        <tr><th>Installed Version</th><th>Installs</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($version_dist as $row):
                        $ver      = $row['version'];
                        $count    = (int)$row['installs'];
                        $is_null  = $ver === '(unknown)';
                        $is_latest = $latest_version && $ver === $latest_version;
                    ?>
                    <tr>
                        <td class="font-monospace"><?= e($ver) ?></td>
                        <td><?= $count ?></td>
                        <td>
                            <?php if ($is_null): ?>
                                <span class="text-muted small">Not yet reported</span>
                            <?php elseif ($is_latest): ?>
                                <span class="badge-active">Up to date</span>
                            <?php else: ?>
                                <span class="badge-suspended">Outdated</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
