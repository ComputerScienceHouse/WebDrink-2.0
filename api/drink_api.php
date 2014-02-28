<?php

// Include the database connectivity functions
require_once('../utils/db_utils.php');
// Include the LDAP connectivity functions
require_once('../utils/ldap_utils.php');
// Include the abstract API class
require_once('./abstract_api.php');
// Include configuration info
require_once("../config.php");
//define("DEBUG", true);

/*
*	Concrete API implementation for WebDrink
*/
class DrinkAPI extends API
{
	private $data = array();
	private $admin = false;
	private $uid = false;
	private $webauth = false;

	// Constructor
	public function __construct($request) {
		parent::__construct($request);
		// Grab the user's uid from Webauth
		if (array_key_exists("WEBAUTH_USER", $_SERVER)) {
			$this->uid = $_SERVER["WEBAUTH_USER"];
			$this->webauth = true;
		} 
		else if ($this->api_key) {
			$this->uid = $this->lookupAPIKey();
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
		// Check if the user is an admin
		if ($this->uid != false) {
			$this->admin = $this->isAdmin($this->uid);
		}
		// If the request is a POST method, verify the user is an admin
		//if ($this->method != "POST" || DEBUG) {
			
		//}
	}

	// Determine if the user is a Drink Admin or not
	protected function isAdmin($uid) {
		$fields = array('drinkAdmin');
		$result = ldap_lookup($uid, $fields);
		if (isset($result[0]['drinkadmin'][0])) {
			return $result[0]['drinkadmin'][0];
		}
		else {
			return false;
		}
	}

	// Lookup api key
	protected function lookupAPIKey() {
		$sql = "SELECT * FROM api_keys WHERE api_key = :apiKey";
		$params["apiKey"] = $this->api_key;
		$query = db_select($sql, $params); 
		if ($query) {
			return $query[0]["uid"];
		} 
		else {
			return false;
		}
	}

	// Sanitize uid
	protected function sanitizeUid($uid) {
		return trim((string) $uid);
	}

	// Sanitize machine_id
	protected function sanitizeMachineId($mid) {
		return (int) trim($mid);
	}

	// Sanitize item_id
	protected function sanitizeItemId($iid) {
		return (int) trim($iid);
	}

	// Sanitize item_name
	protected function sanitizeItemName($name) {
		return trim((string)$name);
	}

	// Test endpoint - make sure you can contact the API
	protected function test() {
		switch ($this->verb) {
			case "api":
				if ($this->uid && $this->api_key) {
					return array("status" => true, "message" => "User Found!", "data" => $this->uid);
				}
				else if (!$this->uid && $this->api_key) {
					return array("status" => false, "message" => "No User Found!", "data" => false);
				}
				else {
					return array("status" => false, "message" => "No API key included!", "data" => false);
				}
				break;
			default:
				return array("status" => true, "message" => "Test Success!", "data" => $this->request);
		}
	}

	// Users enpoint - call the various user-related API methods
	protected function users() {
		// Create an array to store response data
		$result = array();
		// Create an array to store parameters for SQL queries
		$params = array();
		// Determine the specific method to call
		switch($this->verb) {
			/*
			*	Endpoint: users.credits
			*
			*	Methods: 
			*	- get_credits - GET /credits/:uid
			*	- update_credits - POST /credits/:uid/:value
			*/
			case "credits":
				// uid must be provided for both get_credits and update_credits
				$uid = false;
				if (array_key_exists("uid", $this->request)) {
					$uid = $this->request["uid"];
				}
				//else {
				//	$uid = $this->uid;
				//}
				if (!$uid) {
					$result["status"] = false;
					$result["message"] = "Error: uid not supplied (users.credits)";
					$result["data"] = $this->request;
					break;
				}
				// Sanitize uid
				$uid = $this->sanitizeUid($uid);
				// get_credits - GET /credits/:uid
				if (!array_key_exists("value", $this->request)) {
					// Check method type
					if ($this->method != "GET") {
						$result["status"] = false;
						$result["message"] = "Error: only accepts GET requests (users.credits)";
						$result["data"] = false;
						break;
					}
					// Must be an admin (if not getting your own credits)
					if ($this->uid != $uid) {
						if (!$this->admin) {
							$result["status"] = false;
							$result["message"] = "Error: must be an admin to get another's credits (users.credits)";
							$result["data"] = false;
							break;
						}
					}
					// Query LDAP
					$fields = array('drinkBalance');
					$data = ldap_lookup($uid, $fields);
					if (array_key_exists(0, $data)) {
						$result["status"] = true;
						$result["message"] = "Success (users.credits)";
						$result["data"] = $data[0]['drinkbalance'][0];
					}
					else {
						$result["status"] = false;
						$result["message"] = "Error: failed to query LDAP (users.credits)";
						$result["data"] = false;
					}
				}
				// update_credits - POST /credits/:uid/:value
				else {
					// Check method type
					if ($this->method != "POST") {
						$result["status"] = false;
						$result["message"] = "Error: only accepts POST requests (users.credits)";
						$result["data"] = false;
						break;
					}
					// Must be an admin
					if (!$this->admin) {
						$result["status"] = false;
						$result["message"] = "Error: must be an admin to update credits (users.credits)";
						$result["data"] = false;
						break;
					}
					// Make sure the parameters are included
					/*if (count($this->args) != 3) {
						$result["status"] = false;
						$result["message"] = "Error: invalid number of parameters (users.credits)";
						$result["data"] = false;
						break;
					}*/
					// Determine the type and amount of credit change
					$value = (int) trim($this->request["value"]);
					$type = (array_key_exists("type", $this->request)) ? $this->request["type"] : "add";
					$type = strtolower(trim((string) $type));
					if ($type != "add" && $type != "subtract") {
						$result["status"] = false;
						$result["message"] = "Error: invalid type (users.credits)";
						$result["data"] = false;
						break;
					}
					// Check the direction of the transaction
					$direction = "in";
					if ($value < 0 && $type == "add" || $value >= 0 && $type == "subtract") {
						$direction = "out";
					}
					// Query LDAP for old balance
					$fields = array('drinkBalance');
					$data = ldap_lookup($uid, $fields);
					if (array_key_exists(0, $data)) {
						$oldBalance = $data[0]['drinkbalance'][0];
						// Determine the new balance
						$newBalance = 0;
						if ($type == "add") {
							$newBalance = $oldBalance + $value;
						}
						else {
							$newBalance = $oldBalance - $value;
						}
						// Query LDAP to update the balance
						$replace = array('drinkBalance' => $newBalance);
						$data = ldap_update($uid, $replace);
						if ($data) {
							$result["status"] = true;
							$result["message"] = "Success (users.credits)";
							$result["data"] = $newBalance;
						}
						else {
							$result["status"] = false;
							$result["message"] = "Error: failed to query LDAP (users.credits)";
							$result["data"] = false;
						}
						// Add the change to the logs
						$sql = "INSERT INTO money_log (username, admin, amount, direction, reason) VALUES (:uid, :admin, :amount, :direction, :reason)";
						$params["uid"] = $uid;
						$params["admin"] = $this->uid;
						$params["amount"] = $value;
						$params["direction"] = $direction;
						$params["reason"] = $type;
						$query = db_insert($sql, $params);
						// (TODO: handle failure?)
						//$result["message"] .= " also logs work";
					}
					else {
						$result["status"] = false;
						$result["message"] = "Error: failed to query LDAP (users.credits)";
						$result["data"] = false;
					}
				}
				break;
			/*
			*	Endpoint: users.search
			*
			*	Methods:
			*	- search: GET /search/:uid
			*/
			case "search":
				// Check method type
				if ($this->method != "GET") {
					$result["status"] = false;
					$result["message"] = "Error: only accepts GET requests (users.search)";
					$result["data"] = false;
					break;
				}
				// uid must be provided
				$uid = false;
				if (array_key_exists("uid", $this->request)) {
					$uid = $this->request["uid"];
				}
				//else {
				//	$uid = $this->uid;
				//}
				if (!$uid) {
					$result["status"] = false;
					$result["message"] = "Error: uid not supplied (users.search)";
					$result["data"] = $this->request;
					break;
				}
				// Sanitize uid
				$uid = $this->sanitizeUid($uid);
				// Query LDAP
				$fields = array('uid', 'cn');
				$data = ldap_lookup($uid."*", $fields);
				if ($data) {
					$result["status"] = true;
					$result["message"] = "Success (users.search)";
					// Format the data
					$tmp = array();
					$i = 0;
					foreach ($data as $user) {
						$tmp[$i] = array("uid" => $user["uid"][0], "cn" => $user["cn"][0]);
						$i++;
					}
					array_shift($tmp);
					$result["data"] = $tmp;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: failed to query LDAP (users.search)";
					$result["data"] = false;
				}
				break;
			/*
			*	
			*/
			case "info":
				if (!$this->api_key) {
					$result["status"] = false;
					$result["message"] = "Error: must include api_key for this request (users.info)";
					$result["data"] = false;
					break;
				}
				if (!$this->uid || $this->uid == null) {
					$result["status"] = false;
					$result["message"] = "Error: user not found (users.info)";
					$result["data"] = false;
					break;
				}
				if ($this->method != "GET") {
					$result["status"] = false;
 					$result["message"] = "Error: only accepts GET requests (users.info)";
 					$result["data"] = false;
 					break;
 				}
				$fields = array('drinkBalance', 'drinkAdmin', 'ibutton', 'cn');
				$data = ldap_lookup($this->uid, $fields);
				if (array_key_exists(0, $data)) {
					$tmp = array();
					$tmp["uid"] = $this->uid;
					$tmp["credits"] = $data[0]["drinkbalance"][0];
					$tmp["admin"] = $data[0]["drinkadmin"][0];
					$tmp["ibutton"] = $data[0]["ibutton"][0];
					$tmp["cn"] = $data[0]["cn"][0];
					$result["status"] = true;
					$result["message"] = "Success (users.info)";
					$result["data"] = $tmp;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: failed to query LDAP (users.info)";
					$result["data"] = false;
				}
				break;
			/*
			*	Endpoint: users.drops
			*
			*	Methods:
			*	- drops_one: GET /drops/user/:uid
			*	- drops_all: GET /drops
			*/
			case "drops":
				// Check method type
				if ($this->method != "GET") {
					$result["status"] = false;
					$result["message"] = "Error: only accepts GET requests (users.drops)";
					$result["data"] = false;
					break;
				}
				// See if we're getting a specific user's drops
				$uid = false;
				$limit = false;
				$offset = false;
				if (array_key_exists("uid", $this->request)) {
					$uid = $this->request["uid"];
					$uid = $this->sanitizeUid($uid);
				}
				if (array_key_exists("limit", $this->request)) {
					$limit = $this->request["limit"];
					$limit = (int) trim($limit);
					if (array_key_exists("offset", $this->request)) {
						$offset = $this->request["offset"];
						$offset = (int) trim($offset);
					}
				}
				// Form the SQL query
				$sql = "SELECT l.drop_log_id, l.machine_id, m.display_name, l.slot, l.username, l.time, l.status, l.item_id, i.item_name, l.current_item_price 
						FROM drop_log as l, machines as m, drink_items as i WHERE";
				// Add the uid, if provided
				if ($uid) {
					$sql .= " l.username = :username AND";
					$params["username"] = $uid;
				}
				$sql .= " m.machine_id = l.machine_id AND i.item_id = l.item_id 
						ORDER BY l.drop_log_id DESC";
				if ($limit) {
					$sql .= " LIMIT :limit";
					$params["limit"] = $limit;
				}
				if ($offset) {
					$sql .= " OFFSET :offset";
					$params["offset"] = $offset;
				}
				// Query the database
				$query = db_select($sql, $params);
				if ($query) {
					$result["status"] = true;
					$result["message"] = "Success (users.drops)";
					$result["data"] = $query;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: failed to query database (users.drops)";
					$result["data"] = false;
				}
				break;
			/*
			*	Endpoint: users.apikey
			*
			*	Methods:
			*	- get_key: GET /apikey
			*	- generate_key: POST /apikey
			*	- delete_key: DELETE /apikey
			*/
			case "apikey": 
				// Must be on CSH systems (behind Webauth) to retrieve your API key
				if (!$this->webauth) {
					$result["status"] = false;
					$result["message"] = "Error: must use WebDrink to retrieve API key (users.apikey)";
					$result["data"] = false;
					break;
				}
				// UID must be set
				if (!$this->uid) {
					$result["status"] = false;
					$result["message"] = "Error: uid not found (users.apikey)";
					$result["data"] = false;
					break;
				}
				// get_key: GET /apikey
				if ($this->method == "GET") {
					// Form the SQL query
					$sql = "SELECT * FROM api_keys WHERE uid = :uid";
					$params["uid"] = $this->uid;
					// Query the database
					$query = db_select($sql, $params);
					if ($query) {
						$result["status"] = true;
						$result["message"] = "Success (users.apikey)";
						$result["data"] = $query[0];
					}
					else {
						$result["status"] = false;
						$result["message"] = "Error: failed to query database (users.apikey)";
						$result["data"] = false;
					}
				}
				// generate_key: POST /apikey
				else if ($this->method == "POST") {
					// Generate an API key
					$salt = time();
					//$apiKey = hash("fnv164", hash("sha512", $this->uid.$salt));
					$apiKey = md5(hash("sha512", $this->uid.$salt));
					// Form the SQL query
					$sql = "REPLACE INTO api_keys (uid, api_key) VALUES (:uid, :apiKey)";
					$params["uid"] = $this->uid;
					$params["apiKey"] = $apiKey;
					// Query the database
					$query = db_insert($sql, $params);
					if ($query) {
						$data["api_key"] = $apiKey;
						$data["date"] = date("Y-m-d H:i:s");
						$data["uid"] = $this->uid;
						$result["status"] = true;
						$result["message"] = "Success (users.apikey)";
						$result["data"] = $data;
					}
					else {
						$result["status"] = false;
						$result["message"] = "Error: failed to query database (users.apikey)";
						$result["data"] = false;
					}
				}
				// delete_key: DELETE /apikey
				else if ($this->method == "DELETE") {
					// Form the SQL query
					$sql = "DELETE FROM api_keys WHERE uid = :uid";
					$params["uid"] = $this->uid;
					// Query the database
					$query = db_delete($sql, $params);
					if ($query) {
						$result["status"] = true;
						$result["message"] = "Success (users.apikey)";
						$result["data"] = true;
					}
					else {
						$result["status"] = false;
						$result["message"] = "Error: failed to query database (users.apikey)";
						$result["data"] = false;
					}
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: unsupported HTTP method (users.apikey)";
					$result["data"] = false;
				}
				break;
			/*
			*	Base case - no specific API method called
			*/
			default:
				$result["status"] = false;
				$result["message"] = "Invalid API method call (users)";
				$result["data"] = false;
				break;
		}
		// Return the response data
		return $result;
	}

	// Machines endpoint - call the various machine-related API methods
	protected function machines() {
		// Create an array to store response data
		$result = array();
		// Create an array to store parameters for SQL queries
		$params = array();
		// Determine the specific method to call
		switch($this->verb) {
			/*
			*	Enpoint: machines.stock
			*	
			*	Methods:
			*	- stock_one: GET stock/:machine_id
			*	- stock_all: GET stock
			*/
			case "stock":
				// Check method type
				if ($this->method != "GET") {
					$result["status"] = false;
					$result["message"] = "Error: only accepts GET requests (machines.stock)";
					$result["data"] = false;
					break;
				}
				// Check for machine_id
				$mid = false;
				if (array_key_exists("machine_id", $this->request)) {
					$mid = $this->request["machine_id"];
					// Sanitize uid
					$mid = $this->sanitizeMachineId($mid);
				}
				// Form the SQL query
				$sql = "SELECT s.slot_num, s.machine_id, m.display_name, i.item_id, i.item_name, i.item_price, s.available, s.status 
						FROM slots as s, machines as m, drink_items as i 
						WHERE i.item_id = s.item_id AND m.machine_id = s.machine_id";
				if ($mid) {	
					$sql .= " AND s.machine_id = :machineId";
					$params["machineId"] = $mid;
				}
				// Query the database
				$query = db_select($sql, $params);
				if ($query) {
					$data = array();
					foreach($query as $q) {
						$data[$q["machine_id"]][] = $q;
					}
					$result["status"] = true;
					$result["message"] = "Success (machines.stock)";
					$result["data"] = $data;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: failed to query database (machines.stock)";
					$result["data"] = false;
				}
				break;
			/*
			*	Endpoint: machines.info
			*
			*	Methods: 
			*	- info_one: GET /info/:machine_id
			*	- info_all: GET /info
			*/
			case "info":
				// Check method type
				if ($this->method != "GET") {
					$result["status"] = false;
					$result["message"] = "Error: only accepts GET requests (machines.info)";
					$result["data"] = false;
					break;
				}
				// Check for machine_id
				$mid = false;
				if (array_key_exists("machine_id", $this->request)) {
					$mid = $this->request["machine_id"];
					// Sanitize uid
					$mid = $this->sanitizeMachineId($mid);
				}
				// Form the SQL query
				$sql = "SELECT m.machine_id, m.name, m.display_name, a.alias_id, a.alias 
						FROM machines as m, machine_aliases as a 
						WHERE a.machine_id = m.machine_id";
				if ($mid) {	
					$sql .= " AND m.machine_id = :machineId";
					$params["machineId"] = $mid;
				}
				// Query the database
				$query = db_select($sql, $params);
				if ($query) {
					$result["status"] = true;
					$result["message"] = "Success (machines.info)";
					$result["data"] = $query;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: failed to query database (machines.info)";
					$result["data"] = false;
				}		
				break;
			/*
			*	Endpoint: machines.slot
			*
			*	Methods:
			*	- update_slot: POST slot/:slot_num/:machine_id/:item_id/:available/:status
			*/
			case "slot":
				// Check method type
				if ($this->method != "POST") {
					$result["status"] = false;
					$result["message"] = "Error: only accepts POST requests (machines.slot)";
					$result["data"] = false;
					break;
				}
				// Must be an admin
				if (!$this->admin) {
					$result["status"] = false;
					$result["message"] = "Error: must be an admin to update slots (machines.slot)";
					$result["data"] = false;
					break;
				}
				// Check for slot_num
				$slot = 0;
				if (array_key_exists("slot_num", $this->request)) {
					$slot = (int) trim($this->request["slot_num"]);
					$params["slotNum"] = $slot;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: slot_num number not supplied (machines.slot)";
					$result["data"] = false;
					break;
				}
				// Check for machine_id
				$mid = 0;
				if (array_key_exists("machine_id", $this->request)) {
					$mid = $this->sanitizeMachineId($this->request["machine_id"]);
					$params["machineId"] = $mid;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: machine_id number not supplied (machines.slot)";
					$result["data"] = false;
					break;
				}
				// Form the SQL query (TODO: Find a good way to not require all parameters)
				$sql = "UPDATE slots SET";
				$append = "";
				if (array_key_exists("item_id", $this->request)) {
					$item_id = (int) trim($this->request["item_id"]);
					$append .= " item_id = :itemId,";
					$params["itemId"] = $item_id;
				}
				if (array_key_exists("available", $this->request)) {
					$available = (int) trim($this->request["available"]);
					$append .= " available = :available,";
					$params["available"] = $available;
				}
				if (array_key_exists("status", $this->request)) {
					$status = trim((string) $this->request["status"]);
					$append .= " status = :status,";
					$params["status"] = $status;
				}
				if ($append == "") {
					$result["status"] = false;
					$result["message"] = "Error: nothing to update (machines.slot)";
					$result["data"] = false;
					break;
				}
				$append = substr($append, 0, -1);
				$sql .= $append . " WHERE slot_num = :slotNum AND machine_id = :machineId";
				// Query the database
				$query = db_update($sql, $params);
				if ($query) {
					$result["status"] = true;
					$result["message"] = "Success (machines.slot)";
					$result["data"] = true;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: failed to query database (machines.slot)";
					$result["data"] = false;
				}
				break;
			/*
			*	Base case - no specific API method called
			*/
			default:
				$result["status"] = false;
				$result["message"] = "Invalid API method call (machines)";
				$result["data"] = false;
				break;
		}
		// Return the response data
		return $result;
	}

	protected function items() {
		// Create an array to store response data
		$result = array();
		// Create an array to store parameters for SQL queries
		$params = array();
		// Determine the specific method to call
		switch($this->verb) {
			/*
			*	Endpoint: items.list
			*
			*	Methods:
			*	- list: GET /list
			*/
			case "list":
				// Check method type
				if ($this->method != "GET") {
					$result["status"] = false;
					$result["message"] = "Error: only accepts GET requests (items.list)";
					$result["data"] = false;
					break;
				}
				// Form the SQL query (TODO: Either clean up database or make a toggle for active/inactive)
				$sql = "SELECT item_id, item_name, item_price, state FROM drink_items WHERE state = 'active'";
				// Query the database
				$query = db_select($sql, $params);
				if ($query) {
					$result["status"] = true;
					$result["message"] = "Success (items.list)";
					$result["data"] = $query;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: failed to query database (items.list)";
					$result["data"] = $query;
				}
				break;
			/*
			*	Endpoint: items.add
			*
			*	Methods:
			*	- add: GET /items/add/:name/:price
			*/
			case "add":
				// Check method type
				if ($this->method != "POST") {
					$result["status"] = false;
					$result["message"] = "Error: only accepts POST requests (items.add)";
					$result["data"] = false;
					break;
				}
				// Must be an admin
				if (!$this->admin) {
					$result["status"] = false;
					$result["message"] = "Error: must be an admin to update items (items.add)";
					$result["data"] = false;
					break;
				}
				// Check for name
				$name = "";
				if (array_key_exists("name", $this->request)) {
					$name = $this->sanitizeItemName($this->request["name"]);
					$params["name"] = $name;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: name not supplied (items.add)";
					$result["data"] = false;
					break;
				}
				// Check for price
				$price = 0;
				if (array_key_exists("price", $this->request)) {
					$price = (int) trim($this->request["price"]);
					$params["price"] = $price;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: price not supplied (items.add)";
					$result["data"] = false;
					break;
				}
				// Make sure price isn't negative
				if ($price < 0) {
					$result["status"] = false;
					$result["message"] = "Error: price can't be negative (items.add)";
					$result["data"] = false;
					break;
				}
				// Form the SQL query
				$sql = "INSERT INTO drink_items (item_name, item_price) VALUES (:name, :price)";
				// Make the query
				$query = db_insert($sql, $params);
				if ($query) {
					$item_id = db_last_insert_id();
					$result["status"] = true;
					$result["message"] = "Success (items.add)";
					$result["data"] = $item_id;
					// Log price changes to the database
					$sql = "INSERT INTO drink_item_price_history (item_id, item_price) VALUES (:itemId, :price)";
					$params = array();
					$params["itemId"] = $item_id;
					$params["price"] = $price;
					$query = db_insert($sql, $params);
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: failed to query database (items.add)";
					$result["data"] = false;
				}
				break;
			/*
			*	Endpoint: items.update
			*
			*	Methods:
			*	- update_item: POST /update/:item_id/:name/:price/:status
			*/
			case "update":
				// Check method type
				if ($this->method != "POST") {
					$result["status"] = false;
					$result["message"] = "Error: only accepts POST requests (items.update)";
					$result["data"] = false;
					break;
				}
				// Must be an admin
				if (!$this->admin) {
					$result["status"] = false;
					$result["message"] = "Error: must be an admin to update items (items.update)";
					$result["data"] = false;
					break;
				}
				// Make sure an item_id was provided
				$item_id = 0;
				if (array_key_exists("item_id", $this->request)) {
					$item_id = $this->sanitizeItemId($this->request["item_id"]);
					$params["itemId"] = $item_id;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: item_id not provided (items.update)";
					$result["data"] = false;
					break;
				}
				// Form the SQL query
				$append = "";
				$sql = "UPDATE drink_items SET";
				if (array_key_exists("name", $this->request)) {
					$name = $this->sanitizeItemName($this->request["name"]);
					// Make sure the name isn't empty
					if (strlen($name) <= 0) {
						$result["status"] = false;
						$result["message"] = "Error: name can't be empty (items.add)";
						$result["data"] = false;
						break;
					}
					$append .= " item_name = :name,";
					$params["name"] = $name;
				}
				if (array_key_exists("price", $this->request)) {
					$price = (int) trim($this->request["price"]);
					// Make sure price isn't negative
					if ($price < 0) {
						$result["status"] = false;
						$result["message"] = "Error: price can't be negative (items.update)";
						$result["data"] = false;
						break;
					}
					$append .= " item_price = :price,";
					$params["price"] = $price;
				}
				if (array_key_exists("state", $this->request)) {
					$state = trim((string) $this->request["state"]);
					$append .= " state = :state,";
					$params["state"] = $state;
				}
				if ($append == "") {
					$result["result"] = false;
					$result["message"] = "Error: invalid number of parameters (items.update)";
					$result["data"] = false;
					break;
				}
				$append = substr($append, 0, -1);
				$sql .= $append . " WHERE item_id = :itemId";
				// Make the Query
				$query = db_update($sql, $params);
				if ($query) {
					$result["status"] = true;
					$result["message"] = "Success (items.update)";
					$result["data"] = true;
					// Log price changes to the database
					$sql = "INSERT INTO drink_item_price_history (item_id, item_price) VALUES (:itemId, :price)";
					$params = array();
					$params["itemId"] = $item_id;
					$query = db_insert($sql, $params);
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: failed to query database (items.update)";
					$result["data"] = false;
				}
				break;
			/*
			*	Endpoint: items.delete
			*
			*	Methods: 
			*	- delete_item: POST /delete/:item_id
			*/
			case "delete":
				// Check method type
				if ($this->method != "POST") {
					$result["status"] = false;
					$result["message"] = "Error: only accepts POST requests (items.delete)";
					$result["data"] = false;
					break;
				}
				// Must be an admin
				if (!$this->admin) {
					$result["status"] = false;
					$result["message"] = "Error: must be an admin to update items (items.delete)";
					$result["data"] = false;
					break;
				}
				// Make sure an item_id was provided
				$item_id = 0;
				if (array_key_exists("item_id", $this->request)) {
					$item_id = $this->sanitizeItemId($this->request["item_id"]);
					$params["itemId"] = $item_id;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: itemId not provided (items.delete)";
					$result["data"] = false;
					break;
				}
				// Form the SQL query
				$sql = "DELETE FROM drink_items WHERE item_id = :itemId";
				// Make the Query
				$query = db_delete($sql, $params);
				if ($query) {
					$result["status"] = true;
					$result["message"] = "Success (items.delete)";
					$result["data"] = true;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: failed to query database (items.delete)";
					$result["data"] = false;
				}
				break;
			/*
			*	Base case - no specific API method called
			*/
			default:
				$result["status"] = false;
				$result["message"] = "Invalid API method call (items)";
				$result["data"] = false;
				break;
		}
		// Return the response data
		return $result;
	}

	// Temps endpoint, get temperature data for the drink machines
	protected function temps() {
		// Create an array to store response data
		$result = array();
		// Create an array to store parameters for SQL queries
		$params = array();
		// Determine the specific method to call
		switch($this->verb) {
			/*
			*	Endpoint: temps.machines
			*
			*	Methods:
			*	- machine_one: GET /machines/:machine_id/:limit
			*/
			case "machines":
				// Check method type
				if ($this->method != "GET") {
					$result["status"] = false;
					$result["message"] = "Error: only accepts GET requests (temps.machine)";
					$result["data"] = false;
					break;
				}
				// Check for machine_id
				$mid = false;
				if (array_key_exists("machine_id", $this->request)) {
					$mid = $this->sanitizeMachineId($this->request["machine_id"]);
					$params["machineId"] = $mid;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: machine_id not provided (temps.machine)";
					$result["data"] = false;
					break;
				}
				// Check for limit (TODO: Allow for offset as well)
				$limit = 300;
				if (array_key_exists("limit", $this->request)) {
					$limit = (int) trim($this->request["limit"]);
				}
				$params["limit"] = $limit;
				$offset = 0;
				if (array_key_exists("offset", $this->request)) {
					$offset = (int) trim($this->request["offset"]);
				}
				$params["offset"] = $offset;
				// Form the SQL query
				$sql = "SELECT t.machine_id, t.time, t.temp, m.display_name 
						FROM temperature_log as t, machines as m
						WHERE t.machine_id = m.machine_id AND t.machine_id = :machineId
						ORDER BY t.time DESC LIMIT :limit OFFSET :offset";
				// Query the database
				$query = db_select($sql, $params);	
				if ($query) {
					$data = array();
					for ($i = count($query) - 1; $i >= 0; $i--) {
						//$data["temp"][] = $temp["temp"];
						//$data["time"][] = $temp["time"];
						//$data["temp"][] = (float) $query[$i]["temp"];
						//$data["time"][] = $query[$i]["time"];
						$data[] = array(strtotime($query[$i]["time"]), (float) $query[$i]["temp"]);
					}
					$result["status"] = true;
					$result["message"] = "Success (temps.machine)";
					$result["data"] = $data;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: failed to query database (temps.machine)";
					$result["data"] = $query;
				}
				break;		
			/*
			*	Base case - no specific API method called
			*/	
			default:
				$result["status"] = false;
				$result["message"] = "Invalid API method call (temps)";
				$result["data"] = $this->verb;
				break;
		}
		// Return the response data
		return $result;
	}
}

?>