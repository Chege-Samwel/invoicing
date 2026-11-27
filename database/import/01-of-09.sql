-- ===========================================================================
-- Adomin invoices - the schema, part 01 of 09  (paste-sized pieces of
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
--   Part 01 opens with SET NAMES utf8mb4. That replaces the dump's own
--   /*!40101 ... */ charset lines, which are dropped from these parts:
--   a console that does not implement MySQL executable comments would
--   try to parse them as statements. The two SET lines that follow it are
--   from the dump - keep NO_AUTO_VALUE_ON_ZERO, it protects seeded id 0.
--
-- source      database.sql, sha256 cb0faa5ae296, 186 statements, 9 parts
-- this part   16 statements
-- creates       `admin_users`, `clients`, `currencies`, `cusom_plan_requests`, `employees`, `employees_permissions`, `firms_payment_requests`, `firms_plans`, `firms_translations`, `firms_users`, `history`, `individual_plans`
-- seed rows for `admin_users`, `currencies`
-- ===========================================================================

SET NAMES utf8mb4;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

SET time_zone = "+00:00";

CREATE TABLE `admin_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(32) NOT NULL,
  `email` varchar(100) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `registered` int(11) NOT NULL,
  `last_login` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `admin_users` (`id`, `name`, `username`, `password`, `email`, `image`, `registered`, `last_login`) VALUES
(1, 'Kiril Kirkov', 'admin', '21232f297a57a5a743894a0e4a801fc3', 'admin@admin.admin', '16427489_1341882885869514_8248047917573720528_n2.jpg', 0, 1516633925);

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
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
  `accountable_person` varchar(200) NOT NULL COMMENT 'MOL',
  `recipient_name` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `currencies` (
  `id` int(11) NOT NULL,
  `value` varchar(10) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `currencies` (`id`, `value`, `name`) VALUES
(1, 'ALL', 'Albania Lek'),
(2, 'AFN', 'Afghanistan Afghani'),
(3, 'ARS', 'Argentina Peso'),
(4, 'AWG', 'Aruba Guilder'),
(5, 'AUD', 'Australia Dollar'),
(6, 'AZN', 'Azerbaijan New Manat'),
(7, 'BSD', 'Bahamas Dollar'),
(8, 'BBD', 'Barbados Dollar'),
(9, 'BDT', 'Bangladeshi taka'),
(10, 'BYR', 'Belarus Ruble'),
(11, 'BZD', 'Belize Dollar'),
(12, 'BMD', 'Bermuda Dollar'),
(13, 'BOB', 'Bolivia Boliviano'),
(14, 'BAM', 'Bosnia and Herzegovina Convertible Marka'),
(15, 'BWP', 'Botswana Pula'),
(16, 'BGN', 'Bulgaria Lev'),
(17, 'BRL', 'Brazil Real'),
(18, 'BND', 'Brunei Darussalam Dollar'),
(19, 'KHR', 'Cambodia Riel'),
(20, 'CAD', 'Canada Dollar'),
(21, 'KYD', 'Cayman Islands Dollar'),
(22, 'CLP', 'Chile Peso'),
(23, 'CNY', 'China Yuan Renminbi'),
(24, 'COP', 'Colombia Peso'),
(25, 'CRC', 'Costa Rica Colon'),
(26, 'HRK', 'Croatia Kuna'),
(27, 'CUP', 'Cuba Peso'),
(28, 'CZK', 'Czech Republic Koruna'),
(29, 'DKK', 'Denmark Krone'),
(30, 'DOP', 'Dominican Republic Peso'),
(31, 'XCD', 'East Caribbean Dollar'),
(32, 'EGP', 'Egypt Pound'),
(33, 'SVC', 'El Salvador Colon'),
(34, 'EEK', 'Estonia Kroon'),
(35, 'EUR', 'Euro Member Countries'),
(36, 'FKP', 'Falkland Islands (Malvinas) Pound'),
(37, 'FJD', 'Fiji Dollar'),
(38, 'GHC', 'Ghana Cedis'),
(39, 'GIP', 'Gibraltar Pound'),
(40, 'GTQ', 'Guatemala Quetzal'),
(41, 'GGP', 'Guernsey Pound'),
(42, 'GYD', 'Guyana Dollar'),
(43, 'HNL', 'Honduras Lempira'),
(44, 'HKD', 'Hong Kong Dollar'),
(45, 'HUF', 'Hungary Forint'),
(46, 'ISK', 'Iceland Krona'),
(47, 'INR', 'India Rupee'),
(48, 'IDR', 'Indonesia Rupiah'),
(49, 'IRR', 'Iran Rial'),
(50, 'IMP', 'Isle of Man Pound'),
(51, 'ILS', 'Israel Shekel'),
(52, 'JMD', 'Jamaica Dollar'),
(53, 'JPY', 'Japan Yen'),
(54, 'JEP', 'Jersey Pound'),
(55, 'KZT', 'Kazakhstan Tenge'),
(56, 'KPW', 'Korea (North) Won'),
(57, 'KRW', 'Korea (South) Won'),
(58, 'KGS', 'Kyrgyzstan Som'),
(59, 'LAK', 'Laos Kip'),
(60, 'LVL', 'Latvia Lat'),
(61, 'LBP', 'Lebanon Pound'),
(62, 'LRD', 'Liberia Dollar'),
(63, 'LTL', 'Lithuania Litas'),
(64, 'MKD', 'Macedonia Denar'),
(65, 'MYR', 'Malaysia Ringgit'),
(66, 'MUR', 'Mauritius Rupee'),
(67, 'MXN', 'Mexico Peso'),
(68, 'MNT', 'Mongolia Tughrik'),
(69, 'MZN', 'Mozambique Metical'),
(70, 'NAD', 'Namibia Dollar'),
(71, 'NPR', 'Nepal Rupee'),
(72, 'ANG', 'Netherlands Antilles Guilder'),
(73, 'NZD', 'New Zealand Dollar'),
(74, 'NIO', 'Nicaragua Cordoba'),
(75, 'NGN', 'Nigeria Naira'),
(76, 'NOK', 'Norway Krone'),
(77, 'OMR', 'Oman Rial'),
(78, 'PKR', 'Pakistan Rupee'),
(79, 'PAB', 'Panama Balboa'),
(80, 'PYG', 'Paraguay Guarani'),
(81, 'PEN', 'Peru Nuevo Sol'),
(82, 'PHP', 'Philippines Peso'),
(83, 'PLN', 'Poland Zloty'),
(84, 'QAR', 'Qatar Riyal'),
(85, 'RON', 'Romania New Leu'),
(86, 'RUB', 'Russia Ruble'),
(87, 'SHP', 'Saint Helena Pound'),
(88, 'SAR', 'Saudi Arabia Riyal'),
(89, 'RSD', 'Serbia Dinar'),
(90, 'SCR', 'Seychelles Rupee'),
(91, 'SGD', 'Singapore Dollar'),
(92, 'SBD', 'Solomon Islands Dollar'),
(93, 'SOS', 'Somalia Shilling'),
(94, 'ZAR', 'South Africa Rand'),
(95, 'LKR', 'Sri Lanka Rupee'),
(96, 'SEK', 'Sweden Krona'),
(97, 'CHF', 'Switzerland Franc'),
(98, 'SRD', 'Suriname Dollar'),
(99, 'SYP', 'Syria Pound'),
(100, 'TWD', 'Taiwan New Dollar'),
(101, 'THB', 'Thailand Baht'),
(102, 'TTD', 'Trinidad and Tobago Dollar'),
(103, 'TRY', 'Turkey Lira'),
(104, 'TRL', 'Turkey Lira'),
(105, 'TVD', 'Tuvalu Dollar'),
(106, 'UAH', 'Ukraine Hryvna'),
(107, 'GBP', 'United Kingdom Pound'),
(108, 'USD', 'United States Dollar'),
(109, 'UYU', 'Uruguay Peso'),
(110, 'UZS', 'Uzbekistan Som'),
(111, 'VEF', 'Venezuela Bolivar'),
(112, 'VND', 'Viet Nam Dong'),
(113, 'YER', 'Yemen Rial'),
(114, 'ZWD', 'Zimbabwe Dollar');

CREATE TABLE `cusom_plan_requests` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `invoices` int(10) UNSIGNED NOT NULL,
  `companies` int(10) UNSIGNED NOT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL COMMENT '0new, 1rejected, 2added_to_individual_plans',
  `time` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `email` varchar(250) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(100) NOT NULL,
  `schiffer` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `time_added` int(11) NOT NULL,
  `firms_access` varchar(200) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `employees_permissions` (
  `id` int(11) NOT NULL,
  `for_employee` int(11) NOT NULL,
  `perm` varchar(50) NOT NULL,
  `role` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `firms_payment_requests` (
  `id` int(11) NOT NULL,
  `for_user` int(11) NOT NULL,
  `req_num` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `plan_type` varchar(50) NOT NULL,
  `plan_period` int(10) UNSIGNED NOT NULL COMMENT 'in months',
  `date_generated` int(11) NOT NULL,
  `date_activated` int(10) UNSIGNED NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0new, 1rejected, 2activated'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `firms_plans` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL,
  `from_date` int(11) NOT NULL,
  `to_date` int(11) NOT NULL,
  `plan_type` varchar(50) NOT NULL,
  `num_invoices` int(11) NOT NULL,
  `num_firms` int(11) NOT NULL,
  `time` int(10) UNSIGNED NOT NULL COMMENT 'time added',
  `sponsored` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `firms_translations` (
  `id` int(11) NOT NULL,
  `for_firm` int(10) UNSIGNED NOT NULL COMMENT 'firm id',
  `name` varchar(500) NOT NULL,
  `address` varchar(1000) NOT NULL,
  `city` varchar(250) NOT NULL,
  `mol` varchar(100) NOT NULL,
  `image` varchar(500) DEFAULT NULL,
  `trans_name` varchar(100) NOT NULL COMMENT 'for internally usage',
  `is_default` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `firms_users` (
  `id` int(11) NOT NULL,
  `for_user` int(10) UNSIGNED NOT NULL COMMENT 'user id',
  `bulstat` varchar(100) NOT NULL,
  `is_vat_registered` tinyint(1) NOT NULL DEFAULT '0',
  `vat_number` varchar(50) NOT NULL,
  `default_currency` varchar(10) DEFAULT NULL,
  `show_logo` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Show logo in invoices',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `history` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activity` varchar(255) NOT NULL,
  `time` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `individual_plans` (
  `id` int(11) NOT NULL,
  `for_user` int(11) NOT NULL,
  `num_invoices` int(11) NOT NULL,
  `num_firms` int(11) NOT NULL,
  `price` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
