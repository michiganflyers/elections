<?php
include('inc/inc.php');

if (!$user->loggedin()) {
	header('Location: /login.php');
	die();
}

if (!$user->voterId()) {
	header('Location: /login.php?denied');
	die();
}

$active_position = db_get_active_position();
$candidate_selected = (int) $_POST['candidate'];
$ballot = $_POST['ballot'];

if ($candidate_selected != $_POST['candidate']) $error = "An eccor occurred while processing your ballot. Please retry.";
if (empty($active_position) || $ballot !== $active_position['code']) $error = "An eccor occurred while processing your ballot. Please retry.";

if (!$error) {
	//$result = $db->query("INSERT INTO votes (candidate_id, position, member_id) values ($candidate_selected, \"$ballot\", {$user->voterId()})");
	$result = false;
	try {
		$result = $db->query("INSERT INTO votes (candidate_id, position, member_id, vote_type, submitter_id) SELECT $candidate_selected, '$ballot', {$user->voterId()}, 'ONLINE', {$user->voterId()} UNION SELECT $candidate_selected, '$ballot', voting_id, 'PROXY ONLINE', delegate_id from proxy where delegate_id={$user->voterId()}");
	} catch (Throwable $ignore) {}
	$candidate = $db->fetchRow("select skymanager_id, name, username, coalesce(email, '') as gravatar_email from members where skymanager_id=$candidate_selected");
	if ($result) {
		$to = 'mf2022elec@gmail.com';
		$from = 'noreply@tyzoid.com';
		$subject = "Ballot Submitted ({$user->voterId()} -> {$candidate['skymanager_id']})";
		$headers =
			"From: {$from}\r\n" .
			"Message-ID: 2022election-voter-{$user->voterId()}-{$ballot}-" . mt_rand() . "@tyzoid.com\r\n";

		$body = "Position: " . ucwords(strtolower($ballot)) . "\r\n" .
			"Candidate: {$candidate['name']} (ID #{$candidate['skymanager_id']})\r\n" .
			"Voter #{$user->voterId()}\r\n";

		$proxy_votes = $db->fetchAssoc("SELECT member_id, submitter_id from votes where submitter_id={$user->voterId()} and position='$ballot'");
		$num_affected_rows = count($proxy_votes);
		if ($num_affected_rows > 1) {
			$proxy_str = "";
			foreach ($proxy_votes as $proxy_vote) {
				if ($proxy_vote['member_id'] === $proxy_vote['submitter_id'])
					continue;

				$proxy_str .= "#{$proxy_vote['member_id']} ";
			}

			$body .= "Proxies: $proxy_str\r\n";
		}

		if (!mail($to, $subject, $body, $headers, "-f$from"))
			$error = "Ballot audit record failed to create";
	}
}

$header = new Header("Michigan Flyers Election");
$header->addStyle("/styles/style.css");
$header->addStyle("/styles/vote.css");
$header->addScript("/js/jquery-1.11.3.min.js");
$header->addScript("/js/search.js");
$header->setAttribute('title', 'Michigan Flyers');
$header->setAttribute('tagline', 'Online Ballot');
$header->output();
?>
<div id="vote-result">
	<div id="status" class="<?= $result ? "success" : "failure"; ?>"></div>
	<div id="message" class="<?= $result ? "success" : "failure"; ?>">
		<?= $error ? $error : ($result ? "Your Ballot has been successfully Submitted" :
			"Your ballot has already been submitted.") ?>
	</div>
</div>
<a href="/" id="vote-again">Return to voting</a>
<?php if ($result): ?>
<div id="ballot">
	<div class="ballot-section">
		<h4 class="section-heading">Position</h4>
		<h2 class="ballot-position"><?= $active_position['label']; ?></h2>
	</div>
	<div class="ballot-section">
		<h4 class="section-heading">Candidate</h4>
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
	<div class="ballot-section">
		<h4 class="section-heading">Voter ID</h4>
		<h4 id="ballot-voter-id">#<?= $user->voterId(); ?></h4>
	</div>
<?php if ($proxy_str): ?>
	<div class="ballot-section">
		<h4 class="section-heading">Proxy Votes</h4>
		<h4 id="ballot-voter-id"><?= $proxy_str; ?></h4>
	</div>
<?php endif; ?>
</div>
<?php endif; ?>
<?php
$footer = new Footer();
$footer->output();
