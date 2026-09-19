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
if (!empty($_POST['config'])) {
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

$rtconfig = db_get_runtime_config();
$header = new Header("Michigan Flyers Election : Admin");
$header->addStyle("/styles/style.css");
$header->addStyle("/styles/admin.css");
$header->addStyle("/styles/vote.css");
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
<?php
$footer = new Footer();
$footer->output();
