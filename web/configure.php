<?php
define('BASE', __DIR__);
define('BASEURL', $_SERVER['SERVER_NAME']);

$config = json_decode(file_get_contents(BASE . "/inc/config.json"));
if (!empty($config)) {
	header('Location: /index.php');
	die();
}

require_once(BASE . '/inc/db.php');
require_once(BASE . '/inc/user.php');

$required = ['db-host', 'db-username', 'db-password', 'db-database', 'flyers-user', 'flyers-password'];

function test_config($params) {
	global $required, $db, $user;

	if (!empty($params) && count($params) != count($required))
		return "All fields are required";

	mysqli_report(MYSQLI_REPORT_OFF);
	$mysql = mysqli_connect($params['db-host'], $params['db-username'], $params['db-password']);
	if (!$mysql)
		return "Unable to connect to the database.";

	mysqli_select_db($mysql, $params['db-database']);
	if (mysqli_error($mysql))
		return "Unable to access database '" . htmlspecialchars($params['db-database']) . "': " . mysqli_error($mysql);

	mysqli_multi_query($mysql, "
CREATE TABLE IF NOT EXISTS `members` (
	`skymanager_id` integer NOT NULL PRIMARY KEY,
	`name` varchar(128) NOT NULL,
	`username` varchar(64) NOT NULL,
	`voting_id` int DEFAULT NULL UNIQUE,
	`email` varchar(128) DEFAULT NULL,
	`pollworker` BOOLEAN NOT NULL DEFAULT false,
	`checkedin` BOOLEAN NOT NULL DEFAULT false);

CREATE TABLE IF NOT EXISTS `proxy` (
	`voting_id` integer NOT NULL,
	`delegate_id` integer NOT NULL,
	PRIMARY KEY (`voting_id`, `delegate_id`));

CREATE TABLE IF NOT EXISTS `positions` (
	`position` varchar(64) NOT NULL PRIMARY KEY,
	`description` varchar(128) NOT NULL UNIQUE,
	`active` BOOLEAN NOT NULL DEFAULT false
);

CREATE TABLE IF NOT EXISTS `votes` (
	`candidate_id` integer NOT NULL,
	`position` varchar(64) NOT NULL,
	`member_id` integer NOT NULL,
	`vote_type` enum('IN PERSON','ONLINE','PROXY IN PERSON','PROXY ONLINE','UNANIMOUS') NOT NULL DEFAULT 'ONLINE',
	`submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`submitter_id` integer NOT NULL,
	PRIMARY KEY (`position`,`member_id`),
	FOREIGN KEY (`position`) REFERENCES `positions` (`position`) ON DELETE CASCADE)
");

	do {
		if (mysqli_error($mysql))
			return "Unable to set up tables: " . mysqli_error($mysql);
	} while (mysqli_next_result($mysql) || mysqli_error($mysql));

	$db = DBHandler::wrap($mysql);
	$success = $user->login($params['flyers-user'], $params['flyers-password']);
	if (!$success)
		return "Login Failed";

	$db->query("UPDATE members SET `pollworker`=TRUE where skymanager_id=" . ((int) $user->getUserId()));
	if ($db->getError())
		return "Failed to update user permissions";

	$conf = json_encode([
		'host' => $params['db-host'],
		'user' => $params['db-username'],
		'pass' => $params['db-password'],
		'db'   => $params['db-database']
	], JSON_PRETTY_PRINT);

	
	if (file_put_contents(BASE . "/inc/config.json", $conf) === false)
		return "Failed to write configuration.";

	return false;
}

$params = [];
foreach ($required as $field) {
	if (array_key_exists($field, $_POST) && !empty($_POST[$field]))
		$params[$field] = $_POST[$field];
}

$error = null;
if (!empty($params))
		$error = test_config($params);

if ($error === false) {
	header('Location: /index.php');
	die();
}

?>
<!doctype html>
<html>
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
	<link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;600;800&display=swap" />
	<link rel="stylesheet" type="text/css" href="/styles/style.css" />
</head>
<body>
	<div id="container">
		<div class="header">
			<h1>Michigan Flyers</h1>
			<h2>Voting System Setup</h2>
		</div>
		<div class="content">
			<div class="page">
				<?php if(!empty($error)) echo "<span class=\"errormessage\">$error</span>"; ?>
				<form action="configure.php" method="POST">
					<div class="form-section">
						<h3>Database Setup</h3>
						<div class="form-row">
							<label for="db-host">Host</label>
							<input type="text" id="db-host" name="db-host" value="localhost" />
						</div>
						<div class="form-row">
							<label for="db-database">Database Name</label>
							<input type="text" id="db-database" name="db-database" />
						</div>
						<div class="form-row">
							<label for="db-username">Username</label>
							<input type="text" id="db-username" name="db-username" />
						</div>
						<div class="form-row">
							<label for="db-password">Password</label>
							<input type="password" id="db-password" name="db-password" />
						</div>
					</div>
					<div class="form-section">
						<h3>Flyers Access Setup</h3>
						<div class="form-row">
							<label for="flyers-user">Voting Administrator</label>
							<input type="text" id="flyers-user" name="flyers-user" />
						</div>
						<div class="form-row">
							<label for="flyers-password">Password</label>
							<input type="password" name="flyers-password" />
						</div>
						<div class="form-row">
							<input type="submit" name="login" value="Setup!" />
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</body>
</html>
