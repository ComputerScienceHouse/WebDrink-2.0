/*
*   App
*/

// Angular app
var app = angular.module("touchscreen", []);

// app.loadiButton() - Method to be called by Spynner magic that will authenticate the user by ibutotn
app.loadiButton = function(ibutton) {
  $(document).trigger("webdrink.ibutton.receive", { ibutton: ibutton });
};

/*
*   Directives
*/

// "Drink" directive - represents a drink item
app.directive('drink', function() {
  return {
    restrict: "E",
    transclude: true,
    scope: {
      drink: "=data",       // Drink item (object)
      drop: "=drop",        // Drop drink (function)
      disabled: "=disabled" // Item disabled (true or false)
    },
    templateUrl: "directives/drink.html"
  };
});

/*
*   Services
*/

// "TouchscreenService" - make WebDrink API calls
app.factory("TouchscreenService", ["$http", function($http) {

  // API base URL
  var apiUrl = CONFIG.api.baseUrl;

  // ajaxSuccess() - fires appropriate AJAX callback based on response status
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

  // ajaxError() - handle AJAX errors
  var ajaxError = function(e) {
    console.error(e);
  };

  // Service object
  return {
    // getUser() - Get user info (username, credits, etc)
    getUser: function(ibutton, pass, fail) {
      $http.get(apiUrl+"?request=users/info&ibutton="+ibutton).success(ajaxSuccess(pass, fail)).error(ajaxError);
    },
    // getStock() - Get the stock of a drink machine
    getStock: function(machine, pass, fail) {
      $http.get(apiUrl+"?request=machines/stock&machine_id="+machine).success(ajaxSuccess(pass, fail)).error(ajaxError);
    },
    // getStatus() - Test the connection to the drink server
    getStatus: function(ibutton, pass, fail) {
      $http.get(apiUrl+"?request=drops/status&ibutton="+ibutton).success(ajaxSuccess(pass, fail)).error(ajaxError);
    },
    // dropDrink() - Drop a drink
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
*   Controllers
*/

// "TouchscreenController" - Main app controller
app.controller("TouchscreenController", ["$scope", "$timeout", "TouchscreenService", function($scope, $timeout, TouchscreenService) {

  var resetTimeout; // For session timeouts

  // Get the ID of the current Drink machine from the URL query string
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

  // reset() - Reset the state of the controller
  var reset = function() {
    $scope.connected = true;
    $scope.ibutton = false;
    $scope.stock = [];
    $scope.user = {};
    $scope.message = "Touch iButton To Continue";
    $scope.detail = false;
    $timeout.cancel(resetTimeout);
  };
  // Add reset to the $scope
  $scope.logout = reset;

  // authenticate() - Authenticate the user by ibutton value and initialize the drop selection
  var authenticate = function(ibutton) {
    $scope.ibutton = ibutton; // || (CONFIG.devMode ? CONFIG.devIbutton : false);
    getServerStatus();
    getUserInfo();
    getMachineStock();
    resetTimeout = $timeout(reset, CONFIG.app.sessionTimeout);
  };
  // Add authenticate to the $scope (NOTE: for testing only)
  // $scope.login = authenticate;
  // Listen for authenticaiton event
  $(document).on("webdrink.ibutton.receive", function(e, data) {
    authenticate(data.ibutton);
  });

  // getServerStatus() - Check the status of the drink server
  var getServerStatus = function() {
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

  // getUserInfo() - Get the user's full info (username, credits, etc)
  var getUserInfo = function() {
    if ($scope.ibutton === false) return false;
    TouchscreenService.getUser($scope.ibutton, function(data) {
      $scope.user = data;
    });
  };

  // getMachineStock() - Get the stock of the current drink machine
  var getMachineStock = function() {
    if (machineId === false) return false;
    TouchscreenService.getStock(machineId, function(data) {
      $scope.stock = data[machineId];
    });
  };

  // dropDrink() - Drop the selected drink
  var dropDrink = function(drink) {
    if (isDrinkDisabled(drink)) return false;
    if ($scope.connected === false) return false;
    // Close the drop modal
    var closeModal = function() {
      $timeout(function() {
        $("#drop").modal("hide");
        reset();
      }, CONFIG.app.dropTimeout);
    }
    // Don't end the session
    $timeout.cancel(resetTimeout);
    // Drop the drink
    $scope.drop_message = "Dropping " + drink.item_name + "..."
    $("#drop").modal("show");
    TouchscreenService.dropDrink(
      $scope.ibutton, 
      drink.machine_id, 
      drink.slot_num, 
      // Success!
      function(data) {
        $scope.drop_message = "Drink Dropped!";
        closeModal();
      },
      // Failure!
      function(msg) {
        $scope.drop_message = msg;
        closeModal();
      }
    );
  };
  // Add dropDrink to the $scope
  $scope.drop = dropDrink;

  // isDrinkDisabled() - Determine if the selected drink is enabled/available/affordable
  var isDrinkDisabled = function(drink) {
    if (drink.status !== 'enabled' ||
        parseInt(drink.available) === 0 ||
        parseInt(drink.item_price) > parseInt($scope.user.credits)) {
      return true;
    }
    return false;
  }
  // Add isDrinkDisabled to the $scope
  $scope.disabled = isDrinkDisabled;

  // Initialize the page
  reset();

}]);