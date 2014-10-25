// Routes
app.config(['$routeProvider', function($routeProvider) {
	$routeProvider.
		when('/settings', {
			templateUrl: 'views/settings.html',
			controller: 'SettingsCtrl'
		});
}]);

// API Service - generate or retrieve a user's API key
app.factory("SettingsService", function($http, $window, $log) {
	return {
		// Get your API key
		retrieveKey: function(successCallback, errorCallback) {
			$http({
				method: "GET",
				url: baseUrl+"users/apikey"
			}).success(successCallback).error(errorCallback);
		},
		// Generate a new API key
		generateKey: function(successCallback, errorCallback) {
			$http({
				method: "POST",
				url: baseUrl+"users/apikey"
			}).success(successCallback).error(errorCallback);
		},
		// Delete your API key
		deleteKey: function(successCallback, errorCallback) {
			$http({
				method: "DELETE",
				url: baseUrl+"users/apikey"
			}).success(successCallback).error(errorCallback);
		}
		/*
		// Get your thunderdome settings
		getThunderdome: function(successCallback, errorCallback) {
			$http({
				method: "GET",
				url: baseUrl+"users/thunderdome"
			}).success(successCallback).error(errorCallback);
		},
		// Update your thunderdome settings
		updateThunderdome: function(twitter, quietHours, enabled, successCallback, errorCallback) {
			$http({
				method: "POST",
				url: baseUrl+"users/thunderdome",
				data: jQuery.param({"twitter":twitter, "quiet_hours":quietHours, "enabled":enabled}),
				headers: {'Content-Type': 'application/x-www-form-urlencoded'}
			}).success(successCallback).error(errorCallback);
		},
		// Delete your thunderdome settings
		deleteThunderdome: function(successCallback, errorCallback) {
			$http({
				method: "DELETE",
				url: baseUrl+"users/thunderdome"
			}).success(successCallback).error(errorCallback);
		}
		*/
	};
});

// Controller for the API page
app.controller("SettingsCtrl", ['$scope', '$window', '$log', 'SettingsService', function ($scope, $window, $log, SettingsService) {
	$scope.api_key = false;	// User's API key
	$scope.date = "";		// Date the API key was generated
	$scope.api_message = "Looks like you need an API key!";
	$scope.twitter = "";
	$scope.quiet_hours = [];
	$scope.enabled = true;
	$scope.thunderdome_alert = new $scope.Alert();

	// Get the user's API key
	$scope.retrieveKey = function() {
		SettingsService.retrieveKey(
			function (response) {
				if (response.status) {
					$scope.api_key = response.data.api_key;
					$scope.date = response.data.date;
				}
				else {
					$scope.api_key = false;
					$scope.api_message = "Looks like you need an API key!";
					$log.log(response.message);
				}
			},
			function (error) {
				$log.log(error);
			}
		);
	};

	// Generate a new key for the user
	$scope.generateKey = function() {
		$scope.api_key = false;
		$scope.api_message = "Generating your API key...";
		SettingsService.generateKey(
			function (response) {
				if (response.status) {
					$scope.api_key = response.data.api_key;
					$scope.date = response.data.date;
				}
				else {
					$scope.api_key = false;
				}
			},
			function (error) {
				$log.log(error);
			}
		);
	};

	// Delete the user's current API key
	$scope.deleteKey = function () {
		SettingsService.deleteKey(
			function (response) {
				if (response.status) {
					$scope.api_key = false;
					$scope.api_message = "Looks like you need an API key!";
					$scope.date = "";
				}
				else {
					$log.log("uh...");
				}
			},
			function (error) {
				$log.log(error);
			}
		);
	}

	/*
	// Add another quiet hours
	$scope.addQuietHours = function () {
		$scope.quiet_hours.push({"id":$scope.quiet_hours.length+1, "start":"00:00", "end":"00:00"});
		console.log($scope.quiet_hours);
	}

	// Remove a quiet hours
	$scope.removeQuietHours = function (hours) {
		for (var i = 0; i < $scope.quiet_hours.length; i++) {
			if ($scope.quiet_hours[i].id == hours.id) {
				$scope.quiet_hours.splice(i, 1);
				break;
			}
		}
	}

	// Enable thunderdome
	$scope.toggleThunderdome = function () {
		$scope.enabled = !$scope.enabled;
	}

	// Get your Thunderdome settings
	$scope.getThunderdome = function () {
		SettingsService.getThunderdome(
			function (response) {
				if (response.status) {
					$scope.twitter = response.data.twitter;
					$scope.quiet_hours = JSON.parse(response.data.quiet_hours);
					$scope.enabled = response.data.enabled == 1 ? true : false;
				}
				else {
					console.log(response.message);
					// $scope.thunderdome_alert.message = response.message;
					// $scope.thunderdome_alert.type = "alert-warning";
					// $scope.thunderdome_alert.show = true;
				}
			},
			function (error) {
				console.log(error);
			}
		);
	}

	// Save Thunderdome settings
	$scope.updateThunderdome = function () {
		var twitter = $scope.twitter;
		var quiet_hours = JSON.stringify($scope.quiet_hours);
		var enabled = $scope.enabled ? 1 : 0;
		SettingsService.updateThunderdome(twitter, quiet_hours, enabled,
			function (response) {
				if (response.status) {
					$scope.thunderdome_alert.message = "Thunderdome Settings Saved";
					$scope.thunderdome_alert.type = "alert-info";
					$scope.thunderdome_alert.show = true;
				}
				else {
					$scope.thunderdome_alert.message = response.message;
					$scope.thunderdome_alert.type = "alert-danger";
					$scope.thunderdome_alert.show = true;
				}
			},
			function (error) {
				console.log(error);
			}
		)
	}

	// Delete Thunderdome settings
	$scope.deleteThunderdome = function () {
		SettingsService.deleteThunderdome(
			function (response) {
				if (response.status) {
					$scope.thunderdome_alert.message = "Thunderdome Settings Deleted";
					$scope.thunderdome_alert.type = "alert-info";
					$scope.thunderdome_alert.show = true;
					$scope.twitter = "";
					$scope.quiet_hours = [];
					$scope.enabled = true;
				}
				else {
					$scope.thunderdome_alert.message = response.message;
					$scope.thunderdome_alert.type = "alert-danger";
					$scope.thunderdome_alert.show = true;
				}
			},
			function (error) {
				console.log(error);
			}
		);
	}
	*/

	// Check for the user's API key
	$scope.retrieveKey();	
	// Get a user's Thunderdome settings
	// $scope.getThunderdome();
}]);


