<?php
include('inc/inc.php');

if (!$user->loggedin()) {
	header('Location: /login.php');
	die();
}

$positions = db_get_nominating_positions();
$requested = array_filter($positions, fn($row) => $row['code'] === $_POST['nominate']);

$incomplete_form = empty($_POST['nominate']) || empty($_POST['action']) || (empty($_POST['statement']) && $_POST['action'] !== 'withdraw');
$not_nominating = empty($requested) || count($requested) == 0;
if ($incomplete_form || $not_nominating) {
	header('Location: /index.php');
	die();
}

$requested = reset($requested);
if (!empty($_POST['statement']))
	$_POST['statement'] = str_replace("\r", "", $_POST['statement']);

if ($_POST['action'] === 'withdraw') {
	$result = $db->query("DELETE FROM candidates WHERE skymanager_id=" . (int) $user->getUserId() . " AND position='{$db->sanitize($_POST['nominate'])}'");
	if (!$result)
		$error = "Nomination for " . htmlspecialchars($requested['label']) . " failed to withdraw (already withdrawn)";
	else
		$error = "Nomination for " . htmlspecialchars($requested['label']) . " is successfully withdrawn";
}

if ($_POST['action'] === 'nominate') {
	//$result = $db->query(DELETE FROM candidates WHERE skymanager_id=" . (int) $user->getUserId() . " AND position='{$db->sanitize($_POST['nominate'])}'");
	$result = $db->insert('candidates', ['skymanager_id', 'position', 'statement'], [[$user->getUserId(), $_POST['nominate'], $_POST['statement']]]);
	if (!$result)
		$error = "Nomination for " . htmlspecialchars($requested['label']) . " failed (already exists)";
	else
		$error = "Nomination for " . htmlspecialchars($requested['label']) . " is successfully added";
}

if ($_POST['action'] === 'update') {
	$result = $db->query("UPDATE candidates SET statement='{$db->sanitize($_POST['statement'])}' WHERE skymanager_id=" . (int) $user->getUserId() . " AND position='{$db->sanitize($_POST['nominate'])}'");
	if (!$result)
		$error = "Nomination for " . htmlspecialchars($requested['label']) . " failed (doesn't exist)";
	else
		$error = "Nomination for " . htmlspecialchars($requested['label']) . " is successfully updated";
}

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
<a href="/" id="vote-again">Return to Nominating</a>
<?php
$footer = new Footer();
$footer->output();
