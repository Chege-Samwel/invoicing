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
--    (currencies has exactly id/value/name, all NOT NULL, no auto-increment - and
--     as shipped no PRIMARY KEY and no unique index. So INSERT IGNORE is not
--     idempotent here: IGNORE only skips a row when something conflicts with a
--     key, and with no key the second run inserts KES a second time. The explicit
--     id 115 plus this NOT EXISTS test is what makes it repeatable (the dump fills
--     ids 1..114, so 115 is free). Step 1b then adds the missing key.)
-- ---------------------------------------------------------------------------
INSERT INTO `currencies` (`id`, `value`, `name`)
SELECT 115, 'KES', 'Kenyan Shilling'
WHERE NOT EXISTS (SELECT 1 FROM `currencies` WHERE `value` = 'KES');

-- ---------------------------------------------------------------------------
-- 1b) give `currencies` the primary key it is missing. Nothing in this app writes
--     to the table (user-added currencies live in `users_currencies`, see
--     SettingsModel), the dump already declares id NOT NULL, and 114 distinct ids
--     for 114 rows means the key can be added safely. It is what makes every
--     future "add a currency" statement behave, and it stops this file from being
--     able to duplicate a row if it is ever edited back to an INSERT.
--
--     Skipped when duplicates or junk ids exist - the summary at the end shows
--     which. Manual equivalent if your client refuses PREPARE:
--       ALTER TABLE `currencies` ADD PRIMARY KEY (`id`)
-- ---------------------------------------------------------------------------
SET @adomin_sql = CASE
    WHEN (SELECT COUNT(*) FROM information_schema.TABLES
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'currencies') = 0
        THEN 'SELECT ''skip: no currencies table in this schema'' AS `adomin_step_1b`'
    WHEN (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'currencies'
             AND INDEX_NAME = 'PRIMARY') > 0
        THEN 'SELECT ''ok: currencies already has a primary key'' AS `adomin_step_1b`'
    WHEN (SELECT COUNT(*) FROM (SELECT `id` FROM `currencies` GROUP BY `id` HAVING COUNT(*) > 1) AS `dupes`) > 0
        THEN 'SELECT ''!! duplicate ids in currencies - run the dedupe at the end of this file, then re-run to add the key'' AS `adomin_step_1b`'
    WHEN (SELECT COUNT(*) FROM `currencies` WHERE `id` IS NULL OR `id` = 0) > 0
        THEN 'SELECT ''!! currencies rows with NULL/zero id - fix them, then re-run to add the key'' AS `adomin_step_1b`'
    ELSE 'ALTER TABLE `currencies` ADD PRIMARY KEY (`id`)'
END;
PREPARE adomin_stmt FROM @adomin_sql;
EXECUTE adomin_stmt;
DEALLOCATE PREPARE adomin_stmt;

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
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoice_share_views') AS `share_log_table`,
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'currencies'
        AND INDEX_NAME = 'PRIMARY')                                      AS `currencies_pk`,
    (SELECT COUNT(*) FROM (SELECT `id` FROM `currencies` GROUP BY `id` HAVING COUNT(*) > 1) AS `d`) AS `currencies_dupe_ids`;
-- Expected: 1 | KSh/KES | 1 | 1 | 1 | 0
--   kes_currency 2      -> an older copy of this file ran twice (it used INSERT
--                          IGNORE, which cannot skip anything without a key).
--                          Run step 6 below, then re-run this file.
--   currencies_pk 0      -> step 1b skipped: read its message. Usually duplicate
--                          ids, fixed by step 6.
-- Anything else, or "No database selected" on the very first query, means the
-- schema in step 2 was not the one you meant.

-- ---------------------------------------------------------------------------
-- 6) only if you need it: collapse duplicate currencies rows, keeping the row
--    with the lowest id per value. MySQL refuses a subquery on the target table
--    in a DELETE, hence the derived table. Check the count first:
--      SELECT `id`, `value`, COUNT(*) AS n FROM `currencies`
--      GROUP BY `value` HAVING n > 1
--     then run
--      DELETE `c` FROM `currencies` `c`
--      JOIN (SELECT `value`, MIN(`id`) AS keep_id FROM `currencies`
--            GROUP BY `value` HAVING COUNT(*) > 1) AS k ON k.`value` = c.`value`
--      WHERE c.`id` > k.keep_id
-- ---------------------------------------------------------------------------
SELECT 'step 6 is documentation only - run the DELETE above by hand, never this line' AS `adomin_step_6`;
