// Routes
app.config(['$routeProvider', function($routeProvider) {
	$routeProvider.
		when('/machines', {
			templateUrl: 'views/machines.html',
			controller: 'MachineCtrl'
		}).
		otherwise({
			redirectTo: '/machines'
		});
}]);

// Machine Service - Get and update data about the drink machines, machine stock, etc
app.factory("MachineService", function($http, $window, $log) {
	return {
		// Get a list of information about all drink machines
		getMachineAll: function(successCallback, errorCallback) {
			$http({
				method: "GET",
				url: baseUrl+"machines/info"
			}).success(successCallback).error(errorCallback);
		},
		// Get the stock of all drink machines
		getStockAll: function(successCallback, errorCallback) {
			$http({
				method: "GET",
				url: baseUrl+"machines/stock"
			}).success(successCallback).error(errorCallback);
		},
		// Get the stock of one drink machine
		getStockOne: function(machineId, successCallback, errorCallback) {
			$http({
				method: "GET",
				url: baseUrl+"machines/stock",
				params: {"machine_id": machineId},
				headers: {'Content-Type': 'application/x-www-form-urlencoded'}
			}).success(successCallback).error(errorCallback);
		},
		// Get a list of all drink items
		getItemAll: function(successCallback, errorCallback) {
			$http({
				method: "GET",
				url: baseUrl+"items/list"
			}).success(successCallback).error(errorCallback);
		},
		// Update the items and state of a slot in a drink machine
		updateSlot: function(data, successCallback, errorCallback) {
			$http({
				method: "POST",
				url: baseUrl+"machines/slot",
				//url: "./test.php",
				data: jQuery.param(data),
				headers: {'Content-Type': 'application/x-www-form-urlencoded'}
			}).success(successCallback).error(errorCallback);
		},
		// Get the current user's drink credits
		getCredits: function(uid, successCallback, errorCallback) {
			$http({
				method: "GET",
				//url: baseUrl+"users/credits/"+uid
				url: baseUrl+"users/credits",
				params: {"uid": uid, "test": "LOL"},
				headers: {'Content-Type': 'application/x-www-form-urlencoded'}
			}).success(successCallback).error(errorCallback);
		},
		// Drop a drink
	    dropDrink: function(data, successCallback, errorCallback) {
		    $http({
		        method: "POST",
		        url: baseUrl+"drops/drop",
		        data: jQuery.param(data),
		        headers: {'Content-Type': 'application/x-www-form-urlencoded'}
		    }).success(successCallback).error(errorCallback);
	    },
	    // Check the status of the drink server
	    checkStatus: function(successCallback, errorCallback) {
	    	$http({
	    		method: "GET",
	    		url: baseUrl+"drops/status"
	    	}).success(successCallback).error(errorCallback);
	    }
	};
});

// Controller for the machines page
app.controller("MachineCtrl", ['$scope', '$log', '$window', '$timeout', '$interval', 'MachineService','$http', function ($scope, $log, $window, $timeout, $interval, MachineService, $http) {

	// Initialize scope variables
	$scope.stock = {};			// Stock of all machines
	$scope.items = {};			// All existing drink items (admin only)
	$scope.current_slot = {};	// Current slot being dropped/edited
	$scope.new_slot = {};		// New data for slot being edited
	$scope.delay = 0;			// Delay for dropping a drink
	$scope.dropping_message = "";
	$scope.message = "";		// Message to display after edit

	// Alert for displaying a general message to the user
	$scope.message_alert = new $scope.Alert({
		type: "alert-info",
		title: "Welcome to WebDrink!",
		message: "Check out the code on <a href='https://github.com/bencentra/WebDrink-2.0/'>GitHub</a> " +
							"and report any <a href='https://github.com/bencentra/WebDrink-2.0/issues'>issues</a>.",
		show: true,
		closeable: false
	});

	// Data for the websocket connection alert
	$scope.websocket_alert = new $scope.Alert({
		title: "Warning:",
		message: "Websocket not connected!",
		show: false,
		closeable: false
	});

	// Modal for selecting a drink
	$scope.select_modal = new $scope.Modal({
    id: "selectModal",
    title: "Drop?",
    cancel_btn: {
      type: "danger",
      text: "Cancel"
    },
    submit_btn: {
      type: "success",
      text: "Drop"
    }
  });
 	// Attach the drop delay to the select_modal (GROSS)
 	$scope.select_modal.delay = 0;

	// Modal for the drop countdown
  $scope.drop_modal = new $scope.Modal({
  	id: "dropModal",
  	title: "Dropping drink...",
  	cancel_btn: {
  		type: "default",
  		text: "Close"
  	}
  });

  // Modal for editing the slot
  $scope.edit_modal = new $scope.Modal({
  	id: "editSlotModal",
  	title: "Editing Slot...",
  	cancel_btn: {
  		type: "danger"
  	},
  	submit_btn: {
  		type: "success",
  		text: "Save"
  	}
  });

  // Modal for the edit confirmation
  $scope.save_modal = new $scope.Modal({
  	id: "saveSlotModal",
  	title: "Saving Slot...",
  	cancel_btn: {
  		type: "default",
  		text: "Close"
  	}
  });

	// Get the initial stock
	MachineService.getStockAll(
		function (response) { 
			if (response.status) {
				// $scope.stock = response.data;
				for (var machine in response.data) {
					var m = response.data[machine];
					for (var i = 0; i < m.length; i++) {
						var s = m[i];
						s.item_price = parseInt(s.item_price);
						s.available = parseInt(s.available);
					}
				}
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
			$scope.edit_modal.title = "Editing "+slot.display_name+" Slot " + slot.slot_num+"...";
			$scope.current_slot = slot;
			$scope.new_slot.slot_num = $scope.current_slot.slot_num;
			$scope.new_slot.machine_id = $scope.current_slot.machine_id;
			$scope.new_slot.item_id = $scope.current_slot.item_id;
			$scope.new_slot.available = Number($scope.current_slot.available);
			$scope.new_slot.status = $scope.current_slot.status;
		};
		// Save a slot
		$scope.saveSlot = function () {
			$scope.save_modal.title = "Saving "+$scope.current_slot.display_name+" Slot "+$scope.current_slot.slot_num+"...";
			if ($scope.new_slot.item_id > 1) {
				MachineService.updateSlot($scope.new_slot, 
					function (response) {
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
							console.log(response.data);
						}
						jQuery("#saveSlotModal").modal('show');
					},
					function (error) {
						$log.log(error);
					}
				);
			}
		}
	}

	// Initialize modal data for dropping a drink
	$scope.selectDrink = function (slot) {
		$scope.current_slot = slot;
		$scope.select_modal.title = "Drop "+slot.item_name+"?"
		$scope.select_modal.delay = 0;
		// $scope.delay = 0;
	}; 

	// Initialize the drop process
	$scope.startDrop = function () {
		jQuery("#dropModal").modal("show");
		// Get the delay from the select_modal (GROSS)
		$scope.delay = $scope.validateDelayValue($scope.select_modal.delay);
		$scope.dropDrink();
	};

	// Drop a drink
	$scope.dropDrink = function () {
		// Don't drop if the websocket isn't connected
		if (!$scope.authed) {
			$scope.dropping_message = "Warning: Websocket not connected, can't drop drink!";
			return;
		}
    // Cancel a previous drop's delay countdown
    $timeout.cancel($scope.dropTimeout);
		// Send the drop command to the server
		MachineService.dropDrink({
			ibutton: $scope.current_user.ibutton,
			machine_id: $scope.current_slot.machine_id,
			slot_num: $scope.current_slot.slot_num,
			delay: $scope.delay
		}, function (response) {
			if (response.status) {
				$scope.dropping_message = response.message;
 				// Update my drink credits
				$scope.current_user.credits -= $scope.current_slot.item_price;
				MachineService.getCredits($scope.current_user.uid,
					function (response) {
						if (response.status) {
							$scope.current_user.credits = parseInt(response.data);
						}
						else {
							$log.log(response.message);
						}
					},
					function (error) {
						$log.log(error);
					}
				);
                
               /* Receipt plugin for Jamie */
               var confirmResponse = confirm("Would you like a receipt?");
                
                if(confirmResponse==true){
                    $scope.sendReceipt($scope.current_user.uid,$scope.current_slot.item_name,$scope.current_slot.item_price); 
                    
                }
                
			}
			else {
				$scope.dropping_message = response.message;
        $timeout.cancel($scope.dropTimeout);
			}
		}, function (error) {
			$log.log(error);
		});
		// Countdown on the client
		$scope.reduceDelay();
	}

	// Count down the delay until a drink is dropped
	$scope.reduceDelay = function () {
		$scope.dropping_message = "Dropping in " + $scope.delay + " seconds...";
		$scope.dropTimeout = $timeout(function () {
			if ($scope.delay <= 0) {
				$timeout.cancel($scope.dropTimeout);
				$scope.dropping_message = "Dropping drink...";
			}
			else {
				$scope.delay -= 1;
				$scope.reduceDelay();
			}
		}, 1000);
	};

	// Validate the value of the drop delay (no strings, no decimals, no problem)
	$scope.validateDelayValue = function (delay) {
		return (typeof delay === "undefined" || delay == null) ? 3 : Math.floor(delay);
	}

	// Check the status of the drink server
	$scope.checkStatus = function () {
		MachineService.checkStatus(
			function (response) {
				if (response.status) {
					$scope.authed = true;
					$scope.websocket_alert.show = false;
				}
				else {
					$scope.authed = false;
					$scope.websocket_alert.show = true;
					$scope.websocket_alert.message = response.message;
				}
			},
			function (error) {
				$scope.authed = false;
				$scope.websocket_alert.show = true;
				$log.log(error);
			}
		);
	};
    
    /* Receipt plugin for Jamie */
    $scope.sendReceipt = function(user,item,price){
        console.log(user+","+item+","+price); //debug
        
        
       var params = {
            'uid': user,
            'item': item,
            'amount': price,
            'token': "teWBMwXFE0zBDxvYKSbx"
        };
        
        
        
        $http({
            method: 'GET',
            url: "http://csh.rit.edu/~hudson/webdrinkReceipt/?"+$scope.urlEncode(params)
            }).then(function successCallback(data){
                console.log(data);//debug
                alert("Receipt sent!"); //debug        
            
            }, 
            function errorCallback(data){
            console.log(data); //debug
                alert("Error sending receipt!"); //debug
            
            });
        
        
    }
    $scope.urlEncode = function(obj){
        var str = [];
        for(var p in obj)
        str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
        return str.join("&");
        
    }

	// Check the drink server's status now
	$scope.checkStatus();
	// Keep checking the status
	$scope.statusTimeout = $interval(function () {
		$scope.checkStatus();
	}, 15000);

}]);


