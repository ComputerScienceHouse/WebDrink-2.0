// Routes
// app.config(['$routeProvider', function($routeProvider) {
// 	$routeProvider.
// 		when('/thunderdome', {
// 			templateUrl: 'views/thunderdome.html',
// 			controller: 'ThunderdomeCtrl'
// 		}).
// }]);

// Thunderdome Service - get Thunderdome status, trigger a drop, etc
app.factory("ThunderdomeService", function($http, $window, $log) {
	return {
		// Get the status of thunderdome
		getStatus: function(successCallback, errorCallback) {
			$http({
				method: "GET",
				url: baseUrl+"thunderdome/status",
			}).success(successCallback, errorCallback);
		}
	};
});

function ThunderdomeCtrl($scope, $log, MachineService, ThunderdomeService) {
	$scope.stock = {};			// Stock of Thunderdome machine (Little Drink)
	$scope.active = false;		// Is Thunderdome currently happening?
	$scope.tax = 0; 			// How many credits extra a Thunderdome drop costs (to cover expenses)
	$scope.info_alert = new $scope.Alert({
		show: true,
		closeable: false,
		type: "alert-info",
		message: "Welcome to Thunderdome! Drop a drink and watch the ensuing violence!"
	});
	$scope.notdone_alert = new $scope.Alert({
		show: true,
		closeable: false,
		type: "alert-danger",
		message: "Thunderdome does not work yet. Stay tuned!"
	});

	// Get stock of Little Drink
	MachineService.getStockOne(1,
		function (response) {
			if (response.status) {
				$scope.stock = response.data["1"];
			}
			else {
				$log.log(response.message);
				$scope.stock = response.message;
			}
		},
		function (error) {
			$log.log(error);
		}
	);

	// Get the status of thunderdome
	ThunderdomeService.getStatus(
		function (response) {
			if (response.status) {
				$scope.active = (response.data == "1") ? true : false;
			}
			else {
				$log.log(response.message);
			}
		},
		function (error) {
			$log.log(error);
		}
	);

	$scope.triggerThunderdome = function(slot) {
		// Set Thunderdome to "active" in the database
		// TO-DO: A secure way to do this, both here and in the API
		// Drop the drink
		$scope.dropDrink(slot);
	}

	// Drop a drink
	$scope.dropDrink = function(slot) {
		$scope.dropping_message = "Dropping drink...";
		$scope.wsDrop(slot.slot_num, $scope.machines[slot.machine_id].alias, true);
	}
}