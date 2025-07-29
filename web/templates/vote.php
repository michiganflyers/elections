<?php
$position = db_get_active_position();
$candidates = db_get_candidates();
get_gravatar_assoc($candidates);
?>
<script type="text/javascript">
var candidates = <?= json_encode($candidates); ?>;
</script>
<form action="vote.php" method="POST">
<div class="form-row">
	<div class="selector">
		<label class="radio">
			<input type="radio" id="vote-<?= $position['code']; ?>" name="ballot"
				value="<?= $position['code']; ?>" checked />
			<span class="radio-button-label"><?= $position['label'] ?></span>
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
