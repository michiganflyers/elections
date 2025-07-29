<?php
include('../inc/inc.php');

if (!$user->loggedin()) {
	header('Location: /login.php');
	die();
}

if ($user->getRole() < 1) {
	header('Location: /index.php');
	die();
}

if (isset($_POST['voter'])) {
	$voter = (int) $_POST['voter'];
	if (!empty($_POST['voter']) && ((int) $_POST['voter']) == $_POST['voter']) {
		$result = $db->query("update members set checkedin=true where voting_id=$voter");
	} else {
		$result = false;
		$error = "The selected voter is not eligible";
	}
}

$header = new Header("Michigan Flyers Election : Poll Worker");
$header->addStyle("/styles/style.css");
$header->addStyle("/styles/admin.css");
$header->addStyle("/styles/vote.css");
$header->addScript("/js/jquery-1.11.3.min.js");
$header->addScript("/js/admin-search.js");
$header->setAttribute('title', 'Michigan Flyers');
$header->setAttribute('tagline', 'Election Poll Worker Tools');
$header->output();

$voters = $db->fetchAssoc('
select
	MIN(skymanager_id) as skymanager_id,
	MIN(members.voting_id) as voting_id,
	MIN(name) as name,
	MIN(username) as username,
	group_concat(proxy.voting_id) as proxies,
	MIN(upstream_proxy.delegate_id) as delegate,
	coalesce(MIN(email), \'\') as gravatar_email
from members
	left join proxy on (members.voting_id=proxy.delegate_id)
	left join proxy as upstream_proxy on (upstream_proxy.voting_id=members.voting_id)
where members.voting_id is not null
group by members.voting_id
UNION
select skymanager_id, voting_id, name, username, NULL as proxies, NULL as delegate, coalesce(email, \'\') as gravatar_email
from members where members.voting_id is null');

get_gravatar_assoc($voters);
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
			<a class="radio-button-label" href="/pollworker/paper.php">Paper Entry</a>
		</label>
		<label class="radio">
			<input type="radio" name="button" value="re" />
			<a class="radio-button-label" href="/pollworker/results.php">Results</a>
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
<?php if (isset($voter)): ?>
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
