<?php

require_once(__DIR__."/../api/drink_api.php");

try {
	$api = new DrinkAPI("mobileapp/getapikey");
	echo $api->processAPI();
} 
catch (Exception $e) {
	echo $e->getMessage();
}

?>