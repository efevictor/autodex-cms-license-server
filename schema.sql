-- ============================================================
-- AutoDex — Dealership CMS License Server Database Schema
-- Host this on your own server, separate from the CMS itself
-- ============================================================

CREATE TABLE `products` (
    `id`         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100)      NOT NULL,
    `slug`       VARCHAR(100)      NOT NULL,  -- e.g. 'autodesk-cms'
    `price`      DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_product_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `licenses` (
    `id`               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `product_id`       SMALLINT UNSIGNED NOT NULL,
    `license_key`      VARCHAR(64)       NOT NULL,   -- e.g. ADSK-XXXX-XXXX-XXXX
    `purchase_email`   VARCHAR(200)      NOT NULL,
    `buyer_name`       VARCHAR(150)          NULL,
    `plan`             ENUM('standard','extended','developer') NOT NULL DEFAULT 'standard',
    `status`           ENUM('active','suspended','refunded','expired') NOT NULL DEFAULT 'active',
    `max_domains`      TINYINT UNSIGNED  NOT NULL DEFAULT 1,
    `activated_domain` VARCHAR(253)          NULL,   -- primary bound domain
    `extra_domains`    JSON                  NULL,   -- additional domains (multi-site)
    `activations`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `expires_at`       TIMESTAMP             NULL,   -- NULL = lifetime license
    `activated_at`     TIMESTAMP             NULL,
    `last_check_at`    TIMESTAMP             NULL,
    `cms_version`      VARCHAR(20)           NULL,   -- installed CMS version reported on last check
    `order_ref`        VARCHAR(100)          NULL,   -- Gumroad/Stripe order ID
    `notes`            TEXT                  NULL,
    `created_at`       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_license_key` (`license_key`),
    KEY `idx_lic_email`   (`purchase_email`),
    KEY `idx_lic_product` (`product_id`),
    KEY `idx_lic_status`  (`status`),
    CONSTRAINT `fk_lic_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `activation_logs` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `license_id` INT UNSIGNED  NOT NULL,
    `domain`     VARCHAR(253)  NOT NULL,
    `email`      VARCHAR(200)  NOT NULL,
    `event`      VARCHAR(50)   NOT NULL,  -- activated, additional_domain, deactivated, check
    `ip_address` VARCHAR(45)       NULL,
    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_actlog_license` (`license_id`),
    KEY `idx_actlog_domain`  (`domain`),
    CONSTRAINT `fk_actlog_license` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ── Seed: your product ───────────────────────────────────
INSERT INTO `products` (`name`, `slug`, `price`) VALUES ('AutoDex', 'autodex', 99.00);

-- ── Admin Settings ───────────────────────────────────────
CREATE TABLE `settings` (
    `key`        VARCHAR(100) NOT NULL,
    `value`      TEXT             NULL,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`key`, `value`) VALUES
('admin_username',       'admin'),
('admin_pass_hash',      '$2y$12$HWJqTOpDVz1i7PbqFr0Zue9BXudtx6bpLTqN19Cn4HmNbuIg/wKQK'),
('last_password_change', NOW());

-- ── Version History ───────────────────────────────────────
-- Each product has its own version rows. When you release an update,
-- INSERT a row here pointing to the GitHub release ZIP.
CREATE TABLE `versions` (
    `id`              SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`      SMALLINT UNSIGNED NOT NULL,
    `version`         VARCHAR(20)       NOT NULL,
    `notes`           TEXT              NOT NULL,
    `download_url`    VARCHAR(500)          NULL,  -- GitHub API zipball URL for this release
    `sha256_checksum` VARCHAR(64)           NULL,  -- SHA256 hex of the downloaded ZIP
    `released_at`     TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ver_product` (`product_id`),
    CONSTRAINT `fk_ver_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: license server's own changelog (product_id = 1 = AutoDex; adjust if AutoDex has a different id)
INSERT INTO `versions` (`product_id`, `version`, `notes`) VALUES
(1, '1.0.0', 'Initial release: license management, activation logs, domain binding.'),
(1, '1.1.0', 'Plan upgrade/downgrade from license view. Admin settings with username/password change and 60-day expiry enforcement. Version history changelog. Clean URLs.');

-- ── How to add a license after a sale ────────────────────
-- Run this whenever someone buys:
--
-- INSERT INTO licenses (product_id, license_key, purchase_email, buyer_name, plan, order_ref)
-- VALUES (1, 'ADSK-XXXX-YYYY-ZZZZ', 'buyer@email.com', 'John Doe', 'standard', 'GUM-12345');
--
-- Generate key: php -r "echo 'RDSK-'.strtoupper(bin2hex(random_bytes(3))).'-'.strtoupper(bin2hex(random_bytes(3))).'-'.strtoupper(bin2hex(random_bytes(3)));"

-- ── How to publish a new ResolveDesk release ─────────────
-- 1. Create the GitHub release and note the tag (e.g. v1.1.0)
-- 2. Get the zipball URL: https://api.github.com/repos/efevictor/resolvedesk/zipball/v1.1.0
-- 3. Download the ZIP, compute: sha256sum resolvedesk.zip
-- 4. Insert:
--
-- INSERT INTO versions (product_id, version, notes, download_url, sha256_checksum)
-- VALUES (
--   (SELECT id FROM products WHERE slug='resolvedesk'),
--   '1.1.0',
--   'Bug fixes and improvements.\n- Fixed X\n- Improved Y',
--   'https://api.github.com/repos/efevictor/resolvedesk/zipball/v1.1.0',
--   'abc123...sha256hex'
-- );

-- ── Migration for EXISTING live servers ───────────────────
-- Run these once in phpMyAdmin if you already have the database set up:

-- Step 1: Add product_id to versions (make existing rows belong to AutoDex = product 1)
-- ALTER TABLE `versions` ADD COLUMN `product_id` SMALLINT UNSIGNED NULL AFTER `id`;
-- UPDATE `versions` SET `product_id` = 1 WHERE `product_id` IS NULL;
-- ALTER TABLE `versions` MODIFY COLUMN `product_id` SMALLINT UNSIGNED NOT NULL;
-- ALTER TABLE `versions` ADD CONSTRAINT `fk_ver_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
-- ALTER TABLE `versions` ADD KEY `idx_ver_product` (`product_id`);

-- Step 2: Add ResolveDesk product (skip if already done)
-- INSERT IGNORE INTO `products` (`name`, `slug`, `price`) VALUES ('ResolveDesk', 'resolvedesk', 149.00);

-- Step 3: Add key_prefix to products (skip if column already exists)
-- ALTER TABLE `products` ADD COLUMN `key_prefix` VARCHAR(8) NOT NULL DEFAULT 'ADSK' AFTER `slug`;
-- UPDATE `products` SET `key_prefix` = 'ADSK' WHERE `slug` = 'autodex';
-- UPDATE `products` SET `key_prefix` = 'RDSK' WHERE `slug` = 'resolvedesk';
