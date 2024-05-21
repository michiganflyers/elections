<?php
function get_gravatar_assoc(&$results) {
	foreach ($results as &$result) {
		$result['gravatar_hash'] = md5($result['gravatar_email']);
		unset($result['gravatar_email']);
	}
}

