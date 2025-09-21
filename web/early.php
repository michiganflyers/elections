<?php
include('inc/inc.php');

if (!$user->loggedin()) {
	header('Location: /login.php');
	die();
}

$result = null;
if (!empty($_POST['ballot']) && !empty($_POST['action']) && !empty($_POST['proxy-signature'])) {
	$ranks = [];
	$position = $_POST['ballot'];
	for ($i = 1; $i <= 5; $i++) {
		if (!empty($_POST['rank-' . $i])) {
			$ranks[] = [
				(int) $_POST['rank-' . $i],
				$position,
				$user->getUserId(),
				count($ranks) + 1,
			];
		}
	}

	// Update proxy selection
	$proxy_member_id = 0;
	if (!empty($_POST['proxy-member-id']) && $_POST['proxy-election'] !== 'default')
		$proxy_member_id = (int) $_POST['proxy-member-id'];

	$user_id = (int) $user->getUserId();
	$result = $db->query("UPDATE members set proxy_id=$proxy_member_id where skymanager_id=$user_id");
	if ($result)
		$error = 'Set proxy information';
	else
		$error = 'Failed to set proxy member';

	// First, delete prevotes where they exist for this position.
	if ($result && $_POST['action'] === 'withdraw' || $_POST['action'] === 'update') {
		$result = $db->query("DELETE FROM prevotes WHERE position='{$db->sanitize($position)}' AND member_id={$user->getUserId()}");
		if ($result)
			$error = 'Withdrew proxy card';
		else
			$error = 'Failed to withdraw proxy card. Please retry or email the election committee at election2025@michiganflyers.club';
	}

	if ($result && $_POST['action'] !== 'withdraw') {
		$result = $db->insert('prevotes', ['candidate_id', 'position', 'member_id', 'priority'], $ranks);
		if ($result)
			$error = 'Proxy card successfully submitted';
		else
			$error = 'Proxy card submission already exists';
	}

	$positions = db_get_early_positions();
	$candidates = db_get_candidates();
	$requested = reset(array_filter($positions, fn($row) => $row['code'] === $_POST['ballot']));
} else if (!empty($_POST['ballot']) && !empty($_POST['action']) && empty($_POST['proxy-signature'])) {
	$error = 'You must sign the proxy card.';
}

$header = new Header("Michigan Flyers Election");
$header->addStyle("/styles/style.css");
$header->addStyle("/styles/vote.css");
$header->setAttribute('title', 'Michigan Flyers');
$header->setAttribute('tagline', 'Proxy Card');
$header->output();
?>
<div id="vote-result">
	<div id="status" class="<?= $result ? "success" : "failure"; ?>"></div>
	<div id="message" class="<?= $result ? "success" : "failure"; ?>">
		<?= $error ? $error : ($result ? "Success" :
			"Failure") ?>
	</div>
</div>
<a href="/" id="vote-again">Return to Directed Proxy Card</a>
<?php if ($result && $_POST['action'] !== 'withdraw'): ?>
<div id="ballot">
	<div class="ballot-section">
		<h4 class="section-heading">Position</h4>
		<h2 class="ballot-position"><?= $requested['label']; ?></h2>
	</div>
<?php foreach ($ranks as $rank): ?>
	<?php $candidate = reset(array_filter($candidates, fn($row) => $row['skymanager_id'] === $rank[0])); ?>
	<div class="ballot-section">
		<h4 class="section-heading">Preference #<?= $rank[3]; ?></h4>
		<div id="vote-profile" class="candidate">
			<div class="profile-icon">
				<img src="https://www.gravatar.com/avatar/<?= md5($candidate['gravatar_email']); ?>.png?d=mp&s=64" />
			</div>
			<div class="profile">
				<h2 class="profile-name"><?= $candidate['name']; ?></h2>
				<h4 class="profile-id"><?= $candidate['skymanager_id']; ?></h4>
			</div>
		</div>
	</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php

$footer = new Footer();
$footer->output();
