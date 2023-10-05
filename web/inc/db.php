<?php
class DBHandler{
	private $mysql;

	function __construct($hostname, $username, $password, $database){
		global $dbs;

		$this->mysql = mysqli_connect($hostname, $username, $password);
		if(!$this->mysql) die("MySql error: " . mysql_error());

		mysqli_select_db($this->mysql, $database);
	}

	public function sanitize($text){
		return mysqli_real_escape_string($this->mysql, $text);
	}

	public function query($query){
		return mysqli_query($this->mysql, $query);
	}

	public function fetchRow($query){
		$result = $this->query($query);
		if($result === false || $result === true) die(mysqli_error($this->mysql));//return $result;

		return mysqli_fetch_assoc($result);
	}

	public function fetchAssoc($query){
		$result = $this->query($query);
		if($result === false || $result === true) die(mysqli_error($this->mysql));//return $result;

		$data = array();
		while($row = mysqli_fetch_assoc($result)){
			$data[] = $row;
		}

		return $data;
	}

	public function getError() {
		return mysqli_error($this->mysql);
	}

	public function lastInsertId(){
		return mysqli_insert_id($this->mysql);
	}

	public function getAffectedRows() {
		return mysqli_affected_rows($this->mysql);
	}

	public function verifyInteger($input, $minimum = 1){
		/*
		 * Pretty hacky solution.
		 *
		 * First checks if the integer cast of the input is equal to itself.
		 *     This filters out decimals, alternate bases, and exponents.
		 * Then checks if the input is numeric, which filters out other strings that slip by the first check.
		 *     This guarantees that it's in a numeric format, which combined with the first filter, should guarantee that it is an integer
		 */
		return (((int) $input == $input) && is_numeric($input) && (($minimum === false) || (int) $input >= $minimum));
	}

	// Always returns a key of length 40. TODO: Add arbitrary length
	public function randomKey(){
		//Cryptographically Secure Key
		if(function_exists('openssl_random_pseudo_bytes')) return base64_encode(openssl_random_pseudo_bytes(30));


		//Fallback (Not cryptographically secure)
		$str = "";
		for($i=0; $i<30; $i++){
			$str .= chr(mt_rand(0,255));
		}

		return base64_encode($str);
	}
}

$db = new DBHandler('localhost', '2022mfelection', '<password>', '2022mfelection');
