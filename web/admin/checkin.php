<?php
include('../inc/inc.php');

if (!$user->loggedin()) {
	header('Location: /login.php');
	die();
}

if ($user->getRole() !== "admin") {
	header('Location: /index.php');
	die();
}

if (!empty($_POST['voter']) && ((int) $_POST['voter']) == $_POST['voter']) {
	$voter = (int) $_POST['voter'];
	$result = $db->query("update members set checkedin=true where voting_id=$voter");
}

$header = new Header("2021 Michigan Flyers Election : Poll Worker");
$header->addStyle("/styles/style.css");
$header->addStyle("/styles/admin.css");
$header->addStyle("/styles/vote.css");
$header->addScript("/js/jquery-1.11.3.min.js");
$header->addScript("/js/admin-search.js");
$header->setAttribute('title', 'Michigan Flyers');
$header->setAttribute('tagline', '2021 Election Administration');
$header->output();

$voters = $db->fetchAssoc('select ANY_VALUE(skymanager_id) as `skymanager_id`, ANY_VALUE(members.voting_id) as `voting_id`, ANY_VALUE(name) as `name`, ANY_VALUE(username) as `username`, group_concat(proxy.voting_id) as `proxies`, ANY_VALUE(upstream_proxy.delegate_id) as `delegate`, md5(coalesce(ANY_VALUE(email), "")) as `gravatar_hash` from members left join proxy on (members.voting_id=proxy.delegate_id) left join proxy as upstream_proxy on (upstream_proxy.voting_id=members.voting_id) where members.voting_id is not null group by members.voting_id UNION select skymanager_id, voting_id, name, username, NULL as `proxies`, NULL as `delegate`, md5(coalesce(email, "")) as `gravatar_hash` from members where members.voting_id is null');
?>
<script type="text/javascript">
var voters = <?= json_encode($voters); ?>;
</script>
<form action="checkin.php" method="POST">
<div class="form-row">
	<div class="selector">
		<label class="radio">
			<input type="radio" name="button" value="ci" checked />
			<a class="radio-button-label" href="#">Check-In</a>
		</label>
		<label class="radio">
			<input type="radio" name="button" value="pe" />
			<a class="radio-button-label" href="/admin/paper.php">Paper Entry</a>
		</label>
		<label class="radio">
			<input type="radio" name="button" value="re" />
			<a class="radio-button-label" href="/admin/results.php">Results</a>
		</label>
	</div>
</div>
<div class="form-row">
	<input type="text" placeholder="Voter Search" id="voter-searchbox" name="voter-searchbox" value="" />
	<div id="voter-results"></div>
	<input type="hidden" name="voter" id="voter-input" value="0" />
	<div id="selectedVoter" class="selected candidate voter">
		<span class="placeholder">No Selected Voter</span>
	</div>
</div>
<div class="form-row">
	<input class="submit" type="submit" name="submit" value="Check In" />
</div>
</form>
<?php if (!empty($voter)): ?>
<div id="vote-result">
	<div id="status" class="<?= $result ? "success" : "failure"; ?>"></div>
	<div id="message" class="<?= $result ? "success" : "failure"; ?>">
		<?= !empty($error) ? $error : ($result ? "The member has been checked in" :
			"The member could not be checked in") ?>
	</div>
</div>
<?php endif; ?>
<?php
$footer = new Footer();
$footer->output();
