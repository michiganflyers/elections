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

$header = new Header("2021 Michigan Flyers Election");
$header->addStyle("/styles/style.css");
$header->addScript("/js/jquery-1.11.3.min.js");
$header->addScript("/js/search.js");
$header->setAttribute('title', 'Michigan Flyers');
$header->setAttribute('tagline', '2021 Online Ballot');
$header->output();

$candidates = $db->fetchAssoc('select skymanager_id, name, username, md5(coalesce(email, "")) as `gravatar_hash` from members where voting_id is not null');
$votes = $db->fetchAssoc("select position from votes where member_id={$user->voterId()}");

foreach ($votes as &$vote) {
	$vote = $vote['position'];
}
unset($vote);

$president_voted = in_array("PRESIDENT", $votes);
$director_voted = in_array("DIRECTOR", $votes);

$president_disabled = $president_voted;
$director_disabled = $director_voted || !$president_voted;

$president_disabled_reason = $president_voted ? "You have already voted for President." : "";
$director_disabled_reason = $director_disabled ? ($director_voted ? "You have already voted for Director." : "You must vote for President first.") : "";
?>
<script type="text/javascript">
var candidates = <?= json_encode($candidates); ?>;
</script>
<form action="vote.php" method="POST">
<div class="form-row">
	<div class="selector">
		<label class="radio">
			<input type="radio" id="vote-president" name="ballot"
				value="PRESIDENT" <?= $president_disabled ? "disabled" : "checked"; ?> />
			<span class="radio-button-label">President</span>
			<?php if ($president_disabled_reason): ?>
			<div class="hover-tooltip"><?= $president_disabled_reason; ?></div>
			<?php endif; ?>
		</label>
		<label class="radio">
			<input type="radio" id="vote-director" name="ballot"
				value="DIRECTOR" <?= $director_disabled ? "disabled" : ($president_disabled ? "checked" : ""); ?> />
			<span class="radio-button-label">Director-At-Large</span>
			<?php if ($director_disabled_reason): ?>
			<div class="hover-tooltip"><?= $director_disabled_reason; ?></div>
			<?php endif; ?>
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
$footer = new Footer();
$footer->output();
