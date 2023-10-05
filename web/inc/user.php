<?php
require_once('db.php');

class User{
	private $username = "";
	private $email = "";
	private $name = "";
	private $uid = -1;
	private $voterId = -1;
	private $loggedin = false;
	private $role = 0;

	function __construct(){
		if(isset($_SESSION['token']) && strlen($_SESSION['token']) > 41) {
			$this->parseToken($_SESSION['token']);
		}
	}

	public function login($username, $password){
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
		$token = file_get_contents('https://beta.schedule.michiganflyers.org/api/oauth/token', false, $ctx);

		if (!empty($token)) {
			$_SESSION['token'] = json_decode($token)->access_token;
			return $this->parseToken($_SESSION['token']);
		}

		return false;
	}

	private function parseToken($token) {
		global $db;

		$data = explode('.', $token);
		if (count($data) != 3)
			return false;

		$obj = json_decode(base64_decode($data[1]));

		$this->username = $obj->preferred_username;
		$this->name     = $obj->name;
		$this->uid      = $obj->sub;
		$this->email    = $obj->email ?? null;
		$this->loggedin = true;

		// Create user automatically on login
		$_ = $db->query('insert ignore into members (skymanager_id, name, username, email) VALUES (' . ((int) $this->uid) . ', "' . $db->sanitize($this->name) . '", "' . $db->sanitize($this->username) . '", ' . (empty($this->email) ? 'NULL' : '"' . $db->sanitize($this->email) . '"') . ')');

		// Get voter ID
		$result = $db->fetchRow('select members.voting_id from members left join proxy on (members.voting_id=proxy.voting_id) where proxy.delegate_id is null and skymanager_id=' . ((int) $this->uid));

		$admincheck = $db->fetchRow('select members.pollworker from members where skymanager_id=' . ((int) $this->uid));
		if ($result) {
			$this->voterId = $result['voting_id'];
			// Auto check in
			$_ = $db->query('update members set checkedin=1 where voting_id is not null and skymanager_id=' . ((int) $this->uid));
		} else {
			$this->voterId = null;
		}

		if ($admincheck)
			$this->role = $admincheck['pollworker'];
		else
			$this->role = 0;

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
		return $this->role ? 'admin' : 'voter';
		//return $this->role;
	}

	public function logout(){
		$_SESSION['token'] = "";
		$this->username = "";
		$this->uid = -1;
		$this->loggedin = false;
	}

	public function getUserId(){
		return $this->uid;
	}
}

$user = new User();
