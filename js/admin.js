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
			var url = "api/v1/users/searchUsers/search/"+search;
			$http.get(url).success(successCallback).error(errorCallback);
		},
		// Get the drink credit amount for a user
		getCredits: function(uid, successCallback, errorCallback) {
			var url = "api/v1/users/getCredits/uid/"+uid;
			$http.get(url).success(successCallback).error(errorCallback);
		},
		// Update the drink credit amount for a user
		updateCredits: function(uid, credits, successCallback, errorCallback) {
			var url = "api/v1/users/updateCredits/uid/"+uid+"/credits/"+credits;
			$http.post(url, {}).success(successCallback).error(errorCallback);
		}
	};
});

app.factory("ItemService", function($http, $window) {
	return {
		
	};
});

app.factory("TempService", function($http, $window) {
	return {
		
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
	$scope.alert = {					// Alert for success/failure of credit change
		show: false,
		success: false,
		message: ""
	};
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
					$scope.activeUser.drops.push.apply($scope.activeUser.drops, response.data);
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
					$scope.alert.success = true;
					$scope.alert.message = "Credits updated successfully!"
					$scope.activeUser.credits = newCredits;
				}
				else {
					$scope.alert.success = false;
					$scope.alert.message = "Update failed: " + response.message;
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

function ItemCtrl($scope, $log, ItemService) {
	
}

function TempCtrl($scope, $log, TempService) {
	
}

function LogsCtrl($scope, $log, LogsService) {
	
}