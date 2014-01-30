<?php

// Include the database connectivity functions
require_once('../utils/db_utils.php');

// Include the LDAP connectivity functions
require_once('../utils/ldap_utils.php');

// Include the abstract API class
require_once('./abstract_api.php');

/*
*	Concrete API implementation for WebDrink
*/
class DrinkAPI extends API
{
	private $data = array();
	private $admin = false;
	private $uid = false;

	private $debug = true;

	// Constructor
	public function __construct($request) {
		parent::__construct($request);
		// Grab the user's uid from Webauth
		if (array_key_exists("WEBAUTH_USER", $_SERVER)) {
			$this->uid = $_SERVER["WEBAUTH_USER"];
		} 
		else {
			//$this->uid = false;
			$this->uid = "bencentra";
		}
		// If the request is a POST method, verify the user is an admin
		if ($this->method == "POST" || $this->debug) {
			// Check if the user is an admin
			if ($this->uid != false) {
				$this->admin = $this->isAdmin($this->uid);
			}
		}
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

	// Test endpoint - make sure you can contact the API
	protected function test() {
		switch ($this->verb) {
			case "api":

				break;
			default:
				return array("status" => true, "message" => "Test Success!", "data" => true);
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
				$uid = "";
				if (array_key_exists(0, $this->args)) {
					$uid = $this->args[0];
				}
				else {
					$uid = $this->uid;
				}
				if (!$uid) {
					$result["status"] = false;
					$result["message"] = "Error: uid not supplied (users.ibutton)";
					$result["data"] = false;
					break;
				}
				// Sanitize uid
				$uid = $this->sanitizeUid($uid);
				// get_credits - GET /credits/:uid
				if (!array_key_exists(1, $this->args)) {
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
					if ($data) {
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
					if ($this->method != "POST" && !$this->debug) {
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
					// Sanitize value
					$value = (int) trim($this->args[1]);
					// Query LDAP
					$replace = array('drinkBalance' => $value);
					$data = ldap_update($uid, $replace);
					if ($data) {
							$result["status"] = true;
							$result["message"] = "Success (users.credits)";
							$result["data"] = true;
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
				$uid = "";
				if (array_key_exists(0, $this->args)) {
					$uid = $this->args[0];
				}
				else {
					$uid = $this->uid;
				}
				if (!$uid) {
					$result["status"] = false;
					$result["message"] = "Error: uid not supplied (users.ibutton)";
					$result["data"] = false;
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
			*	Endpoint: users.ibutton
			*
			*	Methods:
			*	- get_ibutton: GET /ibutton/:uid
			*/
			/*case "ibutton":
				// Check method type
				if ($this->method != "GET") {
					$result["status"] = false;
					$result["message"] = "Error: only accepts GET requests (users.ibutton)";
					$result["data"] = false;
					break;
				}
				// uid must be provided
				$uid = "";
				if (array_key_exists(0, $this->args)) {
					$uid = $this->args[0];
				}
				else {
					$uid = $this->uid;
				}
				if (!$uid) {
					$result["status"] = false;
					$result["message"] = "Error: uid not supplied (users.ibutton)";
					$result["data"] = false;
					break;
				}
				// Sanitize uid
				$uid = $this->sanitizeUid($uid);
				// Must be an admin (if not getting your own ibutton)
				if ($this->uid != $uid) {
					if (!$this->admin) {
						$result["status"] = false;
						$result["message"] = "Error: must be an admin to get another's ibutton (users.ibutton)";
						$result["data"] = false;
						break;
					}
				}
				// Query LDAP
				$fields = array('ibutton');
				$data = ldap_lookup($uid, $fields);
				if ($data) {
					$result["status"] = true;
					$result["message"] = "Success (users.ibutton)";
					$result["data"] = $data[0]['ibutton'][0];
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: failed to query LDAP (users.ibutton)";
					$result["data"] = false;
				}
				break;*/
			/*
			*	Endpoint: users.drops
			*
			*	Methods:
			*	- drops_one: GET /drops/:uid
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
				if (array_key_exists(0, $this->args)) {
					if ($this->args[0] == "user") {
						// Check for uid
						if (array_key_exists(1, $this->args)) {
							$uid = $this->args[1];
							// Sanitize uid
							$uid = $this->sanitizeUid($uid);
						}
						else {
							$result["status"] = false;
							$result["message"] = "Error: uid not provided (users.drops)";
							$result["data"] = false;
							break;
						}
						// Add a limit and offset, if provided
						if (array_key_exists(2, $this->args)) {
							$limit = (int) trim($this->args[2]);
							$params["limit"] = $limit;
						}
						if (array_key_exists(2, $this->args) && array_key_exists(3, $this->args)) {
							$offset = (int) trim($this->args[3]);
							$params["offset"] = $offset;
						}
					}
					else {
						// Add a limit and offset, if provided
						if (array_key_exists(0, $this->args)) {
							$limit = (int) trim($this->args[0]);
							$params["limit"] = $limit;
						}
						if (array_key_exists(0, $this->args) && array_key_exists(1, $this->args)) {
							$offset = (int) trim($this->args[1]);
							$params["offset"] = $offset;
						}
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
				}
				if ($offset) {
					$sql .= " OFFSET :offset";
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
			*/
			case "apikey": 

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
				if (array_key_exists(0, $this->args)) {
					$mid = $this->args[0];
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
				if (array_key_exists(0, $this->args)) {
					$mid = $this->args[0];
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
				if ($this->method != "POST" && !$this->debug) {
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
				if (array_key_exists(0, $this->args)) {
					$slot = (int) trim($this->args[0]);
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
				if (array_key_exists(1, $this->args)) {
					$mid = $this->sanitizeMachineId($this->args[1]);
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
				if (array_key_exists(2, $this->args)) {
					$item_id = (int) trim($this->args[2]);
					$append .= " item_id = :itemId,";
					$params["itemId"] = $item_id;
				}
				if (array_key_exists(3, $this->args)) {
					$available = (int) trim($this->args[3]);
					$append .= " available = :available,";
					$params["available"] = $available;
				}
				if (array_key_exists(4, $this->args)) {
					$status = trim((string) $this->args[4]);
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
				if ($this->method != "POST" && !$this->debug) {
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
				if (array_key_exists(0, $this->args)) {
					$name = trim((string) $this->args[0]);
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
				if (array_key_exists(1, $this->args)) {
					$price = (int) trim($this->args[1]);
					$params["price"] = $price;
				}
				else {
					$result["status"] = false;
					$result["message"] = "Error: price not supplied (items.add)";
					$result["data"] = false;
					break;
				}
				// Form the SQL query
				$sql = "INSERT INTO drink_items (item_name, item_price) VALUES (:name, :price)";
				// Make the query
				$query = db_insert($sql, $params);
				if ($query) {
					$result["status"] = true;
					$result["message"] = "Success (items.add)";
					$result["data"] = db_last_insert_id();
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
				if ($this->method != "POST" && !$this->debug) {
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
				if (array_key_exists(0, $this->args)) {
					$item_id = $this->sanitizeItemId($this->args[0]);
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
				if (array_key_exists(1, $this->args)) {
					$name = trim((string) $this->args[1]);
					$append .= " item_name = :name,";
					$params["name"] = $name;
				}
				if (array_key_exists(2, $this->args)) {
					$price = (int) trim($this->args[2]);
					$append .= " item_price = :price,";
					$params["price"] = $price;
				}
				if (array_key_exists(3, $this->args)) {
					$state = trim((string) $this->args[3]);
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
				if ($this->method != "POST" && !$this->debug) {
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
				if (array_key_exists(0, $this->args)) {
					$item_id = $this->sanitizeItemId($this->args[0]);
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
				if (array_key_exists(0, $this->args)) {
					$mid = $this->sanitizeMachineId($this->args[0]);
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
				if (array_key_exists(1, $this->args)) {
					$limit = (int) trim($this->args[1]);
				}
				$params["limit"] = $limit;
				// Form the SQL query
				$sql = "SELECT t.machine_id, t.time, t.temp, m.display_name 
						FROM temperature_log as t, machines as m
						WHERE t.machine_id = m.machine_id AND t.machine_id = :machineId
						ORDER BY t.time DESC LIMIT :limit";
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