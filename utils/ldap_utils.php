<?php

// Include the database connection info
require_once(__DIR__."/../../../webdrink_info/ldapInfo.inc");

/*
*	LDAP methods
*/
function ldap_lookup($uid, $fields = null) {
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
		return false;
	}
}

function ldap_lookup_uid($uid, $fields = null) {
	return ldap_lookup($uid, $fields);
}

function ldap_lookup_ibutton($ibutton, $fields = null) {
	global $conn;
	global $userDn;
	try {
		// Make the search
		$filter = "(ibutton=".$ibutton.")";
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
		return false;
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
		return false;
	}
}

?>
