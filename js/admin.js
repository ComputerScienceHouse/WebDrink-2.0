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
		}).
		otherwise({
			redirectTo: '/admin/users'
		});
}]);

app.factory("AdminService", function($http, $window) {
	return {
		
	};
});

function UserCtrl($scope, $log, AdminService) {
	
}

function ItemCtrl($scope, $log, AdminService) {
	
}

function TempCtrl($scope, $log, AdminService) {
	
}

function LogsCtrl($scope, $log, AdminService) {
	
}