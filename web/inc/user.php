<?php
require_once('db.php');

class User {
	private $username = "";
	private $email = "";
	private $name = "";
	private $uid = -1;
	private $voterId = -1;
	private $proxyId = -1;
	private $loggedin = false;
	private $role = 0;

	function __construct(){
		global $db;

		if (!empty($db) && !empty($_COOKIE['token']) && strlen($_COOKIE['token']) == 40) {
			$this->loadSession($_COOKIE['token']);
		}
	}

	private function loadSession($session) {
		global $db;

		$expiration = gmdate('Y-m-d H:i:s', time() - 3600);
		$db->query("DELETE FROM sessions WHERE ctime < '$expiration'");
		$result = $db->fetchRow("SELECT data, member_id, name, username, voting_id, email, permission_level, checkedin, proxy_id FROM sessions LEFT JOIN members ON (sessions.member_id = members.skymanager_id) WHERE sessions.session_token='{$db->sanitize($session)}' AND ctime > '$expiration'");

		if ($result) {
			$this->username = $result['username'];
			$this->name = $result['name'];
			$this->uid = $result['member_id'];
			$this->voterId = $result['voting_id'];
			$this->proxyId = $result['proxy_id'];
			$this->role = min(2, max(0, (int) $result['permission_level']));
			$this->loggedin = true;
		}
	}

	public function login($username, $password){
		$rtConfig = db_get_runtime_config();

		// Testing code to allow demos
		if ($rtConfig['testAccounts'] === 'true' && $password === 'test') {
			$data = [
				"preferred_username" => $username . '_test',
				"name" => ucfirst($username) . " Test",
				"sub"  => hexdec(substr(sha1($username), 0, 7)),
				"email" => "$username@example.net"
			];

			return $this->parseToken('.' . base64_encode(json_encode($data)) . '.');
		}
		// end testing code

		$data = http_build_query([
			'username' => $username,
			'password' => $password,
			'grant_type' => 'password'
		]);

		$opt = [
			'http' => [
				'method' => 'POST',
				'header' => "Content-type: application/x-www-form-urlencoded\r\n"
					. "Content-Length: " . strlen($data) . "\r\n",
				'content' => $data
			]
		];

		$ctx = stream_context_create($opt);
		$token = file_get_contents('https://beta.schedule.michiganflyers.club/api/oauth/token', false, $ctx);

		if (!empty($token)) {
			return $this->parseToken(json_decode($token)->access_token);
		}

		return false;
	}

	private function parseToken($token) {
		global $db;

		$rtConfig = db_get_runtime_config();

		$data = explode('.', $token);
		if (count($data) != 3)
			return false;

		$obj = json_decode(base64_decode($data[1]));

		$this->username = $obj->preferred_username;
		$this->name     = $obj->name;
		$this->uid      = $obj->sub;
		$this->email    = $obj->email ?? null;
		$this->loggedin = true;

		$session_id = $db->randomKey();
		$cookie_opts = ['expires' => time() + 3600, 'httponly' => true, 'samesite' => 'Strict'];
		setcookie('token', $session_id, $cookie_opts);

		// Create user automatically on login
		$_ = $db->insert('members', ['skymanager_id', 'name', 'username', 'email'], [[((int) $this->uid), $this->name, $this->username, (empty($this->email) ? 'NULL' : $this->email)]], true);

		// Assign session token
		$_ = $db->insert('sessions', ['member_id', 'session_token', 'data'], [[(int) $this->uid, $session_id, $token]]);

		// Get voter ID
		$result = $db->fetchRow('select voting_id, proxy_id, permission_level from members where skymanager_id=' . ((int) $this->uid));
		if ($result) {
			$this->voterId = $result['voting_id'];
			$this->proxyId = $result['proxy_id'];
			$this->role = min(2, max(0, (int) $result['permission_level']));
			// Auto check in
			if ($rtConfig['autoCheckIn'] === 'true') {
				$_ = $db->query('update members set checkedin=TRUE where voting_id is not null and skymanager_id=' . ((int) $this->uid));
			}
		} else {
			$this->voterId = null;
			$this->proxyId = null;
			$this->role = 0;
		}

		return true;
	}

	public function username(){
		return $this->username;
	}

	public function name(){
		return $this->name;
	}

	public function voterId(){
		return $this->voterId;
	}

	public function proxyId(){
		return $this->proxyId;
	}

	public function email(){
		return $this->email;
	}

	public function gravatarUrl($size = 128){
		return 'https://www.gravatar.com/avatar/' . md5($this->email) . ".png?r=pg&s=$size";
	}

	public function loggedin(){
		return $this->loggedin;
	}

	public function getRole(){
		return $this->role;
	}

	public function getRoleText(){
		$roles = ['voter', 'pollworker', 'admin'];
		return $roles[$this->role];
	}

	public function logout(){
		global $db;

		if (!empty($_COOKIE['token']))
			$db->query("DELETE FROM sessions WHERE session_token='{$db->sanitize($_COOKIE['token'])}'");

		$this->username = "";
		$this->uid = -1;
		$this->loggedin = false;
	}

	public function getUserId(){
		return $this->uid;
	}
}

$user = new User();
