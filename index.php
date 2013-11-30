<?php

/*
*	Page initialization data 
*/
// Include the LDAP connection info
require_once("./ldapInfo.inc");

// Grab some necessary info from webauth
$user_data = array();
//$user_data['cn'] = $_SERVER['WEBAUTH_LDAP_CN'];
$user_data['cn'] = "Ben Centra";
//$user_data['uid'] = $_SERVER['WEBAUTH_USER'];
$user_data['uid'] = "bencentra";

// Get some initial data from LDAP
$filter = "(uid=".$user_data['uid'].")";
$fields = array('drinkAdmin', 'drinkBalance', 'ibutton');
$search = ldap_search($conn, $userDn, $filter, $fields);
$data = ldap_get_entries($conn, $search);

// Add it to the user_data array
$user_data['admin'] = $data[0]["drinkadmin"][0];
$user_data['credits'] = $data[0]["drinkbalance"][0];
$user_data['ibutton'] = $data[0]["ibutton"][0];

?>
<!DOCTYPE HTML>
<html ng-app="WebDrink">
<head>
	<title>WebDrink 2.0</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<!-- Styles -->
	<link href="css/bootstrap.min.css" rel="stylesheet" media="screen" type="text/css"/>
	<link href="css/main.css" rel="stylesheet" media="screen" type="text/css"/>
	<!-- Scripts -->
	<script src="js/angular.min.js"></script>
	<script src="js/angular-route.min.js"></script>
	<script src="js/angular-animate.min.js"></script>
	<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
	<script type="text/javascript">
		window.current_user = <?php echo json_encode($user_data); ?>;
	</script>
	<script type="text/javascript" src="js/app.js"></script>
	<?php if ($user_data['admin']): ?>
	<script type="text/javascript" src="js/admin.js"></script>
	<?php endif; ?>
</head>
<body ng-controller="RootCtrl">
	<header class="navbar navbar-inverse navbar-fixed-top">
		<div class="container">
			<div class="navbar-header">
				<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
				<a class="navbar-brand" href="#">WebDrink 2.0</a>
			</div>
			<div class="collapse navbar-collapse">
				<ul class="nav navbar-nav">
					<li><a href="#/machines">Machines</a></li>
					<?php if ($user_data['admin']): ?><li><a href="#/admin">Admin Panel</a></li><?php endif; ?>
				</ul>
				<ul class="nav navbar-nav navbar-right">
					<li><a href="#/drops">{{ current_user.uid }} ({{ current_user.credits }} Credits)</a></li>
				</ul>
			</div>
		</div>
	</header>
	<main class="container" ng-view></main>
</body>
</html>