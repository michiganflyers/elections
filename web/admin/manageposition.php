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
if (!empty($_POST['rename'])) {
	$description = $_POST['description'];
	$result = $db->query("UPDATE positions SET description='{$db->sanitize($description)}' WHERE position='{$db->sanitize($position_code)}'");
	$error = $result ? "Position renamed" : "Failed to rename position";
} else if (!empty($_POST['remove'])) {
	$candidate_id = (int) $_POST['candidate-id'];
	$result = $db->query("UPDATE candidates SET rtime=CURRENT_TIMESTAMP WHERE skymanager_id=$candidate_id AND position='{$db->sanitize($position_code)}'");
	$error = $result ? "Candidate removed" : "Failed to remove candidate";
} else if (!empty($_POST['restore'])) {
	$candidate_id = (int) $_POST['candidate-id'];
	$result = $db->query("UPDATE candidates SET rtime=NULL WHERE skymanager_id=$candidate_id AND position='{$db->sanitize($position_code)}'");
	$error = $result ? "Candidate restored" : "Failed to restore candidate";
} else if (!empty($_POST['purge'])) {
	$candidate_id = (int) $_POST['candidate-id'];
	$result = $db->query("DELETE FROM candidates WHERE rtime IS NOT NULL AND skymanager_id=$candidate_id AND position='{$db->sanitize($position_code)}'");
	$error = $result ? "Candidate purged" : "Failed to purge candidate";
} else if (!empty($_POST['add-candidate'])) {
	$candidate_id = (int) $_POST['candidate-id'];
	$result = $db->insert('candidates', ['skymanager_id', 'position', 'statement'], [[$candidate_id, $position_code, '']]);
	$error = $result ? "Candidate added" : "Failed to add candidate";
}

$position = db_get_position($position_code);
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
	<form action="manageposition.php?position=<?= urlencode($position_code) ?>" method="POST">
		<div class="form-row">
			<label for="description">Position Name</label>
			<input type="text" id="description" name="description" value="<?= htmlspecialchars($position['label']) ?>" />
		</div>
		<div class="form-row">
			<input class="submit" type="submit" name="rename" value="Rename Position" />
		</div>
	</form>
</div>
<script type="text/javascript">
var voters = <?= json_encode($users, JSON_HEX_TAG); ?>;
</script>
<div class="form-section">
	<h3>Manage Candidates</h3>
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
</div>
<div class="form-section">
	<h3>Add Candidate</h3>
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
</div>
<?php
$footer = new Footer();
$footer->output();
