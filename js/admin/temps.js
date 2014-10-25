// Routes
app.config(['$routeProvider', function($routeProvider) {
	$routeProvider.
		when('/admin/temps', {
			templateUrl: 'views/admin/temps.html',
			controller: 'TempCtrl'
		});
}]);

// Temp Service - for getting temperature data
app.factory("TempService", function($http, $window) {
	return {
		getTempsOne: function(machineId, successCallback, errorCallback) {
			$http({
				method: "GET",
				url: baseUrl+"temps/machines",
				params: {"machine_id": machineId}
			}).success(successCallback).error(errorCallback);
		}
	};
});

// Controller for the Machine Temperatures page
app.controller("TempCtrl", ['$scope', '$log', 'TempService', function ($scope, $log, TempService) {

	// Get a machine's tempereature data
	$scope.getMachineTemps = function(machineId) {
		TempService.getTempsOne(machineId, 
			function (response) {
				if (response.status) {
					$scope.drawChart(machineId, response.data);
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

	// Draw a temperature chart
	$scope.drawChart = function(id, data) {
		jQuery(function () {
		    jQuery("#"+$scope.machines[id].name).highcharts({
		        chart: {
		            type: 'line'
		        },
		        title: {
		            text: $scope.machines[id].display_name + ' Temperatures'
		        },
		        xAxis: {
		            title: {
		            	text: 'Time'
		            }
		        },
		        yAxis: {
		            title: {
		                text: 'Temperature'
		            }
		        },
		        series: [{
		            name: $scope.machines[id].display_name,
		            data: data
		        }]
		    });
		});
	}

	// Get temperature data for each machine
	for (var machine in $scope.machines) {
		$scope.getMachineTemps(machine);
	}
}]);


