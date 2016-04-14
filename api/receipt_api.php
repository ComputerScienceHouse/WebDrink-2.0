<?php

require 'receipt_keys.php';

$db = new mysqli($host, $dbusername, $dbpassword, $dbname) or die("Connection Error: " . mysqli_error($db));

if(!isset($_GET['amount']) || !isset($_GET['uid']) || !isset($_GET['item'])){

	die(http_response_code(400)); //Missing Params
}

$amount = $db->real_escape_string($_GET['amount']);
$uid = $db->real_escape_string($_GET['uid']);
$item = $db->real_escape_string($_GET['item']);

$query = "INSERT INTO receipts(uid, amount, item) VALUES('$uid','$amount','$item')";

if($db->query($query) === TRUE){
    http_response_code(200);
}
else{
    
    /* Submission Failed */
    
    http_response_code(500);
    echo $db->error;
}



?>