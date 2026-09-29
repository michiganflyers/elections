<?php
include('../inc/inc.php');

if (!$user->loggedin()) {
	header('Location: /login.php');
	die();
}

if ($user->getRole() < 2) {
	header('Location: /index.php');
	die();
}

$position_code = $_GET['position'];
if (empty($position_code)) {
	header('Location: /admin/management.php');
	die();
}

$result = null;
if (!empty($_POST['undo-finalize'])) {
	$position_san = $db->sanitize($position_code);
	$removed_proxy_votes = $db->query("DELETE FROM votes WHERE position='$position_san' AND vote_type='DIRECTED_PROXY' AND EXISTS (SELECT 1 FROM positions WHERE position='$position_san' AND finalized IS NOT NULL)");
	$reopened = $removed_proxy_votes ? $db->query("UPDATE positions SET finalized=NULL WHERE position='$position_san' AND finalized IS NOT NULL") : false;
	if (!$removed_proxy_votes || !$reopened || $db->getAffectedRows() !== 1) {
		$error = 'Failed to undo position finalization';
	} else {
		$result = true;
		$error = 'Finalization undone and directed proxy votes removed';
	}
} else if (!empty($_POST['finalize'])) {
	$position_san = $db->sanitize($position_code);
	$inserted = $db->query("
		INSERT INTO votes (candidate_id, position, member_id, vote_type, submitter_id)
		SELECT prevotes.candidate_id, prevotes.position, members.voting_id, 'DIRECTED_PROXY', members.voting_id
		FROM prevotes
		INNER JOIN members ON (members.skymanager_id=prevotes.member_id AND members.voting_id IS NOT NULL)
		INNER JOIN candidates ON (candidates.skymanager_id=prevotes.candidate_id AND candidates.position=prevotes.position AND candidates.rtime IS NULL)
		INNER JOIN positions ON (positions.position=prevotes.position AND positions.rtime IS NULL AND positions.finalized IS NULL AND positions.state IN (0, 3))
		LEFT JOIN votes AS existing_votes ON (existing_votes.position=prevotes.position AND existing_votes.member_id=members.voting_id)
		WHERE prevotes.position='$position_san'
		AND existing_votes.member_id IS NULL
		AND NOT EXISTS (
			SELECT 1
			FROM prevotes AS better
			INNER JOIN candidates AS better_candidate ON (better_candidate.skymanager_id=better.candidate_id AND better_candidate.position=better.position AND better_candidate.rtime IS NULL)
			WHERE better.position=prevotes.position
			AND better.member_id=prevotes.member_id
			AND (better.priority < prevotes.priority OR (better.priority=prevotes.priority AND better.candidate_id < prevotes.candidate_id))
		)");
	$closed = $inserted ? $db->query("UPDATE positions SET state=0, finalized=CURRENT_TIMESTAMP WHERE position='$position_san' AND rtime IS NULL AND finalized IS NULL AND state IN (0, 3)") : false;
	if (!$inserted || !$closed || $db->getAffectedRows() !== 1) {
		$error = 'Position could not be finalized. Ensure it is not removed or already finalized, and is Closed or Voting.';
	} else {
		$result = true;
		$error = 'Position finalized and directed proxy votes applied';
	}
} else if (!empty($_POST['rename'])) {
	$description = $_POST['description'];
	$result = $db->query("UPDATE positions SET description='{$db->sanitize($description)}' WHERE position='{$db->sanitize($position_code)}' AND finalized IS NULL");
	$error = $result ? "Position renamed" : "Failed to rename position";
} else if (!empty($_POST['remove'])) {
	$candidate_id = (int) $_POST['candidate-id'];
	$result = $db->query("UPDATE candidates SET rtime=CURRENT_TIMESTAMP WHERE skymanager_id=$candidate_id AND position='{$db->sanitize($position_code)}' AND EXISTS (SELECT 1 FROM positions WHERE position='{$db->sanitize($position_code)}' AND finalized IS NULL)");
	$error = $result ? "Candidate removed" : "Failed to remove candidate";
} else if (!empty($_POST['restore'])) {
	$candidate_id = (int) $_POST['candidate-id'];
	$result = $db->query("UPDATE candidates SET rtime=NULL WHERE skymanager_id=$candidate_id AND position='{$db->sanitize($position_code)}' AND EXISTS (SELECT 1 FROM positions WHERE position='{$db->sanitize($position_code)}' AND finalized IS NULL)");
	$error = $result ? "Candidate restored" : "Failed to restore candidate";
} else if (!empty($_POST['purge'])) {
	$candidate_id = (int) $_POST['candidate-id'];
	$result = $db->query("DELETE FROM candidates WHERE rtime IS NOT NULL AND skymanager_id=$candidate_id AND position='{$db->sanitize($position_code)}' AND EXISTS (SELECT 1 FROM positions WHERE position='{$db->sanitize($position_code)}' AND finalized IS NULL)");
	$error = $result ? "Candidate purged" : "Failed to purge candidate";
} else if (!empty($_POST['add-candidate'])) {
	$candidate_id = (int) $_POST['candidate-id'];
	$result = $db->query("INSERT INTO candidates (skymanager_id, position, statement) SELECT $candidate_id, '{$db->sanitize($position_code)}', '' WHERE EXISTS (SELECT 1 FROM positions WHERE position='{$db->sanitize($position_code)}' AND finalized IS NULL)");
	$error = $result ? "Candidate added" : "Failed to add candidate";
}

$position = db_get_position($position_code);
$is_finalized = !empty($position['finalized']);
$candidates = db_get_position_candidates($position_code);
$users = db_get_users();

$header = new Header("Michigan Flyers Election : Admin");
$header->addStyle("/styles/style.css");
$header->addStyle("/styles/admin.css");
$header->addStyle("/styles/vote.css");
$header->addScript("/js/jquery-1.11.3.min.js");
$header->addScript("/js/admin-search.js");
$header->setAttribute('title', 'Michigan Flyers');
$header->setAttribute('tagline', 'Election Administration Tools');
$header->output();
?>
<form>
<div class="form-row">
	<div class="selector">
		<label class="radio">
			<input type="radio" name="admin-page" value="setup" />
			<a class="radio-button-label" href="/admin/admin.php">Setup</a>
		</label>
		<label class="radio">
			<input type="radio" name="admin-page" value="management" checked />
			<a class="radio-button-label" href="/admin/management.php">Management</a>
		</label>
		<label class="radio">
			<input type="radio" name="admin-page" value="settings" />
			<a class="radio-button-label" href="/admin/settings.php">Settings</a>
		</label>
	</div>
</div>
</form>
<?php if (!empty($error) || !empty($result)): ?>
<div id="vote-result">
	<div id="status" class="<?= $result ? "success" : "failure"; ?>"></div>
	<div id="message" class="<?= $result ? "success" : "failure"; ?>">
		<?= $error ?>
	</div>
</div>
<?php endif; ?>
<div class="form-section">
	<h3><?= htmlspecialchars($position['label']) ?></h3>
	<?php if ($is_finalized): ?><div class="form-row">Finalized at <?= htmlspecialchars($position['finalized']) ?></div><?php endif; ?>
	<?php if ($is_finalized): ?>
	<div class="form-row">
		<p>Undoing finalization removes this position’s directed proxy votes and leaves the position Closed.</p>
		<form action="manageposition.php?position=<?= urlencode($position_code) ?>" method="POST">
			<button class="submit danger" type="submit" name="undo-finalize" value="undo-finalize" onclick="return confirm('Undo finalization and remove all directed proxy votes for this position?');">Undo Finalize</button>
		</form>
	</div>
	<?php endif; ?>
	<?php if (!$is_finalized): ?>
	<form action="manageposition.php?position=<?= urlencode($position_code) ?>" method="POST">
		<div class="form-row">
			<label for="description">Position Name</label>
			<input type="text" id="description" name="description" value="<?= htmlspecialchars($position['label']) ?>" />
		</div>
		<div class="form-row">
			<input class="submit" type="submit" name="rename" value="Rename Position" />
		</div>
	</form>
	<?php endif; ?>
</div>
<script type="text/javascript">
var voters = <?= json_encode($users, JSON_HEX_TAG); ?>;
</script>
<?php if (!$is_finalized): ?>
<div class="form-section">
	<h3>Finalize Position</h3>
	<div class="form-row">
		<p>Finalizing closes this position, applies eligible directed proxy votes, and prevents further changes.</p>
		<form action="manageposition.php?position=<?= urlencode($position_code) ?>" method="POST">
			<button class="submit danger" type="submit" name="finalize" value="finalize" onclick="return confirm('Finalize this position and apply directed proxy votes? This cannot be undone.');">Finalize Position</button>
		</form>
	</div>
</div>
<?php endif; ?>
<div class="form-section">
	<h3>Manage Candidates</h3>
<?php if ($is_finalized): ?><div class="form-row">This position is finalized; candidate controls are locked.</div><?php else: ?>
<?php foreach ($candidates as $candidate): ?>
	<div class="form-row admin-candidate-management">
		<form action="manageposition.php?position=<?= urlencode($position_code) ?>" method="POST">
			<input type="hidden" name="candidate-id" value="<?= $candidate['skymanager_id'] ?>" />
			<span class="candidate-name"><?= htmlspecialchars($candidate['name']) ?></span>
		<?php if ($candidate['eligible']): ?>
			<button class="submit danger delete" type=submit name=remove value=remove>X</button>
		<?php else: ?>
			<button class="submit restore" type=submit name=restore value=restore>Restore</button>
			<button class="submit danger purge" type=submit name=purge value=purge>Purge</button>
		<?php endif; ?>
		</form>
	</div>
<?php endforeach; ?>
<?php if (empty($candidates)): ?>
	<div class="form-row">No candidates</div>
<?php endif; ?>
<?php endif; ?>
</div>
<div class="form-section">
	<h3>Add Candidate</h3>
	<?php if (!$is_finalized): ?>
	<form action="manageposition.php?position=<?= urlencode($position_code) ?>" method="POST">
		<div class="form-row">
			<input type="text" placeholder="Search for user" id="voter-searchbox" name="voter-searchbox" value="" />
			<div id="voter-results"></div>
			<input type="hidden" name="candidate-id" id="voter-smid" value="0" />
			<input type="hidden" id="voter-input" value="0" />
			<div id="selectedVoter" class="selected candidate voter">
				<span class="placeholder">No User Selected</span>
			</div>
		</div>
		<div class="form-row">
			<input class="submit" type="submit" name="add-candidate" value="Add Candidate" />
		</div>
	</form>
	<?php else: ?><div class="form-row">This position is finalized; candidates cannot be added.</div><?php endif; ?>
</div>
<?php
$footer = new Footer();
$footer->output();
