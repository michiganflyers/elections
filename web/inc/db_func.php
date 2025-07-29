<?php
function db_get_candidates() {
	global $db;
	//return $db->fetchAssoc("select skymanager_id, name, username, coalesce(email, '') as gravatar_email from members where voting_id is not null");
	return $db->fetchAssoc("select candidates.skymanager_id, position, statement, name, username, coalesce(email, '') as gravatar_email from candidates INNER JOIN members on (candidates.skymanager_id=members.skymanager_id)");
}

function db_get_current_user_votes() {
	global $db;
	global $user;
	return $db->fetchAssoc("select position from votes where member_id={$user->voterId()}");
}

function db_get_positions() {
	global $db;
	static $positions;

	if (!$positions)
		$positions = $db->fetchAssoc("select position as code, description as label, active, nominating, early from positions order by code asc");

	return $positions;
}

function db_get_active_position() {
	global $db;
	$results = db_get_positions();
	if (!$results || !is_array($results))
		return $results;

	$results = array_filter($results, fn($row) => $row['active']);

	if (!$results || !count($results))
		return false;

	return array_intersect_key($results[0], array_flip(['code', 'label']));
}

function db_get_nominating_positions() {
	global $db;
	$results = db_get_positions();
	if (!$results || !is_array($results))
		return $results;

	$results = array_filter($results, fn($row) => $row['nominating']);

	if (!$results || !count($results))
		return false;

	$results = array_values($results);
	return array_map(fn($result) => array_intersect_key($result, array_flip(['code', 'label'])), $results);
}
