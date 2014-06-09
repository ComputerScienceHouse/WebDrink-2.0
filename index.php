<?php

/*
*	Page initialization data 
*/
// Include the LDAP connection info
require_once("./ldapInfo.inc");
// Include configuration info
require_once("./config.php");

// Grab some necessary info from webauth
$user_data = array();
if (DEBUG) {
	$user_data['cn'] = "Ben Centra";
	$user_data['uid'] = "bencentra";
	$user_data['entryuuid'] = "1c624ae8-5a2e-102f-98fa-b57270bb12b3";
}
else {
	$user_data['uid'] = $_SERVER['WEBAUTH_USER'];
	$user_data['cn'] = $_SERVER['WEBAUTH_LDAP_CN'];
	$user_data['entryuuid'] = $_SERVER['WEBAUTH_LDAP_ENTRYUUID'];
}

// Get some initial data from LDAP
$filter = "(entryuuid=".$user_data['entryuuid'].")";
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
	<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
	<script src="js/socket.io-client.js"></script>
	<script type="text/javascript">
		window.current_user = <?php echo json_encode($user_data); ?>;
		var baseUrl = "<?php echo API_BASE_URL; ?>";
	</script>
	<script type="text/javascript" src="js/app.js"></script>
	<?php if ($user_data['admin']): ?>
	<script type="text/javascript" src="js/admin.js"></script>
	<script type="text/javascript" src="//code.highcharts.com/highcharts.js"></script>
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
				<a class="navbar-brand" href="#/machines">WebDrink 2.0</a>
			</div>
			<div id="navbar" class="collapse navbar-collapse">
				<ul class="nav navbar-nav">
					<li ng-class="(location.path() == '/machines') ? 'active' : ''" class="navitem"><a href="#/machines">Machines</a></li>	
					<li ng-class="(location.path() == '/api') ? 'active' : ''" class="navitem"><a href="#/api">API</a></li>
					<li ng-class="(location.path() == '/thunderdome') ? 'active' : ''" class="navitem"><a href="#/thunderdome">Thunderdome</a></li>
					<li ng-class="(location.path().indexOf('/admin') != -1) ? 'active' : ''" class="dropdown" ng-show="current_user.admin">
						<a href="" class="dropdown-toggle" data-toggle="dropdown">Admin<b class="caret"></b></a>
						<ul class="dropdown-menu">
							<li><a class="navitem" href="#admin/users">Users</a></li>
							<li><a class="navitem" href="#admin/items">Items</a></li>
							<li><a class="navitem" href="#admin/temps">Temps</a></li>
							<li><a class="navitem" href="#admin/logs">Logs</a></li>
						</ul>
					</li>
				</ul>
				<ul class="nav navbar-nav navbar-right">
					<li ng-class="(location.path() == '/drops') ? 'active' : ''" class="navitem"><a href="#/drops">{{ current_user.uid }} ({{ current_user.credits }} Credits)</a></li>
				</ul>
			</div>
		</div>
	</header>
	<main class="container" ng-view></main>
</body>
</html>