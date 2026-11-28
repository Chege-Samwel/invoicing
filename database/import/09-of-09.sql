-- ===========================================================================
-- Adomin invoices - the schema, part 09 of 09  (paste-sized pieces of
--   database.sql, for a database console in a browser, which is what a free
--   Render install has: no shell, so no mysql client).
--
-- HOW TO RUN THIS PART
--   1. select your schema in the console (the dropdown or the tree). Neither
--      this file nor database.sql contains a USE statement, so with no schema
--      chosen every statement fails with "No database selected".
--   2. paste the file, then SELECT ALL of it (Ctrl+A / Cmd+A) before Run.
--      This is the step people lose an hour on: a console with the cursor
--      inside the text runs only the statement under the cursor, answers
--      "Query OK" in 0 ms, and changes nothing else. It looks like success.
--   3. when the table list below is all there, close this tab and open the
--      next part. Do not skip ahead: keys and constraints are in the last
--      part on purpose, and they need every table to exist first.
--
-- source      database.sql, sha256 cb0faa5ae296, 186 statements, 9 parts
-- this part   7 statements
--
--   The last two statements are the dump's own migration block. On a
--   FRESH schema they answer "Duplicate column name 'exchange_rate'" and
--   "'base_currency'", and that is expected, not a failed import: the
--   columns are already in the CREATE TABLE for invoices (line 263) and
--   invoices_firms (line 322), and the block exists for installs upgraded
--   from an older database.sql. Nothing follows them, so the import is
--   complete either way. Verify with the count query in README.md.
-- ===========================================================================

ALTER TABLE `warranties_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `warranties_languages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `warranties_translations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `warranty_conditions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `warranty_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `invoices`
  ADD `exchange_rate` decimal(15,6) NOT NULL DEFAULT '1.000000' COMMENT 'rate from inv_currency to invoices_firms.base_currency, snapshotted at issue time' AFTER `inv_currency`;

ALTER TABLE `invoices_firms`
  ADD `base_currency` varchar(10) NOT NULL DEFAULT '' COMMENT 'firm default_currency snapshotted at issue time, reference for invoices.exchange_rate';
