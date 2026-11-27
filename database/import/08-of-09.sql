-- ===========================================================================
-- Adomin invoices - the schema, part 08 of 09  (paste-sized pieces of
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

ALTER TABLE `movements_revisions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `movements_translations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `movement_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `protocols`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `protocols_clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `protocols_contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `protocols_firms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `protocols_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `protocols_languages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `protocols_provider_transmit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `protocols_translations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `quantity_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `questions_translates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `stock_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `users_currencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `users_payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `users_quantity_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `user_no_vat_reasons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `value_store`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `warranties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `warranties_clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `warranties_firms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
