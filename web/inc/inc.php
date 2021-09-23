<?php
// Defines
define('BASE', dirname(__DIR__));
define('BASEURL', $_SERVER['SERVER_NAME']);

// Start the session
session_start();

// Database and Authentication
require_once(BASE . '/inc/db.php');
require_once(BASE . '/inc/user.php');

// Templates
require_once(BASE . '/templates/header.php');
require_once(BASE . '/templates/footer.php');
