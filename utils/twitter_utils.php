<?php

// Include the Twitter API connection class
require_once(__DIR__."../utils/twitter-api-php/TwitterAPIExchange.php");
// Include the database methods
require_once(__DIR__."../utils/db_utils.php");
// Include the connection info
require_once(__DIR__."/../../../webdrink_info/twitterInfo.inc");

// Set the default timezone
date_default_timezone_set("AMERICA/NEW_YORK");

/*
*	Twitter methods
*/
function twitter_tweet_drop($drink, $cameraUrl) {
	global $twitterAuth;
	global $twitterApiUrls;
	$twitter = new TwitterAPIExchange($twitterAuth);
	$postFields = array(
		"status" => "Thunderdome drop! Free $drink! Watch the mayhem at $cameraUrl!"
	);
	$twitter->setPostFields($postFields);
	$twitter->buildOauth($twitterApiUrls["post_tweet"], "POST");
	$result = json_decode($twitter->performRequest());
	return $result;
}

function twitter_alert_users($drink) {
	global $twitterAuth;
	global $twitterApiUrls;
	$twitter = new TwitterAPIExchange($twitterAuth);
	$sql = "SELECT username, twitter, quiet_hours FROM thunderdome_settings WHERE enabled = 1";
	$users = db_select($sql);
	if ($users) {
		foreach ($users as $user) {
			$quietHours = json_decode($user["quiet_hours"]);
			$send = true;
			foreach($quietHours as $q) {
				$start = strtotime($q->start);
				$end = strtotime($q->end);
				if (time() > $start && time() < $end) {
					$send = false;
					break;
				}
			}
		}
		if ($send) {
			$postFields = array(
				"screen_name" => $user["twitter"],
				"text" => "Thunderdome just dropped a $drink! RUN!"
			);
			$twitter->setPostFields($postFields);
			$twitter->buildOauth($twitterApiUrls["send_dm"], "POST");
			$result = json_decode($twitter->performRequest());
		}
	}
}

function twitter_is_user_following($twitterUser) {
	global $twitterAuth;
	global $twitterApiUrls;
	$twitter = new TwitterAPIExchange($twitterAuth);
	$twitter->buildOauth($twitterApiUrls["get_followers_list"], "GET");
	$followers = json_decode($twitter->performRequest());
	$following = false;
	//die (print_r($followers->users, true));
	foreach ($followers->users as $follower) {
		if ($follower->screen_name == $twitterUser) {
			$following = true;
			break;
		}
	}
	return $following;
}

/*
echo "<pre>";
echo "<h1>Test Tweet</h1>";
$tweet = twitter_tweet_drop("Test", "http://www.murderfuck.com");
print_r($tweet);
echo "<br/>";
echo "<h1>Test DM</h1>";
$alert = twitter_alert_users("Test");
print_r($alert);
echo "<br/>";
echo "<h1>Is Following</h1>";
$user = twitter_is_user_following("TheBenCentra");
print_r($user);
echo "</pre>";
*/
?>