-- ---------------------------------------------------------------------------
-- Adomin Technologies — optional personalisation SQL
-- ---------------------------------------------------------------------------
-- The app works with the stock database.sql alone. Run this file *after*
-- importing database.sql if you want the Kenya-first defaults:
--
--   mysql -h <host> -u <user> -p <db> < database/adomin-updates.sql
--
-- Everything here is idempotent: re-running it is safe, and nothing drops or
-- rewrites invoice data.
-- ---------------------------------------------------------------------------

SET NAMES utf8;

-- 1) KES was missing from the seeded currency list, so a Kenyan account could
--    not select its own shilling on an invoice.
INSERT IGNORE INTO `currencies` (`id`, `value`, `name`)
VALUES (115, 'KES', 'Kenyan Shilling');

-- 2) The English language row drives the default currency *symbol* shown in the
--    UI (Language library reads languages.currency). Point it at KES.
UPDATE `languages` SET `currency` = 'KSh', `currencyKey` = 'KES' WHERE `abbr` = 'en';

-- 3) Firm logos: on Render free tier there is no persistent disk, so anything
--    uploaded through Manage firms > Logo disappears at the next deploy. Store
--    an external URL (Cloudinary, S3, GitHub raw, your own CDN) instead and the
--    logo survives every restart. The app prefers the file on disk when present.
SET @stmt = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'firms_users'
                AND COLUMN_NAME = 'logo_url') = 0,
    'ALTER TABLE `firms_users` ADD COLUMN `logo_url` VARCHAR(500) NULL DEFAULT NULL COMMENT ''Adomin: external logo URL, survives an ephemeral filesystem''',
    'SELECT ''firms_users.logo_url already exists''');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- 4) Public "send via link": invoices.uniqid already exists and is what the
--    link is built from. This index makes the public lookup cheap and adds an
--    audit trail of who opened a shared document.
SET @stmt = IF((SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices'
                AND INDEX_NAME = 'idx_invoices_uniqid') = 0,
    'ALTER TABLE `invoices` ADD INDEX `idx_invoices_uniqid` (`uniqid`)',
    'SELECT ''invoices.uniqid index already exists''');
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS `invoice_share_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `for_invoice` int(10) UNSIGNED NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `token` varchar(50) NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `user_agent` varchar(255) NOT NULL DEFAULT '',
  `time` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_share_invoice` (`for_invoice`),
  KEY `idx_share_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 5) firms_users.logo_url is read by adomin_firm_logo_src(): when it is set the
--    invoice, PDF and top bar use that URL instead of the uploaded file, which
--    is the only logo that survives a free-tier redeploy. The upload form still
--    works; the URL simply wins. Manage companies > Edit > Logo URL.
