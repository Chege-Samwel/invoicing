-- ===========================================================================
-- Adomin Technologies - Kenya defaults, and the columns this fork expects
-- ===========================================================================
-- Run this AFTER database.sql, against your own schema, with that schema
-- selected. Neither file contains a USE statement, because the database name
-- differs per installation. With the mysql client, pass --database=dbname (and
-- --default-character-set=utf8 when piping a file). In a GUI console (TiDB SQL
-- editor, phpMyAdmin, Adminer, DBeaver), pick the schema in the tree or the
-- dropdown first: with nothing selected, every statement fails with
-- "No database selected", which reads like a broken migration but is not.
--
--   mysql -h HOST -P PORT -u USER -p --database=dbname < database.sql
--   mysql -h HOST -P PORT -u USER -p --database=dbname < database/adomin-updates.sql
--
-- Five plain statements, deliberately: no PREPARE, no EXECUTE, no DELIMITER, and
-- no semicolon inside a comment or a string. A console that parses the whole
-- script before running it cannot always parse the prepared-statement idiom that
-- would otherwise make an ALTER conditional - and the error it then reports points
-- at the statement AFTER the guard, so a valid CREATE TABLE looks broken. Plain
-- DDL is understood by every client, and the price is only this: the two ALTER
-- statements below answer "Duplicate column name 'logo_url'" or "Duplicate key
-- name 'idx_invoices_uniqid'" when the change is already in place. That is not a
-- failure and the run can continue. To check first, or to confirm afterwards, run
-- these two (written without a terminator, like everything in this file, because a
-- console that splits a script on ';' cuts a comment line that ends with one):
--
--   SHOW COLUMNS FROM `firms_users` LIKE 'logo_url'
--   SHOW INDEX FROM `invoices` WHERE Key_name = 'idx_invoices_uniqid'
--
-- The three data statements are idempotent by themselves.
-- ===========================================================================

-- ---------------------------------------------------------------------------
-- 1) Kenyan Shilling. The seeded currency list has no KES row, so a Kenyan
--    account could not pick its own currency on an invoice. `currencies` is
--    (id, value, name), all NOT NULL, and it is seeded without a primary key, so
--    INSERT IGNORE has nothing to conflict on: hence the explicit id (the dump
--    uses 1..114) and the NOT EXISTS test. FROM DUAL is what makes one statement
--    parse in every MySQL-family client.
-- ---------------------------------------------------------------------------
INSERT INTO `currencies` (`id`, `value`, `name`)
SELECT 115, 'KES', 'Kenyan Shilling' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `currencies` WHERE `value` = 'KES');

-- ---------------------------------------------------------------------------
-- 2) The symbol and the code the interface shows for English - the Language
--    library reads languages.currency and languages.currencyKey.
-- ---------------------------------------------------------------------------
UPDATE `languages` SET `currency` = 'KSh', `currencyKey` = 'KES' WHERE `abbr` = 'en';

-- ---------------------------------------------------------------------------
-- 3) firms_users.logo_url - an external logo URL. Uploads land in
--    attachments/, which is not persistent on ephemeral hosting, so an uploaded
--    logo disappears at the next deploy. A URL (Cloudinary, S3, any CDN) survives
--    every restart. A file on disk still wins when it exists, so this changes
--    nothing for installations with real storage.
-- ---------------------------------------------------------------------------
ALTER TABLE `firms_users` ADD COLUMN `logo_url` VARCHAR(500) NULL DEFAULT NULL COMMENT 'external logo URL, for hosts without a persistent disk';

-- ---------------------------------------------------------------------------
-- 4) Share links are built from invoices.uniqid. The column exists in the dump but
--    is unindexed, so opening a public link scans the whole table.
-- ---------------------------------------------------------------------------
ALTER TABLE `invoices` ADD INDEX `idx_invoices_uniqid` (`uniqid`);

-- ---------------------------------------------------------------------------
-- 5) invoice_share_views - who opened which link, when, from where. The app checks
--    for this table and skips the log if it is missing, so the file is optional in
--    the strict sense. Without it, "viewed by the client" is not recorded.
--    Revoking a link rotates invoices.uniqid, so old URLs stop resolving while the
--    log rows stay as history.
-- ---------------------------------------------------------------------------
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
