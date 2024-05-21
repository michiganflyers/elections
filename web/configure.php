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

$fieldNames = ['db-type', 'db-host', 'db-username', 'db-password', 'db-database', 'flyers-user', 'flyers-password'];

function test_config($params) {
	global $fieldNames, $db, $user;


	$config = [
		"type" => $params['db-type']
	];

	if (empty($params['flyers-user']) || empty($params['flyers-password']))
		return "All fields are required";

	switch ($params['db-type']) {
		case "mysql":
			if (!empty($params) && count($params) != count($fieldNames))
				return "All fields are required";
			
			$config += [
				'host' => $params['db-host'],
				'user' => $params['db-username'],
				'pass' => $params['db-password'],
				'db'   => $params['db-database']
			];

			$db = MysqlDb::Connect($config->host, $config->user, $config->pass, $config->db);
			break;
		case "sqlite":
			$db = SqliteDb::Connect();
			break;
		default:
			return "Invalid Database Type";
	}

	$success = $db->exec_multi("
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
--	`vote_type` enum('IN PERSON','ONLINE','PROXY IN PERSON','PROXY ONLINE','UNANIMOUS') NOT NULL DEFAULT 'ONLINE',
	`vote_type` varchar(24) NOT NULL DEFAULT 'ONLINE',
	`submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`submitter_id` integer NOT NULL,
	PRIMARY KEY (`position`,`member_id`),
	FOREIGN KEY (`position`) REFERENCES `positions` (`position`) ON DELETE CASCADE)
");
	if (!$success)
		return "Failed to set up database schema: " . $db->getError();

	$success = $user->login($params['flyers-user'], $params['flyers-password']);
	if (!$success)
		return "Login Failed";

	$db->query("UPDATE members SET `pollworker`=TRUE where skymanager_id=" . ((int) $user->getUserId()));
	if ($err = $db->getError())
		return "Failed to update user permissions: $err";

	$conf = "";
	$conf = json_encode($config, JSON_PRETTY_PRINT);

	
	if (file_put_contents(BASE . "/inc/config/config.json", $conf) === false)
		return "Failed to write configuration.";

	return false;
}

$params = [];
foreach ($fieldNames as $field) {
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
	<style type="text/css">
form input#db-sqlite:checked~.form-row label[for="db-sqlite"] .radio-button-label,
form input#db-mysql:checked~.form-row label[for="db-mysql"] .radio-button-label {
	background-color: #000;
	color: #fff;
	border: 2px solid #fff;
	box-shadow: 0px 0px 0px 2px #000;
}

form .form-row.conditional { display: none; }
form input#db-mysql:checked~.form-row.mysql { display: block; }

	</style>
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
						<input type="radio" id="db-sqlite" name="db-type" value="sqlite" checked />
						<input type="radio" id="db-mysql" name="db-type" value="mysql" />
						<h3>Database Setup</h3>
						<div class="form-row">
							<div class="selector">
								<label class="radio" for="db-sqlite">
									<span class="radio-button-label">SQLite</span>
								</label>
								<label class="radio" for="db-mysql">
									<span class="radio-button-label">MySQL</span>
								</label>
							</div>
						</div>
						<div class="form-row conditional mysql">
							<label for="db-host">Host</label>
							<input type="text" id="db-host" name="db-host" value="localhost" />
						</div>
						<div class="form-row conditional mysql">
							<label for="db-host">Host</label>
							<input type="text" id="db-host" name="db-host" value="localhost" />
						</div>
						<div class="form-row conditional mysql">
							<label for="db-database">Database Name</label>
							<input type="text" id="db-database" name="db-database" />
						</div>
						<div class="form-row conditional mysql">
							<label for="db-username">Username</label>
							<input type="text" id="db-username" name="db-username" />
						</div>
						<div class="form-row conditional mysql">
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
