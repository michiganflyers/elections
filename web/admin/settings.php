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

$result = null;
$role_operation = null;
$role_operation_success = false;

if (!empty($_POST['role-add']) || !empty($_POST['role-update']) || isset($_POST['delete-role'])) {
	$role_user_id = (int) $_POST['role-user-id'];
	if ($role_user_id === $user->getUserId()) {
		$error = "You cannot change your own permissions";
	} else if (isset($_POST['delete-role'])) {
		$result = $db->query("UPDATE members SET permission_level=0 WHERE skymanager_id=$role_user_id");
		$role_operation = 'remove';
		$role_operation_success = $result && $db->getAffectedRows() >= 1;
	} else if (!empty($_POST['role-add']) || !empty($_POST['role-update'])) {
		$permission_level = (int) ($_POST['role-add'] ?? $_POST['permission-level']);
		if (!in_array($permission_level, [1, 2], true)) {
			$error = "Invalid permission level";
		} else {
			$result = $db->query("UPDATE members SET permission_level=$permission_level WHERE skymanager_id=$role_user_id");
			$role_operation = !empty($_POST['role-add']) ? 'add' : 'update';
			$role_permission_level = $permission_level;
			$role_operation_success = $result && $db->getAffectedRows() >= 1;
		}
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

$users = db_get_users();

if ($role_operation) {
	if ($role_operation === 'remove') {
		$success_message = 'Removed permissions';
		$failure_message = 'Failed to remove permissions';
	} else if ($role_operation === 'add') {
		$success_message = $role_permission_level === 2 ? 'Added Administrator' : 'Added Pollworker';
		$failure_message = 'Failed to add permissions';
	} else {
		$success_message = $role_permission_level === 2 ? 'Updated to Administrator' : 'Updated to Pollworker';
		$failure_message = 'Failed to update permissions';
	}
	$error = $role_operation_success ? $success_message : $failure_message;
}

$rtconfig = db_get_runtime_config();
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
			<input type="radio" name="admin-page" value="management" />
			<a class="radio-button-label" href="/admin/management.php">Management</a>
		</label>
		<label class="radio">
			<input type="radio" name="admin-page" value="settings" checked />
			<a class="radio-button-label" href="/admin/settings.php">Settings</a>
		</label>
	</div>
</div>
</form>
<?php if (!empty($error) || !empty($result)): ?>
<div id="vote-result">
	<div id="status" class="<?= $result ? "success" : "failure"; ?>"></div>
	<div id="message" class="<?= $result ? "success" : "failure"; ?>">
		<?= !empty($error) ? $error : ($result ? "This Ballot has been successfully Submitted" :
			"This ballot has already been submitted.") ?>
	</div>
</div>
<?php endif; ?>
<script type="text/javascript">
var voters = <?= json_encode($users, JSON_HEX_TAG); ?>;
</script>
<div class="form-section">
	<h3>Runtime Configuration</h3>
<?php foreach ($rtconfig as $key => $value): ?>
	<div class="form-row runtime-config">
		<form action="settings.php" method="POST">
			<input type=hidden name="rcconfig-key" value="<?= htmlspecialchars($key) ?>" />
			<label for="config-<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($key) ?></label>
			<input type=text id="config-<?= htmlspecialchars($key) ?>" name="rcconfig-value" value="<?= htmlspecialchars($value) ?>" />
			<input class="submit" type="submit" name="config" value="Save" />
		</form>
	</div>
<?php endforeach; ?>
</div>
<div class="form-section">
	<h3>Role Management</h3>
<?php foreach ($users as $user_row): ?>
<?php if ((int) $user_row['permission_level'] < 1) continue; ?>
	<div class="form-row admin-position-management">
		<form action="settings.php" method="POST">
			<input type="hidden" name="role-user-id" value="<?= $user_row['skymanager_id'] ?>" />
			<span class="position-code"><?= htmlspecialchars($user_row['name']) ?><?= $user_row['skymanager_id'] == $user->getUserId() ? ' (You)' : '' ?></span>

			<select name="permission-level" onchange="this.form.submit()" <?= $user_row['skymanager_id'] == $user->getUserId() ? 'disabled' : '' ?>>
				<option value=1 <?= (int) $user_row['permission_level'] === 1 ? 'selected' : '' ?>>Pollworker</option>
				<option value=2 <?= (int) $user_row['permission_level'] === 2 ? 'selected' : '' ?>>Admin</option>
			</select>
			<input type="hidden" name="role-update" value="1" />
			<button class="submit danger delete" type=submit name=delete-role value=delete <?= $user_row['skymanager_id'] == $user->getUserId() ? 'disabled' : '' ?>>X</button>
		</form>
	</div>
<?php endforeach; ?>
</div>
<form action="settings.php" method="POST">
<div class="form-section">
	<h3>Add Election Worker</h3>
	<div class="form-row">
		<input type="text" placeholder="Search for user" id="voter-searchbox" name="voter-searchbox" value="" />
		<div id="voter-results"></div>
		<input type="hidden" name="role-user-id" id="voter-smid" value="0" />
		<input type="hidden" id="voter-input" value="0" />
		<div id="selectedVoter" class="selected candidate voter">
			<span class="placeholder">No User Selected</span>
		</div>
	</div>
	<div class="form-row split-button">
		<button class="submit" type="submit" name="role-add" value="2">Add Admin</button>
		<button class="submit" type="submit" name="role-add" value="1">Add Pollworker</button>
	</div>
</div>
</form>
<?php
$footer = new Footer();
$footer->output();
