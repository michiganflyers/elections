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

$header = new Header("Michigan Flyers Election");
$header->addStyle("/styles/style.css");
$header->addScript("/js/jquery-1.11.3.min.js");
$header->addScript("/js/search.js");
$header->setAttribute('title', 'Michigan Flyers');
$header->setAttribute('tagline', 'Online Ballot');
$header->output();

$candidates = $db->fetchAssoc('select skymanager_id, name, username, coalesce(email, \'\') as gravatar_email from members where voting_id is not null');
$votes = $db->fetchAssoc("select position from votes where member_id={$user->voterId()}");
$position = $db->fetchRow("select position as code, description as label from positions where active != FALSE limit 1");

get_gravatar_assoc($candidates);

foreach ($votes as &$vote) {
	$vote = $vote['position'];
}
unset($vote);
?>

<?php if (empty($position)): ?>
	<h3>There are no active votes. Reload this page once voting starts.</h3>
<?php else: ?>
<script type="text/javascript">
var candidates = <?= json_encode($candidates); ?>;
</script>
<form action="vote.php" method="POST">
<div class="form-row">
	<div class="selector">
		<label class="radio">
			<input type="radio" id="vote-<?= $position['code']; ?>" name="ballot"
				value="<?= $position['code']; ?>" checked />
			<span class="radio-button-label"><?= $position['label'] ?></span>
		</label>
	</div>
</div>
<div class="form-row">
	<input type="text" placeholder="Candidate Search" id="searchbox" name="searchbox" value="" />
	<div id="results"></div>
	<input type="hidden" name="candidate" id="candidate-input" value="0" />
	<div id="selectedCandidate" class="selected candidate">
		<span class="placeholder">No Candidate Selected</span>
	</div>
</div>
<div class="form-row">
	<input class="submit" type="submit" name="submit" value="Submit Ballot" />
</div>
</form>
<?php
endif;
$footer = new Footer();
$footer->output();
