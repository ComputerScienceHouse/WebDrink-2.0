<?php

require_once("./drink_api_priv.php");

try {
	$api = new DrinkAPI($_REQUEST['request']);
	echo $api->processAPI();
} 
catch (Exception $e) {
	echo $e->getMessage();
}

?>