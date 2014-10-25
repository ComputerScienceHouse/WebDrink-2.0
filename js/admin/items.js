// Routes
app.config(['$routeProvider', function($routeProvider) {
	$routeProvider.
		when('/admin/items', {
			templateUrl: 'views/admin/items.html',
			controller: 'ItemCtrl'
		});
}]);

// Item Service - For adding, updating, and deleting drink items
app.factory("ItemService", function($http, $window) {
	return {
		// Add a new item
		addItem: function(name, price, successCallback, errorCallback) {
			$http({
				method: "POST",
				url: baseUrl+"items/add",
				data: jQuery.param({"name": name, "price": price}),
				headers: {'Content-Type': 'application/x-www-form-urlencoded'}
			}).success(successCallback).error(errorCallback);
		},
		updateItem: function(data, successCallback, errorCallback) {
			$http({
				method: "POST",
				url: baseUrl+"items/update",
				data: jQuery.param(data),
				headers: {'Content-Type': 'application/x-www-form-urlencoded'}
			}).success(successCallback).error(errorCallback);
		},
		deleteItem: function(itemId, successCallback, errorCallback) {
			$http({
				method: "POST",
				url: baseUrl+"items/delete",
				data: jQuery.param({"item_id": itemId}),
				headers: {'Content-Type': 'application/x-www-form-urlencoded'}
			}).success(successCallback).error(errorCallback);
		}
	};
});

// Controller for the Manage Items page
app.controller("ItemCtrl", ['$scope', '$log', 'ItemService', 'MachineService', function ($scope, $log, ItemService, MachineService) {
	$scope.items = new Array();		// All drink items
	$scope.currentItem = {};		// Current item being edited/deleted
	$scope.newItem = {				// New item being added
		item_name: "",
		item_price: 0,
		item_id: 0,
		state: "active"
	};		
	$scope.updateItem = {};			// Temporary item for updates	
	$scope.alert = new $scope.Alert();	// Alert for success/failure of adding an item
	$scope.message = "";			// Message to display after edit/delete

	$scope.item_filter = "";

	// Initialize data, get a list of all drink items
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

	// Add the newItem to the database
	$scope.addItem = function() {
		// Handle empty name
		if ($scope.newItem.item_name == "") {
			$scope.alert.type = "alert-danger";
			$scope.alert.message = "Invalide name: can't be empty!";
			$scope.alert.show = true;
			return;
		}
		// Handle undefined price
		if (typeof $scope.newItem.item_price === "undefined") {
			$scope.alert.type = "alert-danger";
			$scope.alert.message = "Invalid price; must be a positive integer!";
			$scope.alert.show = true;
			return;
		}	
		// Add the item to the database
		ItemService.addItem($scope.newItem.item_name, $scope.newItem.item_price,
			function (response) {
				if (response.status) {
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
		// Handle empty name
		if ($scope.updateItem.item_name == "") {
			$scope.message = "Invalide name: can't be empty!";
			jQuery("#saveItemModal").modal('show');
			return;
		}
		// Handle undefined price
		if (typeof $scope.updateItem.item_price === "undefined") {
			$scope.message = "Invalid price; must be a positive integer!";
			jQuery("#saveItemModal").modal('show');
			return;
		}	
		var data = {
			item_id: $scope.updateItem.item_id,
			name: $scope.updateItem.item_name,
			price: $scope.updateItem.item_price,
			state: $scope.updateItem.state
		};
		ItemService.updateItem(data,
			function (response) {
				if (response.status) {
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
				if (response.status) {
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
}]);


