<?php
	require_once ('steamlug-bot_settings.php');
	require_once ('steamlug-bot_def.php');

	foreach ($GLOBALS['twitter_names'] as $sName)
	{
		$arTweets = GetTweetsArray ($sName, 10);
		TweetsToMySQL ($arTweets);
	}
/***
	foreach ($GLOBALS['steam_groups'] as $sGroup)
	{
		$arNews = GetGroupNews ($sGroup);
		NewsToMySQL ($arNews, $sGroup);
	}
***/
	$arEvents = GetEvents();
	EventsToMySQL ($arEvents);

	mysqli_close ($GLOBALS['link']);
?>
