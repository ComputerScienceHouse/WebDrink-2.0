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

// Alert directive - quickly display a Bootstrap Alert
// data = {show: true/false, closeable: true/false, message: "default", type:"alert-warning"}
app.directive("alert", function() {
	return {
		restrict: "E",
		templateUrl: "templates/alert.html",
		scope: {
			alert: "=data"
		}
	};
});

// Drops directive - table showing a user's drop history/combined drop log
// drops = drop data (i.e. result of /users/drops API call)
// user = user data (i.e. $scope.current_user)
app.directive("drops", function() {
	return {
		restrict: "E",
		templateUrl: "templates/drops_table.html",
		scope: {
			drops: "=data"
		}
	}
});

// Wrapper for Socket.IO functionality in Angular 
// http://www.html5rocks.com/en/tutorials/frameworks/angular-websockets/
app.factory('socket', function ($rootScope) {
  var socket = io.connect("https://drink.csh.rit.edu:8080", {secure: true});
  return {
    on: function (eventName, callback) {
      socket.on(eventName, function () {  
        var args = arguments;
        $rootScope.$apply(function () {
          callback.apply(socket, args);
        });
      });
    },
    emit: function (eventName, data, callback) {
      socket.emit(eventName, data, function () {
        var args = arguments;
        $rootScope.$apply(function () {
          if (callback) {
            callback.apply(socket, args);
          }
        });
      })
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
			"name": "littledrink",
			"display_name": "Little Drink",
			"alias": "ld"
		},
		2: {
			"id": 2,
			"alias": "bigdrink",
			"display_name": "Big Drink",
			"alias":"s"
		},
		3: {
			"id": 3,
			"name": "snack",
			"display_name": "Snack",
			"alias":"s"
		}
	};
	
	// Default data for any alert directives
	$scope.Alert = function(config) {
		if (typeof config === 'undefined') config = {};
		this.show = (config.hasOwnProperty("show")) ? config.show : false;
		this.closeable = (config.hasOwnProperty("closeable")) ? config.closeable : true;
		this.type = (config.hasOwnProperty("type")) ? config.type : "alert-warning";
		this.message = (config.hasOwnProperty("message")) ? config.message : "default";
	};
	$scope.Alert.prototype = {
		toggle: function() {
			this.show = !this.show;
		}
	};

	// Default data for any drops_table directives
	$scope.DropsTable = function(drops, username, config) {
		if (typeof config === 'undefined') config = {};
		this.drops = drops;
		this.user = username;
		this.config = {
			showUser: (config.hasOwnProperty("showUser")) ? config.showUser : false,
			showMore: (config.hasOwnProperty("showMore")) ? config.showMore : true,
			isCondensed: (config.hasOwnProperty("isCondensed")) ? config.isCondensed : false
		}
	};

	// Activate the admin dropdown menu
	if ($scope.current_user.admin) {
		$scope.toggleAdminDropdown = function() {
			jQuery("#adminDropdown").dropdown('toggle');
		}
	}
}

// Controller for the machines page
function MachineCtrl($scope, $log, $window, $timeout, MachineService, socket) {
	// Initialize scope variables
	$scope.stock = {};			// Stock of all machines
	$scope.items = {};			// All existing drink items (admin only)
	$scope.current_slot = {};	// Current slot being dropped/edited
	$scope.new_slot = {};		// New data for slot being edited
	$scope.delay = 0;			// Delay for dropping a drink
	$scope.dropping_message = "";
	$scope.message = "";		// Message to display after edit
	// Data for the websocket connection alert
	$scope.websocket_alert = new $scope.Alert({
		message: "Warning: Websocket not connected!",
		closeable: false
	});

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
	}; 
	// Drop a drink
	$scope.dropDrink = function() {
		$scope.wsDrop($scope.current_slot.slot_num, $scope.machines[$scope.current_slot.machine_id].alias);
	}
	// Count down the delay until a drink is dropped
	$scope.reduceDelay = function () {
		$scope.dropping_message = "Dropping in " + $scope.delay + " seconds...";
		var i = $timeout(function () {
			if ($scope.delay == 0) {
				$timeout.cancel(i);
				$scope.dropDrink();
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

	// Request object
	$scope.Request = function (command, callback, command_data) {
		if (typeof command_data == 'undefined') {
			command_data = {};
		}
		this.command = command;				// Socket command to execute (ibutton, drop, etc)
		this.callback = callback;			// Callback to execute on command success
		this.command_data = command_data;   // Data being passed to the command
	};
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

	// Websocket connection variables
	$scope.authed = false; 			// Authentication status
	$scope.requesting = false; 		// Are we currently handling a request?
	$scope.request_queue = []; 		// Array of pending requests
	$scope.current_request = null; 	// Current request being processed

	// Initialize Websocket Events
	socket.on('connect', function() {
		$scope.websocket_alert.show = false;
	});
	socket.on('disconnect', function() {
		$scope.websocket_alert.show = true;
	});
	socket.on('close', function() {
		$scope.websocket_alert.show = true;
	});
	socket.on('reconnect', function() {
		$scope.websocket_alert.show = true;
	});
	socket.on('reconnecting', function() {
		$scope.websocket_alert.show = false;
	});
	socket.on('stat_recv', function(data) {
		$scope.wsProcessIncomingData(data);
	});
	socket.on('ibutton_recv', function(data) {
		$scope.wsProcessIncomingData(data);
	});
	socket.on('machine_recv', function(data) {
		$scope.wsProcessIncomingData(data);
	});
	socket.on('drop_recv', function(data) {
		$scope.wsProcessIncomingData(data);
	});
	socket.on('balance_recv', function(data) {
		$scope.wsProcessIncomingData(data);
	});

	// Process the next Request in the queue
	$scope.wsProcessQueue = function() {
		if ($scope.request_queue.length > 0) {
			$scope.requesting = true;
			$scope.current_request = $scope.request_queue.pop();
			$scope.current_request.runCommand();
		}
	};

	// Execute the only Request or put a Request in the queue
	$scope.wsPrepRequest = function(request) {
		if (!$scope.requesting) {
			$scope.requesting = true;
			$scope.current_request = request;
			$scope.current_request.runCommand();
		}
		else {
			$scope.request_queue.push(request);
		}
	};

	// Process data received from the socket
	$scope.wsProcessIncomingData = function(data) {
		if ($scope.current_request != null) {
			$log.log(data);
			$scope.current_request.runCallback(data);
		}
		else {
			// Uh...
		}
		// Handle the next request
		$scope.current_request = null;
		$scope.requesting = false;
		$scope.wsProcessQueue();
	};

	// Connect to the drinkjs server
	$scope.wsConnect = function() {
		// Request command
		var command = function() {
			// Send the ibutton command to the server to validate your ibutton
			socket.emit('ibutton', {ibutton: $scope.current_user.ibutton});
		};
		// Request callback
		var callback = function(data) {
			// If the status was OK, we authed successfully
			if (data.substr(0, 2) == 'OK') {
				self.authed = true;
				$scope.websocket_alert.show = false;
			}
			// If not, we have a bogus iButton
			else {
				self.authed = false;
				$scope.websocket_alert.message = "Warning: Invalid iButton";
				$scope.websocket_alert.show = true;
			}
		};
		// Create the Request object and queue it up
		var request = new $scope.Request(command, callback);
		$scope.wsPrepRequest(request);
	};

	// Tell the server to drop a drink
	$scope.wsDrop = function(slot_num, machine_alias) {
		// First Request: connect to the drink machine
		// Request command
		var machine_command = function() {
			socket.emit('machine', {machine_id: machine_alias});
		};
		// Request callback
		var machine_callback = function() {
			// Second Request: drop the drink
			// Request command
			var drop_command = function() {
				socket.emit('drop', {slot_num: slot_num, delay: 0});
			};
			// Request callback
			var drop_callback = function(data) {
				if (data.substr(0, 2) == 'OK') {
					$scope.dropping_message = "Drink dropped!";
					// Update my drink credits
					$scope.current_user.credits -= $scope.current_slot.item_price;
					MachineService.getCredits($scope.current_user.uid,
						function (response) {
							if (response.status) {
								$scope.current_user.credits = response.data;
							}
							else {
								$log.log(response.message);
							}
						},
						function (error) {
							$log.log(error);
						}
					);
				}
				else {
					$scope.dropping_message = data;
				}
			};
			// Create the Request object and queue it up
			var drop_request = new $scope.Request(drop_command, drop_callback);
			$scope.wsPrepRequest(drop_request);
		};
		// Create the Request object and queue it up
		var machine_request = new $scope.Request(machine_command, machine_callback);
		$scope.wsPrepRequest(machine_request);
	};

	// Get the stock of the last connected-to (?) machine
	$scope.wsStat = function() {
		// Request command
		var command = function() {
			// Send the ibutton command to the server to validate your ibutton
			socket.emit('stat', {});
		};
		// Request callback
		var callback = function(data) {
			$scope.wsProcessQueue();
		};
		// Create the Request object and queue it up
		var request = new $scope.Request(command, callback);
		$scope.wsPrepRequest(request);
	}

	// Connect to a machine
	$scope.wsMachine = function(machine_alias) {
		// Request command
		var command = function() {
			// Send the ibutton command to the server to validate your ibutton
			socket.emit('machine', {machine_id: machine_alias});
		};
		// Request callback
		var callback = function(data) {
			$scope.wsProcessQueue();
		};
		// Create the Request object and queue it up
		var request = new $scope.Request(command, callback);
		$scope.wsPrepRequest(request);
	}

	// Get the current user's credit balance
	$scope.wsBalance = function() {
		// Request command
		var command = function() {
			// Send the ibutton command to the server to validate your ibutton
			socket.emit('getbalance', {});
		};
		// Request callback
		var callback = function(data) {
			if (data.substr(0, 2) == 'OK') {
				data = data.split(': ');
				$scope.current_user.credits = data[1];
			}
			else {
				$scope.authed = false;
				$log.log("invalid ibutton, yo");
			}
			$scope.wsProcessQueue();
		};
		// Create the Request object and queue it up
		var request = new $scope.Request(command, callback);
		$scope.wsPrepRequest(request);
	}

	// Establish a websocket connection
	$scope.wsConnect();
}

// Controller for the drops page
function DropCtrl($scope, $window, $log, DropService) {
	// Initialize scope variables
	$scope.drops = new Array();	// List of all user drops
	$scope.pagesLoaded = 0;		// How many pages of drops have been loaded
	$scope.dropsToLoad = 25;	// How many drops to load at a time
	// Drops Table directive config
	$scope.drops_table = new $scope.DropsTable($scope.drops, $scope.current_user.cn);

	// Get a user's drop history
	$scope.getDrops = function() {
		DropService.getDrops($window.current_user.uid, $scope.dropsToLoad, $scope.pagesLoaded * $scope.dropsToLoad,
			function (response) {
				if (response.status) {
					$scope.drops = $scope.drops.concat(response.data);
					$scope.drops_table.drops = $scope.drops;
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

