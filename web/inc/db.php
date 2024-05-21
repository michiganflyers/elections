<?php
class MysqlDb {
	private $mysql;

	private function __construct() {}

	public static function Connect($hostname, $username, $password, $database){
		$handler = new MysqlDb();

		mysqli_report(MYSQLI_REPORT_OFF);
		$handler->mysql = mysqli_connect($hostname, $username, $password);
		if(!$handler->mysql) return false;

		mysqli_select_db($handler->mysql, $database);
		if (mysqli_error($handler->mysql))
			return false;

		return $handler;
	}

	public function sanitize($text){
		return mysqli_real_escape_string($this->mysql, $text);
	}

	public function query($query){
		try {
			return mysqli_query($this->mysql, $query);
		} catch (Throwable $err) {
			return false;
		}
	}

	public function insert($table, $fields, $values_assoc, $ignore = false) {
		$field_list = implode(",", $fields);
		$values_arr = [];
		foreach ($values_assoc as $value) {
			$value_arr = [];
			foreach ($value as $v) {
				if ($this->verifyInteger($v))
					$value_arr[] = $v;
				else if ($v === "NULL")
					$value_arr[] = "NULL";
				else
					$value_arr[] = "\"{$this->sanitize($v)}\"";
			}

			$values_arr[] = "(" . implode(',', $value_arr) . ")";
		}
		
		$values_list = implode(',', $values_arr);

		$ignore_str = '';
		if ($ignore)
			$ignore_str = 'IGNORE';

		return $this->query("INSERT $ignore_str INTO $table ($field_list) VALUES $values_list");
	}

	public function exec_multi($query){
		try {
			$res = mysqli_multi_query($this->mysql, $query);
			do {
				if ($err = $this->getError())
					return false;
			} while (mysqli_next_result($this->mysqli) || $this->getError());
			return true;
		} catch (Throwable $err) {
			return false;
		}
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

	//public function lastInsertId(){
	//	return mysqli_insert_id($this->mysql);
	//}

	//public function getAffectedRows() {
	//	return mysqli_affected_rows($this->mysql);
	//}

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

class SqliteDb {
	private $sqlite;

	private function __construct() {}

	public static function Connect(){
		$handler = new SqliteDb();
		$handler->sqlite = new Sqlite3(BASE . "/inc/config/sqlite3.db");
		$handler->sqlite->exec('PRAGMA foreign_keys = ON');

		return $handler;
	}

	public function sanitize($text){
		return SQLite3::escapeString($text);
	}

	public function query($query){
		try {
			return $this->sqlite->query($query);
		} catch (Throwable $err) {
			return false;
		}
	}

	public function insert($table, $fields, $values_assoc, $ignore = false) {
		$field_list = implode(",", $fields);
		$values_arr = [];
		foreach ($values_assoc as $value) {
			$value_arr = [];
			foreach ($value as $v) {
				if ($this->verifyInteger($v))
					$value_arr[] = $v;
				else if ($v === "NULL")
					$value_arr[] = "NULL";
				else
					$value_arr[] = "\"{$this->sanitize($v)}\"";
			}

			$values_arr[] = "(" . implode(',', $value_arr) . ")";
		}
		
		$values_list = implode(',', $values_arr);

		$ignore_str = '';
		if ($ignore)
			$ignore_str = 'OR IGNORE';

		return $this->query("INSERT $ignore_str INTO $table ($field_list) VALUES $values_list");
	}

	public function exec_multi($query){
		try {
			return $this->sqlite->exec($query);
		} catch (Throwable $err) {
			return false;
		}
	}

	public function fetchRow($query){
		$result = $this->query($query);
		if ($result === false || $result === true)
			return $result;

		return $result->fetchArray();
	}

	public function fetchAssoc($query){
		$result = $this->query($query);
		if($result === false || $result === true) die($this->getError());//return $result;

		$data = array();
		while($row = $result->fetchArray()){
			$data[] = $row;
		}

		return $data;
	}

	public function getError() {
		return $this->sqlite->lastErrorCode() === 0 ? "" : $this->sqlite->lastErrorMsg();
	}

	//public function lastInsertId(){
	//	return mysqli_insert_id($this->mysql);
	//}

	//public function getAffectedRows() {
	//	return mysqli_affected_rows($this->mysql);
	//}

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
