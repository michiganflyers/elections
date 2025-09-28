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

// States:
// 0 Inactive/Closed
// 1 Nominating
// 2 Early Voting
// 3 Active Voting
$states = ['closed', 'nominating', 'early', 'voting'];

function loadPositions() {
	global $db;
	global $states;

	$_pos = db_get_positions();
	$positions = [];
	foreach ($_pos as $position)
		$positions[$position['code']] = [ "label" => $position['label'], "state" => $states[$position['state']]];

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
} else if (!empty($_POST['remove'])) {
	if (!array_key_exists('ballot', $_POST)) {
		$error = "No position selected";
	} else {
		if (empty($error)) {
			$result = $db->query("UPDATE positions SET rtime=CURRENT_TIMESTAMP WHERE position='{$db->sanitize($_POST['ballot'])}'");
			if ($result === false)
				$error = "Failed to remove position";
			else
				$error = "Removed " . htmlspecialchars($_POST['ballot']);
		}
	}
} else if (!empty($_POST['restore'])) {
	if (!array_key_exists('ballot', $_POST)) {
		$error = "No position selected";
	} else {
		if (empty($error)) {
			$result = $db->query("UPDATE positions SET rtime=NULL WHERE position='{$db->sanitize($_POST['ballot'])}'");
			if ($result === false)
				$error = "Failed to restore position";
			else
				$error = "Restored " . htmlspecialchars($_POST['ballot']);
		}
	}
} else if (!empty($_POST['purge'])) {
	if (!array_key_exists('ballot', $_POST)) {
		$error = "No position selected";
	} else {
		if (empty($error)) {
			$result = $db->query("DELETE FROM positions WHERE rtime IS NOT NULL AND position='{$db->sanitize($_POST['ballot'])}'");
			if ($result === false)
				$error = "Failed to remove position";
			else
				$error = "Purged " . htmlspecialchars($_POST['ballot']) . " and all associated records.";
		}
	}
} else if (!empty($_POST['newState'])) {
	if (!array_key_exists('ballot', $_POST)) {
		$error = "No position selected";
	} else {
		$position = $_POST['ballot'];
		$newState = $_POST['newState'];

		if (!in_array($newState, $states, true) && $newState !== 'none') $error = "Invalid position state '" . htmlspecialchars($newState) . "'";

		if (empty($error)) {
			$position_san = $db->sanitize($position);
			// Active state is a bit different, since only one position can be in active state.
			if ($newState === 'voting') {
				$result = $db->query("UPDATE positions SET state=CASE WHEN position='$position_san' THEN 3 WHEN state=3 THEN 0 ELSE state END WHERE position='$position_san' OR state = 3;");

				if ($result === false)
					$error = "Failed to set active position";
				else
					$error = "Set " . htmlspecialchars($position) . " as voting.";
			} else {
				$index = array_search($newState, $states, true); 
				$result = $db->query("UPDATE positions set state=$index where position='$position_san'");
				if ($result === false)
					$error = "Failed to set position '" . htmlspecialchars($position) . "' to '" . ucfirst($newState) . "'";
				else
					$error = "Set position '" . htmlspecialchars($position) . "' to '" . ucfirst($newState) . "'";
			}
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
} else if (!empty($_POST['config'])) {
	$rcKey = $_POST['rcconfig-key'];
	$rcValue = $_POST['rcconfig-value'];

	if (empty($rcKey)) {
		$error = "Cannot update a blank configuration key";
	} else {
		$result = $db->query("UPDATE runtimeconfig SET value='{$db->sanitize($rcValue)}' WHERE parameter='{$db->sanitize($rcKey)}'");
		if (!$result)
			$error = "Failed to update config for key '" . htmlspecialchars($rcKey) . "'";
		else
			$error = "Successfully updated config for key '" . htmlspecialchars($rcKey) . "'";
	}
}

$positions = loadPositions();
$removedPositions = db_get_removed_positions();
$voters = loadVoters();
$rtconfig = db_get_runtime_config();

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
var voters = <?= json_encode($voters, JSON_HEX_TAG); ?>;
</script>
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
<?php foreach ($positions as $code => $position): ?>
	<div class="form-row admin-position-management">
		<form action="admin.php" method="POST">
			<input type="hidden" name="ballot" value="<?= $code ?>" />
			<span class="position-code"><?= $code ?></span>
			<span class="position-name"><?= $position['label'] ?></span>

			<select name=newState onchange="this.form.submit()">
				<option value=closed     <?= $position['state'] === 'closed' ? 'selected' : '' ?>>Closed</option>
				<option value=nominating <?= $position['state'] === 'nominating' ? 'selected' : '' ?>>Nominating</option>
				<option value=early      <?= $position['state'] === 'early' ? 'selected' : '' ?>>Proxying</option>
				<option value=voting     <?= $position['state'] === 'voting' ? 'selected' : '' ?>>Voting</option>
			</select>
			<button class="submit danger delete" type=submit name=remove value=remove>X</button>
		</form>
	</div>
<?php endforeach; ?>
<?php foreach ($removedPositions as $position): ?>
	<div class="form-row admin-position-management">
		<form action="admin.php" method="POST">
			<input type="hidden" name="ballot" value="<?= $position['code'] ?>" />
			<span class="position-code"><?= $position['code'] ?></span>
			<span class="position-name"><?= $position['label'] ?></span>

			<button class="submit restore" type=submit name=restore value=restore>Restore</button>
			<button class="submit danger purge" type=submit name=purge value=purge>Purge</button>
		</form>
	</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<form action="admin.php" method="POST">
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
</form>
<form action="admin.php" method="POST">
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
<div class="form-section">
	<h3>Runtime Configuration</h3>
	<?php foreach ($rtconfig as $key => $value): ?>
	<div class="form-row runtime-config">
		<form action="admin.php" method="POST">
			<input type=hidden name="rcconfig-key" value="<?= htmlspecialchars($key) ?>" />
			<label for="config-<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($key) ?></label>
			<input type=text id="config-<?= htmlspecialchars($key) ?>" name="rcconfig-value" value="<?= htmlspecialchars($value) ?>" />
			<input class="submit" type="submit" name="config" value="Save" />
		</form>
	</div>
	<?php endforeach; ?>
</div>
</form>
<?php
$footer = new Footer();
$footer->output();
