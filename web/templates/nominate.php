<?php
$positions = db_get_nominating_positions();
$candidates = db_get_candidates();
if ($candidates)
	$candidates = array_filter($candidates, fn($row) => $row['skymanager_id'] === $user->getUserId());
get_gravatar_assoc($candidates);
$positions[0]['first'] = true;
?>
<script type="text/javascript">
var candidates = <?= json_encode($candidates); ?>;
var positions = <?= json_encode($positions); ?>;
</script>
<form action="nominate.php" method="POST">
<?php if (!empty($positions)): ?>
<div class="form-section">
	<h3>Self-Nominate For:</h3>
	<div class="form-row">
		<div class="selector">
		<?php foreach ($positions as $position): ?>
			<label class="radio">
				<input type="radio" id="nominate-<?= $position['code'] ?>" name="nominate" value="<?= $position['code'] ?>" <?= !empty($position['first']) ? 'checked' : '' ?> />
				<span class="radio-button-label"><?= $position['label'] ?></span>
			</label>
		<?php endforeach; ?>
		</div>
	</div>
	<div class="form-row">
		<label for=statement>Motivation for Serving/Personal Statement</label>
		<textarea id=statement name=statement data-position="<?= $positions[0]['code']; ?>" maxlength=1000></textarea>
	</div>
	<div class="form-row split-button">
		<button class="defaultHide submit danger" id=withdraw type=submit name=action value=withdraw>Withdraw Nomination</button>
		<button class="defaultHide submit"        id=update   type=submit name=action value=update>Update Bio</button>
		<button class="defaultHide submit"        id=nominate type=submit name=action value=nominate>Nominate</button>
	</div>
</div>
<script type="text/javascript">
function processCheckBoxes(evt) {
	if (!this.checked)
		return;

	var position = this.value;
	var existing_nomination = candidates.filter(candidate => candidate.position === position);

	var text = "";
	if (existing_nomination.length && existing_nomination[0].statement)
		text = existing_nomination[0].statement;

	var statementTextBox = document.getElementById('statement');
	var oldCode = statementTextBox.dataset.position;
	var from_nomination = candidates.filter(candidate => candidate.position === oldCode);
	var mismatch = ((from_nomination.length && from_nomination[0].statement) || "") != statementTextBox.value;
	var confirmText = "Unsaved: Are you sure you want to discard the personal statement?";
	if (!mismatch || position === oldCode || (mismatch && window.confirm(confirmText))) {
		// Switch textarea contents
		statementTextBox.value = text;
		statementTextBox.dataset.position = position;

		var withdrawButton = document.getElementById('withdraw');
		var updateButton = document.getElementById('update');
		var nominateButton = document.getElementById('nominate');

		// Update buttons
		if (text) withdrawButton.style.display = 'block';
		else      withdrawButton.style.display = 'none';

		if (text) updateButton.style.display = 'block';
		else      updateButton.style.display = 'none';

		if (!text) nominateButton.style.display = 'block';
		else       nominateButton.style.display = 'none';
	} else {
		// Undo switching position
		this.checked = false;
		document.getElementById("nominate-" + oldCode).checked = true;
	}
}

document.querySelectorAll("input[type=radio][name=nominate]").forEach(elem => elem.addEventListener('change', processCheckBoxes));

var defaultButton = document.getElementById('nominate-<?= $positions[0]['code'] ?>');
processCheckBoxes.call(defaultButton);
</script>
<?php endif; ?>
