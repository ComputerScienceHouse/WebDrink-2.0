<?php

// Include the database connection info
require_once("../ldapInfo.inc");

/*
*	LDAP methods
*/
function ldap_lookup($uid, $fields = null) {
	return ldap_lookup_uid($uid, $fields);
}

function ldap_lookup_uid($uid, $fields = null) {
	global $conn;
	global $userDn;
	try {
		// Make the search
		$filter = "(uid=".$uid.")";
		$search = false;
		if (is_array($fields))
			$search = ldap_search($conn, $userDn, $filter, $fields);
		else
			$search = ldap_search($conn, $userDn, $filter);
		// Grab the results
		if ($search)
			return ldap_get_entries($conn, $search);
		else 
			return false;
	}
	catch (Exception $e) {
		
	}
}

function ldap_lookup_uuid($uuid, $fields = null) {
	global $conn;
	global $userDn;
	try {
		// Make the search
		$filter = "(entryuuid=".$uuid.")";
		$search = false;
		if (is_array($fields))
			$search = ldap_search($conn, $userDn, $filter, $fields);
		else
			$search = ldap_search($conn, $userDn, $filter);
		// Grab the results
		if ($search)
			return ldap_get_entries($conn, $search);
		else 
			return false;
	}
	catch (Exception $e) {
		
	}
}

function ldap_update($uid, $replace) {
	global $conn;
	global $userDn;
	try {
		// Form the dn
		$dn = "uid=".$uid.",".$userDn;
		// Make the update
		return ldap_mod_replace($conn, $dn, $replace);
	}
	catch (Exception $e) {
		
	}
}

?>