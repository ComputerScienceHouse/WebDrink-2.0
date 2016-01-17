<?php

/*
*	Page initialization data 
*/
// Include the LDAP connection info
require_once("../../webdrink_info/ldapInfo.inc");
// Include configuration info
require_once("./config.php");

// Grab some necessary info from webauth
$user_data = array();
if (DEBUG) {
	$user_data['cn'] = htmlentities(DEBUG_USER_CN);
	$user_data['uid'] = htmlentities(DEBUG_USER_UID);
}
else {
	$user_data['uid'] = htmlentities($_SERVER['WEBAUTH_USER']);
	$user_data['cn'] = htmlentities($_SERVER['WEBAUTH_LDAP_CN']);
}

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
	<title>WebDrink</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<!--
	<link rel="shortcut icon" href="assets/favicon.ico">
	<link href="assets/icons/logo_76.png" rel="apple-touch-icon" sizes="76x76"/>
	<link href="assets/icons/logo_120.png" rel="apple-touch-icon" sizes="120x120"/>
	<link href="assets/icons/logo_152.png" rel="apple-touch-icon" sizes="152x152"/>
	<link href="assets/icons/logo_80.png" rel="apple-touch-icon" sizes="180x180"/>
	-->
	<!-- Styles -->
	<link href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css" rel="stylesheet" media="screen" type="text/css"/>
	<link href="css/main.css" rel="stylesheet" media="screen" type="text/css"/>
    <link href="css/members-flat.min.css" rel="stylesheet" media="screen" type="text/css"/>
    <link href="css/purple-theme.css" id="purpleThemeCSS" rel="stylesheet" media="screen" type="text/css"/>
    <link href="css/pink-theme.css" id="pinkThemeCSS" rel="stylesheet" media="screen" type="text/css"/>
	<style type="text/css">
		body {
			padding-top: 70px;
			min-width: 320px;
		}
	</style>
	<!-- Scripts -->
	<script src="//ajax.googleapis.com/ajax/libs/angularjs/1.3.0/angular.min.js"></script>
	<script src="//ajax.googleapis.com/ajax/libs/angularjs/1.3.0/angular-route.min.js"></script>
	<script src="//ajax.googleapis.com/ajax/libs/angularjs/1.3.0/angular-sanitize.min.js"></script>
	<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
	<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>
	<script src="js/spin.min.js"></script>
	<script type="text/javascript">
		// Get the current user's info
		window.current_user = <?php echo json_encode($user_data); ?>;
		// Base URL of the API
		var baseUrl = "<?php echo API_BASE_URL; ?>";
		// Have the navbar collapse (on mobile) after a page is selected
		document.addEventListener("DOMContentLoaded", function() {
			jQuery('.navitem').click(function() {
				if (jQuery(window).width() <= 768)
					jQuery('#navbar').collapse("hide");
			});
		}, true);
	</script>
    <script src="https://rawgithub.com/gsklee/ngStorage/master/ngStorage.js"></script>
	<script type="text/javascript" src="js/app/app.js"></script>
	<script type="text/javascript" src="js/app/machines.js"></script>
	<script type="text/javascript" src="js/app/drops.js"></script>
	<script type="text/javascript" src="js/app/settings.js"></script>
	<script type="text/javascript" src="js/app/thunderdome.js"></script>
	<?php if ($user_data['admin']): ?>
	<script type="text/javascript" src="js/admin/users.js"></script>
	<script type="text/javascript" src="js/admin/items.js"></script>
	<script type="text/javascript" src="js/admin/temps.js"></script>
	<script type="text/javascript" src="js/admin/logs.js"></script>
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
				<a class="navbar-brand" href="#/machines"><img src="assets/icons/webdrink-icon.svg" class="navbar-brand-icon">WebDrink</a>
			</div>
			<div id="navbar" class="collapse navbar-collapse">
				<ul class="nav navbar-nav">
                    <li ng-class="(location.path() == '/machines') ? 'active' : ''" class="navitem"><a href="#/machines">Machines</a></li>	
					<!--<li ng-class="(location.path() == '/thunderdome') ? 'active' : ''" class="navitem"><a href="#/thunderdome">Thunderdome</a></li>-->
					<li ng-class="(location.path() == '/settings') ? 'active' : ''" class="navitem"><a href="#/settings">Settings</a></li>
					<li ng-class="(location.path().indexOf('/admin') != -1) ? 'active' : ''" class="dropdown" ng-show="current_user.admin === '1'">
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
	<main class="container" ng-view>
    </main>
    
    
</body>
</html>