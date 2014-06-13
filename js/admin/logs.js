// Routes
app.config(['$routeProvider', function($routeProvider) {
	$routeProvider.
		when('/admin/logs', {
			templateUrl: 'partials/admin/logs.html',
			controller: 'LogsCtrl'
		});
}]);

// Logs Service - for getting drop logs
app.factory("LogsService", function($http, $window) {
	return {
		// Use DropService instead
	};
});

// Controller for the Drop Logs page
function LogsCtrl($scope, $log, LogsService, DropService) {
	// Initialize scope variables
	$scope.logs = new Array();	// List of all user drops
	$scope.pagesLoaded = 0;		// How many pages of drops have been loaded
	$scope.dropsToLoad = 50;	// How many drops to load at a time
	// Drops Table directive config
	$scope.drops_table = new $scope.DropsTable($scope.logs, "Drop Logs", {
		showUser: true
	});

	// Get a user's drop history
	$scope.getDrops = function() {
		var data = {
			"limit": $scope.dropsToLoad,
			"offset": $scope.dropsToLoad * $scope.pagesLoaded
		};
		DropService.getDrops(data,
			function (response) {
				if (response.status) {
					$scope.logs = $scope.logs.concat(response.data);
					$scope.drops_table.drops = $scope.logs;
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