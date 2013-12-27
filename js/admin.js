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

app.factory("UserService", function($http, $window, $log) {
	return {	
		searchUsers: function(search, successCallback, errorCallback) {
			var url = "api/v1/users/searchUsers/search/"+search;
			$http.get(url).success(successCallback).error(errorCallback);
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

function UserCtrl($scope, $log, UserService) {
	$scope.searchTerm = "";
	$scope.searchResults = {};

	$scope.getUsers = function() {
		$log.log($scope.searchTerm);
		if ($scope.searchTerm.length > 0) 
		UserService.searchUsers($scope.searchTerm,
			function (response) {
				if (response.result) {
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

function ItemCtrl($scope, $log, ItemService) {
	
}

function TempCtrl($scope, $log, TempService) {
	
}

function LogsCtrl($scope, $log, LogsService) {
	
}