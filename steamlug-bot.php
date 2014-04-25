<?php
/***********************************************/
function ControlC ($iSignal)
/***********************************************/
{
	QuitMessage ('Received Ctrl+c.');
	fclose ($GLOBALS['socket']);
	mysqli_close ($GLOBALS['link']);
	exit();
}
/***********************************************/
function Connect ()
/***********************************************/
{
	$iSecondsTimeout = 5;
	$iTries = 0;

	do {
		$GLOBALS['socket'] = fsockopen ($GLOBALS['irc_host'],
			$GLOBALS['irc_port'], $iError, $sError, $iSecondsTimeout);
		if ($GLOBALS['socket'] == FALSE)
		{
			sleep (3); /*** Wait 3 seconds. ***/
			$iTries++;
			if ($iTries == 10)
			{
				print ('[FAILED] Could not connect!' . "\n");
				exit();
			}
		}
	} while ($GLOBALS['socket'] == FALSE);

	/*** Make sure it doesn't wait for fgets. ***/
	stream_set_blocking ($GLOBALS['socket'], 0);
}
/***********************************************/
function Nick ($sBotName)
/***********************************************/
{
	Write ('NICK ' . $sBotName);
}
/***********************************************/
function User ($sBotName)
/***********************************************/
{
	Write ('USER ' . $sBotName . ' 0 * :' . $GLOBALS['botdesc']);
}
/***********************************************/
function Write ($sString)
/***********************************************/
{
	fputs ($GLOBALS['socket'], $sString . "\r" . "\n");
}
/***********************************************/
function GetSetting ($sKey)
/***********************************************/
{
	$query = "SELECT setting_value FROM `settings` WHERE (setting_key ='" .
		$sKey . "');";
	$result = mysqli_query ($GLOBALS['link'], $query);
	$row = mysqli_fetch_assoc ($result);

	return ($row['setting_value']);
}
/***********************************************/
function SetSetting ($sKey, $sValue)
/***********************************************/
{
	$query = "UPDATE `settings` SET setting_value='" . $sValue .
		"' WHERE (setting_key ='" . $sKey . "');";
	$result = mysqli_query ($GLOBALS['link'], $query);
}
/***********************************************/
function MaxId ($sId, $sTable)
/***********************************************/
{
	$query = "SELECT MAX(" . $sId . ") AS max FROM `" . $sTable . "`;";
	$result = mysqli_query ($GLOBALS['link'], $query);
	$row = mysqli_fetch_assoc ($result);

	return ($row['max']);
}
/***********************************************/
function CheckTweets ()
/***********************************************/
{
	$iLastTweetMentioned = GetSetting ('last_tweet_mentioned');
	if ($iLastTweetMentioned == 0)
	{
		SetSetting ('last_tweet_mentioned', MaxId ('tweet_id', 'tweets'));
	} else {
		$query = "SELECT * FROM `tweets` WHERE (tweet_id > " .
			$iLastTweetMentioned . ");";
		$result = mysqli_query ($GLOBALS['link'], $query);
		if (mysqli_num_rows ($result) > 0)
		{
			while ($row = mysqli_fetch_assoc ($result))
			{
				if ($row['tweet_text'][0] != '@')
				{
					Say ($GLOBALS['channel'], ColorThis ('tweet') .
						' (by ' . $row['tweet_user'] . ') ' .
						html_entity_decode (strip_tags ($row['tweet_text']),
						ENT_QUOTES, 'utf-8') .
						' (' . $row['tweet_date'] . ' - https://twitter.com/' .
						$row['tweet_user'] . '/status/' . $row['tweet_id'] . ')');
				}
				$lastid = $row['tweet_id'];
			}
			SetSetting ('last_tweet_mentioned', $lastid);
		}
	}
}
/***********************************************/
function CheckNews ()
/***********************************************/
{
	$iLastNewsMentioned = GetSetting ('last_news_mentioned');
	if ($iLastNewsMentioned == 0)
	{
		SetSetting ('last_news_mentioned', MaxId ('news_id', 'news'));
	} else {
		$query = "SELECT * FROM `news` WHERE (news_id > " .
			$iLastNewsMentioned . ");";
		$result = mysqli_query ($GLOBALS['link'], $query);
		if (mysqli_num_rows ($result) > 0)
		{
			while ($row = mysqli_fetch_assoc ($result))
			{
				$sText = $row['news_text'];
				if (strlen ($sText) > $GLOBALS['maxnews'])
				{
					$sText = substr ($sText, 0, $GLOBALS['maxnews']) . '...';
				}
					Say ($GLOBALS['channel'], ColorThis ('news') .
						' (by ' . $row['news_group'] . ') [' . $row['news_title'] .
						'] ' . $sText . ' (' .
						$row['news_date'] . ' - ' . $row['news_link'] . ')');
				$lastid = $row['news_id'];
			}
			SetSetting ('last_news_mentioned', $lastid);
		}
	}
}
/***********************************************/
function CheckEvents ()
/***********************************************/
{
	$iLastEventMentioned = GetSetting ('last_event_mentioned');
	if ($iLastEventMentioned == 0)
	{
		SetSetting ('last_event_mentioned', MaxId ('event_id', 'events'));
	} else {
		$query = "SELECT * FROM `events` WHERE (event_id > " .
			$iLastEventMentioned . ");";
		$result = mysqli_query ($GLOBALS['link'], $query);
		if (mysqli_num_rows ($result) > 0)
		{
			while ($row = mysqli_fetch_assoc ($result))
			{
				$sText = $row['event_text'];
				if (strlen ($sText) > $GLOBALS['maxevent'])
				{
					$sText = substr ($sText, 0, $GLOBALS['maxevent']) . '...';
				}
					Say ($GLOBALS['channel'], ColorThis ('event') .
						' [' . $row['event_title'] . '] ' . $sText . ' (' .
						$row['event_date'] . ' - ' . $row['event_link'] . ')');
				$lastid = $row['event_id'];
			}
			SetSetting ('last_event_mentioned', $lastid);
		}
	}
}
/***********************************************/
function ExtractURLInfo ($sRecipient, $sSaid)
/***********************************************/
{
	/*** Test with: http://regex101.com/ ***/
	$regex = '/(http|https)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,4}(\/\S*)?/';

	$arMatches = array();
	if (preg_match_all ($regex, $sSaid, $arMatches))
	{
		$arMatches = array_unique ($arMatches[0]);
		$iMaxURL = 0;
		foreach ($arMatches as $sMatch)
		{
			if (ValidURL ($sMatch) == 1)
			{
				$sMatch = GetFinalURL ($sMatch);
				foreach ($GLOBALS['needurlinfo'] as $key=>$value)
				{
					if ($value == substr ($sMatch, 0, strlen ($sMatch)))
					{
						$sTitle = GetTitle ($sMatch);
						if ($sTitle != FALSE)
						{
							Say ($sRecipient, ColorThis ('url') . ' ' . $sTitle);
							$iMaxURL++;
						}
					}
				}
			}
			if ($iMaxURL == 3) { break; }
		}
	}
}
/***********************************************/
function LastTweets ($sRecipient, $arTweets)
/***********************************************/
{
	foreach ($arTweets as $key=>$value)
	{
		$tweet_id = $value['id_str'];
		$tweet_date = date ('Y-m-d H:i:s',
			strtotime ($value['created_at'] . 'UTC'));
		$tweet_text = $value['text'];
		$tweet_user = $value['user']['screen_name'];

		Say ($sRecipient, ColorThis ('tweet') . ' ' .
			html_entity_decode (strip_tags ($tweet_text),
			ENT_QUOTES, 'utf-8') .
			' (' . $tweet_date . ' - https://twitter.com/' .
			$tweet_user . '/status/' . $tweet_id . ')');
	}
}
/***********************************************/
function ColorThis ($sText)
/***********************************************/
{
	return ($GLOBALS['lightgrey'] . '[' . $GLOBALS['reset'] .
		$GLOBALS['bold'] . $sText . $GLOBALS['reset'] .
		$GLOBALS['lightgrey'] . ']' . $GLOBALS['reset']);
}
/***********************************************/
function GetFinalURL ($sUrl)
/***********************************************/
{
	$ch = curl_init();
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt ($ch, CURLOPT_HEADER, FALSE);
	curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt ($ch, CURLOPT_ENCODING, '');
	curl_setopt ($ch, CURLOPT_USERAGENT, $GLOBALS['useragent']);
	curl_setopt ($ch, CURLOPT_AUTOREFERER, TRUE);
	curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt ($ch, CURLOPT_TIMEOUT, 10);
	curl_setopt ($ch, CURLOPT_MAXREDIRS, 10);
	curl_setopt ($ch, CURLOPT_NOBODY, TRUE);
	curl_setopt ($ch, CURLOPT_URL, $sUrl);
	$page = array();
	$page['html'] = curl_exec ($ch);
	$sUrl = curl_getinfo ($ch, CURLINFO_EFFECTIVE_URL);
	curl_close ($ch);

	return ($sUrl);
}
/***********************************************/
function QuitMessage ($sMessage)
/***********************************************/
{
	fputs ($GLOBALS['socket'], 'QUIT :' . $sMessage . "\r" . "\n");
}
/***********************************************/
function Say ($sTo, $sSay)
/***********************************************/
{
	if (($sTo[0] == '#') || ($sTo == 'NickServ'))
	{
		$sType = 'PRIVMSG'; /*** To a channel. ***/
	} else {
		$sType = 'NOTICE'; /*** To a person. ***/
	}
	fputs ($GLOBALS['socket'], $sType . ' ' . $sTo . ' :' . $sSay . "\r" . "\n");
	LogLine ($GLOBALS['botname'], '', '', $sType, $sTo, '', 1, $sSay);
	$GLOBALS['idlesince'] = time();
}
/***********************************************/
function GetTitle ($sURL)
/***********************************************/
{
	$sHTML = GetURL ($sURL);
	$doc = new DOMDocument();
	@$doc->loadHTML(mb_convert_encoding($sHTML, 'HTML-ENTITIES', 'UTF-8'));
	$nodes = $doc->getElementsByTagName('title');
	if ($nodes->length != 0)
	{
		$sTitle = $nodes->item(0)->nodeValue;
		/*** Convert \r, \n, \t and \f to spaces. ***/
		$sTitle = preg_replace ('/\s+/', ' ', $sTitle);
		/*** Remove space, \t, \n, \r, \0 and \x0B from the ***/
		/*** beginning and end. ***/
		$sTitle = trim ($sTitle);
		if (strlen ($sTitle) > $GLOBALS['maxtitle'])
		{
			$sTitle = substr ($sTitle, 0, $GLOBALS['maxtitle']) . '...';
		}
	} else {
		$sTitle = FALSE;
	}

	return ($sTitle);
}
/***********************************************/
function GetURL ($sUrl)
/***********************************************/
{
	$ch = curl_init();
	curl_setopt ($ch, CURLOPT_USERAGENT, $GLOBALS['useragent']);
	curl_setopt ($ch, CURLOPT_HEADER, FALSE);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt ($ch, CURLOPT_URL, $sUrl);
	curl_setopt ($ch, CURLOPT_RANGE, '0-1048576'); /*** 1M max. ***/
	$sData = curl_exec ($ch);
	curl_close ($ch);

	return ($sData);
}
/***********************************************/
function ValidURL ($sUrl)
/***********************************************/
{
	$sUrl = trim ($sUrl);
	if ((strpos ($sUrl, 'http://') === 0 || strpos ($sUrl, 'https://') === 0) &&
		filter_var ($sUrl, FILTER_VALIDATE_URL,
		FILTER_FLAG_SCHEME_REQUIRED|FILTER_FLAG_HOST_REQUIRED) !== FALSE)
	{
		$iValid = 1;
	} else {
		$iValid = 0;
	}

	return ($iValid);
}
/***********************************************/
function strposnth ($sNeedle, $sHaystack, $iOcc)
/***********************************************/
{
	$arSplit = explode ($sNeedle, $sHaystack);
	if ($iOcc == 0) { return (FALSE); }
	if ($iOcc > max (array_keys ($arSplit))) { return (FALSE); }
	$iResult = strlen (implode ($sNeedle, array_slice ($arSplit, 0, $iOcc)));

	return ($iResult);
}
/***********************************************/
function GetPart ($sString, $iPart)
/***********************************************/
{
	$iHere = strposnth (' ', $sString, $iPart - 1) + 1;
	$sPart = substr ($sString, $iHere);
	$sPart = preg_replace ('/\r|\n/', '', $sPart);

	return ($sPart);
}
/***********************************************/
function LogLine ($sNick, $sIdent, $sHost, $sCommand,
	$sChannel, $sPerson, $iIdentified, $sText)
/***********************************************/
{
	$sText = mysqli_real_escape_string ($GLOBALS['link'], $sText);
	$sIdent = mysqli_real_escape_string ($GLOBALS['link'], $sIdent);
	$sDateTime = DateTime();
	$query = "INSERT INTO `log` VALUES (NULL, '" .
		$sNick . "', '" .
		$sIdent . "', '" .
		$sHost . "', '" .
		$sCommand . "', '" .
		$sChannel . "', '" .
		$sPerson . "', '" .
		$iIdentified . "', '" .
		$sText . "', '" .
		$sDateTime . "');";
	$result = mysqli_query ($GLOBALS['link'], $query);
	if ($result == FALSE)
	{
		print ('[ WARN ] This query failed: ' . $query . "\n");
	}
}
/***********************************************/
function DateTime ()
/***********************************************/
{
	return (date ('Y-m-d H:i:s', time()));
}
/***********************************************/
function CapReq ()
/***********************************************/
{
	Write ('CAP REQ identify-msg');
	/*** For security reasons, we MUST have acknowledgement. ***/
	do {
		$sString = fgets ($GLOBALS['socket'], $GLOBALS['maxlinelength']);
		if ($sString != FALSE) { print ('[ WAIT ] ' . $sString); }
	} while (strpos ($sString, 'CAP ' . $GLOBALS['botname'] .
		' ACK :identify-msg') === FALSE);
}
/***********************************************/
function SetCustomURL ($sNick, $sCustomURL)
/***********************************************/
{
	if ($sCustomURL == '"none"') { $sCustomURL = 'none'; }
	$sNickE = mysqli_real_escape_string ($GLOBALS['link'], $sNick);
	$sCustomURLE = mysqli_real_escape_string ($GLOBALS['link'], $sCustomURL);
	$sReturn = 'failed';

	if (Found ('customurl', 'customurl_nick',
		$sNickE) == FALSE) /*** Insert. ***/
	{
		$query = "INSERT INTO `customurl` VALUES (NULL, '" .
			$sNickE . "', '" .
			$sCustomURLE . "');";
		$result = mysqli_query ($GLOBALS['link'], $query);
		if ($result == FALSE)
		{
			print ('[ WARN ] This query failed: ' . $query . "\n");
		} else if (mysqli_affected_rows ($GLOBALS['link']) == 1) {
			$sReturn = 'insert';
		}
	} else { /*** Update. ***/
		$query = "UPDATE `customurl` SET customurl_url='" . $sCustomURLE .
			"' WHERE (customurl_nick='" . $sNickE . "');";
		$result = mysqli_query ($GLOBALS['link'], $query);
		if ($result == FALSE)
		{
			print ('[ WARN ] This query failed: ' . $query . "\n");
		} else if (mysqli_affected_rows ($GLOBALS['link']) == 1) {
			$sReturn = 'update';
		}
	}

	return ($sReturn);
}
/***********************************************/
function GetCustomURL ($sNick)
/***********************************************/
{
	$sNickE = mysqli_real_escape_string ($GLOBALS['link'], $sNick);

	$query = "SELECT customurl_url FROM `customurl` WHERE (customurl_nick='" .
		$sNickE . "');";
	$result = mysqli_query ($GLOBALS['link'], $query);
	if (mysqli_num_rows ($result) > 0)
	{
		$row = mysqli_fetch_assoc ($result);
		$sReturn = $row['customurl_url'];
	} else {
		$sReturn = FALSE;
	}

	return ($sReturn);
}
/***********************************************/
function PlanAskCustomURL ($sNick)
/***********************************************/
{
	$sNickE = mysqli_real_escape_string ($GLOBALS['link'], $sNick);

	if (Found ('askcustomurl', 'askcustomurl_nick', $sNickE) == TRUE)
	{
		return ('found');
	} else {
		$query = "INSERT INTO `askcustomurl` VALUES (NULL, '" .
			$sNickE . "');";
		$result = mysqli_query ($GLOBALS['link'], $query);
		if ($result == FALSE)
		{
			print ('[ WARN ] This query failed: ' . $query . "\n");
			return (FALSE);
		} else if (mysqli_affected_rows ($GLOBALS['link']) == 1) {
			return ('insert');
		} else {
			return (FALSE);
		}
	}
}
/***********************************************/
function AskCustomURL ($sNick)
/***********************************************/
{
	$sNickE = mysqli_real_escape_string ($GLOBALS['link'], $sNick);

	if (Found ('askcustomurl', 'askcustomurl_nick', $sNickE) == TRUE)
	{
		foreach ($GLOBALS['folks'] as $sChannel=>$value)
		{
			if (isset ($GLOBALS['folks'][$sChannel][$sNick]))
			{
				Say ($sChannel, $sNick . ': ' . $GLOBALS['sidrequest']);
				$query = "DELETE FROM `askcustomurl` WHERE" .
					" (askcustomurl_nick='" . $sNickE . "');";
				$result = mysqli_query ($GLOBALS['link'], $query);
				break;
			}
		}
	}
}
/***********************************************/
function FixString ($sString)
/***********************************************/
{
	return (htmlspecialchars ($sString, ENT_QUOTES));
}
/***********************************************/
function AddUser ($sChannel, $sNick)
/***********************************************/
{
	switch ($sNick[0])
	{
		case '@':
			$GLOBALS['folks'][$sChannel][substr ($sNick, 1)] = '@'; break;
		case '+':
			$GLOBALS['folks'][$sChannel][substr ($sNick, 1)] = '+'; break;
		default:
			$GLOBALS['folks'][$sChannel][$sNick] = '-'; break;
	}
}
/***********************************************/
function ChangeUser ($sNickOld, $sNickNew)
/***********************************************/
{
	foreach ($GLOBALS['folks'] as $sChannel=>$value)
	{
		if (isset ($GLOBALS['folks'][$sChannel][$sNickOld]))
		{
			$GLOBALS['folks'][$sChannel][$sNickNew] =
				$GLOBALS['folks'][$sChannel][$sNickOld];
			unset ($GLOBALS['folks'][$sChannel][$sNickOld]);
		}
	}
}
/***********************************************/
function RemoveUser ($sChannel, $sNick)
/***********************************************/
{
	if ($sChannel == '')
	{
		foreach ($GLOBALS['folks'] as $sChannel=>$value)
		{
			if (isset ($GLOBALS['folks'][$sChannel][$sNick]))
				{ unset ($GLOBALS['folks'][$sChannel][$sNick]); }
		}
	} else {
		if (isset ($GLOBALS['folks'][$sChannel][$sNick]))
			{ unset ($GLOBALS['folks'][$sChannel][$sNick]); }
	}
}
/***********************************************/
function ChangeMode ($sChannel, $sNick, $sMode)
/***********************************************/
{
	/*** TODO: We probably need to enable multi-prefix before it is ***/
	/*** useful to track this. ***/
}
/***********************************************/
function UserInChannel ($sChannel, $sNick)
/***********************************************/
{
	if (isset ($GLOBALS['folks'][$sChannel][$sNick]))
		{ return (TRUE); }
			else { return (FALSE); }
}
/***********************************************/
function GiveSteamInfo ($sRecipient, $sTargetUser, $sCustomURL)
/***********************************************/
{
	$arXML = GetSteamInfo ($sCustomURL);
	if (isset ($arXML['privacyState']))
	{
		if ($arXML['privacyState'] == 'public')
		{
			switch ($arXML['onlineState'])
			{
				case 'in-game':
					if (isset ($arXML['inGameInfo']['gameName']))
					{
						$sInGame = $arXML['inGameInfo']['gameName'];
					}
					/*** Unknown games return an <![CDATA[]]> array? ***/
					if (($sInGame == '') || (is_array ($sInGame)))
						{ $sInGame = 'unknown'; }
					$sExtraInfo = 'in-game: ' . $sInGame;
					break;
				case 'offline':
					$sExtraInfo = 'offline';
					break;
				case 'online':
					$sExtraInfo = 'online';
					break;
			}
		} else {
			$sExtraInfo = 'profile not public';
		}
	} else {
		$sExtraInfo = 'unknown privacyState';
	}
	Say ($sRecipient, ColorThis ('steam') . ' (' . $sTargetUser . ')' .
		' http://steamcommunity.com/id/' . $sCustomURL .
		' (' . $sExtraInfo . ')');
}
/***********************************************/

require_once ('steamlug-bot_settings.php');
require_once ('steamlug-bot_def.php');

pcntl_signal (SIGINT, 'ControlC');
pcntl_signal (SIGTERM, 'ControlC');
pcntl_signal (SIGHUP, 'ControlC');
pcntl_signal (SIGUSR1, 'ControlC');

set_time_limit (0); /*** Do not limit the execution time. ***/

$oldtime = time();

do {
	$iJoined = 0;
	$iNoCloak = 0;
	Connect();
	Nick ($GLOBALS['botname']);
	User ($GLOBALS['botname']);

	while (feof ($GLOBALS['socket']) == FALSE)
	{
		pcntl_signal_dispatch(); /*** For ControlC(). ***/

		if ($GLOBALS['debug'] == 0)
		{
			$currenttime = time();
			/*** Every 15 seconds. ***/
			if($currenttime > $oldtime + 15)
			{
				$oldtime = $currenttime;
				CheckTweets();
				/*** CheckNews(); ***/
				CheckEvents();
			}
		}

		$sString = fgets ($GLOBALS['socket'], $GLOBALS['maxlinelength']);

		if ($sString != FALSE)
		{
			if (strstr ($sString, ' ') == FALSE)
			{
				print ('[ WARN ] Strange: ' . $sString . "\n");
			}
			$ex = explode (' ', $sString);
			$ex = preg_replace ('/\r|\n/', '', $ex);
			if ($ex[1] == '437') /*** Nick taken, reclaim it. ***/
			{
				Nick ($GLOBALS['botnametemp']);
				Say ('NickServ', 'RELEASE ' . $GLOBALS['botname'] .
					' ' . $GLOBALS['password']);
				Nick ($GLOBALS['botname']);
			} else if ($ex[1] == '376') { /*** End of the MOTD. ***/
				if ($GLOBALS['password'] != '')
				{
					Say ('NickServ', 'IDENTIFY ' . $GLOBALS['password']);
				}
				if ($GLOBALS['hascloak'] == 0) { $iNoCloak = 1; }
			} else if (($iJoined == 0) && (($ex[1] == '396') || ($iNoCloak == 1))) {
				CapReq();
				Write ('JOIN ' . $GLOBALS['channel']);
				$iJoined = 1;
			} else if ($ex[1] == '353') { /*** User listing. ***/
				$sChannel = $ex[4];
				if (!isset ($GLOBALS['folks'][$sChannel]))
					{ $GLOBALS['folks'][$sChannel] = array(); }
				$sUsers = substr (GetPart ($sString, 6), 1);
				$arUsers = explode (' ', $sUsers);
				foreach ($arUsers as $sNick) { AddUser ($sChannel, $sNick); }
			} else if ($ex[0] == 'PING') {
				Write ('PONG ' . substr ($sString, 5));
			} else if ($iJoined == 1) {
				$sNick = substr ($sString, 1, strpos ($sString, '!') - 1);
				$sIdent = substr ($sString, strpos ($sString, '!') + 1,
					strpos ($sString, '@') - strpos ($sString, '!') - 1);
				$sHost = substr ($sString, strpos ($sString, '@') + 1,
					strpos ($sString, ' ') - strpos ($sString, '@') - 1);
				switch ($ex[1])
				{
					case 'PRIVMSG':
					case 'NOTICE':
						if ($ex[2] == $GLOBALS['botname'])
						{
							$sRecipient = $sNick;
						} else {
							$sRecipient = $ex[2];
						}
						$sSaid = substr (GetPart ($sString, 4), 2);
						switch (substr (GetPart ($sString, 4), 1, 1))
						{
							case '+': $iIdentified = 1; break;
							case '-': $iIdentified = 0; break;
							default:
								$iIdentified = 0; /*** Fallback. ***/
								print ('[ WARN ] Unknown +/- status: ' . $sString);
								break;
						}
						LogLine ($sNick, $sIdent, $sHost,
							$ex[1], $sRecipient, '', $iIdentified, $sSaid);
						if ((!in_array ($sNick, $GLOBALS['ignore'])) &&
							($ex[1] != 'NOTICE'))
						{
							ExtractURLInfo ($sRecipient, $sSaid);
							$exsay = explode (' ', $sSaid);
							switch ($exsay[0])
							{
								case '!t':
									if (isset ($exsay[1]))
									{
										$sTwitterName = $exsay[1];
										if (isset ($exsay[2]))
										{
											$iLastAmount = intval ($exsay[2]);
											if ($iLastAmount > 3) { $iLastAmount = 3; }
											if ($iLastAmount < 1) { $iLastAmount = 1; }
										} else {
											$iLastAmount = 1;
										}
										$arTweets = GetTweetsArray ($sTwitterName, $iLastAmount);
										if (isset ($arTweets['error']))
										{
											Say ($sRecipient, '(failed; "' .
												$arTweets['error'] . '")');
										} else if (empty ($arTweets)) {
											Say ($sRecipient, '(failed; no such user?)');
										} else {
											LastTweets ($sRecipient, $arTweets);
										}
									} else {
										Say ($sRecipient, 'Usage: !t <Twitter name> [1-3]');
									}
									break;
								case '!ping':
									$sPongReply = substr ($sSaid, 5);
									Say ($sRecipient, ColorThis ('pong') . $sPongReply);
									break;
								case '!w':
									if (isset ($exsay[1]))
									{
										$sSearch = substr ($sSaid, 3);
										$arWiki = Wikipedia ($sSearch);
										if ($arWiki == FALSE)
										{
											Say ($sRecipient, ColorThis ('wiki') . ' No results.');
										} else {
											Say ($sRecipient, ColorThis ('wiki') .
												' ' . $arWiki['desc']);
											if ($arWiki['rest'] != '')
											{
												Say ($sRecipient, ColorThis ('wiki') .
													' Similarly named are ' . $arWiki['rest']);
											}
										}
									} else {
										Say ($sRecipient, 'Usage: !w <Wikipedia search>');
									}
									break;
								case chr(1) . 'VERSION' . chr(1):
									Say ($sRecipient, $GLOBALS['botdesc'] .
										' ' . $GLOBALS['botversion']);
									break;
								case chr(1) . 'PING' . chr(1):
									Say ($sRecipient, 'ERRMSG Syntax is: PING <(milli)seconds>');
									break;
								case chr(1) . 'PING':
									$sTheirTime = substr ($sSaid, 6, -1);
									switch (strlen ($sTheirTime))
									{
										case 10: $sOurTime = time(); break; /*** Seconds. ***/
										case 13: $sOurTime = round (microtime (TRUE)
											* 1000); break; /*** Milliseconds. ***/
										default: $sOurTime = '?'; break; /*** Unknown. ***/
									}
									/*** Use PING here, not PONG. ***/
									Say ($sRecipient, 'PING ' . $sOurTime);
									break;
								case chr(1) . 'SOURCE' . chr(1):
								case chr(1) . 'URL' . chr(1):
									Say ($sRecipient, $GLOBALS['botsource']);
									break;
								case chr(1) . 'FINGER' . chr(1):
									$iIdleFor = time() - $GLOBALS['idlesince'];
									Say ($sRecipient, $GLOBALS['botdesc'] .
										' ' . $GLOBALS['botversion'] . ' idle for ' .
										$iIdleFor . ' second(s).');
									break;
								case chr(1) . 'TIME' . chr(1):
									$sDate = date ('D d M Y H:i:s e');
									Say ($sRecipient, $sDate);
									break;
								case chr(1) . 'CLIENTINFO' . chr(1):
								case chr(1) . 'USERINFO' . chr(1):
									Say ($sRecipient, 'VERSION, PING <(milli)seconds>,' .
										' SOURCE, URL, FINGER, TIME, CLIENTINFO, USERINFO');
									break;
								case 'HELP': case 'Help': case 'help':
								case 'INFO': case 'Info': case 'info':
									if ($sRecipient[0] != '#')
									{
										Say ($sRecipient, 'See README.txt: ' .
											$GLOBALS['botsource']);
									}
									break;
								case '!s':
									if (isset ($exsay[1]))
									{
										if ($exsay[1] == 'set')
										{
											if (isset ($exsay[2]))
											{
												if ($iIdentified == 1)
												{
													$sResult = SetCustomURL ($sNick, rtrim ($exsay[2]));
													switch ($sResult)
													{
														case 'failed':
															$sSay = 'has NOT be added/updated'; break;
														case 'insert':
															$sSay = 'has been added'; break;
														case 'update':
															$sSay = 'has been updated'; break;
													}
													Say ($sRecipient, $sNick .
														': your Steam customURL /id/ ' . $sSay . '.');
												} else {
													Say ($sRecipient, $sNick . ': you are not' .
														' identified to NickServ. Use "/msg NickServ' .
														' IDENTIFY [' . $sNick . '] <password>". |' .
														' Register your nickname with "/msg NickServ' .
														' REGISTER <password> <email>".');
												}
											} else {
												Say ($sRecipient, $GLOBALS['susage']);
											}
										} else {
											$sTargetUser = FixString ($exsay[1]);
											$sCustomURL = GetCustomURL ($sTargetUser);
											switch ($sCustomURL)
											{
												case FALSE:
													if (($sRecipient[0] == '#') &&
														(UserInChannel ($sRecipient, $sTargetUser)))
													{
														Say ($sRecipient, $sTargetUser . ': ' .
															$GLOBALS['sidrequest']);
													} else {
														$sResult = PlanAskCustomURL ($sTargetUser);
														switch ($sResult)
														{
															case 'insert':
																Say ($sRecipient, 'When I see ' .
																	$sTargetUser . ' I will ask about that.');
																break;
															case 'found':
																Say ($sRecipient, 'I already have plans to' .
																	' ask ' . $sTargetUser . ' about that.');
																break;
															case FALSE:
																Say ($sRecipient, 'Please try again later.');
																break;
														}
													}
													break;
												case "none":
													Say ($sRecipient, ColorThis ('steam') . ' Sorry, ' .
														$sTargetUser .
														' prefers not to share Steam information.');
													break;
												default:
													GiveSteamInfo ($sRecipient, $sTargetUser,
														$sCustomURL);
													break;
											}
										}
									} else {
										Say ($sRecipient, $GLOBALS['susage']);
									}
									break;
							}
						}
						break;
					case 'QUIT':
						$sText = substr (GetPart ($sString, 3), 1);
						RemoveUser ('', $sNick);
						LogLine ($sNick, $sIdent, $sHost, 'QUIT', '', '', 0, $sText);
						break;
					case 'JOIN':
						if ($sNick != $GLOBALS['botname']) /*** Or AddUser() fails. ***/
						{
							$sChannel = $ex[2];
							AddUser ($sChannel, $sNick);
							AskCustomURL ($sNick);
							LogLine ($sNick, $sIdent, $sHost, 'JOIN', $sChannel, '', 0, '');
						}
						break;
					case 'NICK':
						$sText = substr (GetPart ($sString, 3), 1);
						ChangeUser ($sNick, $sText);
						AskCustomURL ($sText);
						LogLine ($sNick, $sIdent, $sHost, 'NICK', '', '', 0, $sText);
						break;
					case 'PART':
						$sChannel = $ex[2];
						RemoveUser ($sChannel, $sNick);
						LogLine ($sNick, $sIdent, $sHost, 'PART', $sChannel, '', 0, '');
						break;
					case 'KICK':
						$sChannel = $ex[2];
						$sPerson = $ex[3];
						$sText = substr (GetPart ($sString, 5), 1);
						RemoveUser ($sChannel, $sPerson);
						LogLine ($sNick, $sIdent, $sHost, 'KICK',
							$sChannel, $sPerson, 0, $sText);
						break;
					case 'MODE':
						if (substr ($ex[0], 1) != $GLOBALS['botname'])
						{
							$sChannel = $ex[2];
							if (isset ($ex[4]))
								{ $sPerson = $ex[4]; }
									else { $sPerson = ''; }
							$sMode = $ex[3];
							if ($sPerson != '')
								{ ChangeMode ($sChannel, $sPerson, $sMode); }
							LogLine ($sNick, $sIdent, $sHost, 'MODE',
								$sChannel, $sPerson, 0, $sMode);
						}
						break;
					case 'TOPIC':
						$sChannel = $ex[2];
						$sTopic = substr (GetPart ($sString, 4), 1);
						LogLine ($sNick, $sIdent, $sHost, 'TOPIC',
							$sChannel, '', 0, $sTopic);
						break;
					default:
						print ('[ INFO ] ' . $sString);
				}
			}
		}
		usleep (1000);
	}
} while (1);
?>
