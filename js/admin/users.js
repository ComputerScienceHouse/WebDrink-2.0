// Routes
app.config(['$routeProvider', function($routeProvider) {
	$routeProvider.
		when('/admin/users', {
			templateUrl: 'views/admin/users.html',
			controller: 'UserCtrl'
		});
}]);

// User Service - For getting and updating information about users (drink credits, etc)
app.factory("UserService", function($http, $window, $log) {
	return {	
		// Search for usernames that match a string
		searchUsers: function(search, successCallback, errorCallback) {
			$http({
				method: "GET",
				url: baseUrl+"users/search",
				params: {"uid": search}
			}).success(successCallback).error(errorCallback);
		},
		// Get the balance of a user's drink credits
		getCredits: function(uid, successCallback, errorCallback) {
			$http({
				method: "GET",
				url: baseUrl+"users/credits",
				params: {"uid": uid}
			}).success(successCallback).error(errorCallback);
		},
		// Update the drink credit amount for a user
		updateCredits: function(uid, amount, type, successCallback, errorCallback) {
			$http({
				method: "POST",
				url: baseUrl+"users/credits",
				data: jQuery.param({"uid":uid, "value":amount, "type":type}),
				headers: {'Content-Type': 'application/x-www-form-urlencoded'}
			}).success(successCallback).error(errorCallback);
		}
	};
});

// Controller for the Manage Users page
app.controller("UserCtrl", ['$scope', '$log', 'UserService', 'DropService', function ($scope, $log, UserService, DropService) {
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
	$scope.alert = new $scope.Alert();	    // Alert for success/failure of credit change
	$scope.dropsToLoad = 5;				// How many entries of drop history to load
	// Drops Table directive config
	$scope.drops_table = new $scope.DropsTable($scope.activeUser.drops, $scope.activeUser.cn + "'s Recent Drops", {
		showMore: false
	});

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
					if (response.status) {
						// Update the matched set of usernames
						$scope.searchResults = response.data;
						//$log.log(response.data);
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
				if (response.status) {
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
		var data = {
			"uid": $scope.activeUser.uid,
			"limit": $scope.dropsToLoad
		};
		DropService.getDrops(data,
			function (response) {
				if (response.status) {
					$scope.activeUser.drops = response.data;
					$scope.drops_table.drops = $scope.activeUser.drops;
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

	$scope.loadUserEnter = function(e) {
		if (e.which==13)
    		$scope.loadUser();
	}

	// Set the active user's data
	$scope.loadUser = function() {
		if ($scope.searchTerm != "") {
			var foundUser = false;
			var i = 0;
			for (i = 0; i < $scope.searchResults.length; i++) {
				if ($scope.searchTerm == $scope.searchResults[i].uid) {
					foundUser = $scope.searchResults[i];
					//$log.log("found it: " + foundUser.uid)
					break;
				}
			}
			if (!foundUser) {
				return;
			}
			// Set the common name and uid of the active user
			$scope.activeUser.cn = foundUser.cn;
			$scope.activeUser.uid = foundUser.uid;
			//$log.log($scope.activeUser);
			// Get the active user's drink credit balance and drop history
			$scope.getUserCredits();
			$scope.getUserDrops();
			$scope.drops_table.title = $scope.activeUser.cn + "'s Recent Drops";
		}
	}

	// Update the active user's drink credit balance
	$scope.updateCredits = function() {
		var type = $scope.transactionType;
		var amount = $scope.creditChange;
		// If using the "adjust" feature, convert it to add or subtract
		if ($scope.transactionType == "adjust") {
			if ($scope.creditChange - $scope.activeUser.credits >= 0) {
				amount = $scope.creditChange - $scope.activeUser.credits;
				type = "add";
			}
			else {
				amount = $scope.activeUser.credits - $scope.creditChange;
				type = "subtract";
			}
		}
		// Update the user's credits in LDAP
		UserService.updateCredits($scope.activeUser.uid, amount, type,
			function (response) {
				if (response.status) {
					$scope.alert.type = "alert-success";
					$scope.alert.message = "Credits updated successfully!"
					$scope.activeUser.credits = response.data;
					if ($scope.activeUser.uid == $scope.current_user.uid) {
						$scope.current_user.credits = response.data;
					}
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
}]);


