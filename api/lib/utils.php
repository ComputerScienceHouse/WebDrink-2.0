<?php

// Include the database connectivity functions
require_once(__DIR__.'/../../utils/db_utils.php');
// Include the LDAP connectivity functions
require_once(__DIR__.'/../../utils/ldap_utils.php');

/* 
*	Class of common utility methods
*/
class Utils
{
	// Constructor
	public function __construct() {
		// $this->debug("I'm a Utils insance!");
	}

	// Log messages to a file
	public function debug($msg, $file = "./log.txt") {
		file_put_contents($file, date("Y-m-d H:i:s") . " | " . $msg . "\n", FILE_APPEND);
	}

	// Check if a user is a drink admin
	public function isAdmin($uid) {
		$fields = array("drinkAdmin");
		$result = ldap_lookup_uid($uid, $fields);
		if (isset($result[0]["drinkadmin"][0])) {
			return $result[0]["drinkadmin"][0];
		}
		return false;
	}

	// Lookup a uid by API key
	public function lookupUser($api_key) {
		$sql = "SELECT * FROM api_keys WHERE api_key = :apiKey";
		$params["apiKey"] = $api_key;
		$query = db_select($sql, $params); 
		if ($query !== false) {
			return $query[0]["uid"];
		} 
		return false;
	}

	// Sanitize a string
	public function sanitizeString($str) {
		return htmlentities(trim((string) $str));
	}

	// Sanitize an integer
	public function sanitizeInt($int) {
		return (int) trim($int);
	}

	// Redirect the user
	public function redirect($url, $permanent = false) {
		header('Location: ' . $url, true, $permanent ? 301 : 302);
		exit();
	}

	// Log an API call
	public function logAPICall($uid, $api_method, $detail = null) {
		$sql = "INSERT INTO api_calls (username, api_method, detail) VALUES (:username, :api_method, :detail)";
		$params = array();
		$params["username"] = $uid;
		$params["api_method"] = $api_method;
		$params["detail"] = $detail;
		$query = db_insert($sql, $params);
		if ($query !== false) {
			return true;
		}
		return false;
	}

	// Check if a user is rate-limited
	public function isRateLimited($uid, $api_method, $seconds, $detail = null) {
		$sql = "SELECT * FROM api_calls WHERE username = :username AND api_method = :api_method";
		$params = array();
		$params["username"] = $uid;
		$params["api_method"] = $api_method;
		$sql .= " AND timestamp > DATE_SUB(NOW(), INTERVAL :seconds SECOND)";
		$params["seconds"] = $seconds;
		if ($detail != null) {
			$sql .= " AND detail = :detail";
			$params["detail"] = $detail;
		}
		$sql .= " LIMIT 1";
		$query = db_select($sql, $params);
		if ($query !== false) {
			return $query;
		}
		return false;
	}

}

?>
