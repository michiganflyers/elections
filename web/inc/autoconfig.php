<?php
require_once(BASE . '/inc/db.php');

function get_build_timestamp() {
	return (int) @file_get_contents(BASE . "/inc/config/timestamp.txt");
}

function do_migrations($db) {
	$migrations = [
		1 => 'ALTER TABLE candidates ADD COLUMN ctime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN rtime TIMESTAMP DEFAULT NULL'
	];

	$migration = $db->fetchRow('select migration_number from migrations order by migration_number desc limit 1');
	if ($migration === false)
		return "Failed to read database migrations: " . $db->getError();

	$migration_number = (int) $migration['migration_number'];
	foreach ($migrations as $next_migration => $statement) {
		if ($migration_number >= $next_migration)
			continue;

		$result = $db->query($statement);

		if (!$result)
			return "Failed to run database migration $next_migration: " . $db->getError();

		$result = $db->insert('migrations', ['migration_number'], [[$next_migration]], true);
		if (!$result)
			return "Failed to record database migration $next_migration: " . $db->getError();

		$migration_number = $next_migration;
	}

	return true;
}

function do_autoconfigure() {
	$default_pg_connString = getenv('ELECTIONDB_URL');
	$default_mysql_db = getenv('ELECTIONDB_MYSQL');

	$timestamp = get_build_timestamp();

	$config = @json_decode(file_get_contents(BASE . "/inc/config/config.json"), true);
	if (!empty($config)) {
		return $config;
	}

	// If there's a default connection string and a database already exists (with data)
	// Set configuration to that database and send back to homepage.
	// This can happen if we're operating in a hosted environment and a container restarts or spins up
	if (!empty($default_pg_connString)) {
		$db = PgsqlDb::Connect($default_pg_connString);
		if ($db && !empty($db->fetchRow("select skymanager_id from members limit 1"))) {
			if (($migration_error = do_migrations($db)) !== true)
				die($migration_error);

			$config = [
				'type' => 'pgsql',
				'connString' => $default_pg_connString,
				'timestamp' => $timestamp
			];

			$conf = json_encode($config, JSON_PRETTY_PRINT);

			if (file_put_contents(BASE . "/inc/config/config.json", $conf) !== false) {
				return $config;
			}
		}
	}

	if (!empty($default_mysql_db)) {
		$props = json_decode($default_mysql_db);
		$db = MysqlDb::Connect($props->hostname, $props->username, $props->password, $props->database);
		if ($db && !empty($db->fetchRow("select skymanager_id from members limit 1"))) {
			if (($migration_error = do_migrations($db)) !== true)
				die($migration_error);

			$config = [
				'type' => 'mysql',
				'host' => $props->hostname,
				'user' => $props->username,
				'pass' => $props->password,
				'db'   => $props->database,
				'timestamp' => $timestamp
			];
			$conf = json_encode($config, JSON_PRETTY_PRINT);

			if (file_put_contents(BASE . "/inc/config/config.json", $conf) !== false) {
				return $config;
			}
		}
	}

	return false;
}
