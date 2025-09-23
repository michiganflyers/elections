<?php
// Defines
define('BASE', dirname(__DIR__));
define('BASEURL', $_SERVER['SERVER_NAME']);

$config = @json_decode(file_get_contents(BASE . "/inc/config/config.json"));
if (empty($config)) {
	header('Location: /configure.php');
	die();
}

// Database and Authentication
require_once(BASE . '/inc/db.php');

switch ($config->type) {
	case "mysql":
		$db = MysqlDb::Connect($config->host, $config->user, $config->pass, $config->db);
		break;
	case "pgsql":
		$db = PgsqlDb::Connect($config->connString);
		break;
	case "sqlite":
	default:
		$db = SqliteDb::Connect();
}

require_once(BASE . '/inc/db_func.php');
require_once(BASE . '/inc/user.php');
require_once(BASE . '/inc/misc.php');

// Templates
require_once(BASE . '/templates/header.php');
require_once(BASE . '/templates/footer.php');
