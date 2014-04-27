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
