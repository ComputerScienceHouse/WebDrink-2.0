/*
* Touchscreen Angular App
*/
var app = angular.module("touchscreen", []);

/*
* Custom directives
*/
app.directive('drink', function() {
  return {
    restrict: "E",
    transclude: true,
    scope: {
      drink: "=data"
    },
    templateUrl: "directives/drink.html"
  };
});

/*
* TouchscreenService
*/
app.factory("TouchscreenService", ["$http", function($http) {

  var apiUrl = "https://webdrink.csh.rit.edu/api/";

  var success = function(cb) {
    return function(resp) {
      if (resp.status === true) {
        cb(resp.data);
      }
      else {
        console.error(resp.message);
      }
    };
  }

  var error = function(e) {
    console.error(e);
  };

  return {
    getUser: function(ibutton, callback) {
      $http.get(apiUrl+"?request=users/info&ibutton="+ibutton).success(success(callback)).error(error);
    },
    getStock: function(machine, callback) {
      $http.get(apiUrl+"?request=machines/stock&machine_id="+machine).success(success(callback)).error(error);
    },
    getStatus: function(callback) {
      $http.get(apiUrl+"?request=drops/status").success(success(callback)).error(error);
    },
    dropDrink: function(ibutton, machine, slot, callback) {
      $http.post(apiUrl, {
        request: "drops/drop",
        ibutton: ibutton,
        machine_id: machine,
        slot_num: slot
      }).success(success(callback)).error(error);
    }
  };

}]);

/*
* TouchscreenController
*/
app.controller("TouchscreenController", ["$scope", "TouchscreenService", function($scope, TouchscreenService) {

  var machineId = (function() {
    var search = window.location.search;
    if (search === "" || search.indexOf("machine_id=") === -1) { 
      $scope.message = "Initialization Error";
      $scope.detail = "Missing `machine_id` query parameter";
      return false;
    }
    search = search.split("machine_id=")[1].substr(0, 1);
    return parseInt(search);
  }());

  var reset = function() {
    $scope.connected = false;
    $scope.ibutton = false;
    $scope.stock = [];
    $scope.user = {};
    $scope.message = "Touch iButton To Continue";
    $scope.detail = false;
  };
  $scope.logout = reset;

  var authenticate = function(ibutton) {
    $scope.ibutton = ibutton;
    getUserInfo();
    getMachineStock();
  };
  $scope.login = authenticate;

  var getUserInfo = function() {
    if ($scope.ibutton === false) return false;
    TouchscreenService.getUser($scope.ibutton, function(data) {
      $scope.user = data;
    });
  };

  var getMachineStock = function() {
    if (!machineId) return false;
    TouchscreenService.getStock(machineId, function(data) {
      $scope.stock = data[machineId];
    });
  };

  var checkConnection = function() {
    TouchscreenService.getStatus(function(data) {
      $scope.connected = data;
    });
  };

  reset();

}]);