<?php
	require_once ('steamlug-bot_settings.php');
	require_once ('steamlug-bot_def.php');

	foreach ($GLOBALS['twitter_names'] as $sName)
	{
		$arTweets = GetTweetsArray ($sName, 10);
		TweetsToMySQL ($arTweets);
	}

	/*** We will also poll for XML data in the future. ***/
	/*** http://steamcommunity.com/groups/steamlug/rss/ ***/

	mysqli_close ($GLOBALS['link']);
?>
