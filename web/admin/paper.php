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

$_pos = $db->fetchAssoc("select position as code, description as label from positions");
$positions = [];
foreach ($_pos as $position)
	$positions[$position['code']] = $position['label'];

$result = null;
if (!empty($_POST['ballot']) && !empty($_POST['candidate'])) {
	$candidate_selected = (int) $_POST['candidate'];
	$voter_selected = (int) $_POST['voter'];
	$ballot = $_POST['ballot'];

	if ($candidate_selected != $_POST['candidate']) $error = "An eccor occurred while processing your ballot. Please retry.";
	if ($voter_selected != $_POST['voter']) $error = "An eccor occurred while processing your ballot. Please retry.";
	if (!array_key_exists($ballot, $positions)) $error = "An eccor occurred while processing your ballot. Please retry.";

	if (empty($error)) {
		$result = $db->query("INSERT INTO votes (candidate_id, position, member_id, vote_type, submitter_id) SELECT $candidate_selected, \"$ballot\", $voter_selected, 'IN PERSON', $voter_selected UNION SELECT $candidate_selected, \"$ballot\", voting_id, 'PROXY IN PERSON', delegate_id from proxy where delegate_id=$voter_selected");
		$candidate = $db->fetchRow('select skymanager_id, name, username, md5(coalesce(email, "")) as `gravatar_hash` from members where skymanager_id=' . $candidate_selected);

		if ($result) {
			$proxy_votes = $db->fetchAssoc("SELECT member_id, submitter_id from votes where submitter_id=$voter_selected and position=\"$ballot\"");
			$num_affected_rows = count($proxy_votes);
			if ($num_affected_rows > 1) {
				$proxy_str = "";
				foreach ($proxy_votes as $proxy_vote) {
					if ($proxy_vote['member_id'] === $proxy_vote['submitter_id'])
						continue;

					$proxy_str .= "#{$proxy_vote['member_id']} ";
				}
			}
		}
	}
}

$header = new Header("Michigan Flyers Election : Poll Worker");
$header->addStyle("/styles/style.css");
$header->addStyle("/styles/admin.css");
$header->addStyle("/styles/vote.css");
$header->addScript("/js/jquery-1.11.3.min.js");
$header->addScript("/js/search.js");
$header->addScript("/js/admin-search.js");
$header->setAttribute('title', 'Michigan Flyers');
$header->setAttribute('tagline', 'Election Poll Worker Tools');
$header->output();

$candidates = $db->fetchAssoc('select skymanager_id, name, username, md5(coalesce(email, "")) as `gravatar_hash` from members where voting_id is not null');
$voters = $db->fetchAssoc('select MIN(skymanager_id) as `skymanager_id`, MIN(members.voting_id) as `voting_id`, MIN(name) as `name`, MIN(username) as `username`, group_concat(proxy.voting_id) as `proxies`, MIN(upstream_proxy.delegate_id) as `delegate`, md5(coalesce(MIN(email), "")) as `gravatar_hash` from members left join proxy on (members.voting_id=proxy.delegate_id) left join proxy as upstream_proxy on (upstream_proxy.voting_id=members.voting_id) where members.voting_id is not null group by members.voting_id UNION select skymanager_id, voting_id, name, username, NULL as `proxies`, NULL as `delegate`, md5(coalesce(email, "")) as `gravatar_hash` from members where members.voting_id is null');
?>
<script type="text/javascript">
var voters = <?= json_encode($voters); ?>;
var candidates = <?= json_encode($candidates); ?>;
</script>
<form action="paper.php" method="POST">
<div class="form-row">
	<div class="selector">
		<label class="radio">
			<input type="radio" name="button" value="ci" />
			<a class="radio-button-label" href="/admin/checkin.php">Check-In</a>
		</label>
		<label class="radio">
			<input type="radio" name="button" value="pe" checked />
			<a class="radio-button-label" href="#">Paper Entry</a>
		</label>
		<label class="radio">
			<input type="radio" name="button" value="re" />
			<a class="radio-button-label" href="/admin/results.php">Results</a>
		</label>
	</div>
</div>
<?php if (empty($positions)): ?>
<h3>No positions are open for voting.</h3>
<a href="/admin/voting.php">Create a position</a>
<?php else: ?>
<div class="form-row">
	<div class="selector">
	<?php foreach ($positions as $code => $label): ?>
		<label class="radio">
			<input type="radio" id="vote-<?= $code ?>" name="ballot" value="<?= $code ?>" checked />
			<span class="radio-button-label"><?= $label ?></span>
		</label>
	<?php endforeach; ?>
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
<?php if (!empty($_POST['ballot'])): ?>
<div id="vote-result">
	<div id="status" class="<?= $result ? "success" : "failure"; ?>"></div>
	<div id="message" class="<?= $result ? "success" : "failure"; ?>">
		<?= !empty($error) ? $error : ($result ? "This Ballot has been successfully Submitted" :
			"This ballot has already been submitted.") ?>
	</div>
</div>
<?php endif; ?>
<?php if ($result): ?>
<div id="ballot">
	<div class="ballot-section">
		<h4 class="section-heading">Position</h4>
		<h2 class="ballot-position"><?= $positions[$ballot]; ?></h2>
	</div>
	<div class="ballot-section">
		<h4 class="section-heading">Candidate</h4>
		<div id="vote-profile" class="candidate">
			<div class="profile-icon">
				<img src="https://www.gravatar.com/avatar/<?= $candidate['gravatar_hash']; ?>.png?d=mp&s=64" />
			</div>
			<div class="profile">
				<h2 class="profile-name"><?= $candidate['name']; ?></h2>
				<h4 class="profile-id"><?= $candidate['skymanager_id']; ?></h4>
			</div>
		</div>
	</div>
	<div class="ballot-section">
		<h4 class="section-heading">Voter ID</h4>
		<h4 id="ballot-voter-id">#<?= $voter_selected; ?></h4>
	</div>
<?php if ($proxy_str): ?>
	<div class="ballot-section">
		<h4 class="section-heading">Proxy Votes</h4>
		<h4 id="ballot-voter-id"><?= $proxy_str; ?></h4>
	</div>
<?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
<?php
$footer = new Footer();
$footer->output();
