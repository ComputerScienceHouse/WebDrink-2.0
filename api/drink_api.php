<?php

// Include the database connectivity functions
require_once('../utils/db_utils.php');
// Include the LDAP connectivity functions
require_once('../utils/ldap_utils.php');
// Include the abstract API class
require_once('./abstract_api.php');
// Include configuration info
require_once("../config.php");

// Include Elephant.IO for websocket calls
use ElephantIO\Client as ElephantIOClient;
require('../lib/elephant.io-2.0.4/Client.php');
require('../lib/elephant.io-2.0.4/Payload.php');

/*
*	Concrete API implementation for WebDrink
*/
class DrinkAPI extends API
{
	private $admin = false; 	// Am I a drink admin?
	private $uid = false;		// My uid (username)
	private $webauth = false;	// Am I authenticated with Webauth?

	private $DRINK_SERVER = "https://drink.csh.rit.edu:8080"; // Address of the drink server
	private $drop_data = array(); // Data required to drop a drink
	private $drop_result = array(); // Data to return from dropping a drink

	// Constructor
	public function __construct($request) {
		parent::__construct($request);

		// Grab the uid from Webauth, API Key lookup, etc
		if (array_key_exists("WEBAUTH_USER", $_SERVER)) {
			$this->uid = htmlentities($_SERVER["WEBAUTH_USER"]);
			$this->webauth = true;
		}
		else if ($this->api_key) {
			$this->uid = $this->_lookupUser($this->api_key);
			$this->webauth = false;
		}
		else if (DEBUG) {
			$this->uid = "bencentra";
			$this->webauth = true;
		}
		else {
			$this->uid = false;
			$this->webauth = false;
		}
		// Check if the user is a drink admin
		if ($this->uid != false) {
			$this->admin = $this->_isAdmin($this->uid);
		}
	}

	/* 
	*	Helpful utility methods
	*/

	// Check if a user is a drink admin
	private function _isAdmin($uid) {
		$fields = array("drinkAdmin");
		$result = ldap_lookup_uid($uid, $fields);
		if (isset($result[0]["drinkadmin"][0])) {
			return $result[0]["drinkadmin"][0];
		}
		return false;
	}

	// Lookup a uid by API key
	private function _lookupUser($api_key) {
		$sql = "SELECT * FROM api_keys WHERE api_key = :apiKey";
		$params["apiKey"] = $api_key;
		$query = db_select($sql, $params); 
		if ($query) {
			return $query[0]["uid"];
		} 
		return false;
	}

	// Sanitize a string
	private function _sanitizeString($str) {
		return htmlentities(trim((string) $str));
	}

	// Sanitize an integer
	private function _sanitizeInt($int) {
		return (int) trim($int);
	}

	/*
	*	Test Endpoint
	*
	*	GET /test/ - Test the API ("healthcheck")
	*	GET /test/webauth/ - Test the API with Webauth authentication
	*	GET /test/api/:api_key - Test the API with API key authentication
	*/

	protected function test() {
		// Only allow GET requests
		if ($this->method != "GET") {
			return $this->_result(false, "Only accepts GET requests", false);
		}
		// Determine the specific method to call
		switch ($this->verb) {
			// GET /test/webauth
			case "webauth":
				if ($this->api_key) {
					return $this->_result(false, "Try again without an API key!", false);
				}
				else if ($this->uid) {
					return $this->_result(true, "Greetings, " . $this->uid . "!", true);
				}
				else {
					return $this->_result(false, "Username not found!", false);
				}
				break;
			// GET /test/api/:api_key
			case "api":
				if (!$this->api_key) {
					return $this->_result(false, "Try again with an API key!", false);
				}
				else if ($this->uid) {
					return $this->_result(true, "Greetings, " . $this->uid . "!", true);
				}
				else {
					return $this->_result(false, "Username not found!", false);
				}
				break;
			// GET /test
			default: 
				return $this->_result(true, "Greetings from the Drink API!", true);
		}
	}

	/*
	*	Users Endpoint
	*
	*	GET /users/credits/:uid - Get a user's drink credits value
	* POST /users/credits/:uid/:value/:type - Update a user's drink credits value (admin only)
	*	GET /users/search/:uid - Lookup a partial uid in LDAP
	* GET /users/info/:api_key - Get a user's info (uid, credits, etc) by API key (API key only)
	*	GET /users/drops/:limit/:offset/:uid - Get a portion of the drop logs
	*	GET /users/apikey/ - Get a user's API key (webauth only)
	*	POST /users/apikey/ - Update a user's API key (webauth only)
	*	DELETE /users/apikey/ - Delete a user's API key (webauth only)
	*/

	protected function users() {
		$result = array();
		// Determine the specific method to call
		switch($this->verb) {
			case "credits":
				// Check for the 'uid' parameter
				$uid = false;
				if (array_key_exists("uid", $this->request)) {
					$uid = $this->_sanitizeString($this->request["uid"]);
				}
				else {
					return $this->_result(false, "Missing parameter 'uid' (/users/credits", false);
				}
				// Call an API method
				if ($this->method == "GET") {
					$result = $this->_getCredits($uid);
				}
				else if ($this->method == "POST") {
					$result = $this->_updateCredits($uid);
				}
				else {
					$result = $this->_result(false, "Invalid HTTP method (/users/credits)", false);
				}
				break;
			case "search":
				// Check for the 'uid' parameter
				$uid = false;
				if (array_key_exists("uid", $this->request)) {
					$uid = $this->_sanitizeString($this->request["uid"]);
				}
				else {
					return $this->_result(false, "Missing parameter 'uid' (/users/search)", false);
				}
				// Call an API method
				if ($this->method == "GET") {
					$result = $this->_searchUsers($uid);
				}
				else {
					$result = $this->_result(false, "Invalid HTTP method (/users/search)", false);
				}
				break;
			case "info":
				if ($this->method == "GET") {
					$result = $this->_getUserInfo();
				}
				else {
					$result = $this->_result(false, "Invalid HTTP method (/users/info)", false);
				}
				break;
			case "drops":
				if ($this->method == "GET") {
					$result = $this->_getDrops();
				}
				else {
					$result = $this->_result(false, "Invalid HTTP method (/users/drops)", false);
				}
				break;
			case "apikey":
				if ($this->method == "GET") {
					$result = $this->_getApiKey();
				}
				else if ($this->method == "POST") {
					$result = $this->_updateApiKey();
				}
				else if ($this->method == "DELETE") {
					$result = $this->_deleteApiKey();
				}
				else {
					$result = $this->_result(false, "Invalid HTTP method (/users/apikey)", false);
				}
				break;
			default:
				$result = $this->_result(false, "Invalid API method (/users)", false);
		}
		// Return the response data
		return $result;
	}

	// GET /users/credits/:uid
	private function _getCredits($uid) {
		// Only admins can search for users other than themselves
		if ($this->uid != $uid && !$this->admin) {
			return $this->_result(false, "Must be an admin to get another user's credits (/users/credits)", false);
		}
		// Query LDAP for credit balance
		$fields = array("drinkBalance");
		$data = ldap_lookup_uid($uid, $fields);
		if (array_key_exists(0, $data)) {
			return $this->_result(true, "Success (/users/credits)", (int) $data[0]["drinkbalance"][0]);
		}
		else {
			return $this->_result(false, "Failed to query LDAP (/users/credits)", false);
		}
	}

	// POST /users/credits/:uid/:value
	private function _updateCredits($uid) {
		// Must be an admin to update credits
		if (!$this->admin) {
			return $this->_result(false, "Must be an admin to update a user's credits (/users/credits)", false);
		}
		// Check for the 'value' parameter
		$value = false;
		if (array_key_exists("value", $this->request)) {
			$value = $this->_sanitizeInt($this->request["value"]);
		}
		else {
			return $this->_result(false, "Missing parameter 'value' (/users/credits)", false);
		}
		// Check for the 'type' parameter
		$type = false;
		$direction = "in";
		if (array_key_exists("type", $this->request)) {
			$type = $this->_sanitizeString($this->request["type"]);
			if ($type != "add" && $type != "subtract") {
				return $this->_result(false, "Invalid 'type' - please choose 'add' or 'subtract' (/users/credits)", false);
			}
		}
		else {
			return $this->_result(false, "Missing parameter 'type' (/users/credits)", false);
		}
		// Determine the direction of credit transfer
		$direction = "in";
		if (($value < 0 && $type == "add") || ($value >=0 && $type == "subtract")) {
			$direction = "out";
		}
		// Query LDAP for current credit balance
		$fields = array('drinkBalance');
		$data = ldap_lookup_uid($uid, $fields);
		if (array_key_exists(0, $data)) {
			// Query LDAP to update the credit balance
			$oldBalance = $data[0]['drinkbalance'][0];
			$newBalance = 0;
			if ($type == "add") {
				$newBalance = $oldBalance + $value;
			}
			else {
				$newBalance = $oldBalance - $value;
			}
			$replace = array('drinkBalance' => $newBalance);
			$data = ldap_update($uid, $replace);
			if ($data) {
				// Insert the change into the logs
				$sql = "INSERT INTO money_log (username, admin, amount, direction, reason) VALUES (:uid, :admin, :amount, :direction, :reason)";
				$params = array();
				$params["uid"] = $uid;
				$params["admin"] = $this->uid;
				$params["amount"] = $value;
				$params["direction"] = $direction;
				$params["reason"] = $type;
				db_insert($sql, $params);
				return $this->_result(true, "Success (/users/credits)", $newBalance);
			}
			else {
				return $this->_result(false, "Failed to query LDAP (/users/credits)", false);
			}
		}
		else {
			return $this->_result(false, "Failed to query LDAP (/users/credits)", false);
		}
	}

	// GET /users/search/:uid
	private function _searchUsers($uid) {
		// Query LDAP for the list of matching users
		$fields = array('uid', 'cn');
		$data = ldap_lookup_uid($uid."*", $fields);
		if ($data) {
			$tmp = array();
			$i = 0;
			foreach ($data as $user) {
				$tmp[$i] = array("uid" => $user["uid"][0], "cn" => $user["cn"][0]);
				$i++;
			}
			array_shift($tmp);
			return $this->_result(true, "Success (/users/search)", $tmp);
		}
		else {
			return $this->_result(false, "Failed to query LDAP (/users/search", false);
		}
	}

	// GET /users/info/:ibutton/:uid
	private function _getUserInfo() {
		$uid = false;
		$ibutton = false;
		if (!$this->admin) {
			// Check for the current user's uid
			$uid = $this->uid;
			if (!$uid) {
				return $this->_result(false, "Error looking up info; user not found (/users/info)", false);
			}
		}
		else {
			// Check for a uid to lookup
			if (array_key_exists("uid", $this->request)) {
				$uid = $this->_sanitizeString($this->request["uid"]);
			}
			// Check for an ibutton to lookup
			else if (array_key_exists("ibutton", $this->request)) {
				$ibutton = $this->_sanitizeString($this->request["ibutton"]);
			}
			// If nothing provided, look up your own info
			else if ($this->uid) {
				$uid = $this->uid;
			}
			// If somehow nothing is defined...
			else {
				return $this->_result(false, "Error: please provide a uid or ibutton to look up (/users/info)", false);
			}
		}
		// Query LDAP for the user info
		$fields = array('drinkBalance', 'drinkAdmin', 'ibutton', 'cn', 'uid');
		$data = false;
		if ($uid) {
			$data = ldap_lookup_uid($uid, $fields);
		}
		else if ($ibutton) {
			$data = ldap_lookup_ibutton($ibutton, $fields);
		}
		else {
			return $this->_result(false, "Failed to query LDAP (/users/info)", false);
		}
		// Return the formatted data
		if ($data) {
			if (array_key_exists(0, $data)) {
				$tmp = array();
				$tmp["uid"] = $data[0]["uid"][0];
				$tmp["credits"] = $data[0]["drinkbalance"][0];
				$tmp["admin"] = $data[0]["drinkadmin"][0];
				$tmp["ibutton"] = $data[0]["ibutton"][0];
				$tmp["cn"] = $data[0]["cn"][0];
				return $this->_result(true, "Success (/users/info)", $tmp);
			}
			else {
				return $this->_result(false, "Failed to query LDAP (/users/info)", false);
			}
		}
		else {
			return $this->_result(false, "Failed to query LDAP (/users/info)", false);
		}
	}

	// GET /users/drops/:limit/:offset/:uid
	private function _getDrops() {
		$params = array();
		// Check for a uid
		$uid = false;
		if (array_key_exists("uid", $this->request)) {
			$uid = $this->_sanitizeString($this->request["uid"]);
		}
		// Check for a limit and offset
		$limit = 100;
		$offset = 0;
		if (array_key_exists("limit", $this->request)) {
			$limit = $this->_sanitizeInt($this->request["limit"]);
			if (array_key_exists("offset", $this->request)) {
				$offset = $this->_sanitizeInt($this->request["offset"]);
			}
		}
		// Form the SQL query
		$sql = "SELECT l.drop_log_id, l.machine_id, m.display_name, l.slot, l.username, l.time, l.status, l.item_id, i.item_name, l.current_item_price 
				FROM drop_log as l, machines as m, drink_items as i WHERE";
		if ($uid) {
			$sql .= " l.username = :username AND";
			$params["username"] = $uid;
		}
		$sql .= " m.machine_id = l.machine_id AND i.item_id = l.item_id 
				ORDER BY l.drop_log_id DESC LIMIT :limit OFFSET :offset";
		$params["limit"] = $limit;
		$params["offset"] = $offset;
		// Query the database
		$query = db_select($sql, $params);
		if ($query !== false) {
			return $this->_result(true, "Success (/users/drops)", $query);
		}
		else {
			return $this->_result(false, "Failed to query database (/users/drops)", false);
		}
	}

	// GET /users/apikey
	private function _getApiKey() {
		$params = array();
		// Must be auth'd with Webauth
		if (!$this->webauth) {
			return $this->_result(false, "Must use WebDrink to retrieve API key (/users/apikey)", false);
		}
		// uid must be set
		if (!$this->uid) {
			return $this->_result(false, "uid not found (/users/apikey)", false);
		}
		// Form the SQL query
		$sql = "SELECT * FROM api_keys WHERE uid = :uid";
		$params["uid"] = $this->uid;
		// Query the database
		$query = db_select($sql, $params);
		if ($query !== false) {
			$data = (!$query[0] ? false : $query[0]);
			return $this->_result(true, "Success (/users/apikey)", $data);
		}
		else {
			return $this->_result(false, "Failed to query database (/users/apikey)", false);
		}
	}

	// POST /users/apikey
	private function _updateApiKey() {
		sleep(1);
		$params = array();
		// Must be auth'd with Webauth
		if (!$this->webauth) {
			return $this->_result(false, "Must use WebDrink to retrieve API key (/users/apikey)", false);
		}
		// uid must be set
		if (!$this->uid) {
			return $this->_result(false, "uid not found (/users/apikey)", false);
		}
		// Generate an API key
		$apiKey = "";
		$existingKeys = 0;
		do {
			$apiKey = $this->_generateApiKey($this->uid);
			$sql = "SELECT COUNT(*) as count FROM api_keys WHERE api_key = :apiKey";
			$result = db_select($sql, array("apiKey" => $apiKey));
			$existingKeys = (int) $result[0]["count"];
		} while ($existingKeys == 1);
		
		// Form the SQL query
		$sql = "REPLACE INTO api_keys (uid, api_key) VALUES (:uid, :apiKey)";
		$params["uid"] = $this->uid;
		$params["apiKey"] = $apiKey;
		// Query the database
		$query = db_insert($sql, $params);
		if ($query !== false) {
			$tmp = array();
			$tmp["api_key"] = $apiKey;
			$tmp["date"] = date("Y-m-d H:i:s");
			$tmp["uid"] = $this->uid;
			return $this->_result(true, "Success (/users/apikey)", $tmp);
		}
		else {
			return $this->_result(false, "Failed to query database (/users/apikey)", false);
		}
	}

	private function _generateApiKey($uid) {
		$salt = time();
		$apiKey = hash("sha512", $uid . $salt);
		$apiKey = substr($apiKey, 0, 16);
		return $apiKey;
	}

	// DELETE /users/apikey
	private function _deleteApiKey() {
		$params = array();
		// Must be auth'd with Webauth
		if (!$this->webauth) {
			return $this->_result(false, "Must use WebDrink to retrieve API key (/users/apikey)", false);
		}
		// uid must be set
		if (!$this->uid) {
			return $this->_result(false, "uid not found (/users/apikey)", false);
		}
		// Form the SQL query
		$sql = "DELETE FROM api_keys WHERE uid = :uid";
		$params["uid"] = $this->uid;
		// Query the database
		$query = db_delete($sql, $params);
		if ($query !== false) {
			return $this->_result(true, "Success (/users/apikey)", true);
		}
		else {
			return $this->_result(false, "Failed to query database (/users/apikey)", false);
		}
	}

	/*
	*	Machines Endpoint
	*
	*	GET /machines/stock/:machine_id - Get the stock of one (or all) machines
	* GET /machines/info/:machine_id - Get the info of one (or all) machines
	*	POST /machines/slot/:slot_num/:machine_id/:item_id/:available/:status - Update a machine slot (admin only)
	*/

	protected function machines() {
		$result = array();
		// Determine the specific method to call
		switch($this->verb) {
			case "stock":
				// GET /machines/stock/:machine_id
				if ($this->method == "GET") {
					$result = $this->_getMachineStock();
				}
				else {
					$result = $this->_result(false, "Invalid HTTP method (/machines/stock)", false);
				}
				break;
			case "info":
				// GET /machines/info/:machine_id
				if ($this->method == "GET") {
					$result = $this->_getMachineInfo();
				}
				else {
					$result = $this->_result(false, "Invalid HTTP method (/machines/info)", false);
				}
				break;
			case "slot":
				// POST /machines/slot/:slot_num/:machine_id/:item_id/:available/:status
				if ($this->method == "POST") {
					$result = $this->_updateSlot();
				}
				else {
					$result = $this->_result(false, "Invalid HTTP method (/machines/slot)", false);
				}
				break;
			default:
				$result = $this->_result(false, "Invalid API method (/machines)", false);
		}
		// Return the response data
		return $result;
	}

	// GET /machines/stock/:machine_id
	private function _getMachineStock() {
		$params = array();
		// Check for 'machine_id' parameter
		$machine_id = false;
		if (array_key_exists("machine_id", $this->request)) {
			$machine_id = $this->_sanitizeString($this->request["machine_id"]);
		}
		// Form the SQL query
		$sql = "SELECT s.slot_num, s.machine_id, m.display_name, i.item_id, i.item_name, i.item_price, s.available, s.status 
						FROM slots as s, machines as m, drink_items as i 
						WHERE i.item_id = s.item_id AND m.machine_id = s.machine_id";
		if ($machine_id) {	
			$sql .= " AND s.machine_id = :machineId";
			$params["machineId"] = $machine_id;
		}
		// Query the database
		$query = db_select($sql, $params);
		if ($query !== false) {
			$tmp = array();
			foreach($query as $q) {
				$tmp[$q["machine_id"]][] = $q;
			}
			return $this->_result(true, "Success (/machines/stock)", $tmp);
		}
		else {
			return $this->_result(false, "Failed to query database (/machines/stock)", false);
		}
	}

	// GET /machines/info/:machine_id
	private function _getMachineInfo() {
		$params = array();
		// Check for 'machine_id' parameter
		$machine_id = false;
		if (array_key_exists("machine_id", $this->request)) {
			$machine_id = $this->_sanitizeString($this->request["machine_id"]);
		}
		// Form the SQL query
		$sql = "SELECT m.machine_id, m.name, m.display_name, a.alias_id, a.alias 
						FROM machines as m, machine_aliases as a 
						WHERE a.machine_id = m.machine_id";
		if ($machine_id) {
			$sql .= " AND m.machine_id = :machineId";
			$params["machineId"] = $machine_id;
		}
		// Query the database
		$query = db_select($sql, $params);
		if ($query !== false) {
			return $this->_result(true, "Success (/machines/info)", $query);
		}
		else {
			return $this->_result(false, "Failed to query database (/machines/info)", false);
		}
	}

	// POST /machines/slot/:slot_num/:machine_id/:item_id/:available/:status
	private function _updateSlot() {
		$params = array();
		// Must be an admin to update a slot
		if (!$this->admin) {
			return $this->_result(false, "Must be an admin to update slots (/machines/slot)", false);
		}
		// Check for 'slot_num' parameter
		$slot = 0;
		if (array_key_exists("slot_num", $this->request)) {
			$slot = $this->_sanitizeInt($this->request["slot_num"]);
			$params["slotNum"] = $slot;
		}
		else {
			return $this->_result(false, "Missing 'slot_num' parameter (/machines/slot)", false);
		}
		// Check for 'machine_id' parameter
		$machine_id = 0;
		if (array_key_exists("machine_id", $this->request)) {
			$machine_id = $this->_sanitizeInt($this->request["machine_id"]);
			$params["machineId"] = $machine_id;
		}
		else {
			return $this->_result(false, "Missing 'machine_id' parameter (/machines/slot)", false);
		}
		// Form the SQL query
		$sql = "UPDATE slots SET";
		$append = "";
		if (array_key_exists("item_id", $this->request)) {
			$item_id = $this->_sanitizeInt($this->request["item_id"]);
			$append .= " item_id = :itemId,";
			$params["itemId"] = $item_id;
		}
		if (array_key_exists("available", $this->request)) {
			$available = $this->_sanitizeString($this->request["available"]);
			$append .= " available = :available,";
			$params["available"] = $available;
		}
		if (array_key_exists("status", $this->request)) {
			$status = strtolower($this->_sanitizeString($this->request["status"]));
			if ($status != "enabled") {
				$status = "disabled";
			}
			$append .= " status = :status,";
			$params["status"] = $status;
		}
		if ($append == "") {
			return $this->_result(false, "Nothing to update (/machines/slot)", false);
		}
		$append = substr($append, 0, -1);
		$sql .= $append . " WHERE slot_num = :slotNum AND machine_id = :machineId";
		// Query the database
		$query = db_update($sql, $params);
		if ($query !== false) {
			return $this->_result(true, "Success (/machines/slot)", true);
		}
		else {
			return $this->_result(false, "Failed to query database (/machines/slot)", false);
		}
	}

	/*
	*	Items endpoint
	*
	* GET /items/list - Get a list of all items
	*	POST /items/add/:name/:price - Add a new item to the database (admin only)
	* POST /items/update/:item_id/:name/:price/:status - Update an item (admin only)
	*	POST /items/delete/:item_id - Delete an item (admin only)
	*/

	protected function items() {
		$result = array();
		// Determine the specific method to call
		switch($this->verb) {
			case "list":
				// GET /items/list
				if ($this->method == "GET") {
					$result = $this->_getItems();
				}
				else {
					$result = $this->_result(false, "Invalid HTTP method (/items/list)", false);
				}
				break;
			case "add":
				// POST /items/add/:name/:price
				if ($this->method == "POST") {
					$result = $this->_addItem();
				}
				else {
					$result = $this->_result(false, "Invalid HTTP method (/items/add)", false);
				}
				break;
			case "update":
				// POST /items/update/:item_id/:name/:price/:status
				if ($this->method == "POST") {
					$result = $this->_updateItem();
				}
				else {
					$result = $this->_result(false, "Invalid HTTP method (/items/update)", false);
				}
				break;
			case "delete":
				// POST /items/delete/:item_id 
				if ($this->method == "POST") {
					$result = $this->_deleteItem();
				}
				else {
					$result = $this->_result(false, "Invalid HTTP method (/items/delete)", false);
				}
				break;
			default:
				$result = $this->_result(false, "Invalid API method (/items)", false);
		}
		// Return the response data
		return $result;
	}

	// GET /items/list
	private function _getItems() {
		$params = array();
		// Form the SQL query (TODO: Either clean up database or make a toggle for active/inactive)
		$sql = "SELECT item_id, item_name, item_price, state FROM drink_items WHERE state = 'active'";
		// Query the database
		$query = db_select($sql, $params);
		if ($query !== false) {
			return $this->_result(true, "Success (/items/list)", $query);
		} 
		else {
			return $this->_result(false, "Failed to query database (/items/list)", false);
		}
	}

	// POST /items/add/:name/:price
	private function _addItem() {
		$params = array();
		// Must be an admin to add items
		if (!$this->admin) {
			return $this->_result(false, "Must be an admin to add items (/items/add)", false);
		}
		// Check for 'name' parameter
		$name = false;
		if (array_key_exists("name", $this->request)) {
			$name = $this->_sanitizeString($this->request["name"]);
			$params["name"] = $name;
		}
		else {
			return $this->_result(false, "Missing parameter 'name' (/items/add)", false);
		}
		// Check for 'price' parameter
		$price = false;
		if (array_key_exists("price", $this->request)) {
			$price = $this->_sanitizeInt($this->request["price"]);
			if ($price < 0) {
				$price = $price * -1;
			}
			$params["price"] = $price;
		}
		else {
			return $this->_result(false, "Missing parameter 'price' (/items/add)", false);
		}
		// Form the SQL query
		$sql = "INSERT INTO drink_items (item_name, item_price) VALUES (:name, :price)";
		// Query the database
		$query = db_insert($sql, $params);
		if ($query !== false) {
			// Log price changes to the database
			$item_id = db_last_insert_id();
			$sql = "INSERT INTO drink_item_price_history (item_id, item_price) VALUES (:itemId, :price)";
			$params = array();
			$params["itemId"] = $item_id;
			$params["price"] = $price;
			db_insert($sql, $params);
			return $this->_result(true, "Success (/items/add)", (int) $item_id);
		}
		else {
			return $this->_result(false, "Failed to query database (/items/add)", false);
		}
	}

	// POST /items/update/:item_id/:name/:price/:status
	private function _updateItem() {
		$params = array();
		// Must be an admin to add items
		if (!$this->admin) {
			return $this->_result(false, "Must be an admin to add items (/items/update)", false);
		}
		// Check for 'item_id' parameter
		$item_id = 0;
		if (array_key_exists("item_id", $this->request)) {
			$item_id = $this->_sanitizeInt($this->request["item_id"]);
			$params["itemId"] = $item_id;
		}
		else {
			return $this->_result(false, "Missing parameter 'item_id' (/items/update)", false);
		}
		// Form the SQL query
		$append = "";
		$sql = "UPDATE drink_items SET";
		if (array_key_exists("name", $this->request)) {
			$name = $this->_sanitizeString($this->request["name"]);
			// Make sure the name isn't empty
			// if (strlen($name) <= 0) {
			// 	return $this->_result 
			// 	$result["status"] = false;
			// 	$result["message"] = "Error: name can't be empty (items.add)";
			// 	$result["data"] = false;
			// 	break;
			// }
			$append .= " item_name = :name,";
			$params["name"] = $name;
		}
		$price = false;
		if (array_key_exists("price", $this->request)) {
			$price = $this->_sanitizeInt($this->request["price"]);
			if ($price < 0) {
				$price = $price * -1;
			}
			$append .= " item_price = :price,";
			$params["price"] = $price;
		}
		if (array_key_exists("state", $this->request)) {
			$state = $this->_sanitizeString($this->request["state"]);
			$append .= " state = :state,";
			$params["state"] = $state;
		}
		if ($append == "") {
			return $this->_result(false, "Nothing to update (/items/update)", false);
		}
		$append = substr($append, 0, -1);
		$sql .= $append . " WHERE item_id = :itemId";
		// Query the database
		$query = db_update($sql, $params);
		if ($query !== false) {
			// Log price changes to the database
			if ($price != false) {
				$sql = "INSERT INTO drink_item_price_history (item_id, item_price) VALUES (:itemId, :price)";
				$params = array();
				$params["itemId"] = $item_id;
				$params["price"] = $price;
				db_insert($sql, $params);
			}
			return $this->_result(true, "Success (/items/update)", true);
		}
		else {
			return $this->_result(false, "Failed to query database (/items/update)", false);
		}
	}

	// POST /items/delete/:item_id 
	private function _deleteItem() {
		$params = array();
		// Must be an admin to add items
		if (!$this->admin) {
			return $this->_result(false, "Must be an admin to add items (/items/update)", false);
		}
		// Check for 'item_id' parameter
		$item_id = 0;
		if (array_key_exists("item_id", $this->request)) {
			$item_id = $this->_sanitizeInt($this->request["item_id"]);
			$params["itemId"] = $item_id;
		}
		else {
			return $this->_result(false, "Missing parameter 'item_id' (/items/update)", false);
		}
		// Form the SQL query
		$sql = "DELETE FROM drink_items WHERE item_id = :itemId";
		// Query the database
		$query = db_delete($sql, $params);
		if ($query !== false) {
			return $this->_result(true, "Success (/items/delete)", true);
		}
		else {
			return $this->_result(false, "Failed to query database (/items/delete)", false);
		}
	}

	/*
	*	Temperatures endpoint
	*
	*	GET /temps/machines/:machine_id/:limit
	*/

	protected function temps() {
		$result = array();
		// Determine the specific method to call
		switch($this->verb) {
			case "machines":
				// GET /temps/machines/:machine_id/:limit
				if ($this->method == "GET") {
					$result = $this->_getMachineTemps();
				}
				else {
					$result = $this->_result(false, "Invalid HTTP method (/temps/machines)", false);
				}
				break;
			default:
				$result = $this->_result(false, "Invalid API method (/temps)", false);
		}
		// Return the response data
		return $result;
	}

	// GET /temps/machines/:machine_id/:limit/:offset
	private function _getMachineTemps() {
		$params = array();
		// Check for 'machine_id' parameter
		$machine_id = false;
		if (array_key_exists("machine_id", $this->request)) {
			$machine_id = $this->_sanitizeInt($this->request["machine_id"]);
			$params["machineId"] = $machine_id;
		}
		else {
			return $this->_result(false, "Missing parameter 'machine_id' (/temps/machines)", false);
		}
		// Check for limit
		$limit = 300;
		$offset = 0;
		if (array_key_exists("limit", $this->request)) {
			$limit = $this->_sanitizeInt($this->request["limit"]);
			if (array_key_exists("offset", $this->request)) {
				$offset = $this->_sanitizeInt($this->request["offset"]);
			}
		}
		$params["limit"] = $limit;
		$params["offset"] = $offset;
		// Form the SQL query
		$sql = "SELECT t.machine_id, t.time, t.temp, m.display_name 
						FROM temperature_log as t, machines as m
						WHERE t.machine_id = m.machine_id AND t.machine_id = :machineId
						ORDER BY t.time DESC LIMIT :limit OFFSET :offset";
		// Query the database
		$query = db_select($sql, $params);
		if ($query != false) {
			$tmp = array();
			for ($i = count($query) - 1; $i >= 0; $i--) {
				$tmp[] = array($query[$i]["time"], (float) $query[$i]["temp"]);
			}
			return $this->_result(true, "Success (/temps/machines)", $tmp);
		}
		else {
			return $this->_result(false, "Failed to query database (/temps/machines)", false);
		}
	}


	/*
	*	Drops Endpoint
	*
	*	GET /drops/status/:machine_id/:slot_num
	* POST /drops/drop/:ibutton/:slot_num/:machine_id
	*/
	protected function drops() {
		$result = array();
		switch ($this->verb) {
			case "status":
				// GET /drops/status/:machine_id/:slot_num
				if ($this->method == "GET") {
					$result = $this->_checkStatus();
				}
				else {
					$result = $this->_result(false, "Invalid HTTP method (/drops/status)", false);
				}
				break;
			case "drop":
				// POST /drops/drop/:ibutton/:slot_num/:machine_id
				if ($this->method == "POST") {
					$result = $this->_dropDrink();
				}
				else {
					$result = $this->_result(false, "Invalid HTTP method (/drops/drop)", false);
				}
				break;
			default:
				$result = $this->_result(false, "Invalid API method (/drops)", false);
				break;
		}
		return $result;
	}

	// POST /drops/drop/:ibutton/:slot_num/:machine_id/:delay
	private function _dropDrink() {
		// Map of machine_id's to machine aliases
		$machines = array(
			"1" => "ld",
			"2" => "d",
			"3" => "s"
		);
		// Check for ibutton
		if (array_key_exists("ibutton", $this->request)) {
			$this->drop_data["ibutton"] = $this->_sanitizeString($this->request["ibutton"]);
		}
		else {
			return $this->_result(false, "Missing parameter 'ibutton' (/drops/drop)", false);
		}
		// Check for slot_num
		if (array_key_exists("slot_num", $this->request)) {
			$this->drop_data["slot_num"] = $this->_sanitizeInt($this->request["slot_num"]);
		}
		else {
			return $this->_result(false, "Missing parameter 'slot_num' (/drops/drop)", false);
		}
		// Check for machine_id and convert to machine_alias
		if (array_key_exists("machine_id", $this->request)) {
			$machine_id = $this->_sanitizeString($this->request["machine_id"]);
			if (array_key_exists($machine_id, $machines)) {
				$this->drop_data["machine_alias"] = $machines[$machine_id];
			}
			else {
				return $this->_result(false, "Invalid 'machine_id' (/drops/drop)", false);
			}
		}
		else {
			return $this->_result(false, "Missing parameter 'machine_id' (/drops/drop)", false);
		}
		// Check for drop delay
		if (array_key_exists("delay", $this->request)) {
			$this->drop_data["delay"] = $this->_sanitizeInt($this->request["delay"]);
		}
		else {
			$this->drop_data["delay"] = 0;
		}
		// Connect to the drink server and drop a drink
		try {
			// Create a new client
			$this->elephant = new ElephantIOClient($this->DRINK_SERVER, "socket.io", 1, false, true, true);
			$this->elephant->init();
			// Validate iButton
			$this->elephant->emit('ibutton', array('ibutton' => $this->drop_data["ibutton"]));
			$this->elephant->on('ibutton_recv', function($data) {
				if ($this->_isWebsocketSuccess($data)) {
					// Connect to the drink machine
					$this->elephant->emit('machine', array('machine_id' => $this->drop_data["machine_alias"]));
					$this->elephant->on('machine_recv', function($data) {
						if ($this->_isWebsocketSuccess($data)) {
							// Drop the drink
							$this->elephant->emit('drop', array('slot_num' => $this->drop_data["slot_num"], 'delay' => $this->drop_data["delay"]));
							$this->elephant->on('drop_recv', function($data) {
								if ($this->_isWebsocketSuccess($data)) {
									$this->drop_result = array(true, "Drink dropped!", true);
									$this->elephant->close();
								}
								else {
									$this->drop_result = array(false, "Error dropping drink: ".$data." (/drops/drop)", false);
									$this->elephant->close();
								}
							});
						}
						else {
							$this->drop_result = array(false, "Error contacting machine: ".$data." (/drops/drop)", false);
							$this->elephant->close();
						}
					});
				}
				else {
					$this->drop_result = array(false, "Error authenticating iButton: ".$data." (/drops/drop)", false);
					$this->elephant->close();
				}
			});
			$this->elephant->keepAlive();
		}
		catch (Exception $e) {
			return $this->_result(false, $e->getMessage()." (/drops/drop)", false);
		}
		// Return result
		return $this->_result($this->drop_result[0], $this->drop_result[1], $this->drop_result[2]);
	}

	// Check the status of websocket connection (at all, to a machine, or to a specific slot)
	private function _checkStatus() {
		return $this->_result(true, "Success! (/drops/status)", true);
	}

	// Check the response of a websocket call for success/failure
	private function _isWebsocketSuccess($data) {
		$success = explode(":", $data);
		$success = $success[0];
		if ($success === "OK") {
			return true;
		}
		return false;
	}

}

?>