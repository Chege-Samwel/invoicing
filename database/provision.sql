-- ===========================================================================
-- One schema per project - run this BEFORE importing the application's tables
-- ===========================================================================
-- Why this file exists: on a MySQL host you can keep as many installations apart
-- as you like by giving each one its own schema, and one free TiDB Cloud Starter
-- cluster is enough for several projects. The name below is the only thing to edit
-- per project, and it is the value that goes into DB_NAME on that project's host.
--
-- How to run it: open it in a client (DBeaver: Alt+X to execute the script), edit
-- the name in BOTH statements, then run it. Then run database.sql and
-- database/adomin-updates.sql against the schema you just created.
--
-- Safe to re-run: CREATE DATABASE IF NOT EXISTS leaves an existing schema exactly
-- as it is, so running this again for a project that is already installed cannot
-- damage it. The skip is reported only as note 1007, which several consoles hide, so
-- to see what is already on this cluster:
--   SHOW DATABASES
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'the name'
--
-- Naming: no spaces, no dots, lowercase with underscores (`shop_ke`, `adomin_dev`)
-- - a schema name goes into a MySQL identifier, into a DSN path and often into a
--   backup filename, and lowercase avoids the case-sensitivity differences between
--   Linux and macOS hosts. Keep one project per name and never reuse a name after
--   dropping a schema, because a stale DB_NAME elsewhere would then write into it.
--
-- What one cluster does NOT give you: isolation of credentials. A Starter cluster
-- has a single user, so every project shares it; a mistake in one project's DB_NAME
-- can therefore write into another project's schema. If that matters (client work),
-- give that client their own cluster, or move to a paid plan where you can create
-- one user per schema and grant it only its own.
-- ===========================================================================

CREATE DATABASE IF NOT EXISTS `adomin` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE `adomin`;

SELECT DATABASE() AS schema_in_use,
       (SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()) AS tables_here;
