<?php
$positions = db_get_early_positions();
$candidates = db_get_candidates();
$earlyvotes = db_get_current_user_early_votes();
//if (!$earlyvotes)
//	$earlyvotes = [];

get_gravatar_assoc($candidates);

// Shuffle candidates using userid as seed
mt_srand($user->getUserId());
shuffle($candidates);
?>
<script type="text/javascript">
var candidates = <?= json_encode($candidates, JSON_HEX_TAG); ?>;
var positions = <?= json_encode($positions, JSON_HEX_TAG); ?>;
var votes = <?= json_encode($earlyvotes, JSON_HEX_TAG); ?>;
</script>
<form action="early.php" method="POST">
<?php if (!empty($positions)): ?>
<div class="form-section">
	<h3>Select Ballot:</h3>
	<div class="form-row">
		<div class="selector">
		<?php foreach ($positions as $index => $position): ?>
			<label class="radio">
				<input type="radio" id="ballot-<?= $position['code'] ?>" name="ballot" value="<?= $position['code'] ?>" <?= ($index === 0) ? 'checked' : '' ?> />
				<span class="radio-button-label"><?= $position['label'] ?></span>
			</label>
		<?php endforeach; ?>
		</div>
	</div>
</div>
<div class="form-section">
	<h3>Personal Statements</h3>
	<section id=statements></section>
</div>
<div class="form-section">
	<h3>Preferential Ranking</h3>
	<div class="form-row">
		Rank in order of preference.
		<em>Position 1 gets your vote if eligible. If not, then position 2, etc.</em>
	</div>
	<section id=ranking></section>
	<div class="form-row split-button">
		<button class="defaultHide submit danger" id=withdraw type=submit name=action value=withdraw>Withdraw Ballot</button>
		<button class="defaultHide submit"        id=update   type=submit name=action value=update>Update Ballot</button>
		<button class="submit"                    id=vote type=submit name=action value=vote>Submit Ballot</button>
	</div>
</div>
<script type="text/javascript">
function selectBallot(evt) {
	if (!this.checked)
		return;

	var position = this.value;
	var availableCandidates = candidates.filter(candidate => candidate.position === position);
	var currentVotes = votes.filter(vote => vote.position === position).reduce((acc, cur) => Object.assign(acc, {[cur.candidate_id]: cur.priority}), {});

	var statementSection = document.getElementById('statements');
	var statementList = document.createDocumentFragment();

	var rankingSection = document.getElementById('ranking');
	var rankingList = document.createDocumentFragment();

	var rankingCount = Math.min(availableCandidates.length, 5);
	var rankHeader = document.createElement('div');
	rankHeader.classList.add('form-row', 'ranking', 'rank-header');
	var rankHeaderTitle = document.createElement('div');
	rankHeaderTitle.classList.add('name-column');
	rankHeaderTitle.append('Name');

	rankHeader.appendChild(rankHeaderTitle);
	for (var i = 1; i <= rankingCount; i++) {
		var rankNum = document.createElement('div');
		rankNum.append(i);
		rankHeader.appendChild(rankNum);
	}

	rankingList.appendChild(rankHeader);

	availableCandidates.forEach(function(candidate) {
		var profileRow = document.createElement('div');
		profileRow.classList.add('form-row');

		// Build Profile
		var profile = document.createElement('div');
		profile.classList.add('candidate');

		var profileimgsect = document.createElement('div');
		profileimgsect.classList.add('profile-icon');

		var profileimg = document.createElement('img');
		profileimg.src = 'https://www.gravatar.com/avatar/' + candidate.gravatar_hash + '.png?d=mp&s=64';
		profileimgsect.appendChild(profileimg);

		var profiletext = document.createElement('div');
		profiletext.classList.add('profile');

		var profilename = document.createElement('h2');
		profilename.classList.add('profile-name');
		profilename.textContent = candidate.name;

		var profileid = document.createElement('h4');
		profileid.classList.add('profile-id');
		profileid.textContent = candidate.skymanager_id;

		profiletext.appendChild(profilename);
		profiletext.appendChild(profileid);

		profile.appendChild(profileimgsect);
		profile.appendChild(profiletext);

		// Build Statement
		var statement = document.createElement('div');
		statement.classList.add('statement');
		statement.append(candidate.statement);

		profileRow.appendChild(profile);
		profileRow.appendChild(statement);

		statementList.appendChild(profileRow);

		// Build Ranking List
		var rankRow = document.createElement('div');
		rankRow.classList.add('form-row', 'ranking');

		var rankName = document.createElement('div');
		rankName.classList.add('name');
		rankName.append(candidate.name);

		rankRow.appendChild(rankName);
		
		for (var i = 1; i <= rankingCount; i++) {
			var checkbox = document.createElement('input');
			checkbox.type = 'checkbox';
			checkbox.name = 'rank-' + i;
			checkbox.value = candidate.skymanager_id;
			if (currentVotes[candidate.skymanager_id] && currentVotes[candidate.skymanager_id] === i)
				checkbox.checked = true;

			checkbox.addEventListener('change', function() {
				console.log(this);
				if (!this.checked)
					return;

				var boxes = document.querySelectorAll('input[name=' + this.name + ']');
				boxes.forEach(elem => (elem === this) || (elem.checked = false));
				this.parentElement.querySelectorAll('input').forEach(elem => (elem === this) || (elem.checked = false));
			});

			rankRow.appendChild(checkbox);
		}

		rankingList.appendChild(rankRow);
	});

	var updateButton = document.getElementById('update');
	var submitButton = document.getElementById('vote');
	var withdrawButton = document.getElementById('withdraw');

	statementSection.replaceChildren(statementList);
	rankingSection.replaceChildren(rankingList);

	if (Object.keys(currentVotes).length > 0) {
		updateButton.style.display = 'block';
		withdrawButton.style.display = 'block';
		submitButton.style.display = 'none';
	} else {
		updateButton.style.display = 'none';
		withdrawButton.style.display = 'none';
		submitButton.style.display = 'block';
	}
}

document.querySelectorAll("input[type=radio][name=ballot]").forEach(elem => elem.addEventListener('change', selectBallot));

var defaultButton = document.getElementById('ballot-<?= $positions[0]['code'] ?>');
selectBallot.call(defaultButton);
</script>
<?php endif; ?>
