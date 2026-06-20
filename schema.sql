-- ============================================================
-- AutoDesk CMS — License Server Database Schema
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
INSERT INTO `products` (`name`, `slug`, `price`) VALUES ('AutoDesk CMS', 'autodesk-cms', 99.00);

-- ── How to add a license after a sale ────────────────────
-- Run this whenever someone buys:
--
-- INSERT INTO licenses (product_id, license_key, purchase_email, buyer_name, plan, order_ref)
-- VALUES (1, 'ADSK-XXXX-YYYY-ZZZZ', 'buyer@email.com', 'John Doe', 'standard', 'GUM-12345');
--
-- Generate the key with: php -r "echo 'ADSK-' . strtoupper(bin2hex(random_bytes(3))) . '-' . strtoupper(bin2hex(random_bytes(3))) . '-' . strtoupper(bin2hex(random_bytes(3)));"
