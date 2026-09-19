<?php
function db_get_candidates() {
	global $db;
	return $db->fetchAssoc("select candidates.skymanager_id, position, statement, name, username, coalesce(email, '') as gravatar_email from candidates INNER JOIN members on (candidates.skymanager_id=members.skymanager_id)");
}

function db_get_proxylist() {
	global $db;
	return $db->fetchAssoc("select skymanager_id, name, username, coalesce(email, '') as gravatar_email from members");
}

function db_get_voters() {
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

function db_get_current_user_votes() {
	global $db;
	global $user;
	return $db->fetchAssoc("select position from votes where member_id={$user->voterId()}");
}

function db_get_current_user_early_votes() {
	global $db;
	global $user;
	return $db->fetchAssoc("select candidate_id, position, priority from prevotes where member_id={$user->getUserId()}");
}

function db_get_runtime_config() {
	global $db;
	static $runtimeConfig;

	if (!$runtimeConfig) {
		$results = $db->fetchAssoc("select parameter, value from runtimeconfig");
		if (!$results) return $results;
 
		$runtimeConfig = [];
		foreach ($results as $result) {
			$runtimeConfig[$result['parameter']] = $result['value'];
		}
	}

	return $runtimeConfig;
}

function db_get_positions() {
	global $db;
	static $positions;

	$states = [
		'Inactive/Closed',
		'Nominating',
		'Proxying',
		'Active Voting'
	];

	if (!$positions) {
		$positions = $db->fetchAssoc("select position as code, description as label, state from positions where rtime is null order by ctime asc");
		foreach ($positions as &$position) {
			$position['state_name'] = $states[$position['state']];
		}
	}

	return $positions;
}

function db_get_removed_positions() {
	global $db;
	static $positions;

	if (!$positions)
		$positions = $db->fetchAssoc("select position as code, description as label, state from positions where rtime is not null order by ctime asc");

	return $positions;
}

// States:
// 0 Inactive/Closed
// 1 Nominating
// 2 Early Voting
// 3 Active Voting
function db_get_active_position() {
	global $db;
	$results = db_get_positions();
	if (!$results || !is_array($results))
		return $results;

	$results = array_filter($results, fn($row) => $row['state'] === 3);

	if (!$results || !count($results))
		return false;

	$result = reset($results);
	return array_intersect_key($result, array_flip(['code', 'label']));
}

function db_get_nominating_positions() {
	global $db;
	$results = db_get_positions();
	if (!$results || !is_array($results))
		return $results;

	$results = array_filter($results, fn($row) => $row['state'] === 1);

	if (!$results || !count($results))
		return false;

	$results = array_values($results);
	return array_map(fn($result) => array_intersect_key($result, array_flip(['code', 'label'])), $results);
}

function db_get_early_positions() {
	global $db;
	$results = db_get_positions();
	if (!$results || !is_array($results))
		return $results;

	$results = array_filter($results, fn($row) => $row['state'] === 2);

	if (!$results || !count($results))
		return false;

	$results = array_values($results);
	return array_map(fn($result) => array_intersect_key($result, array_flip(['code', 'label'])), $results);
}
