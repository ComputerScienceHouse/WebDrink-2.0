// Register the app with angular
var app = angular.module("WebDrink", ['ngRoute']);

// Alert directive - quickly display a Bootstrap Alert
// See $scope.Alert in RootCtrl for parameters details
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
// See $scope.DropsTable in RootCtrl for parameters details
app.directive("drops", function() {
	return {
		restrict: "E",
		templateUrl: "templates/drops_table.html",
		scope: {
			drops: "=data"
		}
	}
});

// Machine directive - table for displaying a drink machine's stock
app.directive("machine", function() {
	return {
		restrict: "E",
		templateUrl: "templates/machine_table.html",
		scope: {
			machine: "=data"
		}
	};
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

// Root controller - for shared data/services
function RootCtrl($scope, $log, $window, $location, socket) {
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
			"name": "bigdrink",
			"display_name": "Big Drink",
			"alias":"d"
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
	$scope.DropsTable = function(drops, title, config) {
		if (typeof config === 'undefined') config = {};
		this.drops = drops;
		this.title = title;
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

	// See if a user can afford a drink
	$scope.canAfford = function (price) {
		if (Number($scope.current_user.credits) < Number(price)) 
			return false;
		return true;
	};

	/*
	* Websocket Things
	*/

	// Data for the websocket connection alert
	$scope.websocket_alert = new $scope.Alert({
		message: "Warning: Websocket not connected!",
		show: true,
		closeable: false
	});
	// Websocket connection variables
	$scope.authed = false; 			// Authentication status
	$scope.requesting = false; 		// Are we currently handling a request?
	$scope.request_queue = []; 		// Array of pending requests
	$scope.current_request = null; 	// Current request being processed

	// Request object
	$scope.Request = function (command, callback, command_data) {
		if (typeof command_data == 'undefined') {
			command_data = {};
		}
		this.command = command;				// Socket command to execute (ibutton, drop, etc)
		this.callback = callback;			// Callback to execute on command success
		this.command_data = command_data;   // Data being passed to the command
	};
	$scope.Request.prototype = {
		// Execute the Request's callback function
		runCallback: function(callback_data) {
			this.callback(callback_data);
		},
		// Execute the Request's command
		runCommand: function() {
			this.command(this.command_data);
		}
	};

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
				$scope.authed = true;
				$scope.websocket_alert.show = false;
			}
			// If not, we have a bogus iButton
			else {
				$scope.authed = false;
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