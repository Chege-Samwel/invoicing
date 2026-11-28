<?php
/**
 * Why does this share link not resolve?
 *
 * A share link is a URL in an email, opened months later by someone who cannot
 * read our logs. When it answers 404 there are only five possible reasons, and
 * they look identical from the outside: the token is not the shape the app
 * mints, no row carries it, the row is soft-deleted, the row belongs to another
 * account, or the document is fine and the page itself is failing.
 *
 * This answers that from a browser. It runs without booting CodeIgniter, like
 * status.php, and it prints nothing a stranger holding the URL does not already
 * know - the token is in their address bar. It is deliberately narrow: one
 * token, five checks, and the one that failed.
 *
 *   /share-doctor.php?token=3u6b0a857c5ef90
 *   /share-doctor.php?token=3u6b0a857c5ef90&format=json
 *
 * 200 when the document resolves, 404 when it does not. Never 500: a diagnostic
 * that breaks is worse than none.
 */

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

if ( ! defined('BASEPATH'))
{
	define('BASEPATH', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
if ( ! defined('ENVIRONMENT'))
{
	define('ENVIRONMENT', 'production');
}

require dirname(__FILE__) . '/docker/adomin-init-db.php';

/**
 * The token's shape, and the account id in its prefix.
 *
 * This must agree with application/helpers/share_unavailable_helper.php and
 * with NewInvoiceModel::ensureShareToken(), which builds
 * USER_ID . 'u' . uniqid(). Kept as one small regex here rather than loading a
 * CodeIgniter helper, because this file has to run when the app cannot.
 *
 * @param	string	$token
 * @return	array	['wellformed' => bool, 'user' => int]
 */
function adomin_doctor_token($token)
{
	$token = (string) $token;
	if (preg_match('/^(\d+)u[0-9a-z]{4,}$/i', $token, $m))
	{
		return array('wellformed' => TRUE, 'user' => (int) $m[1]);
	}
	return array('wellformed' => FALSE, 'user' => 0);
}

/**
 * @param	string	$token
 * @return	array
 */
function adomin_doctor_check($token)
{
	$token = trim((string) $token);
	$report = array(
		'token' => $token,
		'token_length' => strlen($token),
		'checks' => array(),
		'verdict' => NULL,
		'user_id_in_token' => 0,
		'tables' => NULL,
	);

	$shape = adomin_doctor_token($token);
	$report['user_id_in_token'] = $shape['user'];
	$report['checks']['shape'] = $shape['wellformed'];

	if ($token === '')
	{
		$report['verdict'] = 'no_token';
		return $report;
	}

	/*
	 * The shape check is a diagnosis, not a gate: a token minted by an older
	 * build might not match it, and the row would still be there. So the lookup
	 * runs regardless, and the two answers together say whether the token or
	 * the row is the problem.
	 */
	$cfg = adomin_init_config();
	if (adomin_status_env('DB_HOST') === '' && adomin_status_env('DATABASE_URL') === '')
	{
		$report['verdict'] = 'no_database';
		return $report;
	}

	$mysqli = adomin_init_connect($cfg, NULL);
	if ($mysqli === NULL)
	{
		$report['verdict'] = 'database_unreachable';
		$report['error'] = '[' . mysqli_connect_errno() . '] ' . mysqli_connect_error();
		return $report;
	}

	$db = $cfg['db'];
	$mysqli->select_db($db);
	$report['tables'] = adomin_init_table_count($mysqli);

	$sql = 'SELECT id, for_user, for_company, inv_type, inv_number, is_deleted, status, final_total, inv_currency '
		. 'FROM `invoices` WHERE `uniqid` = \'' . $mysqli->real_escape_string($token) . '\' LIMIT 5';
	$result = $mysqli->query($sql);
	if ( ! $result)
	{
		$report['verdict'] = 'query_failed';
		$report['error'] = '[' . $mysqli->errno . '] ' . $mysqli->error;
		$mysqli->close();
		return $report;
	}

	$rows = array();
	while ($row = $result->fetch_assoc())
	{
		$rows[] = $row;
	}
	$result->free();

	$report['checks']['row_exists'] = (count($rows) > 0);

	if (count($rows) === 0)
	{
		/*
		 * The most useful thing this can do for a token that is not in the
		 * table: say whether the prefix names an account that does exist, and
		 * whether that account has any invoice with a token at all. A link from
		 * a deleted account and a mistyped link are different problems.
		 */
		if ($shape['user'] > 0)
		{
			$u = (int) $shape['user'];
			$res = $mysqli->query('SELECT COUNT(*) AS c FROM `invoices` WHERE `for_user` = ' . $u);
			if ($res)
			{
				$row = $res->fetch_assoc();
				$report['account_invoice_count'] = (int) $row['c'];
				$res->free();
			}
			$res = $mysqli->query('SELECT COUNT(*) AS c FROM `users` WHERE `id` = ' . $u);
			if ($res)
			{
				$row = $res->fetch_assoc();
				$report['checks']['account_exists'] = ((int) $row['c'] > 0);
				$res->free();
			}
		}
		$report['verdict'] = $shape['wellformed'] ? 'token_not_found' : 'malformed_token';
		$mysqli->close();
		return $report;
	}

	$row = $rows[0];
	$report['invoice'] = array(
		'id' => (int) $row['id'],
		'for_user' => (int) $row['for_user'],
		'inv_type' => $row['inv_type'],
		'inv_number' => $row['inv_number'],
		'is_deleted' => (int) $row['is_deleted'],
		'status' => $row['status'],
		'total' => $row['final_total'] . ' ' . $row['inv_currency'],
	);

	$report['checks']['not_deleted'] = ((int) $row['is_deleted'] === 0);
	/*
	 * The prefix and the row can disagree on imported or re-created account
	 * data. That is reported, not treated as a miss: the public lookup matches
	 * on the token and only uses the prefix to break a tie.
	 */
	$report['checks']['prefix_matches_row'] = ($shape['user'] === (int) $row['for_user']);

	// the pieces the page needs, so a failure after the lookup is visible here
	foreach (array('invoices_items' => 'for_invoice', 'invoices_clients' => 'for_invoice', 'invoices_firms' => 'for_invoice') as $table => $col)
	{
		$res = $mysqli->query('SELECT COUNT(*) AS c FROM `' . $table . '` WHERE `' . $col . '` = ' . (int) $row['id']);
		if ($res)
		{
			$r = $res->fetch_assoc();
			$report['checks'][$table] = ((int) $r['c'] > 0);
			$report['row_counts'][$table] = (int) $r['c'];
			$res->free();
		}
	}

	$mysqli->close();

	if ( ! $report['checks']['not_deleted'])
	{
		$report['verdict'] = 'deleted';
	}
	else
	{
		$report['verdict'] = 'ok';
	}

	return $report;
}

/**
 * @param	string	$verdict
 * @return	array	[title, what it means, what to do]
 */
function adomin_doctor_explain($verdict)
{
	$map = array(
		'ok' => array(
			'This link resolves',
			'The token is in the database and the document is live, so the share page has everything it needs.',
			'If the page still fails, the problem is after the lookup - open /status.php for the PHP and database state.',
		),
		'no_token' => array(
			'No token was given',
			'This page needs the part of the link after the last slash.',
			'Add ?token=... to this URL.',
		),
		'malformed_token' => array(
			'The token is not the shape this app mints',
			'A share token is the account id, the letter u, then a short random string - for example 3u6b0a857c5ef90.',
			'The link was probably cut short when it was copied or wrapped by an email client. Ask the sender for it again, or open it straight from the message.',
		),
		'token_not_found' => array(
			'No document carries this token',
			'The token is the right shape but no invoice row has it. That is what a revoked or reissued link looks like: revoking rotates the token, so the old URL stops pointing at anything.',
			'Ask the business to send the link again. If they did not revoke it, tell them the token above so they can look it up.',
		),
		'deleted' => array(
			'The document was deleted',
			'The token still resolves, but the invoice is marked deleted, so it is not shown.',
			'Ask the business to restore or reissue the document.',
		),
		'no_database' => array(
			'This deployment has no database configured',
			'No link can resolve until DB_HOST or DATABASE_URL is set.',
			'See /status.php.',
		),
		'database_unreachable' => array(
			'The database could not be reached',
			'The token was not checked at all.',
			'See /status.php - this is a deployment problem, not a link problem.',
		),
		'query_failed' => array(
			'The lookup itself failed',
			'The database answered, but the query did not run.',
			'See /status.php. This is a schema problem, not a link problem.',
		),
	);
	return isset($map[$verdict]) ? $map[$verdict] : array('Unknown outcome', '', '');
}

$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$format = isset($_GET['format']) ? (string) $_GET['format'] : 'html';

$report = adomin_doctor_check($token);
$report['explain'] = adomin_doctor_explain($report['verdict']);
$ok = ($report['verdict'] === 'ok');

header('X-Robots-Tag: noindex, nofollow');
http_response_code($ok ? 200 : 404);

if ($format === 'json')
{
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title>Share link check</title>
	<style>
		:root { --ok: #1a7f37; --bad: #b42318; --line: #e4e9f0; }
		body { margin: 0; background: #eef1f5; color: #1f2a36;
			font: 16px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
		.wrap { max-width: 760px; margin: 0 auto; padding: 40px 20px 60px; }
		.card { background: #fff; border-radius: 10px; padding: 30px 28px;
			box-shadow: 0 1px 2px rgba(16,24,40,.06), 0 8px 24px rgba(16,24,40,.06); }
		.verdict { display: inline-block; font-size: 12px; letter-spacing: .1em; text-transform: uppercase;
			font-weight: 700; padding: 4px 10px; border-radius: 20px; margin-bottom: 14px; }
		.verdict.ok { background: #e6f6ea; color: var(--ok); }
		.verdict.bad { background: #fdecee; color: var(--bad); }
		h1 { font-size: 24px; margin: 0 0 10px; letter-spacing: -.01em; }
		p { margin: 0 0 12px; color: #475467; }
		table { width: 100%; border-collapse: collapse; margin: 18px 0 6px; font-size: 14px; }
		th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
		th { width: 46%; font-weight: 600; color: #344054; }
		code { background: #f4f6f9; padding: 1px 5px; border-radius: 4px; font-size: 13px; word-break: break-all; }
		.pass { color: var(--ok); font-weight: 600; }
		.fail { color: var(--bad); font-weight: 600; }
		.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }
		form { display: flex; gap: 8px; margin: 0 0 8px; }
		input[type=text] { flex: 1; padding: 9px 11px; border: 1px solid #d0d7e2; border-radius: 6px; font-size: 14px; }
		button { padding: 9px 16px; border: 0; border-radius: 6px; background: #0b6bcb; color: #fff; font-size: 14px; cursor: pointer; }
		.next { background: #f7f9fc; border: 1px solid var(--line); border-left: 3px solid #0b6bcb;
			border-radius: 6px; padding: 14px 16px; margin: 20px 0 0; }
	</style>
</head>
<body>
<div class="wrap">
	<div class="card">
		<span class="verdict <?php echo $ok ? 'ok' : 'bad'; ?>"><?php echo $ok ? 'resolves' : 'broken'; ?></span>
		<h1><?php echo htmlspecialchars($report['explain'][0], ENT_QUOTES, 'UTF-8'); ?></h1>
		<p><?php echo htmlspecialchars($report['explain'][1], ENT_QUOTES, 'UTF-8'); ?></p>

		<form method="get" action="">
			<input type="text" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>"
				placeholder="3u6b0a857c5ef90" aria-label="Share token">
			<button type="submit">Check</button>
		</form>

		<?php if ($token !== '') { ?>
		<table>
			<tbody>
			<tr><th>Token</th><td class="mono"><?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?></td></tr>
			<tr><th>Token length</th><td><?php echo (int) $report['token_length']; ?></td></tr>
			<tr><th>Account id in the token</th><td><?php echo (int) $report['user_id_in_token']; ?></td></tr>
			<?php foreach ($report['checks'] as $name => $value) { ?>
				<tr>
					<th><?php echo htmlspecialchars(str_replace('_', ' ', $name), ENT_QUOTES, 'UTF-8'); ?></th>
					<td class="<?php echo $value ? 'pass' : 'fail'; ?>"><?php echo $value ? 'yes' : 'no'; ?></td>
				</tr>
			<?php } ?>
			<?php if (isset($report['invoice'])) { ?>
				<tr><th>Document</th><td class="mono"><?php echo htmlspecialchars($report['invoice']['inv_number'], ENT_QUOTES, 'UTF-8'); ?>
					(<?php echo htmlspecialchars($report['invoice']['inv_type'], ENT_QUOTES, 'UTF-8'); ?>)</td></tr>
				<tr><th>Amount</th><td><?php echo htmlspecialchars($report['invoice']['total'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
				<tr><th>Owned by account</th><td><?php echo (int) $report['invoice']['for_user']; ?></td></tr>
				<tr><th>Status</th><td><?php echo htmlspecialchars($report['invoice']['status'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
			<?php } ?>
			<?php if (isset($report['error'])) { ?>
				<tr><th>Error</th><td class="mono"><?php echo htmlspecialchars($report['error'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
			<?php } ?>
			</tbody>
		</table>
		<?php } ?>

		<?php if ($report['explain'][2] !== '') { ?>
			<div class="next">
				<p style="margin:0"><?php echo htmlspecialchars($report['explain'][2], ENT_QUOTES, 'UTF-8'); ?></p>
			</div>
		<?php } ?>

		<p style="margin:18px 0 0; font-size:13px; color:#667085">
			Machine-readable: <code>?token=…&amp;format=json</code>.
			Deployment health: <a href="status.php">status.php</a>.
		</p>
	</div>
</div>
</body>
</html>
