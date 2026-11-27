-- ===========================================================================
-- Adomin invoices - the schema, part 06 of 09  (paste-sized pieces of
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

ALTER TABLE `protocols_clients`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `protocols_contracts`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `protocols_firms`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `protocols_items`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `protocols_languages`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `protocols_provider_transmit`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `protocols_translations`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `quantity_types`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `questions_translates`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `stock_availability`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_unique` (`email`);

ALTER TABLE `users_currencies`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `users_payment_methods`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `users_quantity_types`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `user_no_vat_reasons`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `value_store`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `warranties`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `warranties_clients`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `warranties_firms`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `warranties_items`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `warranties_languages`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `warranties_translations`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `warranty_conditions`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `warranty_events`
  ADD PRIMARY KEY (`id`);
