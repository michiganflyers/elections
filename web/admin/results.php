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

if (!empty($_POST['voter']) && ((int) $_POST['voter']) == $_POST['voter']) {
	$voter = (int) $_POST['voter'];
	$result = $db->query("update members set checkedin=true where voting_id=$voter");
}

$header = new Header("2022 Michigan Flyers Election : Poll Worker");
$header->addStyle("/styles/style.css");
$header->addStyle("/styles/admin.css");
$header->addStyle("/styles/vote.css");
$header->addScript("/js/jquery-1.11.3.min.js");
$header->addScript("/js/admin-search.js");
$header->setAttribute('title', 'Michigan Flyers');
$header->setAttribute('tagline', '2022 Election Administration');
$header->output();

$checkedin = $db->fetchAssoc('select name, username, voting_id, NULL as `proxy` from members where checkedin=true UNION select voter.name, voter.username, voter.voting_id, members.voting_id as `proxy` from members inner join proxy on (proxy.delegate_id=members.voting_id) left join members as `voter` on (voter.voting_id=proxy.voting_id) where members.checkedin = true');
$members = $db->fetchRow('select count(*) as `count` from members where voting_id is not null');
$count = $members['count'];

$results = $db->fetchAssoc('select votes.position, members.name, count(*) as `votes` from votes left join members on (votes.candidate_id=members.skymanager_id) group by candidate_id, position');
?>
<form>
<div class="form-row">
	<div class="selector">
		<label class="radio">
			<input type="radio" name="button" value="ci" />
			<a class="radio-button-label" href="/admin/checkin.php">Check-In</a>
		</label>
		<label class="radio">
			<input type="radio" name="button" value="pe" />
			<a class="radio-button-label" href="/admin/paper.php">Paper Entry</a>
		</label>
		<label class="radio">
			<input type="radio" name="button" value="re" checked />
			<a class="radio-button-label" href="#">Results</a>
		</label>
	</div>
</div>
</form>
<h2>Quorum</h2>
<h4><?= count($checkedin) ?> / <?= ceil($count * 0.2) ?> required</h4>
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
<?php
$footer = new Footer();
$footer->output();
