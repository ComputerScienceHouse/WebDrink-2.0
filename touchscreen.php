<!DOCTYPE HTML>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="">
  <meta name="keywords" content="">
  <meta name="author" content="">
  <title>Page Title</title>
  <!-- Styles -->
  <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css">
  <style>
    body {
      padding-top: 10px;
      padding-bottom: 10px;
    }
    .navbar {
      margin-bottom: 15px;
    }
    .drink {
      font-size: 1.25em;
      background: #f8f8f8;
      padding: 5px;
      border: 1px solid #e7e7e7;
      border-radius: 5px;
      margin-bottom: 30px;
      text-align: center;
      cursor: pointer;
      transition: border .5s;
    }
    .drink:hover {
      border: 1px solid #337ab7;
      transition: border .5s;
    }
    .name {
      font-weight: normal;
      font-size: 1.25em;
    }
    .holder {
      background: #ffffff;
      border: 1px solid #e7e7e7;
      border-radius: 5px;
      width: 100px;
      height: 100px;
      margin: auto;
    }
  </style>
</head>
<body>
  <!-- Content -->
  <div class="container">
    <nav class="navbar navbar-default">
      <div class="container-fluid">
        <div class="navbar-header">
          <a class="navbar-brand" href="#">Little Drink</a>
        </div>
        <ul class="nav navbar-nav navbar-right">
          <li class="navitem"><a href="#/drops">bencentra (1337 Credits) </a></li>
        </ul>
      </div>
    </nav>
    <div class="row" id="drinks">
      
    </div>
  </div>
  <!-- Scripts -->
  <script src="//code.jquery.com/jquery-2.1.1.min.js"></script>
  <script src="//netdna.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js"></script>
  <!--<script src="//code.angularjs.org/snapshot/angular.min.js"></script>-->
  <script>

    document.addEventListener("DOMContentLoaded", function() {

      var little_drink = [{"slot_num":"1","machine_id":"1","display_name":"Little Drink","item_id":"8","item_name":"Dr. Pepper","item_price":"1","available":"1","status":"enabled"},{"slot_num":"2","machine_id":"1","display_name":"Little Drink","item_id":"90","item_name":"Cherry Coke","item_price":"1","available":"1","status":"enabled"},{"slot_num":"3","machine_id":"1","display_name":"Little Drink","item_id":"117","item_name":"Sprite Cranberry","item_price":"1","available":"1","status":"enabled"},{"slot_num":"4","machine_id":"1","display_name":"Little Drink","item_id":"9","item_name":"Coke","item_price":"1","available":"1","status":"enabled"},{"slot_num":"5","machine_id":"1","display_name":"Little Drink","item_id":"107","item_name":"Vanilla Coke","item_price":"1","available":"1","status":"enabled"}];

      var $drinks = $("#drinks");
      little_drink.forEach(function(e, i, a) {
        var html = "<div class='col-md-4 col-sm-4'><div class='drink lead'>";
        html += "<p><div class='holder'></div></p>";
        html += "<p class='name'>"+e.item_name+"</p>";
        html += "<p class='price'>"+e.item_price+" Credits</p>";
        html += "</div></div>";
        $drinks.append(html);
      });

    });

  </script> 
</body>
</html>