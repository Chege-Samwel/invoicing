-- ===========================================================================
-- Adomin Technologies — Kenya-first defaults + the columns the app expects
-- ===========================================================================
-- Read the "Before you run this" box first: most failures people hit with this
-- file are the schema not being selected, not anything in the SQL.
--
-- Before you run this
--   1. Import database.sql into your database FIRST (this file only adjusts the
--      app's own tables, it does not create them).
--   2. Select that database. In the mysql CLI pass it on the command line:
--        mysql -h HOST -P PORT -u USER -p --database=DBNAME < database/adomin-updates.sql
--      In a GUI (TiDB Cloud SQL editor, phpMyAdmin, DBeaver, TablePlus, Adminer)
--      pick the schema in the tree/selector, or uncomment the USE below.
--        USE your_database_name
--      If you skip this, every statement fails with "No database selected" and
--      the file looks broken when nothing is. Note there is deliberately no
--      statement terminator inside comments or strings in this file: several
--      GUI consoles split on the statement terminator without parsing strings, which
--      would cut a guard query in half. Add the terminator yourself when you uncomment
--      USE.
--   3. The first query prints which schema you are in. If it says
--      "!! no schema selected", stop and do step 2 - nothing else can work.
--
-- Safe to re-run: every statement is idempotent, nothing drops or rewrites
-- invoice data.
-- ===========================================================================

SET NAMES utf8;

-- ---------------------------------------------------------------------------
-- 0) guard / self-check
-- ---------------------------------------------------------------------------
SELECT IF(
    DATABASE() IS NULL,
    '!! no schema selected - add a USE line above (see the header) or pick the database in your client - nothing below can succeed until you do',
    CONCAT('ok - applying Adomin updates to schema `', DATABASE(), '`')
) AS `adomin_step_0_schema`;

SELECT IF(
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('currencies', 'languages', 'invoices', 'firms_users')) = 4,
    'ok - the app tables are here, database.sql has been imported',
    '!! database.sql is missing in this schema (need currencies, languages, invoices, firms_users) - import it first'
) AS `adomin_step_0_import`;

-- ---------------------------------------------------------------------------
-- 1) KES: the seeded currency list has no Kenyan Shilling row, so a Kenyan
--    account could not pick its own currency on an invoice.
--    (currencies has exactly id/value/name, all NOT NULL, no auto-increment -
--     the explicit id 115 is what makes this repeatable, and IGNORE keeps it so.)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `currencies` (`id`, `value`, `name`) VALUES (115, 'KES', 'Kenyan Shilling');

-- ---------------------------------------------------------------------------
-- 2) The English language row drives the default currency symbol shown in the UI
--    (the Language library reads languages.currency / languages.currencyKey).
-- ---------------------------------------------------------------------------
UPDATE `languages` SET `currency` = 'KSh', `currencyKey` = 'KES' WHERE `abbr` = 'en';

-- ---------------------------------------------------------------------------
-- 3) firms_users.logo_url - an external logo URL.
--    On Render free tier there is no persistent disk, so a logo uploaded through
--    Manage firms > Logo is gone at the next deploy. Store a URL (Cloudinary,
--    S3, GitHub raw, your own CDN) instead and the logo survives every restart.
--    The app prefers the file on disk when it exists, so this changes nothing for
--    installs that do have storage.
--
--    MySQL has no "ADD COLUMN IF NOT EXISTS", hence the guard. If your client
--    refuses PREPARE (some GUI consoles do), run the single ALTER yourself:
--      ALTER TABLE `firms_users` ADD COLUMN `logo_url` VARCHAR(500) NULL DEFAULT NULL
-- ---------------------------------------------------------------------------
SET @adomin_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'firms_users'
        AND COLUMN_NAME = 'logo_url') = 0,
    'ALTER TABLE `firms_users` ADD COLUMN `logo_url` VARCHAR(500) NULL DEFAULT NULL COMMENT ''Adomin: external logo URL, survives an ephemeral filesystem''',
    'SELECT ''skip: firms_users.logo_url already exists'' AS `adomin_step_3`'
);
PREPARE adomin_stmt FROM @adomin_sql;
EXECUTE adomin_stmt;
DEALLOCATE PREPARE adomin_stmt;

-- ---------------------------------------------------------------------------
-- 4) Send-via-link lookups: invoices.uniqid already exists (it is what the
--    public link is built from) but is unindexed, so every share hit scans the
--    table. This adds the index, and the audit table for "who opened it".
--
--    Manual equivalents if PREPARE is unavailable:
--      ALTER TABLE `invoices` ADD INDEX `idx_invoices_uniqid` (`uniqid`)
-- ---------------------------------------------------------------------------
SET @adomin_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices'
        AND INDEX_NAME = 'idx_invoices_uniqid') = 0,
    'ALTER TABLE `invoices` ADD INDEX `idx_invoices_uniqid` (`uniqid`)',
    'SELECT ''skip: invoices.uniqid already indexed'' AS `adomin_step_4`'
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

-- ---------------------------------------------------------------------------
-- 5) result summary - what the app will now see
-- ---------------------------------------------------------------------------
SELECT
    (SELECT COUNT(*) FROM `currencies` WHERE `value` = 'KES')          AS `kes_currency`,
    (SELECT CONCAT(`currency`, '/', `currencyKey`) FROM `languages` WHERE `abbr` = 'en') AS `en_currency_key`,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'firms_users'
        AND COLUMN_NAME = 'logo_url')                                    AS `logo_url_column`,
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoice_share_views') AS `share_log_table`;
-- Expected: 1 | KSh/KES | 1 | 1 . Anything else, or "No database selected" on
-- the very first query, means the schema in step 2 was not the one you meant.
