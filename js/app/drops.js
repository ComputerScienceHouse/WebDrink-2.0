// Routes
app.config(['$routeProvider', function($routeProvider) {
	$routeProvider.
		when('/drops', {
			templateUrl: 'partials/drops.html',
			controller: 'DropCtrl'
		});
}]);

// Drop Service - retrieve a user's drop history, etc.
app.factory("DropService", function($http, $window, $log) {
	return {
		// Get a user's drop history
		getDrops: function(data, successCallback, errorCallback) {
			$http({
				method: "GET",
				url: baseUrl+"users/drops",
				params: data
			}).success(successCallback).error(errorCallback);
		}
	};
});

// Controller for the drops page
function DropCtrl($scope, $window, $log, DropService) {
	// Initialize scope variables
	$scope.drops = new Array();	// List of all user drops
	$scope.pagesLoaded = 0;		// How many pages of drops have been loaded
	$scope.dropsToLoad = 25;	// How many drops to load at a time
	// Drops Table directive config
	$scope.drops_table = new $scope.DropsTable($scope.drops, $scope.current_user.cn + "'s Drops");

	// Get a user's drop history
	$scope.getDrops = function() {
		var data = {
			"uid": $scope.current_user.uid,
			"limit": $scope.dropsToLoad,
			"offset": $scope.dropsToLoad * $scope.pagesLoaded
		};
		DropService.getDrops(data,
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