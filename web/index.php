<?php
include('inc/inc.php');

if (!$user->loggedin()) {
	header('Location: /login.php');
	die();
}

/*if (!$user->voterId()) {
	header('Location: /login.php?denied');
	die();
}
*/

$active = db_get_active_position();
$nominate = db_get_nominating_positions();
$early = db_get_early_positions();

$tagline = "Online Election Platform";
if (!empty($nominate))
	$tagline = "Candidate Self-Nomination";
elseif (!empty($early))
	$tagline = "Proxy Card";
elseif (!empty($active))
	$tagline = "Online Ballot";

$header = new Header("Michigan Flyers Election");
$header->addStyle("/styles/style.css");
$header->addScript("/js/jquery-1.11.3.min.js");
$header->addScript("/js/search.js");
$header->setAttribute('title', 'Michigan Flyers');
$header->setAttribute('tagline', $tagline);
$header->output();
?>
<?php if (!empty($nominate)): ?>
	<?php include(BASE . '/templates/nominate.php'); ?>
<?php elseif (!empty($early)): ?>
	<?php include(BASE . '/templates/early.php'); ?>
<?php elseif (!$user->voterId()): ?>
	<h3>Your account is not marked as eligible to vote.</h3>
	<p>Please see the voting administrator if this is an error.</p>
<?php elseif (!empty($active)): ?>
	<?php include(BASE . '/templates/vote.php'); ?>
<?php else: ?>
	<h3>There are no active votes. Reload this page once voting starts.</h3>
<?php
endif;
$footer = new Footer();
$footer->output();
