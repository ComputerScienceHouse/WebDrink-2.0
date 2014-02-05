<!DOCTYPE HTML>
<html>
<head>
	<title>FakeDrink</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style>
		* {
			font-family: "Comic Sans MS", serif;
		}
		.hide {
			display: none;
		}
		table, td, th {
			border: 1px solid black;
		}
		#ohshit {
			color: red;
		}
	</style>
</head>
<body>
	<div id="header">
		<h1>hullo i am a more differnt drink client</h1>
		<p>here belong your api key: <input type="text" id="apikeyholder"/></p>
		<p>press this to make it work: <button type="button" id="startup">click</button></p>
	</div>
	<hr/>
	<div id="ohboy" class="hide">

	</div>
	<div id="therest" class="hide">
		<h1>oh hullo there <span id="user"></span>, you have <span id="credits"></span> credits</h1>
		<h2>here are some drinks</h2>
		<h3>large drank</h3>
		<table id="bigdrink">
			
		</table>
		<h3>small drank</h3>
		<table id="littledrink">
			
		</table>
		<h3>non-liquid drank</h3>
		<table id="snack">
			
		</table>
	</div>
	<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
	<script>
		var baseUrl = "api/"; // "api/index.php?request="
		$(document).ready(function() {
			$("#startup").on("click", function () {
				var apikey = $("#apikeyholder").val();
				$.ajax({
					url: baseUrl+"users/info/api_key/"+apikey, 
					method: "GET",
					dataType: "json",
					success: function (response) {
						if (response.status) {
							$("#user").html(response.data.cn);
							$("#credits").html(response.data.credits);
						}
						else {
							$("#ohboy").removeClass("hide");
							$("#ohboy").html("<h1>WRONG API KEY YOU DINGUS</h1>");
						}
						$.ajax({
							url: baseUrl+"machines/stock/api_key/"+apikey,
							method: "GET",
							dataType: "json",
							success: function (response) {
								if (response.status) {
									var i = 1;
									while(typeof response.data[i] != "undefined") {
										var machine = response.data[i];
										var html = "<tr><th>drank</th><th>monies</th><th>buttons</th></tr>";
										for (var j = 0; j < machine.length; j++) {
											html += "<tr><td>"+machine[j].item_name+"</td><td>"+machine[j].item_price+"</td><td><button onclick='alert(\"LOL\");'>drop</button>";
										}
										if (machine[0].machine_id == "1") {
											$("#littledrink").html(html);
										}
										else if (machine[0].machine_id == "2") {
											$("#bigdrink").html(html);
										}
										else {
											$("#snack").html(html);
										}
										i++;
									}
									$("#therest").removeClass("hide");
								}
								else {
									$("#ohboy").removeClass("hide");
									$("#ohboy").html("<h1>I DUNNO SOMETHING BROKE I GUESS</h1>");
								}
							},
							error: function (error) {
								console.log(error);
							}
						});
					},
					error: function (error) {
						console.log(error);
					}
				});
			});
		});
	</script>
</body>
</html>