<?php

// Include the database connection info
require_once("../../../webdrink_info/dbInfo.inc");

/*
*	Database methods
*/
function db_select($sql, $data)
{
	global $pdo;
	$stmt = null;
	// Make the query
	try {
		// Prepare the SQL statement
		$stmt = $pdo->prepare($sql);
	}
	// Catch any exceptions/errors
	catch (Exception $e) {
		return false;
	}
	// Execute the statement
	try {
		if ($stmt->execute($data)) {
			// Return the selected data as an assoc array
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}
		else {
			return false;
		}
	}
	catch (Exception $e) {
		return false;
	}
}

function db_insert($sql, $data)
{
	global $pdo;
	$stmt = null;
	// Make the query
	try {
		// Prepare the SQL statement
		$stmt = $pdo->prepare($sql);
	}
	// Catch any exceptions/errors
	catch (Exception $e) {
		return false;
	}
	// Execute the statement
	try {
		if ($stmt->execute($data)) {
			// Return the number of rows affected
			return $stmt->rowCount();
		}
		else {
			return false;
		}
	}
	catch (Exception $e) {
		return false;
	}
}

function db_update($sql, $data)
{
	return db_insert($sql, $data);
}

function db_delete($sql, $data)
{
	return db_insert($sql, $data);
}

function db_last_insert_id() {
	global $pdo;
	return $pdo->lastInsertId();
}

?>