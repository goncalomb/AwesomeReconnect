<?php
/*
 * Copyright (c) 2014 Gonçalo Baltazar <me@goncalomb.com>
 *
 * Awesome Reconnect is released under the terms of the MIT License,
 * check the file LICENSE.txt.
 *
 */

/* http://steamcommunity.com/dev */
define('STEAM_API_KEY', '{YOUR_STEAM_API_KEY}');

function steam_api_request($method, $params=array()) {
	$params['key'] = STEAM_API_KEY;
	$params['format'] = 'json';
	$query = array();
	foreach ($params as $key => $value) {
		$query[] = urlencode($key) . '=' . urlencode($value);
	}
	return @json_decode(file_get_contents('http://api.steampowered.com/' . $method . '/?' . implode('&', $query)), true);
}

function steam_api_get_summary($steamid) {
	$result = steam_api_request('ISteamUser/GetPlayerSummaries/v0002', array('steamids' => $steamid));
	return (empty($result['response']['players'][0]) ? null : $result['response']['players'][0]);
}

function steam_api_resolve_vanity($url) {
	$result = steam_api_request('ISteamUser/ResolveVanityURL/v0001', array('vanityurl' => $url));
	return (empty($result['response']['steamid']) ? null : $result['response']['steamid']);
}

$stats = null;
function load_stats() {
	global $stats;
	if ($stats === null) {
		$day = ceil(time()/(3600*24));
		if (file_exists('stats.json.txt')) {
			$stats = json_decode(file_get_contents('stats.json.txt'), true);
			if ($day != $stats['Day']) {
				$stats = null;
			}
		}
		if (!$stats) {
			$stats = array(
				'Day' => $day,
				'SteamAPI' => array(
					'ResolveVanityURL' => 0,
					'GetPlayerSummaries' => 0
				)
			);
			save_stats();
		}
	}
}

function save_stats() {
	global $stats;
	if ($stats !== null) {
		file_put_contents('stats.json.txt', json_encode($stats, JSON_PRETTY_PRINT));
	}
}

function send_json($data) {
	save_stats();
	header('Content-Type: application/json');
	echo json_encode($data);
	exit();
}

function send_json_error($message) {
	send_json(array('error' => $message));
}

load_stats();

if (!empty($_GET['id'])) {
	$id = $_GET['id'];

	$isVanity = true;
	if (preg_match('#(?:http://)?steamcommunity\.com/id/([^/\?&]+)#', $id, $matches)) {
		$id = urldecode($matches[1]);
	} else if (preg_match('#(?:http://)?steamcommunity\.com/profiles/(7656119\d+)#', $id, $matches)) {
		$id = $matches[1];
		$isVanity = false;
	} else if (preg_match('/^7656119\d+$/', $id)) {
		$isVanity = false;
	}

	if ($isVanity) {
		$id = steam_api_resolve_vanity($id);
		$stats['SteamAPI']['ResolveVanityURL']++;
		if (!$id) {
			send_json_error('Profile not found with that vanity url.');
		}
	}

	$statusName = array('Offline', 'Online', 'Busy', 'Away', 'Snooze', 'Looking to Trade', 'Looking to Play');
	$summary = steam_api_get_summary($id);
	$stats['SteamAPI']['GetPlayerSummaries']++;
	if (!$summary) {
		send_json_error('Profile not found.');
	} else if ($summary['communityvisibilitystate'] == 1) {
		send_json_error('Profile is private.');
	} else {
		$result = array(
			'id' => $summary['steamid'],
			'name' => $summary['personaname'],
			'avatar' => $summary['avatarmedium'],
			'status' => $statusName[$summary['personastate']]
			);
		if (isset($summary['gameid'])) {
			$result['game'] = array(
				'name' => $summary['gameextrainfo'],
				'logo' => 'http://cdn.steamstatic.com/steam/apps/' . $summary['gameid'] . '/capsule_sm_120.jpg',
				'link' => 'http://steamcommunity.com/app/' . $summary['gameid'],
				);
			$result['status'] .= ' - In-Game';
			if (isset($summary['gameserverip'])) {
				$result['game']['join'] = 'steam://connect/' . $summary['gameserverip'];
			} else if (isset($summary['lobbysteamid'])) {
				$result['game']['join'] = 'steam://joinlobby/' . $summary['gameid'] . '/' . $summary['lobbysteamid'] . '/' . $summary['steamid'];
			}
		}
		send_json($result);
	}

}

?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<title>Awesome Reconnect</title>

		<link rel="stylesheet" type="text/css" href="//cdn.jsdelivr.net/bootswatch/3.1.1.1/darkly/bootstrap.min.css">
		<link rel="stylesheet" type="text/css" href="//cdn.jsdelivr.net/fontawesome/4.0.3/css/font-awesome.min.css">
		<script type="text/javascript" src="//cdn.jsdelivr.net/jquery/2.1.0/jquery.min.js"></script>

		<link rel="stylesheet" type="text/css" href="main.css">
		<script type="text/javascript" src="main.js"></script>

	</head>

	<body>

		<header>

			<h1>Awesome Reconnect</h1>

			<form class="form-inline" onsubmit="return false;">
				<p>A reconnect button for online Steam games!</p>
				<input id="steam-id" class="form-control input-lg" type="text" placeholder="SteamID / VanityURL / CommunityURL">
				<button class="btn btn-success btn-lg" type="submit">OK</button>
			</form>

			<div id="stuff">

				<div id="profile">
					<div class="user">
						<div class="avatar"></div>
						<div class="info">
							<p class="name"></p>
							<p class="status"></p>
						</div>
					</div>
					<div class="information"></div>
					<div class="game">
						<div class="logo"></div>
						<div class="name"></div>
						<a class="btn btn-success" href="">Join</a>
					</div>
				</div>

				<div class="alert alert-info" style="width: 700px"><strong>Go play games now</strong>, leave this page open, if you get disconnected come back here and rejoin.</div>

				<div id="forget">
					<button type="button" class="btn btn-warning btn-sm">Forget Me</button>
				</div>

			</div>

		</header>

		<div id="faq">

			<h2>FAQ</h2>

			<h3>What is this?</h3>
			<p>Insert your SteamID, the site will ping your Steam profile every minute, keeping track of the last game/server that you were playing on, allowing you to rejoin if the connection is lost.</p>

			<h3>Why create this?</h3>
			<p>I enjoy playing <a href="http://store.steampowered.com/app/204300/" target="_blank">Awesomenauts</a>, but sadly it lacks a rejoin button. When for some reason I lose the connection there is no way to find the same match, now, this keeps track of it for me.</p>

			<h3>Does it work with all online Steam games?</h3>
			<p>No, only with games that use Steam based servers. With supported games you will see a 'Join' button on your profile (and here) while playing.</p>

		</div>

		<div id="buttons">
			<a class="btn btn-primary" href="http://www.awesomenauts.com/forum/viewtopic.php?f=6&amp;t=29536" target="_blank"><i class="fa fa-file-text"></i> Forum Topic</a>
			<a class="btn btn-primary" href="https://github.com/goncalomb/AwesomeReconnect" target="_blank"><i class="fa fa-github"></i> Code on GitHub</a>
		</div>

		<footer>
			<p><?php echo 'Steam API requests today: ', ($stats['SteamAPI']['ResolveVanityURL'] + $stats['SteamAPI']['GetPlayerSummaries']); ?></p>

			<p>Created by <a href="http://goncalomb.com">Gonçalo Baltazar</a> (<a href="http://steamcommunity.com/id/goncalomb" target="_blank">Steam</a>), 2014. <br><small>Using <a href="http://getbootstrap.com/" target="_blank">Bootstrap 3</a> (<a href="http://bootswatch.com/darkly/" target="_blank">darkly</a>) and <a href="http://fortawesome.github.io/Font-Awesome/" target="_blank">Font Awesome</a>. <a href="http://steampowered.com/" target="_blank">Powered by Steam</a>.</small></p>
		</footer>
	</body>

</html>
