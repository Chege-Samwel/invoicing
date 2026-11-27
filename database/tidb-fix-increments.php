<?php
/**
 * Adomin Technologies — repair primary keys that lost AUTO_INCREMENT.
 *
 * WHY THIS EXISTS
 * ---------------
 * database.sql is a phpMyAdmin dump. It creates every table without the
 * AUTO_INCREMENT attribute and adds it at the end, once per table:
 *
 *     ALTER TABLE `clients` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
 *
 * MySQL accepts that. TiDB does not, and cannot - the attribute can only be set
 * in CREATE TABLE:
 *
 *     8200 Unsupported modify column: can't set auto_increment
 *
 * The tables still get created, so an import reports "58 tables" and looks
 * finished, but every table's primary key has no counter. The first INSERT into
 * any of them fails with
 *
 *     Field 'id' doesn't have a default value
 *
 * which is what makes an account registration or a login fail on a TiDB
 * deployment even though the schema "imported fine".
 *
 * The container repairs this automatically on every start
 * (docker/adomin-init-db.php, function adomin_init_repair_auto_increment). Run
 * this file only if you imported by hand into TiDB, or if /status.php reports
 * "Primary keys missing AUTO_INCREMENT".
 *
 * HOW
 * ---
 * For each affected table: create `<table>_adomin_old` with the same definition
 * plus AUTO_INCREMENT, copy the rows, drop the original, rename the copy into
 * place. That is the only supported way in TiDB, and it is what a DBA would do
 * by hand. The copy is removed if any step fails, so a failed run never leaves
 * the schema half-migrated. Tables that already have the attribute are skipped,
 * so running it twice is harmless.
 *
 * USAGE
 * -----
 *   php database/tidb-fix-increments.php            # uses DB_* / DATABASE_URL
 *   php database/tidb-fix-increments.php --check    # report only, change nothing
 *
 * Run it where the DB_* variables are set (the Render shell, or a machine with
 * the same environment). It exits 1 if anything is still broken, 0 when healthy.
 */

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');

if (PHP_SAPI !== 'cli')
{
	header('Content-Type: text/plain; charset=utf-8');
}

$check_only = in_array('--check', $argv, TRUE);

/*
 * Reuse the container initialiser: same environment parsing, same TLS handling,
 * same rebuild routine. Including it does not start the import, because the
 * entry point in that file matches on the script name.
 */
require dirname(__DIR__) . '/docker/adomin-init-db.php';

$cfg = adomin_init_config();
echo 'Adomin: connecting to ' . $cfg['host'] . ':' . $cfg['port'] . ' as ' . $cfg['user']
	. ' / db ' . $cfg['db'] . ' (TLS ' . (is_array($cfg['encrypt']) ? 'on' : 'off') . ')...' . PHP_EOL;

$mysqli = adomin_init_connect($cfg, $cfg['db']);
if ($mysqli === NULL)
{
	echo 'ERROR: could not connect: [' . mysqli_connect_errno() . '] ' . mysqli_connect_error() . PHP_EOL;
	echo 'Check DB_HOST / DB_USER / DB_PASS / DB_PORT=4000 / DB_SSL=1 (SETUP.md section 5).' . PHP_EOL;
	exit(1);
}

$tables = adomin_init_table_count($mysqli);
echo 'Schema ' . $cfg['db'] . ' has ' . $tables . ' tables.' . PHP_EOL;

$missing = adomin_init_tables_missing_auto_increment($mysqli);
if (empty($missing))
{
	echo 'Nothing to do: every table with an `id` column has AUTO_INCREMENT.' . PHP_EOL;
	$mysqli->close();
	exit(0);
}

echo count($missing) . ' table(s) need the attribute: ' . implode(', ', $missing) . PHP_EOL;

if ($check_only)
{
	$mysqli->close();
	echo '--check was given, so nothing was changed. Re-run without it to repair.' . PHP_EOL;
	exit(1);
}

foreach ($missing as $table)
{
	echo '  rebuilding ' . $table . '... ';
	echo adomin_init_rebuild_with_auto_increment($mysqli, $table) ? 'ok' : 'FAILED' . PHP_EOL;
}

$still = adomin_init_tables_missing_auto_increment($mysqli);
$mysqli->close();

if ( ! empty($still))
{
	echo 'ERROR: still missing AUTO_INCREMENT on: ' . implode(', ', $still) . PHP_EOL;
	echo 'Re-run and read the error printed above, or recreate those tables by hand.' . PHP_EOL;
	exit(1);
}

echo 'Done: every `id` column is AUTO_INCREMENT again. Reload the site.' . PHP_EOL;
exit(0);
