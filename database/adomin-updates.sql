-- ===========================================================================
-- Adomin Technologies - Kenya defaults, and the columns the app expects
-- ===========================================================================
-- Run this AFTER database.sql, against your own schema, with that schema
-- selected. Neither file contains a USE statement, because the database name
-- differs per installation. On the mysql command line, pass --database=dbname. In a
-- GUI console (TiDB SQL editor, phpMyAdmin, DBeaver, Adminer), pick the schema in the
-- tree or the schema selector first. Without a selected schema, every statement fails
-- with "No database selected", which looks like a broken migration but is not.
--
--   mysql -h HOST -P PORT -u USER -p --database=dbname < database.sql
--   mysql -h HOST -P PORT -u USER -p --database=dbname < database/adomin-updates.sql
--
-- Every statement here is idempotent, so a second run is harmless. There is
-- deliberately no semicolon inside a comment or a string: some GUI consoles split a
-- script on that character without parsing it, which would cut a statement in half.
-- ===========================================================================

SET NAMES utf8;

-- ---------------------------------------------------------------------------
-- 1) Kenyan Shilling. The seeded currency list has no KES row, so a Kenyan
--    account could not choose its own currency on an invoice.
--    `currencies` is (id, value, name), all NOT NULL, with no primary key, and the
--    dump uses ids 1..114 - hence the explicit 115 and the NOT EXISTS test, which
--    is what makes re-running this file safe.
-- ---------------------------------------------------------------------------
INSERT INTO `currencies` (`id`, `value`, `name`)
SELECT 115, 'KES', 'Kenyan Shilling'
WHERE NOT EXISTS (SELECT 1 FROM `currencies` WHERE `value` = 'KES');

-- ---------------------------------------------------------------------------
-- 2) The symbol and code the interface shows for English (the Language library
--    reads languages.currency and languages.currencyKey).
-- ---------------------------------------------------------------------------
UPDATE `languages` SET `currency` = 'KSh', `currencyKey` = 'KES' WHERE `abbr` = 'en';

-- ---------------------------------------------------------------------------
-- 3) firms_users.logo_url - an external logo URL.
--    Uploads land in attachments/, which is not persistent on ephemeral hosting:
--    the logo disappears at the next deploy. A URL (Cloudinary, S3, your own CDN)
--    survives every restart. A file on disk still wins when it exists, so this
--    changes nothing for installations with real storage.
--    MySQL has no ADD COLUMN IF NOT EXISTS, hence the guard. If your client refuses
--    PREPARE, run the ALTER yourself:
--      ALTER TABLE `firms_users` ADD COLUMN `logo_url` VARCHAR(500) NULL DEFAULT NULL
-- ---------------------------------------------------------------------------
SET @adomin_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'firms_users'
        AND COLUMN_NAME = 'logo_url') = 0,
    'ALTER TABLE `firms_users` ADD COLUMN `logo_url` VARCHAR(500) NULL DEFAULT NULL COMMENT ''external logo URL, for hosts without a persistent disk''',
    'SELECT ''firms_users.logo_url already exists'' AS `adomin_skip`'
);
PREPARE adomin_stmt FROM @adomin_sql;
EXECUTE adomin_stmt;
DEALLOCATE PREPARE adomin_stmt;

-- ---------------------------------------------------------------------------
-- 4) Share links. invoices.uniqid already exists - it is what a public link is
--    built from - but is unindexed, so every share hit scans the table. Add the
--    index, and the table that records who opened a link.
--      ALTER TABLE `invoices` ADD INDEX `idx_invoices_uniqid` (`uniqid`)
-- ---------------------------------------------------------------------------
SET @adomin_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices'
        AND INDEX_NAME = 'idx_invoices_uniqid') = 0,
    'ALTER TABLE `invoices` ADD INDEX `idx_invoices_uniqid` (`uniqid`)',
    'SELECT ''invoices.uniqid is already indexed'' AS `adomin_skip`'
);
PREPARE adomin_stmt FROM @adomin_sql;
EXECUTE adomin_stmt;
DEALLOCATE PREPARE adomin_stmt;

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
