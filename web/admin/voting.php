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

function loadPositions() {
	global $db;

	$_pos = $db->fetchAssoc("select position as code, description as label, active from positions");
	$positions = [];
	foreach ($_pos as $position)
		$positions[$position['code']] = [ "label" => $position['label'], "active" => $position['active']];

	return $positions;
}

function loadVoters() {
	global $db;

	return $db->fetchAssoc('
	select
		MIN(skymanager_id) as `skymanager_id`,
		MIN(members.voting_id) as `voting_id`,
		MIN(name) as `name`,
		MIN(username) as `username`,
		group_concat(proxy.voting_id) as `proxies`,
		MIN(upstream_proxy.delegate_id) as `delegate`,
		md5(coalesce(MIN(email), "")) as `gravatar_hash`
	from members
		left join proxy on (members.voting_id=proxy.delegate_id)
		left join proxy as upstream_proxy on (upstream_proxy.voting_id=members.voting_id)
	where members.voting_id is not null
	group by members.voting_id
	UNION
	select skymanager_id, voting_id, name, username, NULL as `proxies`, NULL as `delegate`, md5(coalesce(email, "")) as `gravatar_hash`
	from members where members.voting_id is null');
}

$result = null;
// Create Position
if (!empty($_POST['create'])) {
	$code = $_POST['add-position'];
	$desc = $_POST['add-description'];

	if (empty($code) || empty($desc)) $error = "Both code and description are required";

	if (empty($error)) {
		$result = $db->query('INSERT INTO positions (position, description) VALUES ("' . $db->sanitize($code) . '", "' . $db->sanitize($desc) . '")');
		if ($result === false)
			$error = "That position already exists.";
		else
			$error = "Created position " . htmlspecialchars($desc);
	}
} else if (!empty($_POST['setActive']) || !empty($_POST['deactivate'])) {
	$positions = loadPositions();

	$position = $_POST['ballot'];
	if (!array_key_exists($position, $positions)) $error = "That position does not exist";
	if (!empty($_POST['deactivate']))
		$position='';
		

	if (empty($error)) {
		$result = $db->query('UPDATE positions set active=(position="' . $db->sanitize($position) . '")');
		if ($result === false)
			$error = "Failed to set active position";
		else if (empty($position))
			$error = "Deactivated voting form";
		else
			$error = "Set " . htmlspecialchars($positions[$_POST['ballot']]['label']) . " as active.";
	}
} else if (!empty($_POST['remove'])) {
	$positions = loadPositions();

	if (!array_key_exists($_POST['ballot'], $positions)) $error = "That position does not exist";

	if (empty($error)) {
		$result = $db->query('DELETE FROM positions WHERE position="' . $db->sanitize($_POST['ballot']) . '"');
		if ($result === false)
			$error = "Failed to remove position";
		else
			$error = "Removed " . htmlspecialchars($positions[$_POST['ballot']]['label']) . " and discarded cast ballots.";
	}
} else if (!empty($_POST['force'])) {
	$voters = loadVoters();
	$values = [];
	$vid = [];
	foreach ($voters as $voter) {
		$vid[$voter['skymanager_id']] = $voter;

		if ($voter['voting_id'])
			array_push($values, $voter['voting_id']);
	}

	if (!array_key_exists($_POST['voter-smid'], $vid))
		$error = "Voter does not exist";

	sort($values);
	$count = count($values);
	$rand = rand(100, 999-$count);
	for ($i = 0; $i < $count && $values[$i] <= $rand; $i++) {
		$rand++;
	}

	if (empty($error)) {
		$result = $db->query('UPDATE members set voting_id=' . ((int) $rand) . ' where skymanager_id=' . ((int) $_POST['voter-smid']));
		if ($result === false)
			$error = "Failed to force check-in. Please try again.";
		else
			$error = "Assigned voting id $rand to {$vid[$_POST['voter-smid']]['name']}";
	}
}

$positions = loadPositions();
$voters = loadVoters();

$header = new Header("Michigan Flyers Election : Admin");
$header->addStyle("/styles/style.css");
$header->addStyle("/styles/admin.css");
$header->addStyle("/styles/vote.css");
$header->addScript("/js/jquery-1.11.3.min.js");
$header->addScript("/js/search.js");
$header->addScript("/js/admin-search.js");
$header->setAttribute('title', 'Michigan Flyers');
$header->setAttribute('tagline', 'Election Administration Tools');
$header->output();

?>
<script type="text/javascript">
var voters = <?= json_encode($voters); ?>;
</script>
<form action="voting.php" method="POST">
<?php if (!empty($error) || !empty($result)): ?>
<div id="vote-result">
	<div id="status" class="<?= $result ? "success" : "failure"; ?>"></div>
	<div id="message" class="<?= $result ? "success" : "failure"; ?>">
		<?= !empty($error) ? $error : ($result ? "This Ballot has been successfully Submitted" :
			"This ballot has already been submitted.") ?>
	</div>
</div>
<?php endif; ?>
<?php if (!empty($positions)): ?>
<div class="form-section">
	<h3>Manage Positions</h3>
	<div class="form-row">
		<div class="selector">
		<?php foreach ($positions as $code => $position): ?>
			<label class="radio">
				<input type="radio" id="vote-<?= $code ?>" name="ballot" value="<?= $code ?>" <?= ($position['active']) ? 'checked' : '' ?> />
				<span class="radio-button-label"><?= $position['label'] ?></span>
			</label>
		<?php endforeach; ?>
		</div>
	</div>
	<div class="form-row split-button">
		<input class="submit danger" type="submit" name="remove" value="Remove Position & Ballots" />
		<input class="submit" type="submit" name="deactivate" value="Deactivate" />
		<input class="submit" type="submit" name="setActive" value="Set Active" />
	</div>
</div>
<?php endif; ?>
<div class="form-section">
	<h3>Add Position</h3>
	<div class="form-row">
		<label for="add-position">Add Position Code</label>
		<input type="text" placeholder="PRES" id="add-position" name="add-position" value="" />
	</div>
	<div class="form-row">
		<label for="add-description">Add Position Description</label>
		<input type="text" placeholder="President" id="add-description" name="add-description" value="" />
	</div>
	<div class="form-row">
		<input class="submit" type="submit" name="create" value="Create Position" />
	</div>
</div>
<div class="form-section">
	<h3>Force Check-In</h3>
	<div class="form-row">
		<input type="text" placeholder="Voter Search" id="voter-searchbox" name="voter-searchbox" value="" />
		<div id="voter-results"></div>
		<input type="hidden" name="voter" id="voter-input" value="0" />
		<input type="hidden" name="voter-smid" id="voter-smid" value="0" />
		<div id="selectedVoter" class="selected candidate voter">
			<span class="placeholder">No Selected Voter</span>
		</div>
	</div>
	<div class="form-row">
		<input class="submit" type="submit" name="force" value="Force Check In" />
	</div>
</div>
</form>
<?php
$footer = new Footer();
$footer->output();
