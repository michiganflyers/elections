<?php
class DbHelpers {
	// Always returns a key of length 40. TODO: Add arbitrary length
	public static function randomKey() {
		//Cryptographically Secure Key
		if (function_exists('openssl_random_pseudo_bytes')) return base64_encode(openssl_random_pseudo_bytes(30));

		//Fallback (Not cryptographically secure)
		$str = "";
		for ($i=0; $i<30; $i++) {
			$str .= chr(mt_rand(0,255));
		}

		return base64_encode($str);
	}
}

class MysqlDb {
	private $mysql;

	private function __construct() {}

	public static function Connect($hostname, $username, $password, $database) {
		$handler = new MysqlDb();

		mysqli_report(MYSQLI_REPORT_OFF);
		$handler->mysql = mysqli_init();
		if (!$handler->mysql)
			return false;

		if (!mysqli_options($handler->mysql, MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true))
			return false;

		if (!mysqli_real_connect($handler->mysql, $hostname, $username, $password))
			return false;

		mysqli_select_db($handler->mysql, $database);
		if (mysqli_error($handler->mysql))
			return false;

		return $handler;
	}

	public function sanitize($text) {
		return mysqli_real_escape_string($this->mysql, $text);
	}

	public function query($query) {
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
					$value_arr[] = "'{$this->sanitize($v)}'";
			}

			$values_arr[] = "(" . implode(',', $value_arr) . ")";
		}

		$values_list = implode(',', $values_arr);

		$ignore_str = '';
		if ($ignore)
			$ignore_str = 'IGNORE';

		return $this->query("INSERT $ignore_str INTO $table ($field_list) VALUES $values_list");
	}

	public function exec_multi($query) {
		try {
			$res = mysqli_multi_query($this->mysql, $query);
			do {
				if ($err = $this->getError())
					return false;
			} while (mysqli_next_result($this->mysql) || $this->getError());
			return true;
		} catch (Throwable $err) {
			return false;
		}
	}

	public function fetchRow($query) {
		$result = $this->query($query);
		if ($result === false || $result === true) return $result;

		return mysqli_fetch_assoc($result);
	}

	public function fetchAssoc($query) {
		$result = $this->query($query);
		if ($result === false || $result === true) return $result;

		$data = array();
		while ($row = mysqli_fetch_assoc($result)) {
			$data[] = $row;
		}

		return $data;
	}

	public function getError() {
		return mysqli_error($this->mysql);
	}

	//public function lastInsertId() {
	//	return mysqli_insert_id($this->mysql);
	//}

	//public function getAffectedRows() {
	//	return mysqli_affected_rows($this->mysql);
	//}

	public function verifyInteger($input, $minimum = 1) {
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

	public function setup() { return true; }
	public function randomKey() {
		return DbHelpers::randomKey();
	}
}

class PgsqlDb {
	private $pgsql;

	private function __construct() {}

	public static function Connect($connString) {
		$handler = new PgsqlDb();

		$handler->pgsql = pg_connect($connString);
		if (!$handler->pgsql) return false;

		return $handler;
	}

	public function sanitize($text) {
		return pg_escape_string($this->pgsql, $text);
	}

	public function query($query) {
		try {
			return pg_query($this->pgsql, $query);
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
					$value_arr[] = "'{$this->sanitize($v)}'";
			}

			$values_arr[] = "(" . implode(',', $value_arr) . ")";
		}

		$values_list = implode(',', $values_arr);

		$ignore_str = '';
		if ($ignore)
			$ignore_str = 'ON CONFLICT DO NOTHING';

		return $this->query("INSERT INTO $table ($field_list) VALUES $values_list $ignore_str");
	}

	public function exec_multi($query) {
		return !!$this->query($query);
	}

	public function fetchRow($query) {
		$result = $this->query($query);
		if ($result === false || $result === true) return $result;

		return reset($this->fetchAllTyped($result, 1));
	}

	public function fetchAssoc($query) {
		$result = $this->query($query);
		if ($result === false || $result === true) return $result;

		return $this->fetchAllTyped($result);
	}

	private const BOOLOID = 16;
	private const INT8OID = 20;
	private const INT2OID = 21;
	private const INT4OID = 23;

	/**
	 * Fetch all rows from a result and cast columns by type.
	 * @param resource $result pg_query result
	 * @param int|null $limit  max rows to return (null = all)
	 * @return array           list of assoc rows
	 */
	public function fetchAllTyped($result, int $limit = null): array {
		$rows = [];
		if ($limit === 0) return $rows;

		$n    = pg_num_fields($result);
		$is64 = (PHP_INT_SIZE >= 8);

		$boolCols = [];
		$intCols  = [];
		for ($i = 0; $i < $n; $i++) {
			$oid = pg_field_type_oid($result, $i);

			if ($oid === self::BOOLOID)
				$boolCols[] = pg_field_name($result, $i);
			elseif ($oid === self::INT2OID || $oid === self::INT4OID || ($is64 && $oid === self::INT8OID))
				$intCols[] = pg_field_name($result, $i);
		}

		while ($row = pg_fetch_assoc($result)) {
			foreach ($boolCols as $name)
				if (isset($row[$name])) $row[$name] = ($row[$name] === 't');

			foreach ($intCols as $name)
				if (isset($row[$name])) $row[$name] = (int)$row[$name];

			$rows[] = $row;
			if ($limit !== null && --$limit === 0) break;
		}

		return $rows;
	}

	public function getError() {
		return pg_last_error($this->pgsql);
	}

	//public function lastInsertId() {
	//}

	//public function getAffectedRows() {
	//}

	public function verifyInteger($input, $minimum = 1) {
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

	public function setup() {
		return $this->query('
CREATE OR REPLACE FUNCTION grp_concat_int(
  state text,
  val   integer,
  sep   text
) RETURNS text
LANGUAGE sql IMMUTABLE STRICT AS $$
  SELECT CASE
           WHEN state = \'\' THEN val::text
           ELSE state || sep || val::text
         END
$$;

CREATE OR REPLACE FUNCTION grp_concat_int_default(
  state text,
  val   integer
) RETURNS text
LANGUAGE sql IMMUTABLE STRICT AS $$
  SELECT grp_concat_int(state, val, \',\')
$$;

CREATE AGGREGATE group_concat(integer) (
  SFUNC    = grp_concat_int_default,
  STYPE    = text,
  INITCOND = \'\'
);
');
	}

	public function randomKey() {
		return DbHelpers::randomKey();
	}
}

class SqliteDb {
	private $sqlite;

	private function __construct() {}

	public static function Connect() {
		$handler = new SqliteDb();
		$handler->sqlite = new Sqlite3(BASE . "/inc/config/sqlite3.db");
		$handler->sqlite->exec('PRAGMA foreign_keys = ON');

		return $handler;
	}

	public function sanitize($text) {
		return SQLite3::escapeString($text);
	}

	public function query($query) {
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
					$value_arr[] = "'{$this->sanitize($v)}'";
			}

			$values_arr[] = "(" . implode(',', $value_arr) . ")";
		}

		$values_list = implode(',', $values_arr);

		$ignore_str = '';
		if ($ignore)
			$ignore_str = 'OR IGNORE';

		return $this->query("INSERT $ignore_str INTO $table ($field_list) VALUES $values_list");
	}

	public function exec_multi($query) {
		try {
			return $this->sqlite->exec($query);
		} catch (Throwable $err) {
			return false;
		}
	}

	public function fetchRow($query) {
		$result = $this->query($query);
		if ($result === false || $result === true)
			return $result;

		return $result->fetchArray();
	}

	public function fetchAssoc($query) {
		$result = $this->query($query);
		if ($result === false || $result === true) return $result;

		$data = array();
		while ($row = $result->fetchArray()) {
			$data[] = $row;
		}

		return $data;
	}

	public function getError() {
		return $this->sqlite->lastErrorCode() === 0 ? "" : $this->sqlite->lastErrorMsg();
	}

	//public function lastInsertId() {
	//	return mysqli_insert_id($this->mysql);
	//}

	//public function getAffectedRows() {
	//	return mysqli_affected_rows($this->mysql);
	//}

	public function verifyInteger($input, $minimum = 1) {
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

	public function setup() { return true; }
	public function randomKey() {
		return DbHelpers::randomKey();
	}
}
