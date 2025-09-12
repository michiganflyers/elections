<?php
include('../inc/inc.php');

if (!$user->loggedin()) {
	header('Location: /login.php');
	die();
}

if ($user->getRole() < 1) {
	header('Location: /index.php');
	die();
}

if (!empty($_POST['voter']) && ((int) $_POST['voter']) == $_POST['voter']) {
	$voter = (int) $_POST['voter'];
	$result = $db->query("update members set checkedin=true where voting_id=$voter");
}

$header = new Header('Michigan Flyers Election : Poll Worker');
$header->addStyle('/styles/style.css');
$header->addStyle('/styles/admin.css');
$header->addStyle('/styles/vote.css');
$header->addScript('/js/jquery-1.11.3.min.js');
$header->addScript('/js/admin-search.js');
$header->setAttribute('title', 'Michigan Flyers');
$header->setAttribute('tagline', 'Election Poll Worker Tools');
$header->output();

$checkedin = $db->fetchAssoc('select name, username, voting_id, NULL as proxy from members where checkedin=true UNION select voter.name, voter.username, voter.voting_id, members.voting_id as proxy from members inner join proxy on (proxy.delegate_id=members.voting_id) left join members as voter on (voter.voting_id=proxy.voting_id) where members.checkedin = true');
$members = $db->fetchRow('select count(*) as count from members where voting_id is not null');
$count = $members['count'];

$results = $db->fetchAssoc('select votes.position, members.name, count(*) as votes from votes left join members on (votes.candidate_id=members.skymanager_id) group by candidate_id, position, members.name');
$positions = db_get_positions();
$candidates = db_get_candidates();

foreach ($positions as &$position) {
	$position['candidates'] = array_filter($candidates, fn($candidate) => $candidate['position'] === $position['code']);
}
unset($position);
?>
<form>
<div class=form-row>
	<div class=selector>
		<label class=radio>
			<input type=radio name=button value=ci />
			<a class=radio-button-label href="/pollworker/checkin.php">Check-In</a>
		</label>
		<label class=radio>
			<input type=radio name=button value=pe />
			<a class=radio-button-label href="/pollworker/paper.php">Paper Entry</a>
		</label>
		<label class=radio>
			<input type=radio name=button value=re checked />
			<a class=radio-button-label href="#">Results</a>
		</label>
	</div>
</div>
</form>

<div class="form-section quorum">
<h3>Quorum</h3>
<div class=form-row>
<span class=label>Total Eligible Members</span>
<span class=count><?= $count ?></span>
</div>
<div class=form-row>
<span class=label>Checked In or Absentee</span>
<span class=count><?= count($checkedin) ?></span>
</div>
<div class=form-row>
<span class=label>Required for Quorum (20%)</span>
<span class=count><?= ceil($count * 0.2) ?></span>
</div>
<div class=form-row>
<?php if (count($checkedin) >= ceil($count * 0.2) && count($checkedin) > 0): ?>
<span class="label success">Quorum Met</span>
<?php else: ?>
<span class="label fail">Quorum Not Met</span>
<?php endif; ?>
</div>
</div>
<?php /*
<table border=1>
<thead><tr><th>Position</th><th>Candidate</th><th>Votes</th></tr></thead>
<tbody>
<?php foreach ($results as $line): ?>
	<tr>
		<td><?= $line['position'] ?></td>
		<td><?= $line['name'] ?></td>
		<td><?= $line['votes'] ?></td>
	</tr>
<?php endforeach; ?>
</tbody>
</table>
*/ ?>

<?php foreach ($positions as $position): ?>
<div class="form-section election-results">
	<h3><?= htmlspecialchars($position['label']); ?> (<?= $position['state_name']; ?>)</h3>
	<?php foreach ($position['candidates'] as $candidate): ?>
	<div class="candidate-result form-row">
		<span class=name><?= htmlspecialchars($candidate['name']); ?></span>
		<span class=votes></span>
	</div>
	<?php endforeach; ?>
	<?php if (empty($position['candidates'])): ?>
	<div class="candidate-result form-row">
		<span class=name>No Candidates</span>
	</div>
	<?php endif; ?>
</div>
<?php endforeach; ?>
<?php
$footer = new Footer();
$footer->output();
