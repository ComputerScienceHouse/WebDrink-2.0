// Routes
app.config(['$routeProvider', function($routeProvider) {
	$routeProvider.
		when('/admin/users', {
			templateUrl: 'partials/admin/users.html',
			controller: 'UserCtrl'
		}).
		when('/admin/items', {
			templateUrl: 'partials/admin/items.html',
			controller: 'ItemCtrl'
		}).
		when('/admin/temps', {
			templateUrl: 'partials/admin/temps.html',
			controller: 'TempCtrl'
		}).
		when('/admin/logs', {
			templateUrl: 'partials/admin/logs.html',
			controller: 'LogsCtrl'
		});
}]);

// User Service - For getting and updating information about users (drink credits, etc)
app.factory("UserService", function($http, $window, $log) {
	return {	
		// Search for usernames that match a string
		searchUsers: function(search, successCallback, errorCallback) {
			var url = baseUrl+"users/searchUsers/search/"+search;
			$http.get(url).success(successCallback).error(errorCallback);
		},
		// Get the drink credit amount for a user
		getCredits: function(uid, successCallback, errorCallback) {
			var url = baseUrl+"users/getCredits/uid/"+uid;
			$http.get(url).success(successCallback).error(errorCallback);
		},
		// Update the drink credit amount for a user
		updateCredits: function(uid, credits, successCallback, errorCallback) {
			var url = baseUrl+"users/updateCredits/uid/"+uid+"/credits/"+credits;
			$http.post(url, {}).success(successCallback).error(errorCallback);
		}
	};
});

app.factory("ItemService", function($http, $window) {
	return {
		// Add a new item
		addItem: function(name, price, successCallback, errorCallback) {
			var url = baseUrl+"machines/addItem/name/"+name+"/price/"+price;
			$http.post(url, {}).success(successCallback).error(errorCallback);
		},
		updateItem: function(data, successCallback, errorCallback) {
			var url = baseUrl+"machines/updateItem/itemId/"+data.item_id;
			if (data.hasOwnProperty("item_name")) {
				url += "/name/"+data.item_name;
			}
			if (data.hasOwnProperty("item_price")) {
				url += "/price/"+data.item_price;
			}
			if (data.hasOwnProperty("state")) {
				url += "/status/"+data.state;
			}
			$http.post(url, {}).success(successCallback).error(errorCallback);
		},
		deleteItem: function(itemId, successCallback, errorCallback) {
			var url = baseUrl+"machines/deleteItem/itemId/"+itemId;
			$http.post(url, {}).success(successCallback).error(errorCallback);
		}
	};
});

app.factory("TempService", function($http, $window) {
	return {
		getTempsOne: function(machineId, successCallback, errorCallback) {
			var url = baseUrl+"temps/getDataOne/machineId/"+machineId;
			$http.get(url).success(successCallback).error(errorCallback);
		}
	};
});

app.factory("LogsService", function($http, $window) {
	return {
		
	};
});

// Controller for the Manage Users page
function UserCtrl($scope, $log, UserService, DropService) {
	$scope.searchTerm = "";				// Username being searched for
	$scope.searchResults = {};			// All matching usernames from a search
	$scope.activeUser = {				// Current user being managed
		uid: "",
		cn: "",
		credits: 0,
		drops: []
	};
	$scope.creditChange = 0;			// Value to adjust drink credits by
	$scope.transactionType = "add";		// How credits are being adjusted (add, subtract, update)
	$scope.alert = $scope.getAlertDefaults();	// Alert for success/failure of credit change
	$scope.dropsToLoad = 5;				// How many entries of drop history to load

	// Change the transaction type for updating credits (add, subtract, or update)
	$scope.changeType = function(type) {
		// If type is invalid, default to add
		if ($scope.transactionType != "add" && 
			$scope.transactionType != "subtract" && 
			$scope.transactionType != "update") {
			$scope.transactionType = "add";
		}
		// Change the transaction type
		else {
			$scope.transactionType = type;
		}
	}

	// Search for users whose UID matches the search term
	$scope.getUsers = function() {
		//$log.log($scope.searchTerm);
		// Only search if the search term is not empty
		if ($scope.searchTerm.length > 0) {
			// Search for matching usernames
			UserService.searchUsers($scope.searchTerm,
				function (response) {
					if (response.result) {
						// Update the matched set of usernames
						$scope.searchResults = response.data;
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
	}

	// Get the drink credit balance for the active user
	$scope.getUserCredits = function() {
		UserService.getCredits($scope.activeUser.uid, 
			function (response) {
				if (response.result) {
					$scope.activeUser.credits = response.data;
				}
				else {
					$log.log(response.message);
				}
			},
			function (error) {
				$log.log(response.message);
			}
		);
	}

	// Get the drop history for the active user
	$scope.getUserDrops = function() {
		DropService.getDrops($scope.activeUser.uid, $scope.dropsToLoad, 0,
			function (response) {
				if (response.result) {
					//$scope.activeUser.drops.push.apply($scope.activeUser.drops, response.data);
					$scope.activeUser.drops = response.data;
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

	// Set the active user's data
	$scope.loadUser = function() {
		//$log.log($scope.searchTerm);
		// Only load if the search result has one user
		if ($scope.searchResults.length == 1) {
			// Set the common name and uid of the active user
			$scope.activeUser.cn = $scope.searchResults[0].cn;
			$scope.activeUser.uid = $scope.searchResults[0].uid;
			// Get the active user's drink credit balance and drop history
			$scope.getUserCredits();
			$scope.getUserDrops();
		}
	}

	// Update the active user's drink credit balance
	$scope.updateCredits = function() {
		var newCredits = 0;
		// Adjust the user's credits based on the transaction type
		if ($scope.transactionType == "add") {
			newCredits = Number($scope.activeUser.credits) + Number($scope.creditChange);
		}
		else if ($scope.transactionType == "subtract") {
			newCredits = Number($scope.activeUser.credits) - Number($scope.creditChange);
		}
		else if ($scope.transactionType == "update") {
			newCredits = $scope.creditChange;
		}
		else {
			// This shouldn't happen
		}
		// Update the user's credits in LDAP
		UserService.updateCredits($scope.activeUser.uid, newCredits, 
			function (response) {
				if (response.result) {
					$scope.alert.type = "alert-success";
					$scope.alert.message = "Credits updated successfully!"
					$scope.activeUser.credits = newCredits;
				}
				else {
					$scope.alert.type = "alert-danger";
					$scope.alert.message = response.message;
				}
				// Show the success/failure alert
				$scope.alert.show = true;
			},
			function (error) {
				$log.log(error);
			}
		);
	}
}

// Controller for the Manage Items page
function ItemCtrl($scope, $log, ItemService, MachineService) {
	$scope.items = new Array();		// All drink items
	$scope.currentItem = {};		// Current item being edited/deleted
	$scope.newItem = {				// New item being added
		item_name: "",
		item_price: 0,
		item_id: 0,
		state: "active"
	};		
	$scope.updateItem = {};			// Temporary item for updates	
	$scope.alert = $scope.getAlertDefaults();	// Alert for success/failure of adding an item
	$scope.message = "";			// Message to display after edit/delete

	// Initialize data, get a list of all drink items
	MachineService.getItemAll(
		function (response) {
			if (response.result) {
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

	// Add the newItem to the database
	$scope.addItem = function() {
		// Add the item to the database
		ItemService.addItem($scope.newItem.item_name, $scope.newItem.item_price,
			function (response) {
				if (response.result) {
					// Set the item ID and state of the new item
					$scope.newItem.item_id = response.data; //Number($scope.items[$scope.items.length - 1].item_id) + 1;
					$scope.newItem.state = "active";
					// Add the new item to the items array
					$scope.items.push.call($scope.items, $scope.newItem);
					// Reset the new item
					$scope.newItem = { item_name: "", item_price: 0 };
					// Show the alert
					$scope.alert.type = "alert-success"; 
					$scope.alert.message = "Item added successfully!";
				}
				else {
					$scope.alert.type = "alert-danger"; 
					$scope.alert.message = response.message;
					//$log.log(response.message);
				}
				$scope.alert.show = true;
			},
			function (error) {
				$log.log(error);
			}
		);
	}

	// Set the item to be updated 
	$scope.editItem = function(item) {
		$scope.currentItem = item;
		$scope.updateItem.item_id = $scope.currentItem.item_id;
		$scope.updateItem.item_name = $scope.currentItem.item_name;
		$scope.updateItem.item_price = Number($scope.currentItem.item_price);
		$scope.updateItem.state = $scope.currentItem.state;
	}

	// Save the updated item in the database
	$scope.saveItem = function() {
		ItemService.updateItem($scope.updateItem, 
			function (response) {
				if (response.result) {
					// Update the currentItem to reflect changes
					$scope.currentItem.item_name = $scope.updateItem.item_name;
					$scope.currentItem.item_price = Number($scope.updateItem.item_price);
					$scope.currentItem.state = $scope.updateItem.state;
					$scope.message = "Item updated successfully!";
				}
				else {
					$scope.message = response.message;
				}
				jQuery("#saveItemModal").modal('show');
			},
			function (error) {
				$log.log(error);
			}
		);
	}

	// Lookup an item by id
	$scope.lookupItemIndex = function (id) {
		for (var i = 0; i < $scope.items.length; i++) {
			if ($scope.items[i].item_id == id) {
				return i;
			}
		}
	}

	// Prepare an item to be deleted 
	$scope.confirmDelete = function(item) {
		$scope.currentItem = item;
	}

	// Delete an item from the database
	$scope.deleteItem = function() {
		ItemService.deleteItem($scope.currentItem.item_id,
			function (response) {
				if (response.result) {
					// Remove the item from the items array
					var indexToRemove = $scope.lookupItemIndex($scope.currentItem.item_id);
					$scope.items.splice(indexToRemove, 1);
					$scope.message = "Item deleted!";
				}
				else {
					$scope.message = response.message;
				}
				jQuery("#deleteModal").modal('show');
			},
			function (error) {
				$log.log(error);
			}
		);
	}

}

// Controller for the Machine Temperatures page
function TempCtrl($scope, $log, TempService) {
	// Mapping of machine IDs to names
	$scope.machines = {
		1: "littledrink",
		2: "bigdrink",
		3: "snack"
	};

	// Get a machine's tempereature data
	$scope.getMachineTemps = function(machineId) {
		TempService.getTempsOne(machineId, 
			function (response) {
				if (response.result) {
					$scope.drawChart($scope.machines[machineId], response.data);
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

	// Draw a temperature chart
	$scope.drawChart = function(machine, data) {
		jQuery(function () {
		    jQuery("#"+machine).highcharts({
		        chart: {
		            type: 'line'
		        },
		        title: {
		            text: 'Temperatures'
		        },
		        xAxis: {
		            title: {
		            	text: 'Time'
		            }
		        },
		        yAxis: {
		            title: {
		                text: 'Temperature'
		            }
		        },
		        series: [{
		            name: machine,
		            data: data.temp
		        }]
		    });
		});
	}

	// Get temperature data for each machine
	$scope.getMachineTemps(1);
	$scope.getMachineTemps(2);
	$scope.getMachineTemps(3);
}

// Controller for the Drop Logs page
function LogsCtrl($scope, $log, LogsService, DropService) {
	// Initialize scope variables
	$scope.logs = new Array();	// List of all user drops
	$scope.pagesLoaded = 0;		// How many pages of drops have been loaded
	$scope.dropsToLoad = 50;	// How many drops to load at a time

	// Get a user's drop history
	$scope.getDrops = function() {
		DropService.getDrops(false, $scope.dropsToLoad , $scope.pagesLoaded * $scope.dropsToLoad,
			function (response) {
				if (response.result) {
					$scope.logs.push.apply($scope.logs, response.data);
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