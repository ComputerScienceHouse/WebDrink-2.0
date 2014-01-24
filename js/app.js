var baseUrl = "api/"; // "api/index.php?request="

// Have the navbar collapse (on mobile) after a page is selected
document.addEventListener("DOMContentLoaded", function() {
	jQuery('.navitem').click(function() {
		if (jQuery(window).width() <= 768)
			jQuery('#navbar').collapse("hide");
	});
}, true);

// Register the app with angular
var app = angular.module("WebDrink", ['ngRoute']);

// Routes
app.config(['$routeProvider', function($routeProvider) {
	$routeProvider.
		when('/machines', {
			templateUrl: 'partials/machines.html',
			controller: 'MachineCtrl'
		}).
		when('/drops', {
			templateUrl: 'partials/drops.html',
			controller: 'DropCtrl'
		}).
		otherwise({
			redirectTo: '/machines'
		});
}]);

// Alert directive - data contains show (boolean), type (string, alert class), and message (string)
app.directive("alert", function() {
	return {
		restrict: "E",
		templateUrl: "templates/alert.html",
		scope: {
			alert: "=data"
		}
	};
});

// Drop Service - retrieve a user's drop history, etc.
app.factory("DropService", function($http, $window, $log) {
	return {
		// Get a user's drop history
		getDrops: function(uid, limit, offset, successCallback, errorCallback) {
			var url = baseUrl+"users/drops";
			if (uid !== false) {
				url += "/user/"+uid;
			}
			if (limit > 0) {
				url += "/"+limit;
			}
			if (limit > 0 && offset > 0) {
				url += "/"+offset;
			}
			$http.get(url).success(successCallback).error(errorCallback);
			//$log.log(url);
		}
	};
});

// Machine Service - Get and update data about the drink machines, machine stock, etc
app.factory("MachineService", function($http, $window, $log) {
	return {
		// Get a list of information about all drink machines
		getMachineAll: function(successCallback, errorCallback) {
			$http.get(baseUrl+"machines/info").success(successCallback).error(errorCallback);
		},
		// Get the stock of all drink machines
		getStockAll: function(successCallback, errorCallback) {
			$http.get(baseUrl+"machines/stock").success(successCallback).error(errorCallback);
		},
		// Get a list of all drink items
		getItemAll: function(successCallback, errorCallback) {
			$http.get(baseUrl+"items/list").success(successCallback).error(errorCallback);
		},
		// Update the items and state of a slot in a drink machine
		updateSlot: function(data, successCallback, errorCallback) {
			var url = baseUrl+"machines/slot/"+data.slot_num+"/"+data.machine_id;
			if (data.hasOwnProperty("item_id"))
				url += "/"+data.item_id;
			if (data.hasOwnProperty("available")) 
				url += "/"+data.available;
			if (data.hasOwnProperty("status")) 
				url += "/"+data.status;
			$http.post(url, {}).success(successCallback).error(errorCallback);
		},
		// Get the current user's drink credits
		getCredits: function(uid, successCallback, errorCallback) {
			$http.get(baseUrl+"users/credits").success(successCallback).error(errorCallback);
		}
	};
});

// Root controller - for shared data/services
function RootCtrl($scope, $log, $window, $location) {
	// Current user data
	$scope.current_user = $window.current_user;
	// Current page
	$scope.location = $location;
	// Machine data
	$scope.machines = {
		1: {
			"id": 1,
			"alias": "littledrink",
			"name": "Little Drink"
		},
		2: {
			"id": 2,
			"alias": "bigdrink",
			"name": "Big Drink"
		},
		3: {
			"id": 3,
			"alias": "snack",
			"name": "Snack"
		}
	};
	
	// Default data for any alert directives
	$scope.Alert = function() {
		return {
			show: false,
			closeable: true,
			type: "alert-warning",
			message: "default"
		};
	}

	// Activate the admin dropdown menu
	if ($scope.current_user.admin) {
		$scope.toggleAdminDropdown = function() {
			jQuery("#adminDropdown").dropdown('toggle');
		}
	}
}

// Controller for the machines page
function MachineCtrl($scope, $log, $window, $timeout, MachineService) {
	// Initialize scope variables
	$scope.stock = {};			// Stock of all machines
	$scope.items = {};			// All existing drink items (admin only)
	$scope.current_slot = {};	// Current slot being dropped/edited
	$scope.new_slot = {};		// New data for slot being edited
	$scope.delay = 0;			// Delay for dropping a drink
	$scope.dropping_message = "";
	$scope.message = "";		// Message to display after edit

	$scope.websocket_alert = $scope.Alert();
	$scope.websocket_alert.message = "Warning: Websocket not connected!";
	$scope.websocket_alert.show = true;
	$scope.websocket_alert.closeable = false;

	// Get the initial stock
	MachineService.getStockAll(
		function (response) { 
			if (response.status) {
				$scope.stock = response.data;
			}
			else {
				$log.log(response.message);
			}
		}, 
		function (error) { 
			$log.log(error); 
		}
	);
	// Admin only functions
	if ($scope.current_user.admin) {
		// Get all items (for editing a slot)
		MachineService.getItemAll(
			function (response) {
				if (response.status) {
					$scope.items = response.data;
					//$log.log($scope.items);
				}
				else {
					$log.log(response.message);
				}
			},
			function (error) {
				$log.log(error);
			}
		);
		// Lookup an item by id
		$scope.lookupItem = function (id) {
			for (var i = 0; i < $scope.items.length; i++) {
				if ($scope.items[i].item_id == id) {
					//$log.log("Found it: "+$scope.items[i].item_name);
					return $scope.items[i];
				}
			}
		}
		// Edit a slot
		$scope.editSlot = function (slot) {
			$scope.current_slot = slot;
			$scope.new_slot.slot_num = $scope.current_slot.slot_num;
			$scope.new_slot.machine_id = $scope.current_slot.machine_id;
			$scope.new_slot.item_id = $scope.current_slot.item_id;
			$scope.new_slot.available = Number($scope.current_slot.available);
			$scope.new_slot.status = $scope.current_slot.status;
			//$log.log("Edit:");
			//$log.log($scope.new_slot);
		};
		// Save a slot
		$scope.saveSlot = function () {
			MachineService.updateSlot($scope.new_slot, 
				function (response) {
					//$log.log(response);
					if (response.status) {
						$scope.current_slot.item_id = $scope.new_slot.item_id;
						$scope.current_slot.available = Number($scope.new_slot.available);
						$scope.current_slot.status = $scope.new_slot.status;
						$scope.current_slot.item_name = $scope.lookupItem($scope.new_slot.item_id).item_name;
						$scope.current_slot.item_price = $scope.lookupItem($scope.new_slot.item_id).item_price;
						$scope.message = "Edit success!";
					}
					else {
						//$log.log(response.message);
						$scope.message = response.message;
					}
					jQuery("#saveSlotModal").modal('show');
				},
				function (error) {
					$log.log(error);
				}
			);
		}
	}
	// Initialize modal data for dropping a drink
	$scope.selectDrink = function (slot) {
		$scope.current_slot = slot;
		$scope.delay = 0;
		//$log.log("Drop:");
		//$log.log($scope.current_slot);
	}; 
	// Drop a drink
	$scope.dropDrink = function() {
		$scope.dropping_message = "Dropping in " + $scope.delay + " seconds...";
		$scope.reduceDelay();
	}
	// Count down the delay until a drink is dropped
	$scope.reduceDelay = function () {
		var i = $timeout(function () {
			if ($scope.delay == 0) {
				$timeout.cancel(i);
				//jQuery("#dropModal").modal('hide');
				//$scope.dropping_message = "Drink dropped!";
			}
			else {
				$scope.delay -= 1;
				$scope.dropping_message = "Dropping in " + $scope.delay + " seconds...";
				$scope.reduceDelay();
			}
		}, 1000);
	};

	/*
	*	Websocket Nonsense
	*
	*	Pretty much straight up copying from Sean McGary 
	*	(github.com/ComputerScienceHouse/WebDrink/js/websocket.js)
	*/

	// Create a new Request object
	$scope.Request = function (command, callback, command_data) {
		if (typeof command_data == 'undefined') {
			command_data = {};
		}

		this.command = command;				// Socket command to execute (ibutton, drop, etc)
		this.callback = callback;			// Callback to execute on command success
		this.command_data = command_data;   // Data being passed to the command
	}

	// Extend the prototype of Request objects
	$scope.Request.prototype =  {
		// Execute the Request's callback function
		runCallback: function(callback_data) {
			this.callback(callback_data);
		},
		// Execute the Request's command
		runCommand: function() {
			this.command(this.command_data);
		}
	};

	//$scope.req = new $scope.Request(function() { $log.log(1); }, function() { $log.log(2); }, {lol:3});
	//$log.log($scope.req);

	// Create a new WebsocketConn object
	$scope.WebsocketConn = function (ibutton) {
		this.ibutton = ibutton;			// User's ibutton
		this.authed = false;			// Authentication success/failure
		this.requesting = false;		// Are we currently handling a request?
		this.request_queue = [];		// Queue to store requests
		this.current_request = null;	// Current request being processed
		this.slot_to_drop = null;		// Slot we wish to drop

		// Connect to the drink server
		this.socket = $window.io.connect('https://drink.csh.rit.edu:8080', {secure: true});
		// Initialize events for server responses
		var self = this;
		this.initWebsocketEvents(function() {
			self.connect();
			self.initClickEvents();
		});
	}

	// Extend the prototype of WebsocketConn objects
	$scope.WebsocketConn.prototype = {
		initClickEvents: function() {
			// Do I need this?
		},
		initWebsocketEvents: function(callback) {
			// If the socket connects/disconnects, hide/show the alert
			this.socket.on('connect', function() {
				$scope.websocket_alert.show = false;
			});
			this.socket.on('disconnect', function() {
				$scope.websocket_alert.show = true;
			});
			this.socket.on('close', function() {
				$scope.websocket_alert.show = true;
			});
			this.socket.on('reconnect', function() {
				$scope.websocket_alert.show = true;
			});
			this.socket.on('reconnecting', function() {
				$scope.websocket_alert.show = false;
			});
			// Process incoming data
			this.socket.on('stat_recv', function(data) {
				this.processIncomingData(data);
			});
			this.socket.on('ibutton_recv', function(data) {
				this.processIncomingData(data);
			});
			this.socket.on('machine_recv', function(data) {
				this.processIncomingData(data);
			});
			this.socket.on('drop_recv', function(data) {
				this.processIncomingData(data);
			});
			this.socket.on('balance_recv', function(data) {
				this.processIncomingData(data);
			});
			// Run the callback
			callback();
		},
		// Process the next Request in the queue
		processQueue: function() {
			if (this.request_queue.length > 0) {
				this.requesting = true;
				this.current_request = this.request_queue.pop();
				this.current_request.runCommand();
			}
		},
		// Execute the only Request or put it in the queue
		prepRequest: function(request) {
			if (!this.requesting) {
				this.requesting = true;
				this.current_request = request;
				this.current_request.runCommand();
			}
			else {
				this.request_queue.push(request);
			}
		},
		// Process data received from the socket
		processIncomingData: function(data) {
			if (this.current_request != null) {
				console.log(data);
				//this.current_request.runCallback(data);
			}
			else {
				// Uh...
			}

			this.current_request = null;
			this.requesting = false;
			this.processQueue();
		},
		// Connect to the drink server as the current user
		connect: function() {
			// Request command
			var command = function() {
				// Send the ibutton command to the server to validate your ibutton
				this.socket.emit('ibutton', {ibutton: this.ibutton});
			};
			// Request callback
			var callback = function(data) {
				// If the status was OK, we authed successfully
				if (data.substr(0, 2) == 'OK') {
					this.authed = true;
					$log.log(data);
				}
				// If not, we have a bogus iButton
				else {
					this.authed = false;
					$scope.websocket_alert.message = "Warning: Invalid iButton";
					$scope.websocket_alert.show = true;
				}
			};
			// Create the Request object and queue it up
			var request = new $scope.Request(command, callback);
			this.prepRequest(request);
		},
		// Tell the server to drop a drink
		drop: function(slot_num, machine_alias, delay) {
			// First Request: connect to the drink machine
			// Request command
			var machine_command = function() {
				this.socket.emit('machine', {machine_id: machine_alias});
			};
			// Request callback
			var machine_callback = function() {
				// Second Request: drop the drink
				// Request command
				var drop_command = function() {
					this.socket.emit('drop', {slot_num: slot_num, delay: delay});
					$scope.reduceDelay();
				};
				// Request callback
				var drop_callback = function() {
					if (data.substr(0, 2) == 'OK') {
						$scope.dropping_message = "Drink dropped!";
						// Update my drink credits
						//$scope.current_user.credits -= $scope.current_slot.item_price;
						MachineService.getCredits($scope.current_user.uid,
							function (response) {
								if (response.status) {
									$scope.current_user.credits = response.data;
								}
								else {
									$log.log("uh oh");
								}
							},
							function (error) {
								$log.log(error);
							}
						);
						// Update the stock

					}
				};
				// Create the Request object and queue it up
				var drop_request = new Request(drop_command, drop_callback);
				this.prepRequest(drop_request);
			};
			// Create the Request object and queue it up
			var machine_request = new Request(machine_command, machine_callback);
			this.prepRequest(machine_request);
		},
		// Get the stock of the last connected-to (?) machine
		stat: function() {
			// Request command
			var command = function() {
				// Send the ibutton command to the server to validate your ibutton
				this.socket.emit('stat', {});
			};
			// Request callback
			var callback = function(data) {
				this.processQueue();
			};
			// Create the Request object and queue it up
			var request = new $scope.Request(command, callback);
			this.prepRequest(request);
		},
		// Connect to a machine
		machine: function(machine_alias) {
			// Request command
			var command = function() {
				// Send the ibutton command to the server to validate your ibutton
				this.socket.emit('machine', {machine_id: machine_alias});
			};
			// Request callback
			var callback = function(data) {
				this.processQueue();
			};
			// Create the Request object and queue it up
			var request = new $scope.Request(command, callback);
			this.prepRequest(request);
		},
		// Get the current user's credit balance
		balance: function() {
			// Request command
			var command = function() {
				// Send the ibutton command to the server to validate your ibutton
				this.socket.emit('getbalance', {});
			};
			// Request callback
			var callback = function(data) {
				if (data.substr(0, 2) == 'OK') {
					data = data.split(': ');
					$scope.current_user.credits = data[1];
				}
				else {
					this.authed = false;
					$log.log("invalid ibutton, yo");
				}
				this.processQueue();
			};
			// Create the Request object and queue it up
			var request = new $scope.Request(command, callback);
			this.prepRequest(request);
		}
	};

	//$scope.drinkConn = new $scope.WebsocketConn(12345);
	//$log.log($scope.drinkConn);

	/*$scope.$on('$destroy', function (event) {
		DrinkSocket.removeAllListeners();
	});*/
}

// Controller for the drops page
function DropCtrl($scope, $window, $log, DropService) {
	// Initialize scope variables
	$scope.drops = new Array();	// List of all user drops
	$scope.pagesLoaded = 0;		// How many pages of drops have been loaded
	$scope.dropsToLoad = 25;	// How many drops to load at a time

	// Get a user's drop history
	$scope.getDrops = function() {
		DropService.getDrops($window.current_user.uid, $scope.dropsToLoad, $scope.pagesLoaded * $scope.dropsToLoad,
			function (response) {
				if (response.status) {
					$scope.drops.push.apply($scope.drops, response.data);
					$scope.pagesLoaded += 1;
				}
				else {
					$log.log(response.message);
				}
			},
			function (error) {
				$log.log(error);
			}
		);
	};

	// Get the first page of a user's drops
	$scope.getDrops();
}

