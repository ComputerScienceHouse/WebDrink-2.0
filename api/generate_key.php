<?php

// Include the database connectivity functions
require_once('../utils/db_utils.php');

$uid = "bencentra"; //$_SERVER['WEBAUTH_USER'];
$salt = time();
$apiKey = sha1($uid.$salt);
$apiKey = substr($apiKey, 0, strlen($apiKey));

$sql = "REPLACE INTO api_keys (uid, api_key) VALUES (:uid, :apiKey)";
$params["uid"] = $uid;
$params["apiKey"] = $apiKey;

$query = db_insert($sql, $params);
if ($query) {
	echo true;
}
else {
	echo false;
}

?>