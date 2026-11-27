-- ===========================================================================
-- Adomin invoices - the schema, part 03 of 09  (paste-sized pieces of
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
-- this part   19 statements
-- creates       `movements_from_to_store`, `movements_languages`, `movements_revisions`, `movements_translations`, `movement_items`, `payment_methods`, `protocols`, `protocols_clients`, `protocols_contracts`, `protocols_firms`, `protocols_items`, `protocols_languages`, `protocols_provider_transmit`, `protocols_translations`, `quantity_types`
-- seed rows for `movements_languages`, `payment_methods`, `protocols_languages`, `quantity_types`
-- ===========================================================================

CREATE TABLE `movements_from_to_store` (
  `id` int(11) NOT NULL,
  `for_movement` int(10) UNSIGNED NOT NULL,
  `from_store` varchar(250) NOT NULL,
  `to_store` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `movements_languages` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `language_name` varchar(500) NOT NULL,
  `bill_of_goods` varchar(100) NOT NULL,
  `date` varchar(100) NOT NULL,
  `recipient` varchar(100) NOT NULL,
  `sender` varchar(100) NOT NULL,
  `bulstat` varchar(100) NOT NULL,
  `address` varchar(100) NOT NULL,
  `betrayed` varchar(100) NOT NULL,
  `accepted` varchar(100) NOT NULL,
  `amount` varchar(100) NOT NULL,
  `vat` varchar(100) NOT NULL,
  `vat_amount` varchar(100) NOT NULL,
  `no_vat_reason` varchar(100) NOT NULL,
  `final_amount` varchar(20) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_quantity` varchar(10) NOT NULL,
  `product_quantity_type` varchar(100) NOT NULL,
  `single_price` varchar(20) NOT NULL,
  `product_amount` varchar(20) NOT NULL,
  `payment_method` varchar(100) NOT NULL,
  `page` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `remarks` varchar(100) NOT NULL,
  `vat_number` varchar(100) NOT NULL,
  `lot` varchar(100) NOT NULL,
  `expire_date` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `movements_languages` (`id`, `for_user`, `language_name`, `bill_of_goods`, `date`, `recipient`, `sender`, `bulstat`, `address`, `betrayed`, `accepted`, `amount`, `vat`, `vat_amount`, `no_vat_reason`, `final_amount`, `product_name`, `product_quantity`, `product_quantity_type`, `single_price`, `product_amount`, `payment_method`, `page`, `city`, `remarks`, `vat_number`, `lot`, `expire_date`) VALUES
(1, 0, 'English', 'Store order', 'Date', 'Recipient', 'Sender', 'Bulstat', 'Address', 'Betrayed', 'Accepted', 'Amount', 'Vat', 'Vat amount', 'No vat reason', 'Amount', 'Name', 'Quantity', 'Quantity type', 'Price', 'Amount', 'Payment method', 'Page', 'City', 'Remarks', 'Vat number', 'LOT#', 'Expiry date'),
(2, 0, 'Bulgarian', 'Стокова разписка', 'Дата', 'Получател', 'Доставчик', 'Булстат', 'Адрес', 'Предал', 'Приел', 'Сума', 'ДДС', 'ДДС сума', 'Основание за неначисляване на ддс', 'Общо', 'Наименование на стока', 'Количество', 'Тип количество', 'Ед.цена', 'Общо', 'Начин на плащане', 'Страница', 'Град', 'Забележки', 'ДДС Номер', 'Партиден номер', 'Срок на годност'),
(3, 0, 'Français', 'Reçu de marchandise', 'Date', 'Destinataire', 'Fournisseur', 'Bulstat', 'Adresse', 'Il a abandonné', 'Il a accepté', 'Montant', 'TVA', 'Montant de la TVA', 'Raison de la non-facturation de DPS', 'Total', 'Nom du produit', 'Quantité', 'Type de quantité', 'Prix unique', 'Total', 'Méthode de paiement', 'Page', 'Ville', 'Remarques', 'Numéro de TVA', 'Numéro de lot', 'Durée de conservation');

CREATE TABLE `movements_revisions` (
  `id` int(11) NOT NULL,
  `for_movement` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `before_revision` varchar(20) NOT NULL,
  `after_revision` varchar(20) NOT NULL,
  `difference` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `movements_translations` (
  `id` int(11) NOT NULL,
  `language_name` varchar(500) NOT NULL,
  `for_movement` int(10) UNSIGNED NOT NULL,
  `bill_of_goods` varchar(100) NOT NULL,
  `date` varchar(100) NOT NULL,
  `recipient` varchar(100) NOT NULL,
  `sender` varchar(100) NOT NULL,
  `bulstat` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `address` varchar(100) NOT NULL,
  `betrayed` varchar(100) NOT NULL,
  `accepted` varchar(100) NOT NULL,
  `amount` varchar(100) NOT NULL,
  `vat` varchar(100) NOT NULL,
  `vat_amount` varchar(100) NOT NULL,
  `no_vat_reason` varchar(100) NOT NULL,
  `final_amount` varchar(20) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_quantity` varchar(10) NOT NULL,
  `product_quantity_type` varchar(100) NOT NULL,
  `single_price` varchar(20) NOT NULL,
  `product_amount` varchar(20) NOT NULL,
  `payment_method` varchar(100) NOT NULL,
  `page` varchar(100) NOT NULL,
  `remarks` varchar(100) NOT NULL,
  `vat_number` varchar(100) NOT NULL,
  `lot` varchar(100) NOT NULL,
  `expire_date` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `movement_items` (
  `id` int(11) NOT NULL,
  `for_movement` int(10) UNSIGNED NOT NULL,
  `name` varchar(500) NOT NULL,
  `quantity` varchar(20) NOT NULL,
  `quantity_type` varchar(50) NOT NULL,
  `single_price` varchar(20) NOT NULL,
  `total_price` varchar(20) NOT NULL,
  `position` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `payment_methods` (`id`, `name`) VALUES
(1, 'Credit or Debit Card'),
(2, 'Check'),
(3, 'PayPal'),
(4, 'Easypay'),
(5, 'E-Pay'),
(6, 'Other');

CREATE TABLE `protocols` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `for_company` int(10) UNSIGNED NOT NULL,
  `protocol_number` varchar(20) NOT NULL,
  `type` varchar(10) NOT NULL,
  `from_date` int(10) UNSIGNED NOT NULL,
  `to_invoice` varchar(20) NOT NULL,
  `remarks` longtext NOT NULL,
  `created` int(10) UNSIGNED NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `received` varchar(100) NOT NULL,
  `compiled` varchar(100) NOT NULL,
  `provider_transmit` text NOT NULL,
  `contract` text NOT NULL,
  `amount` varchar(50) NOT NULL,
  `vat_percent` varchar(50) NOT NULL,
  `vat_sum` varchar(50) NOT NULL,
  `final_total` varchar(50) NOT NULL,
  `currency` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `protocols_clients` (
  `id` int(11) NOT NULL,
  `for_protocol` int(10) UNSIGNED NOT NULL,
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

CREATE TABLE `protocols_contracts` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `contract` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `protocols_firms` (
  `id` int(11) NOT NULL,
  `for_protocol` int(10) UNSIGNED NOT NULL,
  `bulstat` varchar(100) NOT NULL,
  `name` varchar(500) NOT NULL,
  `address` varchar(1000) NOT NULL,
  `city` varchar(250) NOT NULL,
  `accountable_person` varchar(100) NOT NULL,
  `image` varchar(500) NOT NULL,
  `is_vat_registered` tinyint(1) NOT NULL DEFAULT '0',
  `vat_number` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `protocols_items` (
  `id` int(11) NOT NULL,
  `for_protocol` int(10) UNSIGNED NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `for_company` int(10) UNSIGNED NOT NULL,
  `name` varchar(500) NOT NULL,
  `quantity` varchar(20) NOT NULL,
  `quantity_type` varchar(50) NOT NULL,
  `single_price` varchar(20) NOT NULL,
  `total_price` varchar(20) NOT NULL,
  `position` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `protocols_languages` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `language_name` varchar(500) NOT NULL,
  `transmission_protocol` varchar(100) NOT NULL,
  `protocol_number` varchar(100) NOT NULL,
  `from_date` varchar(100) NOT NULL,
  `recipient` varchar(100) NOT NULL,
  `supplier` varchar(100) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_quantity` varchar(100) NOT NULL,
  `product_quantity_type` varchar(100) NOT NULL,
  `product_single_price` varchar(100) NOT NULL,
  `product_final_price` varchar(100) NOT NULL,
  `amount` varchar(100) NOT NULL,
  `vat` varchar(100) NOT NULL,
  `vat_amount` varchar(100) NOT NULL,
  `final_amount` varchar(100) NOT NULL,
  `received` varchar(100) NOT NULL,
  `compiled` varchar(100) NOT NULL,
  `signature` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `address` varchar(100) NOT NULL,
  `bulstat` varchar(100) NOT NULL,
  `to_invoice` varchar(100) NOT NULL,
  `page` varchar(100) NOT NULL,
  `vat_number` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `protocols_languages` (`id`, `for_user`, `language_name`, `transmission_protocol`, `protocol_number`, `from_date`, `recipient`, `supplier`, `product_name`, `product_quantity`, `product_quantity_type`, `product_single_price`, `product_final_price`, `amount`, `vat`, `vat_amount`, `final_amount`, `received`, `compiled`, `signature`, `city`, `address`, `bulstat`, `to_invoice`, `page`, `vat_number`) VALUES
(1, 0, 'English', 'Admission - transmission protocol', 'Number', 'From date', 'Recipient', 'Supplier', 'Name', 'Quantity', 'Quantity type', 'Single Price', 'Amount', 'Amount', 'Vat', 'Vat amount', 'Amount', 'Received', 'Compiled', 'Signature', 'City', 'Address', 'Bulstat', 'To invoice', 'Page', 'Vat number'),
(2, 0, 'Bulgarian', 'Приемно-предавателен протокол', 'Номер', 'От дата', 'Получател', 'Доставчик', 'Име', 'Количество', 'Тип', 'Ец.цена', 'Крайна цена', 'Цена', 'ДДС', 'ДДС Сума', 'Сума крайна', 'Получил', 'Съставил', 'Подпис', 'Град', 'Адрес', 'Булстат', 'Към фактура', 'Страница', 'ДДС Номер'),
(3, 0, 'Français', 'Protocole d\'acceptation', 'Numéro', 'De Date', 'Destinataire', 'Fournisseur', 'Nom', 'Quantité', 'Type', 'Prix unique', 'Prix final', 'Prix', 'TVA', 'Montant de la TVA', 'Montant final', 'Reçu', 'Compilé', 'Signature', 'Ville', 'Adresse', 'Bulstat', 'Pour une facture', 'Page', 'Numéro de TVA');

CREATE TABLE `protocols_provider_transmit` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `protocols_translations` (
  `id` int(11) NOT NULL,
  `for_protocol` int(10) UNSIGNED NOT NULL,
  `language_name` varchar(500) NOT NULL,
  `transmission_protocol` varchar(100) NOT NULL,
  `protocol_number` varchar(100) NOT NULL,
  `from_date` varchar(100) NOT NULL,
  `recipient` varchar(100) NOT NULL,
  `supplier` varchar(100) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_quantity` varchar(100) NOT NULL,
  `product_quantity_type` varchar(100) NOT NULL,
  `product_single_price` varchar(100) NOT NULL,
  `product_final_price` varchar(100) NOT NULL,
  `amount` varchar(100) NOT NULL,
  `vat` varchar(100) NOT NULL,
  `vat_amount` varchar(100) NOT NULL,
  `final_amount` varchar(100) NOT NULL,
  `received` varchar(100) NOT NULL,
  `compiled` varchar(100) NOT NULL,
  `signature` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `address` varchar(100) NOT NULL,
  `bulstat` varchar(100) NOT NULL,
  `to_invoice` varchar(100) NOT NULL,
  `page` varchar(100) NOT NULL,
  `vat_number` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `quantity_types` (
  `id` int(11) NOT NULL,
  `name` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `quantity_types` (`id`, `name`) VALUES
(1, 'kilogram'),
(2, 'number'),
(3, 'days'),
(4, 'months');
