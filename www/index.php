<?php

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

function send_json($data) {
	header('Content-Type: application/json');
	echo json_encode($data);
	exit();
}

function send_json_error($message) {
	send_json(array('error' => $message));
}

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
		if (!$id) {
			send_json_error('Profile not found with that vanity url.');
		}
	}

	$statusName = array('Offline', 'Online', 'Busy', 'Away', 'Snooze', 'Looking to Trade', 'Looking to Play');
	$summary = steam_api_get_summary($id);
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
		<script type="text/javascript" src="//cdn.jsdelivr.net/jquery/2.1.0/jquery.min.js"></script>

		<style>
		body {
			overflow-y: scroll;
		}
		header {
			background-color: #303030;
			padding: 40px 0 70px;
			margin-bottom: 20px;
		}

		header h1 {
			font-size: 60px;
		}

		header h1 small {
			font-size: 20px;
		}

		header h1, header form, header .alert, footer {
			text-align: center;
		}

		#steam-id {
			width: 500px;
		}

		#stuff {
			display: none;
		}

		#stuff>div {
			margin: 0 auto 20px;
		}

		#profile {
			width: 400px;
			padding: 15px;
			background-color: #232323;
			border-radius: 5px;
		}

		#profile>div>div {
			display: inline-block;
			vertical-align: top;
		}

		#profile p {
			margin-bottom: 0;
		}

		#profile .name {
			font-weight: bold;
		}

		#profile .user .avatar {
			width: 68px;
			height: 68px;
			border: 2px solid #343434;
			border-radius: 5px;
			margin-right: 10px;
		}

		#profile .information, #profile .game {
			display: none;
			margin-top: 10px;
		}

		#profile .game .logo {
			width: 124px;
			height: 49px;
			border: 2px solid #343434;
			border-radius: 5px;
			margin-right: 10px;
		}

		#profile .game .name {
			line-height: 49px;
			height: 49px;
			margin-right: 10px;
			width: 160px;
			overflow: hidden;
			white-space: nowrap;
		}

		#profile .game .btn {
			line-height: 27px;
			height: 49px;
		}

		#forget {
			margin: 0 auto !important;
			text-align: center;
		}

		#faq {
			text-align: center;
			width: 800px;
			margin: 0 auto 40px;
		}
		</style>

		<script>
		$(document).ready(function() {
			var $form = $("form");
			var $inputs = $("input, button", $form);
			var $steamId = $("#steam-id");
			var $stuff = $("#stuff");
			var $profile = $("#profile");
			var $forget = $("#forget .btn");

			var $profileAvatar = $(".user .avatar", $profile);
			var $profileName = $(".user .info .name", $profile);
			var $profileStatus = $(".user .info .status", $profile);

			var $profileInformation = $(".information", $profile);

			var $profileGame = $(".game", $profile);
			var $profileGameLogo = $(".logo", $profileGame);
			var $profileGameName = $(".name", $profileGame);
			var $profileGameJoin = $(".btn", $profileGame);

			var steamId = null;
			var lastGame = null;
			var timer = null;

			var setSteamId = function(id) {
				if (steamId == id) return;
				steamId = id;
				if (localStorage) {
					localStorage.setItem("SteamID", steamId);
				}
			}

			var forget = function(soft) {
				clearTimeout(timer);
				lastGame = null;
				$stuff.hide();
				$inputs.attr("disabled", false);
				if (!soft) {
					$steamId.val("");
				}
				if (localStorage) {
					localStorage.clear();
				}
				$form.show();
			}

			var updateProfile = function(data) {
				$form.hide();

				// Update SteamID.
				if (data.id != "") {
					setSteamId(data.id);
				}

				// User data.
				$profileAvatar.css("background-image", "url(" + data.avatar + ")");
				$profileName.text(data.name);
				$profileStatus.attr("class", "status");
				$profileStatus.addClass(data.status == "Offline" ? "text-danger" : "text-success").text(data.status);

				// Game data.
				var game = lastGame;
				if (data.game && (data.game.join || !game || !game.join)) {
					game = data.game;
					$profileInformation.hide();
				} else if (game) {
					$profileInformation.text("Previous game:").show();
				}
				lastGame = game;

				if (game) {
					$profileGameLogo.css("background-image", "url(" + game.logo + ")");
					$profileGameName.text(game.name);
					if (game.join) {
						$profileGameJoin.attr("href", game.join).show();
					} else {
						$profileGameJoin.hide();
					}
					$profileGame.show();
				} else {
					$profileGame.hide();
				}

				$stuff.show();
			}

			if (!localStorage) {
				$forget.text("Back");
			}

			var doRequest = function(silent) {
				$.ajax("?", {
					type: "GET",
					data: { "id": steamId },
					success: function(data) {
						if (data.error) {
							if (!silent) {
								alert(data.error);
								forget(true);
							}
							return;
						}
						updateProfile(data);
					},
					error: function() {
						if (!silent) {
							alert("Server error, sorry.");
							forget(true);
						}
					}
				});
				clearTimeout(timer);
				timer = setTimeout(function() { doRequest(true); }, 60*1000);
			}

			$form.submit(function() {
				var id = $steamId.val();
				if (id.length == 0) {
					return;
				}
				setSteamId(id);
				$inputs.attr("disabled", true);
				doRequest();
			});

			$forget.click(function() { forget(false); });

			if (localStorage) {
				var id = localStorage.getItem("SteamID");
				if (id) {
					$steamId.val(id);
					setSteamId(id);
					$inputs.attr("disabled", true);
					doRequest();
				}
			}

		});
		</script>

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
			<p>Insert your SteamID, the site will ping your Steam profile every minute, keeping track of the last game/server that you were playing on, allowing you to rejoin if the connecting is lost.</p>

			<h3>Why create this?</h3>
			<p>I enjoy playing <a href="http://store.steampowered.com/app/204300/" target="_blank">Awesomenauts</a>, but sadly it lacks a rejoin button. When for some reason I lose the connection there is no way to find the same match. So I created this to keep track of the last match.</p>
		</div>

		<footer>
			<p>Created by <a href="http://goncalomb.com">Gonçalo Baltazar</a> (<a href="http://steamcommunity.com/id/goncalomb" target="_blank">Steam</a>), 2014. <br><small>Using <a href="http://getbootstrap.com/" target="_blank">Bootstrap 3</a> (<a href="http://bootswatch.com/darkly/" target="_blank">darkly</a>). <a href="http://steampowered.com/" target="_blank">Powered by Steam</a>.</p>
		</footer>
	</body>

</html>
