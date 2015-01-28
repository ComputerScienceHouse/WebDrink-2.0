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
      drink: "=data",
      drop: "=drop",
      disabled: "=disabled"
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
  };

  var ajaxSuccess = function(cbPass, cbFail) {
    return function(resp) {
      if (resp.status === true) {
        cbPass(resp.data);
      } else {
        if (typeof cbFail === "function") {
          cbFail(resp.message);
        } else {
          console.error(resp.message);
        }
      }
    };
  }

  var ajaxError = function(e) {
    console.error(e);
  };

  return {
    getUser: function(ibutton, pass, fail) {
      $http.get(apiUrl+"?request=users/info&ibutton="+ibutton).success(ajaxSuccess(pass, fail)).error(ajaxError);
    },
    getStock: function(machine, pass, fail) {
      $http.get(apiUrl+"?request=machines/stock&machine_id="+machine).success(ajaxSuccess(pass, fail)).error(ajaxError);
    },
    getStatus: function(ibutton, pass, fail) {
      $http.get(apiUrl+"?request=drops/status&ibutton="+ibutton).success(ajaxSuccess(pass, fail)).error(ajaxError);
    },
    dropDrink: function(ibutton, machine, slot, pass, fail) {
      var data = {
        ibutton: ibutton,
        machine_id: machine,
        slot_num: slot
      };
      $http({
        method: "POST",
        url: apiUrl+"?request=drops/drop",
        data: jQuery.param(data),
        headers: {'Content-Type': 'application/x-www-form-urlencoded'}
      }).success(ajaxSuccess(pass, fail)).error(ajaxError);
    }
  };

}]);

/*
* TouchscreenController
*/
app.controller("TouchscreenController", ["$scope", "$timeout", "TouchscreenService", function($scope, $timeout, TouchscreenService) {

  var resetTimeout;

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
    $timeout.cancel(resetTimeout);
  };
  $scope.logout = reset;

  var authenticate = function(ibutton) {
    $scope.ibutton = ibutton;
    checkConnection();
    getUserInfo();
    getMachineStock();
    resetTimeout = $timeout(reset, 10000);
  };
  $scope.login = authenticate;

  var getUserInfo = function() {
    if ($scope.ibutton === false) return false;
    TouchscreenService.getUser($scope.ibutton, function(data) {
      $scope.user = data;
    });
  };

  var getMachineStock = function() {
    if (machineId === false) return false;
    TouchscreenService.getStock(machineId, function(data) {
      $scope.stock = data[machineId];
    });
  };

  var checkConnection = function() {
    TouchscreenService.getStatus(
      $scope.ibutton, 
      function(data) {
        $scope.connected = data;
      },
      function(msg) {
        console.error(msg);
        $scope.connected = false;
      }
    );
  };

  var dropDrink = function(drink) {
    if (isDrinkDisabled(drink)) return false;
    if ($scope.connected === false) return false;
    var closeModal = function() {
      $timeout(function() {
        $("#drop").modal("hide");
        reset();
      }, 2000);
    }
    $scope.drop_message = "Dropping " + drink.item_name + "..."
    $timeout.cancel(resetTimeout);
    $("#drop").modal("show");
    TouchscreenService.dropDrink(
      $scope.ibutton, 
      drink.machine_id, 
      drink.slot_num, 
      function(data) {
        $scope.drop_message = "Drink Dropped!";
        closeModal();
      },
      function(msg) {
        $scope.drop_message = msg;
        closeModal();
      }
    );
  };
  $scope.drop = dropDrink;

  var isDrinkDisabled = function(drink) {
    if (drink.status !== 'enabled' ||
        parseInt(drink.available) === 0 ||
        parseInt(drink.item_price) > parseInt($scope.user.credits)) {
      return true;
    }
    return false;
  }
  $scope.disabled = isDrinkDisabled;

  reset();

}]);