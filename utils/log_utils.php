<?php

/*
*	Exception logging methods
*/
function log_exception($message, $file = "../exceptions.txt") 
{
	// Open the file
	$file = fopen($file, "a");
	if (!$file) {
		//return "Failed to open file";
		return false;
	}
	// Write the message
	if (!fwrite($file, date('Y-m-d H:i:s').": ".$message)) {
		//return "Failed to write to file";
		return false;
	}
	// Close the file
	if(!fclose($file)) {
		//return "Failed to close file";
		return false;
	}
	//return $message;
	return true;
}

function get_exception_log($filename = "../exceptions.txt")
{
	// Open the file
	$file = fopen($filename, "r");
	if (!$file) {
		echo "Failed to open file";
	}
	// Read the log data
	$contents = fread($file, filesize($filename));
	if (!$contents) {
		echo "Failed to read file";
	}
	// Close the file
	if(!fclose($file)) {
		echo "Failed to close file";
	}
	// Return the file contents
	return nl2br($contents);
}

?>