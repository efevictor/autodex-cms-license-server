<?php
/**
 * AutoDex — Dealership CMS License Server Configuration
 * Copy this file to config.php and fill in your values.
 * NEVER commit config.php to git.
 */

define('LS_DB_HOST', 'localhost');
define('LS_DB_PORT', '3306');
define('LS_DB_NAME', 'your_db_name');
define('LS_DB_USER', 'your_db_user');
define('LS_DB_PASS', 'your_db_password');

// Generate hash: php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT, ['cost'=>12]);"
define('LS_ADMIN_USER',      'admin');
define('LS_ADMIN_PASS_HASH', 'REPLACE_WITH_HASH');

// The public URL of this license server (no trailing slash)
define('LS_URL',     'https://license.evodesignco.com');
define('LS_PRODUCT', 'AutoDex');
define('LS_SESSION', 'ls_admin_sess');
