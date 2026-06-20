<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title ?? 'Dashboard') ?> — License Manager</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <style>
        :root {
            --brand:   #E07B22;
            --dark:    #1a1e3a;
            --sidebar: 230px;
        }
        body         { background: #f4f5f7; font-family: 'Segoe UI', system-ui, sans-serif; font-size: .9rem; }

        /* Sidebar */
        .ls-sidebar  { width: var(--sidebar); background: var(--dark); min-height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; z-index: 100; }
        .ls-logo     { padding: 1.4rem 1.2rem 1rem; border-bottom: 1px solid rgba(255,255,255,.08); }
        .ls-logo-txt { font-size: 1.1rem; font-weight: 800; color: #fff; letter-spacing: -.3px; }
        .ls-logo-txt span { color: var(--brand); }
        .ls-logo-sub { font-size: .65rem; color: rgba(255,255,255,.4); margin-top: 2px; text-transform: uppercase; letter-spacing: .5px; }
        .ls-nav      { padding: .75rem 0; flex: 1; }
        .ls-nav a    { display: flex; align-items: center; gap: .6rem; padding: .6rem 1.2rem; color: rgba(255,255,255,.65); font-size: .82rem; font-weight: 500; text-decoration: none; border-left: 3px solid transparent; transition: all .15s; }
        .ls-nav a:hover, .ls-nav a.active { color: #fff; background: rgba(255,255,255,.07); border-left-color: var(--brand); }
        .ls-nav a i  { font-size: 1rem; width: 18px; }
        .ls-nav .nav-section { padding: 1rem 1.2rem .3rem; font-size: .63rem; font-weight: 700; color: rgba(255,255,255,.28); text-transform: uppercase; letter-spacing: .7px; }
        .ls-user     { padding: 1rem 1.2rem; border-top: 1px solid rgba(255,255,255,.08); display: flex; align-items: center; gap: .6rem; }
        .ls-user-av  { width: 30px; height: 30px; border-radius: 50%; background: var(--brand); color: #fff; font-size: .75rem; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .ls-user-name{ font-size: .78rem; color: #fff; font-weight: 600; }
        .ls-user-role{ font-size: .65rem; color: rgba(255,255,255,.4); }
        .ls-logout   { margin-left: auto; color: rgba(255,255,255,.4); font-size: .9rem; text-decoration: none; }
        .ls-logout:hover { color: #ff6b6b; }

        /* Main area */
        .ls-main     { margin-left: var(--sidebar); padding: 2rem; min-height: 100vh; }
        .ls-topbar   { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; }
        .ls-title    { font-size: 1.3rem; font-weight: 700; color: #1a1e3a; margin: 0; }

        /* Cards */
        .ls-card     { background: #fff; border-radius: 12px; border: 1px solid #e8eaf0; box-shadow: 0 1px 4px rgba(0,0,0,.05); }
        .ls-card-hd  { padding: 1rem 1.25rem; border-bottom: 1px solid #f0f2f5; display: flex; align-items: center; justify-content: space-between; }
        .ls-card-hd h6 { margin: 0; font-weight: 700; font-size: .88rem; }

        /* Stat cards */
        .stat-card   { background: #fff; border-radius: 12px; border: 1px solid #e8eaf0; padding: 1.25rem 1.4rem; }
        .stat-val    { font-size: 2rem; font-weight: 800; color: #1a1e3a; line-height: 1; }
        .stat-lbl    { font-size: .75rem; color: #6c757d; font-weight: 500; margin-top: .3rem; }
        .stat-icon   { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; }

        /* Tables */
        .ls-table    { width: 100%; border-collapse: collapse; font-size: .83rem; }
        .ls-table th { padding: .65rem 1rem; background: #f8f9fb; color: #6c757d; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; border-bottom: 1px solid #e8eaf0; white-space: nowrap; }
        .ls-table td { padding: .75rem 1rem; border-bottom: 1px solid #f0f2f5; vertical-align: middle; }
        .ls-table tr:last-child td { border-bottom: none; }
        .ls-table tr:hover td { background: #fafbfd; }

        /* Badges */
        .badge-active     { background: #d1fae5; color: #065f46; font-size: .68rem; font-weight: 700; padding: .25rem .6rem; border-radius: 20px; }
        .badge-suspended  { background: #fee2e2; color: #991b1b; font-size: .68rem; font-weight: 700; padding: .25rem .6rem; border-radius: 20px; }
        .badge-expired    { background: #f3f4f6; color: #6b7280; font-size: .68rem; font-weight: 700; padding: .25rem .6rem; border-radius: 20px; }
        .badge-refunded   { background: #fef3c7; color: #92400e; font-size: .68rem; font-weight: 700; padding: .25rem .6rem; border-radius: 20px; }

        /* Forms */
        .form-label  { font-weight: 600; font-size: .82rem; color: #374151; }
        .form-control, .form-select { font-size: .88rem; border-radius: 8px; border-color: #d1d5db; }
        .form-control:focus, .form-select:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(224,123,34,.15); }
        .btn-brand   { background: var(--brand); border-color: var(--brand); color: #fff; font-weight: 600; border-radius: 8px; }
        .btn-brand:hover { background: #c56b1a; border-color: #c56b1a; color: #fff; }

        /* Key display */
        .lic-key     { font-family: monospace; font-size: .88rem; background: #f8f9fb; border: 1px solid #e8eaf0; border-radius: 6px; padding: .3rem .65rem; letter-spacing: .05em; }
    </style>
</head>
<body>

<?php $cur = $_GET['p'] ?? 'dashboard'; ?>

<!-- Sidebar -->
<aside class="ls-sidebar">
    <div class="ls-logo">
        <div class="ls-logo-txt">Auto<span>Desk</span></div>
        <div class="ls-logo-sub">License Manager</div>
    </div>

    <nav class="ls-nav">
        <div class="nav-section">Overview</div>
        <a href="<?= ls_url('dashboard') ?>" class="<?= $cur === 'dashboard' ? 'active' : '' ?>">
            <i class="ph ph-squares-four"></i> Dashboard
        </a>

        <div class="nav-section">Licenses</div>
        <a href="<?= ls_url('licenses') ?>" class="<?= str_starts_with($cur, 'licenses') ? 'active' : '' ?>">
            <i class="ph ph-key"></i> All Licenses
        </a>
        <a href="<?= ls_url('licenses.add') ?>" class="<?= $cur === 'licenses.add' ? 'active' : '' ?>">
            <i class="ph ph-plus-circle"></i> Issue New License
        </a>
    </nav>

    <div class="ls-user">
        <div class="ls-user-av">A</div>
        <div>
            <div class="ls-user-name"><?= e(LS_ADMIN_USER) ?></div>
            <div class="ls-user-role">Super Admin</div>
        </div>
        <a href="<?= ls_url('logout') ?>" class="ls-logout" title="Sign Out"><i class="ph ph-sign-out"></i></a>
    </div>
</aside>

<!-- Main -->
<main class="ls-main">

<?php if ($f = flash_get()): ?>
    <div class="alert alert-<?= $f['type'] === 'success' ? 'success' : ($f['type'] === 'error' ? 'danger' : 'warning') ?> alert-dismissible mb-3" role="alert">
        <?= e($f['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
