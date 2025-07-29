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
	return $voters;
}

$result = null;
// Create Position
if (!empty($_POST['create'])) {
	$code = $_POST['add-position'];
	$desc = $_POST['add-description'];

	if (empty($code) || empty($desc)) $error = "Both code and description are required";

	if (empty($error)) {
		$result = $db->insert('positions', ['position', 'description'], [[$code, $desc]]);
		if ($result === false)
			$error = "That position already exists.";
		else
			$error = "Created position " . htmlspecialchars($desc);
	}
} else if (!empty($_POST['newState'])) {
	if (!array_key_exists('ballot', $_POST)) {
		$error = "No position selected";
	} else {
		$positions = loadPositions();

		$position = $_POST['ballot'];

		$states = ['active', 'nominating', 'early'];
		$newState = $_POST['newState'];

		if (!in_array($newState, $states, true) && $newState !== 'none') $error = "Invalid position state '" . htmlspecialchars($newState) . "'";
		if (!array_key_exists($position, $positions)) $error = "That position does not exist";

		if (($index = array_search($newState, $states, true)) !== false) {
			unset($states[$index]);
		} else {
			$newState = false;
		}

		if (empty($error)) {
			$position_san = $db->sanitize($position);
			// Active state is a bit different, since only one position can be in active state.
			if ($newState === 'active') {
				$result = $db->query("UPDATE positions set nominating=(nominating AND NOT position='$position_san'), early=(early AND NOT position='$position_san'), active=(position='$position_san')");
				if ($result === false)
					$error = "Failed to set active position";
				else
					$error = "Set " . htmlspecialchars($positions[$_POST['ballot']]['label']) . " as active.";
			} else {
				$error = "Not Implemented";
				$states_query = array_map(fn($state) => "$state=false", $states);
				if (!empty($newState))
					$states_query[] = "$newState=true";

				$states_query = implode(', ', $states_query);

				$result = $db->query("UPDATE positions set $states_query where position='$position_san'");
				if ($result === false)
					$error = "Failed to set position '" . htmlspecialchars($position) . "' to '" . (empty($newState) ? "Inactive" : ucfirst($newState)) . "'";
				else
					$error = "Set position '" . htmlspecialchars($position) . "' to '" . (empty($newState) ? "Inactive" : ucfirst($newState)) . "'";
			}
		}
	}
} else if (!empty($_POST['remove'])) {
	if (!array_key_exists('ballot', $_POST)) {
		$error = "No position selected";
	} else {
		$positions = loadPositions();

		if (!array_key_exists($_POST['ballot'], $positions)) $error = "That position does not exist";

		if (empty($error)) {
			$result = $db->query("DELETE FROM positions WHERE position='{$db->sanitize($_POST['ballot'])}'");
			if ($result === false)
				$error = "Failed to remove position";
			else
				$error = "Removed " . htmlspecialchars($positions[$_POST['ballot']]['label']) . " and discarded cast ballots.";
		}
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
<form action="admin.php" method="POST">
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
		<input class="submit danger" type=submit name=remove value="Remove Position & Ballots" />
		<button class="submit" type=submit name=newState value=none>Deactivate</button>
		<button class="submit" type=submit name=newState value=nominating>Open Nominations</button>
		<button class="submit" type=submit name=newState value=active>Open Voting</button>
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
