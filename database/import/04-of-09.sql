-- ===========================================================================
-- Adomin invoices - the schema, part 04 of 09  (paste-sized pieces of
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
-- creates       `questions`, `questions_translates`, `stock_availability`, `stores`, `users`, `users_currencies`, `users_payment_methods`, `users_quantity_types`, `user_no_vat_reasons`, `value_store`, `warranties`, `warranties_clients`, `warranties_firms`, `warranties_items`, `warranties_languages`, `warranties_translations`, `warranty_conditions`, `warranty_events`
-- seed rows for `questions`, `questions_translates`, `warranties_languages`
-- ===========================================================================

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `position` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `questions` (`id`, `position`) VALUES
(1, 1),
(2, 2);

CREATE TABLE `questions_translates` (
  `id` int(11) NOT NULL,
  `for_id` int(10) UNSIGNED NOT NULL,
  `abbr` varchar(5) NOT NULL,
  `question` varchar(500) NOT NULL,
  `answer` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `questions_translates` (`id`, `for_id`, `abbr`, `question`, `answer`) VALUES
(1, 1, 'en', 'How to register?', 'Registration is the easy part. Just click Register Now and fill in the email and password after you fill them correctly will be redirected to your personal account on invoicing.'),
(2, 2, 'en', 'In which format are invoices', 'Invoices are in PDF format. They can be sent via email as an attachment or link that when opened by the customer invoice is displayed in the browser, so can be downloaded and to accept or refuse that will be reflected in your system.'),
(3, 1, 'bg', 'Как да се регистрирате?', 'Регистрацията е лесната част. Просто кликнете върху "Регистриране сега" и попълнете имейла и паролата, след като ги попълните правилно, ще бъдат пренасочени към личния Ви акаунт при фактуриране.'),
(4, 1, 'fr', 'Comment s\'inscrire?', 'L\'inscription est la partie facile. Il suffit de cliquer sur S\'inscrire maintenant et de remplir l\'e-mail et le mot de passe après les avoir correctement remplis seront redirigés vers votre compte personnel lors de la facturation.'),
(5, 2, 'fr', 'Dans quel format sont les factures', 'Les factures sont en format PDF. Ils peuvent être envoyés par e-mail sous forme de pièce jointe ou de lien qui, une fois ouvert par la facture du client, est affiché dans le navigateur. Vous pouvez donc le télécharger et l\'accepter ou le refuser dans votre système.'),
(6, 2, 'bg', 'В какъв формат са фактурите', 'Фактурите са в PDF формат. Те могат да бъдат изпратени по електронна поща като прикачен файл или връзка, която след като бъде отворена от сметката на клиента, се показва в браузъра. Можете да го изтеглите и да го приемете или отхвърлите във вашата система.');

CREATE TABLE `stock_availability` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `for_company` int(10) UNSIGNED NOT NULL,
  `for_store` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `quantity` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `stores` (
  `id` int(11) NOT NULL,
  `name` varchar(250) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `for_company` int(10) UNSIGNED NOT NULL,
  `created` int(10) UNSIGNED NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(100) NOT NULL,
  `schiffer` varchar(100) NOT NULL,
  `email` varchar(250) NOT NULL,
  `password` varchar(100) NOT NULL,
  `ip_address` varchar(15) NULL DEFAULT NULL,
  `time_registered` int(10) UNSIGNED NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `users_currencies` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `value` varchar(10) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `users_payment_methods` (
  `id` int(11) NOT NULL,
  `name` varchar(500) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `users_quantity_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `user_no_vat_reasons` (
  `id` int(11) NOT NULL,
  `reason` varchar(500) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `value_store` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `_key` varchar(50) NOT NULL,
  `value` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `warranties` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `for_company` int(10) UNSIGNED NOT NULL,
  `warranty_number` varchar(20) NOT NULL,
  `valid_from` int(10) UNSIGNED NOT NULL,
  `to_invoice` varchar(20) NOT NULL,
  `conditions` longtext NOT NULL,
  `remarks` longtext NOT NULL,
  `created` int(10) UNSIGNED NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `received` varchar(100) NOT NULL,
  `compiled` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `warranties_clients` (
  `id` int(11) NOT NULL,
  `for_warranty` int(10) UNSIGNED NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `client_bulstat` varchar(50) NOT NULL,
  `is_to_person` tinyint(1) NOT NULL,
  `client_vat_registered` tinyint(1) NOT NULL,
  `vat_number` varchar(50) NOT NULL,
  `client_ident_num` varchar(50) NOT NULL,
  `client_address` varchar(500) NOT NULL,
  `client_city` varchar(80) NOT NULL,
  `client_country` varchar(80) NOT NULL,
  `accountable_person` varchar(200) NOT NULL,
  `recipient_name` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `warranties_firms` (
  `id` int(11) NOT NULL,
  `for_warranty` int(10) UNSIGNED NOT NULL,
  `bulstat` varchar(100) NOT NULL,
  `name` varchar(500) NOT NULL,
  `address` varchar(1000) NOT NULL,
  `city` varchar(250) NOT NULL,
  `accountable_person` varchar(100) NOT NULL,
  `image` varchar(500) NOT NULL,
  `is_vat_registered` tinyint(1) NOT NULL DEFAULT '0',
  `vat_number` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `warranties_items` (
  `id` int(11) NOT NULL,
  `for_warranty` int(10) UNSIGNED NOT NULL,
  `name` varchar(500) NOT NULL,
  `months` varchar(20) NOT NULL,
  `valid_to` int(10) UNSIGNED NOT NULL,
  `serial` varchar(100) NOT NULL,
  `position` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `warranties_languages` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `language_name` varchar(500) NOT NULL,
  `warranty_card` varchar(100) NOT NULL,
  `date_valid` varchar(100) NOT NULL,
  `recipient` varchar(100) NOT NULL,
  `sender` varchar(100) NOT NULL,
  `bulstat` varchar(100) NOT NULL,
  `address` varchar(100) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_months` varchar(100) NOT NULL,
  `product_valid_to` varchar(100) NOT NULL,
  `product_serial_num` varchar(100) NOT NULL,
  `page` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `remarks` varchar(100) NOT NULL,
  `received` varchar(100) NOT NULL,
  `compiled` varchar(100) NOT NULL,
  `signature` varchar(100) NOT NULL,
  `warranty_conditions` varchar(100) NOT NULL,
  `vat_number` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `warranties_languages` (`id`, `for_user`, `language_name`, `warranty_card`, `date_valid`, `recipient`, `sender`, `bulstat`, `address`, `product_name`, `product_months`, `product_valid_to`, `product_serial_num`, `page`, `city`, `remarks`, `received`, `compiled`, `signature`, `warranty_conditions`, `vat_number`) VALUES
(1, 0, 'English', 'Warranty Card', 'Valid from:', 'Recipient', 'Sender', 'Bulstat', 'Address', 'Name', 'Months', 'Valid to', 'Serial number', 'Page', 'City', 'Remarks', 'Received', 'Compiled', 'Signature', 'Warranty conditions:', 'Vat number'),
(2, 0, 'Français', 'Carte de garantie', 'Valide à partir de:', 'Bénéficiaire', 'expéditeur', 'Bulstat', 'Adresse', 'prénom', 'Mois', 'Valable pour', 'Numéro de série', 'Page', 'Ville', 'Remarques', 'Reçu', 'Compilé', 'Signature', 'Conditions de garantie:', 'Numéro de TVA');

CREATE TABLE `warranties_translations` (
  `id` int(11) NOT NULL,
  `for_warranty` int(10) UNSIGNED NOT NULL,
  `language_name` varchar(500) NOT NULL,
  `warranty_card` varchar(100) NOT NULL,
  `date_valid` varchar(100) NOT NULL,
  `recipient` varchar(100) NOT NULL,
  `sender` varchar(100) NOT NULL,
  `bulstat` varchar(100) NOT NULL,
  `address` varchar(100) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_months` varchar(100) NOT NULL,
  `product_valid_to` varchar(100) NOT NULL,
  `product_serial_num` varchar(100) NOT NULL,
  `page` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `remarks` varchar(100) NOT NULL,
  `received` varchar(100) NOT NULL,
  `compiled` varchar(100) NOT NULL,
  `signature` varchar(100) NOT NULL,
  `warranty_conditions` varchar(100) NOT NULL,
  `vat_number` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `warranty_conditions` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `condition_title` varchar(255) NOT NULL,
  `condition_description` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `warranty_events` (
  `id` int(11) NOT NULL,
  `for_warranty` int(10) UNSIGNED NOT NULL,
  `type` varchar(20) NOT NULL,
  `on_date` int(10) UNSIGNED NOT NULL,
  `item` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `created` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `currencies`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `cusom_plan_requests`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);
