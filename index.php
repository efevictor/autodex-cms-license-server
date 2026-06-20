<?php
require_once __DIR__ . '/bootstrap.php';

$page = trim($_GET['p'] ?? 'dashboard');

// Public pages
if ($page === 'login')  { require __DIR__ . '/pages/login.php';  exit; }
if ($page === 'logout') {
    session_destroy();
    header('Location: index.php?p=login');
    exit;
}

// All other pages require auth
ls_require_login();

$allowed = [
    'dashboard'        => 'pages/dashboard.php',
    'licenses'         => 'pages/licenses/list.php',
    'licenses.add'     => 'pages/licenses/add.php',
    'licenses.view'    => 'pages/licenses/view.php',
];

if (!isset($allowed[$page])) {
    header('Location: index.php?p=dashboard'); exit;
}

require __DIR__ . '/' . $allowed[$page];
