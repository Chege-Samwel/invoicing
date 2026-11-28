-- ===========================================================================
-- Adomin invoices - the schema, part 07 of 09  (paste-sized pieces of
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
-- this part   26 statements
-- ===========================================================================

ALTER TABLE `admin_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `currencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

ALTER TABLE `cusom_plan_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `employees_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `firms_payment_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `firms_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `firms_translations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `firms_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `individual_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `invoices_clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `invoices_firms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `invoices_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `invoices_languages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `invoices_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `invoices_translations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `languages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `movements_clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `movements_firms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `movements_from_to_store`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `movements_languages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
