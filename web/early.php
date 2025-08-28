<?php
include('inc/inc.php');

if (!$user->loggedin()) {
	header('Location: /login.php');
	die();
}

$result = false;
$error = "Not implemented";

$header = new Header("Michigan Flyers Election");
$header->addStyle("/styles/style.css");
$header->addStyle("/styles/vote.css");
$header->setAttribute('title', 'Michigan Flyers');
$header->setAttribute('tagline', 'Online Ballot');
$header->output();
?>
<div id="vote-result">
	<div id="status" class="<?= $result ? "success" : "failure"; ?>"></div>
	<div id="message" class="<?= $result ? "success" : "failure"; ?>">
		<?= $error ? $error : ($result ? "Success" :
			"Failure") ?>
	</div>
</div>
<a href="/" id="vote-again">Return to Early Voting</a>
<?php
$footer = new Footer();
$footer->output();
