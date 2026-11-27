-- ===========================================================================
-- Adomin invoices - the schema, part 02 of 09  (paste-sized pieces of
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
-- this part   14 statements
-- creates       `invoices`, `invoices_clients`, `invoices_firms`, `invoices_items`, `invoices_languages`, `invoices_logs`, `invoices_translations`, `items`, `languages`, `movements`, `movements_clients`, `movements_firms`
-- seed rows for `invoices_languages`, `languages`
-- ===========================================================================

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `for_company` int(10) UNSIGNED NOT NULL,
  `inv_type` varchar(10) NOT NULL,
  `status` varchar(10) NOT NULL,
  `inv_number` varchar(20) NOT NULL,
  `inv_currency` varchar(50) NOT NULL,
  `exchange_rate` decimal(15,6) NOT NULL DEFAULT '1.000000' COMMENT 'rate from inv_currency to invoices_firms.base_currency, snapshotted at issue time',
  `date_create` int(10) UNSIGNED NOT NULL,
  `date_tax_event` int(10) UNSIGNED NOT NULL,
  `cash_accounting` tinyint(1) NOT NULL,
  `have_maturity_date` tinyint(1) NOT NULL,
  `maturity_date` int(10) UNSIGNED NOT NULL,
  `remarks` longtext NOT NULL,
  `payment_method` varchar(200) NOT NULL,
  `payment_status` varchar(20) NOT NULL,
  `to_inv_number` varchar(20) NOT NULL,
  `to_inv_date` int(10) UNSIGNED NOT NULL,
  `invoice_amount` varchar(50) NOT NULL,
  `discount` varchar(50) NOT NULL,
  `discount_type` varchar(50) NOT NULL,
  `tax_base` varchar(50) NOT NULL,
  `vat_percent` varchar(50) NOT NULL,
  `vat_sum` varchar(50) NOT NULL,
  `no_vat` tinyint(1) NOT NULL,
  `no_vat_reason` varchar(255) NOT NULL,
  `final_total` varchar(50) NOT NULL,
  `composed` varchar(255) NOT NULL,
  `schiffer` varchar(100) NOT NULL,
  `created` int(10) UNSIGNED NOT NULL,
  `date_received` int(10) UNSIGNED NOT NULL,
  `return_reason` varchar(500) NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `uniqid` varchar(50) NOT NULL COMMENT 'used for links'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `invoices_clients` (
  `id` int(11) NOT NULL,
  `for_invoice` int(10) UNSIGNED NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `for_company` int(10) UNSIGNED NOT NULL,
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

CREATE TABLE `invoices_firms` (
  `id` int(11) NOT NULL,
  `for_invoice` int(10) UNSIGNED NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `bulstat` varchar(100) NOT NULL,
  `name` varchar(500) NOT NULL,
  `address` varchar(1000) NOT NULL,
  `city` varchar(250) NOT NULL,
  `accountable_person` varchar(100) NOT NULL,
  `image` varchar(500) NOT NULL,
  `is_vat_registered` tinyint(1) NOT NULL,
  `vat_number` varchar(20) NOT NULL,
  `base_currency` varchar(10) NOT NULL DEFAULT '' COMMENT 'firm default_currency snapshotted at issue time, reference for invoices.exchange_rate'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `invoices_items` (
  `id` int(11) NOT NULL,
  `for_invoice` int(10) UNSIGNED NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `for_company` int(10) UNSIGNED NOT NULL,
  `name` varchar(500) NOT NULL,
  `quantity` varchar(20) NOT NULL,
  `quantity_type` varchar(50) NOT NULL,
  `single_price` varchar(20) NOT NULL,
  `total_price` varchar(20) NOT NULL,
  `position` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `invoices_languages` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `language_name` varchar(500) NOT NULL,
  `recipient` varchar(250) NOT NULL,
  `bulstat` varchar(100) NOT NULL,
  `mol` varchar(100) NOT NULL,
  `sender` varchar(100) NOT NULL,
  `original` varchar(100) NOT NULL,
  `number` varchar(100) NOT NULL,
  `date_of_issue` varchar(100) NOT NULL,
  `a_date_of_a_tax_event` varchar(100) NOT NULL,
  `to_an_invoice` varchar(100) NOT NULL,
  `invoice` varchar(100) NOT NULL,
  `debit_note` varchar(100) NOT NULL,
  `credit_note` varchar(100) NOT NULL,
  `remarks` varchar(100) NOT NULL,
  `pro_forma` varchar(100) NOT NULL,
  `products_name` varchar(100) NOT NULL,
  `quantity` varchar(100) NOT NULL,
  `single_price` varchar(100) NOT NULL,
  `value` varchar(100) NOT NULL,
  `amount` varchar(100) NOT NULL,
  `tax_base` varchar(100) NOT NULL,
  `percentage_vat` varchar(100) NOT NULL,
  `vat_charget` varchar(100) NOT NULL,
  `everything` varchar(100) NOT NULL,
  `reason_for_non_vat` varchar(100) NOT NULL,
  `compiled` varchar(100) NOT NULL,
  `signature` varchar(100) NOT NULL,
  `schiffer` varchar(100) NOT NULL,
  `page` varchar(100) NOT NULL,
  `payment_type` varchar(100) NOT NULL,
  `from_date` varchar(100) NOT NULL,
  `discount` varchar(100) NOT NULL,
  `receive_inv` varchar(100) NOT NULL,
  `i_accept` varchar(100) NOT NULL,
  `i_refuse` varchar(100) NOT NULL,
  `receive_inv_from` varchar(100) NOT NULL,
  `vat_number` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `invoices_languages` (`id`, `for_user`, `language_name`, `recipient`, `bulstat`, `mol`, `sender`, `original`, `number`, `date_of_issue`, `a_date_of_a_tax_event`, `to_an_invoice`, `invoice`, `debit_note`, `credit_note`, `remarks`, `pro_forma`, `products_name`, `quantity`, `single_price`, `value`, `amount`, `tax_base`, `percentage_vat`, `vat_charget`, `everything`, `reason_for_non_vat`, `compiled`, `signature`, `schiffer`, `page`, `payment_type`, `from_date`, `discount`, `receive_inv`, `i_accept`, `i_refuse`, `receive_inv_from`, `vat_number`) VALUES
(1, 0, 'English', 'Beneficiary\n', 'Bulstat:', 'Contact:', 'Sender', 'Original', 'Number', 'Date of issue', 'Date of tax event', 'To an invoice', 'Invoice', 'Debit note', 'Credit note', 'Remakrs', 'Pro-forma', 'Description', 'Quantity', 'Unit Cost', 'Price', 'Subtotal:', 'VAT base:', 'Percentage vat', 'Vat charged', 'Total:', 'Reason for non vat', 'Compiled', 'Signature', 'Schiffer', 'Page', 'Payment type', 'From date', 'Discount:', 'Reveice invoice', 'I accept', 'I refuse', 'Receive invoice from', 'Vat Number:'),
(2, 0, 'Bulgarian', 'Получател', 'Булстат:', 'Мол:', 'Доставчик', 'Оригинал', 'Номер', 'Дата на издаване', 'Дата на данъчно събитие', 'Към фактура', 'Фактура', 'Дебитно известие', 'Кредитно известие', 'Забележки', 'Проформа', 'Наименование на продукта', 'Количество', 'Ед.цена', 'Стойност', 'Сума по фактура:', 'Данъчна основа:', 'Процент ДДС', 'ДДС', 'Общо:', 'Причина за неначисляване на ДДС', 'Съставил', 'Подпис', 'Шифър', 'Страница', 'Начин на плащане', 'От дата', 'Отстъпка:', 'Получихте фактура', 'Приемам', 'Отказвам', 'Получихте фактура от', 'ДДС Номер:'),
(3, 0, 'Français', 'Destinataire', 'Bulstat:', 'Destinataire:', 'Fournisseur', 'Original', 'Numéro', 'Date d\'émission', 'Date de l\'événement fiscal', 'Pour une facture', 'Facture', 'Notification de la dette', 'Note de crédit', 'Remarques', 'Proform', 'Nom du produit', 'Quantité', 'Prix unique', 'Valeur', 'Montant de la facture:', 'Base d\'imposition:', 'Pourcentage de TVA', 'TVA', 'Total:', 'Raison de la TVA non facturée', 'Compilé', 'Signature', 'Schiffer', 'Page', 'Méthode de paiement', 'De Date', 'Remise:', 'Vous avez reçu une facture', 'J\'accepte', 'Je refuse', 'Vous avez reçu une facture de', 'Numéro de TVA:');

CREATE TABLE `invoices_logs` (
  `id` int(11) NOT NULL,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `action` varchar(50) NOT NULL,
  `info` varchar(500) NOT NULL,
  `time` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `invoices_translations` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `for_invoice` int(10) UNSIGNED NOT NULL,
  `language_name` varchar(500) NOT NULL,
  `recipient` varchar(250) NOT NULL,
  `bulstat` varchar(100) NOT NULL,
  `mol` varchar(100) NOT NULL,
  `sender` varchar(100) NOT NULL,
  `original` varchar(100) NOT NULL,
  `number` varchar(100) NOT NULL,
  `date_of_issue` varchar(100) NOT NULL,
  `a_date_of_a_tax_event` varchar(100) NOT NULL,
  `to_an_invoice` varchar(100) NOT NULL,
  `from_date` varchar(100) NOT NULL COMMENT 'to an invoice from date',
  `invoice` varchar(100) NOT NULL,
  `debit_note` varchar(100) NOT NULL,
  `credit_note` varchar(100) NOT NULL,
  `remarks` varchar(100) NOT NULL,
  `pro_forma` varchar(100) NOT NULL,
  `products_name` varchar(100) NOT NULL,
  `quantity` varchar(100) NOT NULL,
  `single_price` varchar(100) NOT NULL,
  `value` varchar(100) NOT NULL,
  `amount` varchar(100) NOT NULL,
  `tax_base` varchar(100) NOT NULL,
  `percentage_vat` varchar(100) NOT NULL,
  `vat_charget` varchar(100) NOT NULL,
  `discount` varchar(100) NOT NULL,
  `everything` varchar(100) NOT NULL,
  `reason_for_non_vat` varchar(100) NOT NULL,
  `compiled` varchar(100) NOT NULL,
  `signature` varchar(100) NOT NULL,
  `schiffer` varchar(100) NOT NULL,
  `payment_type` varchar(100) NOT NULL,
  `page` varchar(100) NOT NULL,
  `receive_inv` varchar(100) NOT NULL,
  `i_accept` varchar(100) NOT NULL,
  `i_refuse` varchar(100) NOT NULL,
  `receive_inv_from` varchar(100) NOT NULL,
  `vat_number` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `for_company` int(10) UNSIGNED NOT NULL,
  `name` varchar(500) NOT NULL,
  `quantity_type` varchar(50) NOT NULL,
  `single_price` varchar(20) NOT NULL,
  `currency` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `languages` (
  `id` int(10) UNSIGNED NOT NULL,
  `abbr` varchar(5) NOT NULL,
  `name` varchar(30) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `flag` varchar(255) NOT NULL,
  `currencyKey` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `languages` (`id`, `abbr`, `name`, `currency`, `flag`, `currencyKey`) VALUES
(2, 'en', 'english', 'EUR', 'en.png', 'EUR'),
(3, 'bg', 'bulgarian', 'лв', '', 'bgn'),
(4, 'fr', 'france', 'EUR', '', 'EUR');

CREATE TABLE `movements` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `for_company` int(10) UNSIGNED NOT NULL,
  `movement_number` varchar(20) NOT NULL,
  `movement_type` varchar(15) NOT NULL,
  `store_id` int(10) UNSIGNED NOT NULL,
  `movement_currency` varchar(50) NOT NULL,
  `amount` varchar(50) NOT NULL,
  `discount` varchar(50) NOT NULL,
  `discount_type` varchar(50) NOT NULL,
  `tax_base` varchar(50) NOT NULL,
  `vat_percent` varchar(50) NOT NULL,
  `vat_sum` varchar(50) NOT NULL,
  `no_vat_reason` varchar(255) NOT NULL,
  `final_total` varchar(50) NOT NULL,
  `remarks` longtext NOT NULL,
  `payment_method` varchar(200) NOT NULL,
  `created` int(10) UNSIGNED NOT NULL COMMENT 'date_create field in view',
  `cancelled` tinyint(1) NOT NULL DEFAULT '0',
  `betrayed` varchar(100) NOT NULL,
  `accepted` varchar(100) NOT NULL,
  `lot` varchar(20) NOT NULL,
  `expire_date` int(10) UNSIGNED NOT NULL,
  `to_invoice` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `movements_clients` (
  `id` int(11) NOT NULL,
  `for_movement` int(10) UNSIGNED NOT NULL,
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

CREATE TABLE `movements_firms` (
  `id` int(11) NOT NULL,
  `for_movement` int(10) UNSIGNED NOT NULL,
  `bulstat` varchar(100) NOT NULL,
  `name` varchar(500) NOT NULL,
  `address` varchar(1000) NOT NULL,
  `city` varchar(250) NOT NULL,
  `accountable_person` varchar(100) NOT NULL,
  `image` varchar(500) NOT NULL,
  `is_vat_registered` tinyint(1) NOT NULL DEFAULT '0',
  `vat_number` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
