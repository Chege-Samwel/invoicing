-- ===========================================================================
-- Adomin invoices - the schema, part 05 of 09  (paste-sized pieces of
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

ALTER TABLE `employees_permissions`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `firms_payment_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `req_num` (`req_num`);

ALTER TABLE `firms_plans`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `firms_translations`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `firms_users`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `history`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `individual_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user` (`for_user`);

ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `invoices_clients`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `invoices_firms`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `invoices_items`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `invoices_languages`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `invoices_logs`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `invoices_translations`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `items`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `languages`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `movements`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `movements_clients`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `movements_firms`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `movements_from_to_store`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `movements_languages`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `movements_revisions`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `movements_translations`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `movement_items`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `protocols`
  ADD PRIMARY KEY (`id`);
