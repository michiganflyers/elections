<?php
define('BASE', __DIR__);
define('BASEURL', $_SERVER['SERVER_NAME']);

$default_pg_connString = getenv('ELECTIONDB_URL');
$default_mysql_db = getenv('ELECTIONDB_MYSQL');

$timestamp = (int) @file_get_contents(BASE . "/inc/config/timestamp.txt");

$config = @json_decode(file_get_contents(BASE . "/inc/config/config.json"));
if (!empty($config)) {
	header('Location: /index.php');
	die();
}

require_once(BASE . '/inc/db.php');
require_once(BASE . '/inc/db_func.php');
require_once(BASE . '/inc/user.php');

// If there's a default connection string and a database already exists (with data)
// Set configuration to that database and send back to homepage.
// This can happen if we're operating in a hosted environment and a container restarts or spins up
if (!empty($default_pg_connString)) {
	$db = PgsqlDb::Connect($default_pg_connString);
	if ($db && !empty($db->fetchRow("select skymanager_id from members limit 1"))) {
		$conf = json_encode([
			'type' => 'pgsql',
			'connString' => $default_pg_connString,
			'timestamp' => $timestamp
		], JSON_PRETTY_PRINT);

		if (file_put_contents(BASE . "/inc/config/config.json", $conf) !== false) {
			header('Location: /index.php');
			die();
		}
	}
}

if (!empty($default_mysql_db)) {
	$props = json_decode($default_mysql_db);
	$db = MysqlDb::Connect($props->hostname, $props->username, $props->password, $props->database);
	if ($db && !empty($db->fetchRow("select skymanager_id from members limit 1"))) {
		$conf = json_encode([
			'type' => 'mysql',
			'host' => $props->hostname,
			'user' => $props->username,
			'pass' => $props->password,
			'db'   => $props->database,
			'timestamp' => $timestamp
		], JSON_PRETTY_PRINT);

		if (file_put_contents(BASE . "/inc/config/config.json", $conf) !== false) {
			header('Location: /index.php');
			die();
		}
	}
}

$fieldNames = ['db-type', 'db-host', 'db-username', 'db-password', 'db-database', 'flyers-user', 'flyers-password'];

function test_config($params) {
	global $fieldNames, $db, $user, $default_pg_connString, $default_mysql_db;

	if (empty($params) || empty($params['flyers-user']) || empty($params['flyers-password']))
		return "All fields are required";

	if (empty($params['db-type']) && !empty($default_pg_connString)) {
		$params['db-type'] = 'pgsql';
		$params['db-connString'] = $default_pg_connString;
	} elseif (empty($params['db-type']) && !empty($default_mysql_db)) {
		$props = json_decode($default_mysql_db);
		$params['db-type'] = 'mysql';
		$params['db-host'] = $props->hostname;
		$params['db-username'] = $props->username;
		$params['db-password'] = $props->password;
		$params['db-database'] = $props->database;
	}

	$config = [
		"type" => $params['db-type'],
		'timestamp' => $timestamp
	];

	switch ($params['db-type']) {
		case "mysql":
			if (count($params) != count($fieldNames))
				return "All fields are required";

			$config += [
				'host' => $params['db-host'],
				'user' => $params['db-username'],
				'pass' => $params['db-password'],
				'db'   => $params['db-database']
			];

			$db = MysqlDb::Connect($config['host'], $config['user'], $config['pass'], $config['db']);
			break;
		case "pgsql":
			if (empty($params['db-connString']))
				return "All fields are required";

			$config += [
				'connString' => $params['db-connString']
			];

			$db = PgsqlDb::Connect($config['connString']);
			break;
		case "sqlite":
			$db = SqliteDb::Connect();
			break;
		default:
			return "Invalid Database Type";
	}

	if (!$db)
		return "Failed to connect to the database with the given configuration";

	$success = $db->setup();
	if (!$success)
		return "Failed to perform database-specific initialization: " . $db->getError();

	$success = $db->exec_multi("
CREATE TABLE IF NOT EXISTS members (
	skymanager_id INTEGER NOT NULL PRIMARY KEY,
	name VARCHAR(128) NOT NULL,
	username VARCHAR(64) NOT NULL,
	voting_id INTEGER DEFAULT NULL UNIQUE,
	email VARCHAR(128) DEFAULT NULL,
	permission_level INTEGER NOT NULL DEFAULT 0,
	checkedin BOOLEAN NOT NULL DEFAULT false,
	proxy_id INTEGER NOT NULL DEFAULT 0,
);

CREATE TABLE IF NOT EXISTS proxy (
	voting_id INTEGER NOT NULL,
	delegate_id INTEGER NOT NULL,
	PRIMARY KEY (voting_id, delegate_id)
);

CREATE TABLE IF NOT EXISTS positions (
	position VARCHAR(64) NOT NULL PRIMARY KEY,
	description VARCHAR(128) NOT NULL UNIQUE,
	state SMALLINT NOT NULL DEFAULT 0,
	-- States:
	-- 0 Inactive/Closed
	-- 1 Nominating
	-- 2 Early Voting
	-- 3 Active Voting
	ctime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	rtime TIMESTAMP DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS votes (
	candidate_id INTEGER NOT NULL,
	position VARCHAR(64) NOT NULL,
	member_id INTEGER NOT NULL,
	vote_type VARCHAR(24) NOT NULL DEFAULT 'ONLINE',
	submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	submitter_id INTEGER NOT NULL,
	PRIMARY KEY (position, member_id),
	FOREIGN KEY (position) REFERENCES positions (position) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sessions (
	member_id INTEGER NOT NULL,
	session_token CHAR(40) NOT NULL PRIMARY KEY,
	data TEXT NOT NULL,
	ctime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

	FOREIGN KEY (member_id) REFERENCES members (skymanager_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS prevotes (
	candidate_id INTEGER NOT NULL,
	position VARCHAR(64) NOT NULL,
	member_id INTEGER NOT NULL,
	priority INTEGER NOT NULL,
	submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (member_id, candidate_id, position),

	FOREIGN KEY (member_id) REFERENCES members (skymanager_id) ON DELETE CASCADE,
	FOREIGN KEY (position) REFERENCES positions (position) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS candidates (
	skymanager_id INTEGER NOT NULL,
	position VARCHAR(64) NOT NULL,
	statement TEXT NOT NULL,

	PRIMARY KEY (skymanager_id, position),
	FOREIGN KEY (skymanager_id) REFERENCES members (skymanager_id) ON DELETE CASCADE,
	FOREIGN KEY (position) REFERENCES positions (position) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS runtimeconfig (
	parameter VARCHAR(64) NOT NULL PRIMARY KEY,
	value TEXT NOT NULL DEFAULT ''
)
");

	if (!$success)
		return "Failed to set up database schema: " . $db->getError();

	$success = $db->insert('runtimeconfig', ['parameter', 'value'], [['testAccounts', 'false']], true);
	if (!$success)
		return "Failed to insert initial data: " . $db->getError();

	session_start();
	$success = $user->login($params['flyers-user'], $params['flyers-password']);
	if (!$success)
		return "Login Failed";

	$db->query("UPDATE members SET permission_level=2 where skymanager_id=" . ((int) $user->getUserId()));
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
	header('Location: /admin/admin.php');
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
form input#db-pgsql:checked~.form-row label[for="db-pgsql"] .radio-button-label,
form input#db-mysql:checked~.form-row label[for="db-mysql"] .radio-button-label {
	background-color: #000;
	color: #fff;
	border: 2px solid #fff;
	box-shadow: 0px 0px 0px 2px #000;
}

form .form-row.conditional { display: none; }
form input#db-mysql:checked~.form-row.mysql { display: block; }
form input#db-pgsql:checked~.form-row.pgsql { display: block; }

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
				<?php if (empty($default_pg_connString) && empty($default_mysql_db)): ?>
					<div class="form-section">
						<input type="radio" id="db-sqlite" name="db-type" value="sqlite" checked />
						<input type="radio" id="db-mysql" name="db-type" value="mysql" />
						<input type="radio" id="db-pgsql" name="db-type" value="pgsql" />
						<h3>Database Setup</h3>
						<div class="form-row">
							<div class="selector">
								<label class="radio" for="db-sqlite">
									<span class="radio-button-label">SQLite</span>
								</label>
								<label class="radio" for="db-mysql">
									<span class="radio-button-label">MySQL</span>
								</label>
								<label class="radio" for="db-pgsql">
									<span class="radio-button-label">PostgresQL</span>
								</label>
							</div>
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
						<div class="form-row conditional pgsql">
							<label for="db-connString">Connection String</label>
							<input type="text" id="db-connString" name="db-connString" value="" />
						</div>
					</div>
				<?php endif; ?>
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
