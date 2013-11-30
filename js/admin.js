// Routes
app.config(['$routeProvider', function($routeProvider) {
	$routeProvider.
		when('/admin', {
			templateUrl: 'partials/admin.html',
			controller: 'AdminCtrl'
		});
}]);

app.factory("AdminService", function($http, $window) {
	return {
		
	};
});

function AdminCtrl($scope, AdminService) {
	
}
