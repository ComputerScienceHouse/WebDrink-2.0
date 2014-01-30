<?php

// Include the database connectivity functions
require_once('../utils/db_utils.php');

$uid = "bencentra"; //$_SERVER['WEBAUTH_USER'];
$salt = date("h:i:s");
$apiKey = base64_encode($uid.$salt);

echo $uid;
echo "<br/>";
echo $salt;
echo "<br/>";
echo $apiKey;
echo "<br/>";

$sql = "REPLACE INTO api_keys (uid, api_key) VALUES (:uid, :apiKey)";
echo $sql;
echo "<br/>";
$params["uid"] = $uid;
$params["apiKey"] = $apiKey;

$query = db_insert($sql, $params);
if ($query) {
	echo "Yep";
}
else {
	echo "Nope";
}

?>