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
			if ($iTries % 10 == 0)
			{
				print ('[WARNING] Could not connect!' . "\n");
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
						$row['event_pubdate'] . ' - ' . $row['event_link'] . ')');
				$lastid = $row['event_id'];
			}
			SetSetting ('last_event_mentioned', $lastid);
		}
	}
}
/***********************************************/
function Imgur ($sRecipient, $sMatch)
/***********************************************/
{
	foreach ($GLOBALS['imgur'] as $key=>$value)
	{
		$sMatch = str_replace ($value, '', $sMatch);
	}
	$sCode = substr ($sMatch, 0, strpos ($sMatch, '.'));
	$sTitle = GetTitle ('http://imgur.com/' . $sCode);
	if (($sTitle != FALSE) && ($sTitle != 'imgur: the simple image sharer') &&
		($sTitle != 'Imgur') &&
		($sTitle != 'Imgur: The most awesome images on the Internet'))
	{
		$sTitle = str_replace (' - Imgur', '', $sTitle);
		Say ($sRecipient, ColorThis ('imgur') . ' ' . $sTitle);
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

				/*** Imgur image comment. ***/
				foreach ($GLOBALS['imgur'] as $key=>$value)
				{
					if ($value == substr ($sMatch, 0, strlen ($value)))
						{ Imgur ($sRecipient, $sMatch); }
				}

				/*** Tweet information. ***/
				if (((substr ($sMatch, 0, 24) == 'https://www.twitter.com/') ||
					(substr ($sMatch, 0, 23) == 'http://www.twitter.com/') ||
					(substr ($sMatch, 0, 20) == 'https://twitter.com/') ||
					(substr ($sMatch, 0, 19) == 'http://twitter.com/')) &&
					(substr ($sMatch, -26, 8) == '/status/'))
				{
					$sTweetId = substr ($sMatch, -18, 18);
					$arTweet = GetTweetsArray ('', 0, $sTweetId);
					if (isset ($arTweet['errors']))
					{
						Say ($sRecipient, '(failed; "' .
							$arTweet['errors'][0]['message'] . '")');
					} else {
						ShowTweet ($sRecipient, $arTweet);
					}
				}

				/*** Title information. ***/
				foreach ($GLOBALS['needurlinfo'] as $key=>$value)
				{
					if ($value == substr ($sMatch, 0, strlen ($value)))
					{
						$sTitle = GetTitle ($sMatch);
						if ($sTitle != FALSE)
						{
							/*** Get prices. ***/
							$sPrices = ''; /*** Do not move this down. ***/
							$sPatternP = "/store.steampowered.com\/app\/([0-9]+)?.*/";
							$iBingoP = preg_match ($sPatternP, $sMatch, $arMatchP);
							if ($iBingoP == 1)
							{
								$sAppId = $arMatchP[1];
								$sPrices = CPrices ($sAppId);
							}

							Say ($sRecipient, ColorThis ('url') . ' ' . $sTitle . $sPrices);
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
function CPrices ($sAppId)
/***********************************************/
{
	$sPrices = '';
	$arPrices = array();

	/*** de/it ***/
	$arPrices['de'] = GetCurrentPrice ($sAppId, 'de');
	$arPrices['it'] = GetCurrentPrice ($sAppId, 'it');
	if (($arPrices['de'] != FALSE) && ($arPrices['it'] != FALSE)
		&& ($arPrices['de'] != $arPrices['it']))
		{ $iEUDiffer = 1; } else {$iEUDiffer = 0; }
	if ($arPrices['de'] != FALSE)
	{
		$sPrices .= $arPrices['de'];
		if ($iEUDiffer == 1) { $sPrices .= '(t1)'; }
	}
	if (($arPrices['it'] != FALSE) && ($arPrices['de'] !=
		$arPrices['it']))
	{
		if ($sPrices != '') { $sPrices .= '/'; }
		$sPrices .= $arPrices['it'];
		if ($iEUDiffer == 1) { $sPrices .= '(t2)'; }
	}

	/*** uk ***/
	$arPrices['uk'] = GetCurrentPrice ($sAppId, 'uk');
	if ($arPrices['uk'] != FALSE)
	{
		if ($sPrices != '') { $sPrices .= '/'; }
		$sPrices .= $arPrices['uk'];
	}

	/*** us ***/
	$arPrices['us'] = GetCurrentPrice ($sAppId, 'us');
	if ($arPrices['us'] != FALSE)
	{
		if ($sPrices != '') { $sPrices .= '/'; }
		$sPrices .= $arPrices['us'];
	}

	if ($sPrices != '') { $sPrices = ' (now ' . $sPrices . ')'; }

	return ($sPrices);
}
/***********************************************/
function ExtractThanks ($sRecipient, $sNick, $sSaid)
/***********************************************/
{
	$sIn = strtolower ($sSaid);
	$sPattern1 = '/^' . strtolower ($GLOBALS['botname']) . '[:,]? thanks[.!]?$/';
	$iBingo1 = preg_match ($sPattern1, $sIn, $arMatch);
	$sPattern2 = '/^thanks,? ' . strtolower ($GLOBALS['botname']) . '[.! ]?$/';
	$iBingo2 = preg_match ($sPattern2, $sIn, $arMatch);
	if (($iBingo1 == 1) || ($iBingo2 == 1))
	{
		Say ($sRecipient, $sNick . ': no problem! ^-^');
	}
}
/***********************************************/
function ExtractAnswers ($sNick, $sSaid)
/***********************************************/
{
	if (strtolower ($GLOBALS['qa_answer']) == strtolower ($sSaid))
	{
		if ($GLOBALS['qa_winnick'] == '') { $GLOBALS['qa_winnick'] = $sNick; }
	}
}
/***********************************************/
function ShowTweet ($sRecipient, $arTweet)
/***********************************************/
{
	/*** Similar to LastTweets(). Keeping them separated, because one ***/
	/*** of the APIs may change. ***/

	$tweet_id = $arTweet['id_str'];
	$tweet_date = date ('Y-m-d H:i:s',
		strtotime ($arTweet['created_at'] . 'UTC'));
	$tweet_text = $arTweet['text'];
	$tweet_user = $arTweet['user']['screen_name'];

	Say ($sRecipient, ColorThis ('tweet') . ' ' .
		html_entity_decode (strip_tags ($tweet_text),
		ENT_QUOTES, 'utf-8') .
		' (' . $tweet_date . ' - https://twitter.com/' .
		$tweet_user . '/status/' . $tweet_id . ')');
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
	/*** Never allow newlines in $sSay. ***/
	$arSearch = array ('\r', '\n', chr(10), chr(13));
	$arReplace = array (' ', ' ', ' ', ' ');
	$sSay = str_replace ($arSearch, $arReplace, $sSay);

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
function GetFirst ($sString)
/***********************************************/
{
	$msgArray = explode(' ',trim($sString));

	return($msgArray[0]);
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
		print ('[ WARN ] This query failed (' .
			mysqli_error ($GLOBALS['link']) . '): ' . $query . "\n");
	}
}
/***********************************************/
function CapReq ()
/***********************************************/
{
	Write ('CAP REQ identify-msg');
	/*** For security reasons, we MUST have acknowledgement. ***/
	$sString = '';
	$iGotAck = 0;
	do {
		$sGot = fgets ($GLOBALS['socket'], $GLOBALS['maxlinelength']);
		if ($sGot != FALSE) { $sString .= $sGot; }
		if (substr ($sString, -2) == "\r\n")
		{
			print ('[ WAIT ] ' . $sString);
			if (strpos ($sString, 'CAP ' . $GLOBALS['botname'] .
				' ACK :identify-msg') != FALSE) { $iGotAck = 1; }
			$sString = '';
		}
		usleep (1000);
	} while ($iGotAck == 0);
	print ('[ INFO ] Identified with server.' . "\n");
}
/***********************************************/
function SetCustomURL ($sNick, $sCustomURL)
/***********************************************/
{
	$sNickE = mysqli_real_escape_string ($GLOBALS['link'], $sNick);

	/*** Remove URL parts if necessary. ***/
	$arSearch = array ('http://', 'https://',
		'steamcommunity.com/id/', '/id/');
	$arReplace = array ('', '', '');
	$sCustomURL = str_replace ($arSearch, $arReplace, $sCustomURL);
	$sCustomURL = rtrim ($sCustomURL, '/');
	if ($sCustomURL == '') { $sCustomURL = 'none'; }

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
	$arReturn = array();
	$arReturn['url'] = FALSE;
	$arReturn['with'] = FALSE;

	/*** nick ***/
	$query = "SELECT customurl_url FROM `customurl` WHERE" .
		" (customurl_nick='" . $sNickE . "');";
	$result = mysqli_query ($GLOBALS['link'], $query);
	if (mysqli_num_rows ($result) > 0)
	{
		$row = mysqli_fetch_assoc ($result);
		$arReturn['url'] = $row['customurl_url'];
	}

	/*** nick_ ***/
	if ($arReturn['url'] == FALSE)
	{
		$query = "SELECT customurl_url FROM `customurl` WHERE" .
			" (customurl_nick='" . $sNickE . "_');";
		$result = mysqli_query ($GLOBALS['link'], $query);
		if (mysqli_num_rows ($result) > 0)
		{
			$row = mysqli_fetch_assoc ($result);
			$arReturn['url'] = $row['customurl_url'];
			$arReturn['with'] = '_';
		}
	}

	/*** nick- ***/
	if ($arReturn['url'] == FALSE)
	{
		$query = "SELECT customurl_url FROM `customurl` WHERE" .
			" (customurl_nick='" . $sNickE . "-');";
		$result = mysqli_query ($GLOBALS['link'], $query);
		if (mysqli_num_rows ($result) > 0)
		{
			$row = mysqli_fetch_assoc ($result);
			$arReturn['url'] = $row['customurl_url'];
			$arReturn['with'] = '-';
		}
	}

	return ($arReturn);
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
function GiveSteamInfoXML ($sRecipient, $sTargetUser, $sCustomURL)
/***********************************************/
{
	/*** Deprecated. ***/

	$arXML = GetSteamInfoXML ($sCustomURL);
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
function GiveSteamInfoAPI ($sRecipient, $sTargetUser, $sCustomURL)
/***********************************************/
{
	$arResult = GetSteamInfoAPI ($sCustomURL);
	if (isset ($arResult['response']['players'][0]['communityvisibilitystate']))
	{
		$communityvisibilitystate =
			$arResult['response']['players'][0]['communityvisibilitystate'];
		if ($communityvisibilitystate == 3)
		{
			if (isset ($arResult['response']['players'][0]['personastate']))
			{
				$personastate =
					$arResult['response']['players'][0]['personastate'];
				switch ($personastate)
				{
					case 0:
						if (isset ($arResult['response']['players'][0]['lastlogoff']))
						{
							$lastlogoff =
								$arResult['response']['players'][0]['lastlogoff'];
							$sDateTime = date ('Y-m-d H:i:s', $lastlogoff) . ' UTC';
						} else {
							$sDateTime = 'unknown';
						}
						$sExtraInfo = 'offline since ' . $sDateTime;
						break;
					case 1: $sExtraInfo = 'online'; break;
					case 2: $sExtraInfo = 'busy'; break;
					case 3: $sExtraInfo = 'away'; break;
					case 4: $sExtraInfo = 'snooze'; break;
					case 5: $sExtraInfo = 'looking to trade'; break;
					case 6: $sExtraInfo = 'looking to play'; break;
				}
				if ((isset ($arResult['response']['players'][0]['gameextrainfo'])) &&
					(isset ($arResult['response']['players'][0]['gameserverip'])))
				{
					$gameextrainfo =
						$arResult['response']['players'][0]['gameextrainfo'];
					$gameserverip =
						$arResult['response']['players'][0]['gameserverip'];
					$sExtraInfo .= '; in-game: ' . $gameextrainfo;
					if ($gameserverip != '0.0.0.0:0')
					{
						$sExtraInfo .= ' - ' . $gameserverip;
					}
				}
			} else {
				$sExtraInfo = 'unknown personastate';
			}
		} else {
			$sExtraInfo = 'profile not public';
		}
	} else {
		$sExtraInfo = 'unknown communityvisibilitystate';
	}
	Say ($sRecipient, ColorThis ('steam') . ' (' . $sTargetUser . ')' .
		' http://steamcommunity.com/id/' . $sCustomURL .
		' (' . $sExtraInfo . ')');
}
/***********************************************/
function LastSeen ($sTargetUser)
/***********************************************/
{
	$sTargetUser = mysqli_real_escape_string ($GLOBALS['link'], $sTargetUser);
	$query = "SELECT log_channel, log_text, log_datetime FROM `log` WHERE" .
		" (log_nick='" . $sTargetUser . "') AND (log_identified='1') AND" .
		" (log_channel LIKE '#%') AND (log_channel NOT LIKE '#botwar%') ORDER" .
		" BY log_datetime DESC;";
	$result = mysqli_query ($GLOBALS['link'], $query);
	if (mysqli_num_rows ($result) > 0)
	{
		$row = mysqli_fetch_assoc ($result);
		$arReturn = array();
		$arReturn['log_channel'] = $row['log_channel'];
		$arReturn['log_text'] = $row['log_text'];
		$arReturn['log_datetime'] = $row['log_datetime'];
		$dtThen = new DateTime ($row['log_datetime']);
		$dtNow = new DateTime (DateTime());
		$arReturn['time_ago'] = date_diff ($dtThen, $dtNow);
		$iAgoY = $arReturn['time_ago']->y;
		$iAgoM = $arReturn['time_ago']->m;
		$iAgoD = $arReturn['time_ago']->d;
		$iAgoH = $arReturn['time_ago']->h;
		$iAgoI = $arReturn['time_ago']->i;
		$iAgoS = $arReturn['time_ago']->s;
		$arReturn['tas'] = '';
		$iTas = 0;
		if ($iAgoY != 0)
		{
			$arReturn['tas'] .= $iAgoY . ' years'; $iTas++;
		}
		if ($iAgoM != 0)
		{
			if ($iTas != 0) { $arReturn['tas'] .= ', '; }
			$arReturn['tas'] .= $iAgoM . ' months'; $iTas++;
		}
		if ($iAgoD != 0)
		{
			if ($iTas != 0) { $arReturn['tas'] .= ', '; }
			$arReturn['tas'] .= $iAgoD . ' days'; $iTas++;
		}
		if ($iAgoH != 0)
		{
			if ($iTas != 0) { $arReturn['tas'] .= ', '; }
			$arReturn['tas'] .= $iAgoH . ' hours'; $iTas++;
		}
		if ($iAgoI != 0)
		{
			if ($iTas != 0) { $arReturn['tas'] .= ', '; }
			$arReturn['tas'] .= $iAgoI . ' minutes'; $iTas++;
		}
		if ($iAgoS != 0)
		{
			if ($iTas != 0) { $arReturn['tas'] .= ' and '; }
			$arReturn['tas'] .= $iAgoS . ' seconds'; $iTas++;
		}
		if ($iTas == 0)
			{ $arReturn['tas'] = 'just now'; }
				else { $arReturn['tas'] .= ' ago'; }
	} else {
		$arReturn = FALSE;
	}

	return ($arReturn);
}
/***********************************************/
function CheckNewReleases ()
/***********************************************/
{
	$query = "SELECT * FROM `newreleases` WHERE (newrelease_said='0');";
	$result = mysqli_query ($GLOBALS['link'], $query);
	$iResults = mysqli_num_rows ($result);
	$iRowCount = 0;
	while ($row = mysqli_fetch_assoc ($result))
	{
		$iRowCount++;

		$sId = $row['newrelease_id'];
		$sType = $row['newrelease_type'];
		$sName = $row['newrelease_name'];
		if (($sType == 'dlc') && ($row['newrelease_fullgame'] != ''))
		{
			$sType .= ' of ' . $row['newrelease_fullgame'];
		}

		/* Unfortunately, Steam also uses type:game for movies, and we
		 * do not want to mislead people. If Valve ever fixes it, the
		 * following code can be removed.
		 */
		if ($sType == 'game')
			{ $sType = ' '; }
				else { $sType = ' (' . $sType . ') '; }

		Say ($GLOBALS['channel'], ColorThis ('new') . $sType .
			$sName . ' http://store.steampowered.com/app/' .
			$sId . '/' . CPrices ($sId));

		/*** Said. ***/
		$query_update = "UPDATE `newreleases` SET newrelease_said='1' WHERE" .
			" (newrelease_id='" . $sId . "');";
		$result_update = mysqli_query ($GLOBALS['link'], $query_update);

		/*** Prevent the bot from flooding; wait 1 second. ***/
		if ($iRowCount < $iResults) { sleep (1); }
	}
}
/***********************************************/
function UTCDiff ($sThere)
/***********************************************/
{
	$dtThere = new DateTime ($sThere);
	$dtUTC = new DateTime (DateTime());
	$arDiff = date_diff ($dtUTC, $dtThere, FALSE);
	if (($arDiff->h != 0) || ($arDiff->i != 0))
		{ $sDiff = '; UTC'; } else { $sDiff = ''; }
	if ($arDiff->h != 0) { $sDiff .= $arDiff->format('%R%h'); }
	if ($arDiff->i != 0) { $sDiff .= '.' . $arDiff->i; }
	return ($sDiff);
}
/***********************************************/
function ReturnTime ($sInTime)
/***********************************************/
{
	if (strlen ($sInTime) == 2)
	{
		$arNames = timezone_identifiers_list (DateTimeZone::PER_COUNTRY, $sInTime);
		switch (count ($arNames))
		{
			case 0:
				$arTimeZone = array (FALSE, 'Unknown two-letter country code.' .
					' See: http://en.wikipedia.org/wiki/ISO_3166-1_alpha-2');
				break;
			case 1:
				$arTimeZone = array (TRUE, $arNames[0]);
				break;
			default:
				$arTimeZone = array (FALSE, 'This country has several timezones ("' .
					$arNames[0] . '", "' . $arNames[1] . '", ...); please provide a' .
					' timezone name. See: http://www.php.net/manual/en/timezones.php');
				break;
		}
	} else {
		$arTimeZone = array (TRUE, $sInTime);
	}
	if ($arTimeZone[0] != FALSE)
	{
		/*** timezone_identifiers_list() is incomplete; do NOT use it. ***/
		try {
			$d = new DateTime('now', new DateTimeZone($arTimeZone[1]));
		} catch (Exception $e) {
			return ('Unknown timezone.');
		}
		return ($d->format('H:i:s') . ' (on ' . $d->format('Y-m-d') .
			')' . UTCDiff ($d->format('Y-m-d H:i:s')));
	} else {
		return ($arTimeZone[1]);
	}
}
/***********************************************/
function UpcomingEvents ()
/***********************************************/
{
	$iNothing = 1;

	/*** Current time. ***/
	$sReturn = '(' . date ('H:i', time()) . ' UTC) ';

	/*** Right now. ***/
	$query = "SELECT DISTINCT event_title, event_link, event_date FROM" .
		" `events` WHERE (event_date > DATE_SUB(UTC_TIMESTAMP()," .
		" INTERVAL 2 HOUR)) AND (event_date < UTC_TIMESTAMP());";
	$result = mysqli_query ($GLOBALS['link'], $query);
	if (mysqli_num_rows ($result) == 1)
	{
		$row = mysqli_fetch_assoc ($result);
		$sTitle = str_replace ('SteamLUG ', '', $row['event_title']);
		$sReturn .= 'Right now: "' . $sTitle . '". ';
		$iNothing = 0;
	}

	/*** Future. ***/
	$query = "SELECT DISTINCT event_title, event_link, event_date FROM" .
		" `events` WHERE (event_date > UTC_TIMESTAMP()) ORDER BY event_date;";
	$result = mysqli_query ($GLOBALS['link'], $query);
	$iResults = mysqli_num_rows ($result);
	if ($iResults > 0)
	{
		$iNothing = 0;
		$iEvents = 0;
		while ($row = mysqli_fetch_assoc ($result))
		{
			$iEvents++;
			$sTitle = str_replace ('SteamLUG ', '', $row['event_title']);
			$sLink = $row['event_link'];
			$sDate = $row['event_date'];
			$sDay = ltrim (substr ($sDate, 8, 2), '0') . ' ' .
				date ('M', strtotime ($sDate));
			if ($sDay == date ('j M', time()))
				{ $sDay = ', today'; } else { $sDay = ' on ' . $sDay; }
			$sWhen = $sDay . ' ' . substr ($sDate, 11, -3) . ' UTC';
			if ($iEvents == 1)
			{
				$sReturn .= 'Next up is "' . $sTitle . '"' .
					$sWhen . ' (' . $sLink . ').';
			} else if ($iEvents <= 3) {
				$sReturn .= ' Then "' . $sTitle . '"' . $sWhen . '.';
			} else {
				$sReturn .= ' etc.';
				break;
			}
		}
	}

	/*** Nothing? ***/
	if ($iNothing == 1) { $sReturn .= 'No upcoming events.'; }

	return ($sReturn);
}
/***********************************************/
function Wikipedia ($sSearch)
/***********************************************/
{
	$enWikiS = 'http://en.wikipedia.org/w/api.php?action=opensearch&search=';
	$enWikiE = '&format=xml&limit=10';
	$sPage = GetPage ($enWikiS . rawurlencode ($sSearch) . $enWikiE, 0);
	$xmlpage = simplexml_load_string ($sPage);
	$iResults = count ($xmlpage->Section->Item);
	if ($iResults != 0)
	{
		$arWiki = array();
		$sRest = '';
		for ($iCount = 0; $iCount < $iResults; $iCount++)
		{
			if ($iCount == 0)
			{
				$arWiki['desc'] = $xmlpage->Section->Item[0]->Description;
			} else {
				if ($iCount > 1) { $sRest = $sRest . ', '; }
				$sRest = $sRest . '"' . $xmlpage->Section->Item[$iCount]->Text . '"';
			}
		}
		$arWiki['rest'] = $sRest;
	} else { $arWiki = FALSE; }
	return ($arWiki);
}
/***********************************************/
function GetSteamInfoXML ($sCustomURL)
/***********************************************/
{
	/*** Deprecated. ***/

	ini_set ('user_agent', $GLOBALS['useragent']);
	$xmlXML = simplexml_load_file ('http://steamcommunity.com/id/' .
		$sCustomURL . '/?xml=1', NULL, LIBXML_NOCDATA);
	if ($xmlXML === FALSE)
	{
		print ('[ WARN ] Could not retrieve XML data!' . "\n");
	}
	$arXML = json_decode (json_encode ((array)$xmlXML), TRUE);

	return ($arXML);
}
/***********************************************/
function GetSteamInfoAPI ($sCustomURL)
/***********************************************/
{
	$sURL = $GLOBALS['steam_api_base'] . 'ResolveVanityURL/v0001/' .
		'?key=' . $GLOBALS['steam_api_key'] . '&vanityurl=' . $sCustomURL;
	$jsn = GetPage ($sURL, 0);
	$arResult = json_decode ($jsn, TRUE);
	if ($arResult['response']['success'] == 1)
	{
		$sSteamId = $arResult['response']['steamid'];
		$sURL = $GLOBALS['steam_api_base'] . 'GetPlayerSummaries/v0002/' .
			'?key=' . $GLOBALS['steam_api_key'] . '&steamids=' . $sSteamId;
		$jsn = GetPage ($sURL, 0);
		$arResult = json_decode ($jsn, TRUE);

		return ($arResult);
	} else {
		return (FALSE);
	}
}
/***********************************************/
function CheckNewHumbleTitles ()
/***********************************************/
{
	$query = "SELECT * FROM `humbletitles` WHERE (humbletitles_said='0');";
	$result = mysqli_query ($GLOBALS['link'], $query);
	while ($row = mysqli_fetch_assoc ($result))
	{
		$iId = $row['humbletitles_id'];
		switch ($row['humbletitles_weekly'])
		{
			case 0: $sPage = 'main';
				$sURL = 'https://www.humblebundle.com/'; break;
			case 1: $sPage = 'weekly';
				$sURL = 'https://www.humblebundle.com/weekly'; break;
		}
		$sTitle = $row['humbletitles_title'];
		$arSearch = array ('Humble Bundle: ', 'Humble Weekly Bundle: ',
			' (pay what you want and help charity)');
		$arReplace = array ('', '', '');
		$sTitle = str_replace ($arSearch, $arReplace, $sTitle);
		Say ($GLOBALS['channel'], ColorThis ('humble') .
			' (<title> change for ' . $sPage . ') ' . $sTitle . ' ' . $sURL);

		/*** Said. ***/
		$query_update = "UPDATE `humbletitles` SET humbletitles_said='1' WHERE" .
			" (humbletitles_id='" . $iId . "');";
		$result_update = mysqli_query ($GLOBALS['link'], $query_update);
	}
}
/***********************************************/
function SteamStat ($sRecipient)
/***********************************************/
{
	$jsn = GetPage ($GLOBALS['steamstatus'], 1);
	$arStat = json_decode ($jsn, TRUE);
	if ($arStat['success'] != '1')
	{
		Say ($sRecipient, ColorThis ('steamstatus') . ' Sorry, currently' .
			' unavailable.');
	} else {
		$sStatTime = gmdate ('H:i:s', $arStat['time']) . ' UTC';
		$sPeopleOnline = $arStat['services']['online']['title'];
		if (($sPeopleOnline == '0') || (strtolower ($sPeopleOnline) == 'unknown'))
			{ $sPeopleOnline = 'unknown'; }
		$sInfo = 'Store status: ' .
			$arStat['services']['store']['status'] . ' | ' .
			'Community status: ' .
			$arStat['services']['community']['status'] . ' | ' .
			'People online: ' .
			$sPeopleOnline . ' | ' .
			'SteamDB database/client: ' .
			$arStat['services']['database']['status'] . '/' .
			$arStat['services']['steam']['status'];
		Say ($sRecipient, ColorThis ('steamstatus') .
			' (' . $sStatTime . ') ' . $sInfo . ' (source: steamstat.us)');
/***
		$sInfo = 'Australia: ' .
			$arStat['services']['cm-AU']['status'] . ' (' .
			$arStat['services']['cm-AU']['title'] . ') | ' .
			'Singapore: ' .
			$arStat['services']['cm-SG']['status'] . ' (' .
			$arStat['services']['cm-SG']['title'] . ') | ' .
			'Europe: ' .
			$arStat['services']['cm-EU']['status'] . ' (' .
			$arStat['services']['cm-EU']['title'] . ') | ' .
			'United States: ' .
			$arStat['services']['cm-US']['status'] . ' (' .
			$arStat['services']['cm-US']['title'] . ') | ' .
			'China: ' .
			$arStat['services']['cm-CN']['status'] . ' (' .
			$arStat['services']['cm-CN']['title'] . ') | ' .
			'Netherlands: ' .
			$arStat['services']['cm-NL']['status'] . ' (' .
			$arStat['services']['cm-NL']['title'] . ')';
***/
		$sInfo = 'CONNECTION MANAGER: ' .
			strtoupper ($arStat['services']['cms']['status']) . ' (' .
			strtoupper ($arStat['services']['cms']['title']) . ') | ' .
			'CS:GO: ' .
			$arStat['services']['csgo']['status'] . ' | ' .
			'Dota 2: ' .
			$arStat['services']['dota2']['status'] . ' | ' .
			'TF2: ' .
			$arStat['services']['tf2']['status'] . ' | ' .
			'Web API: ' .
			$arStat['services']['webapi']['status'];
		Say ($sRecipient, ColorThis ('steamstatus') .
			' (' . $sStatTime . ') ' . $sInfo . ' (source: steamstat.us)');
	}
}
/***********************************************/
function PCGWGameName ($sSearch)
/***********************************************/
{
	$sURL = 'http://pcgamingwiki.com/w/api.php?action=query' .
		'&list=search&srsearch="' . rawurlencode ($sSearch) .
		'"&srlimit=1&format=json';
	$jsn = GetPage ($sURL, 0);
	$arResult = json_decode ($jsn, TRUE);
	if (isset ($arResult['query']['search'][0]['title']))
	{
		$sGameName = $arResult['query']['search'][0]['title'];
		return ($sGameName);
	} else {
		return (FALSE);
	}
}
/***********************************************/
function PCGWUrl ($sSearch)
/***********************************************/
{
	$sGameName = PCGWGameName ($sSearch);
	if ($sGameName != FALSE)
	{
		return ('http://pcgamingwiki.com/wiki/' .
			rawurlencode (str_replace (' ', '_', $sGameName)));
	} else {
		return ('Not found.');
	}
}
/***********************************************/
function PCGWInfo ($sSearch)
/***********************************************/
{
	$sGameName = PCGWGameName ($sSearch);
	$arReturn = array();
	if ($sGameName != FALSE)
	{
		$sURL = 'http://pcgamingwiki.com/w/api.php?action=askargs&conditions=' .
			rawurlencode ($sGameName) . '&printouts=' .
			rawurlencode ('Steam AppID') . '|' .
			rawurlencode ('GOG.com page') . '|' .
			rawurlencode ('Wikipedia') . '|' .
			rawurlencode ('Available on') . '|' .
			rawurlencode ('Release date') . '|' .
			rawurlencode ('Release date Linux') . '|' .
			rawurlencode ('Developed by') . '|' .
			rawurlencode ('Ported to Linux by') . '|' .
			rawurlencode ('Ported to OS X by') . '|' .
			rawurlencode ('Linux XDG support') .
			'&format=json';
		$jsn = GetPage ($sURL, 0);
		$arResult = json_decode ($jsn, TRUE);
		if (isset ($arResult['error']['info']))
		{
			$arReturn[0] = -2;
			$arReturn[1] = $arResult['error']['info'];
		}
		$sArrayName = current (array_keys ($arResult['query']['results']));

		$arReturn[0] = '';
		$arReturn[1] = '';

		/*** Steam ***/
		if (isset ($arResult['query']['results'][$sArrayName]['printouts']
			['Steam AppID'][0]))
		{
			$sAdd = $arResult['query']['results'][$sArrayName]['printouts']
				['Steam AppID'][0];
			$arReturn[0] .= 'http://store.steampowered.com/app/' .
				$sAdd . '/' . CPrices ($sAdd);
		} else { $arReturn[0] .= '(no Steam)'; }

		/*** PCGW ***/
		$arReturn[0] .= ' | ';
		if (isset ($arResult['query']['results'][$sArrayName]['fullurl']))
		{
			$sAdd = $arResult['query']['results'][$sArrayName]['fullurl'];
			$arReturn[0] .= $sAdd;
		} else { $arReturn[0] .= '(no PCGW)'; }

		/*** GOG ***/
		$arReturn[0] .= ' | ';
		if (isset ($arResult['query']['results'][$sArrayName]['printouts']
			['GOG.com page'][0]))
		{
			$sAdd = $arResult['query']['results'][$sArrayName]['printouts']
				['GOG.com page'][0];
			$arReturn[0] .= 'http://www.gog.com/game/' . $sAdd;
		} else { $arReturn[0] .= '(no GOG)'; }

		/*** Wikipedia ***/
		$arReturn[0] .= ' | ';
		if (isset ($arResult['query']['results'][$sArrayName]['printouts']
			['Wikipedia'][0]))
		{
			$sAdd = $arResult['query']['results'][$sArrayName]['printouts']
				['Wikipedia'][0];
			/*** Only here: ***/
			$sAdd = rawurlencode (str_replace (' ', '_', $sAdd));
			$arReturn[0] .= 'http://en.wikipedia.org/wiki/' . $sAdd;
		} else { $arReturn[0] .= '(no Wiki)'; }

		$arReturn[0] .= ' (source: PCGW)';

		/*** platforms ***/
		if (isset ($arResult['query']['results'][$sArrayName]['printouts']
			['Available on'][0]))
		{
			$sPlatforms = '';
			foreach ($arResult['query']['results'][$sArrayName]['printouts']
				['Available on'] as $key=>$value)
			{
				if ($sPlatforms != '') { $sPlatforms .= ', '; }
				$sPlatforms .= $value;
			}
			$arReturn[1] .= 'Platforms: ' . $sPlatforms;
		} else { $arReturn[1] .= '(no platforms)'; }

		/*** release date (general) ***/
		$arReturn[1] .= ' | ';
		$iGDate = FALSE;
		if (isset ($arResult['query']['results'][$sArrayName]['printouts']
			['Release date'][0]))
		{
			$iGDate = $arResult['query']['results'][$sArrayName]['printouts']
				['Release date'][0];
			$sGDate = date ('F j, Y', $iGDate);
			$arReturn[1] .= 'Release date: ' . $sGDate;
		} else { $arReturn[1] .= '(unknown release date)'; }

		/*** release date (Linux) ***/
		if (isset ($arResult['query']['results'][$sArrayName]['printouts']
			['Release date Linux'][0]))
		{
			$arReturn[1] .= ' | '; /*** Correct here. ***/
			$iLDate = $arResult['query']['results'][$sArrayName]['printouts']
				['Release date Linux'][0];
			if ($iGDate != $iLDate)
			{
				$sLDate = date ('F j, Y', $iLDate);
				$arReturn[1] .= 'Linux release date: ' . $sLDate;
			} else { $arReturn[1] .= '(day one Linux release)'; }
		} /*** No else. ***/

		/*** developer ***/
		$arReturn[1] .= ' | ';
		if (isset ($arResult['query']['results'][$sArrayName]['printouts']
			['Developed by'][0]['fulltext']))
		{
			$sAdd = $arResult['query']['results'][$sArrayName]['printouts']
				['Developed by'][0]['fulltext'];
			$sAdd = str_replace ('Developer:', '', $sAdd);
			$arReturn[1] .= 'Developer: ' . $sAdd;
		} else { $arReturn[1] .= '(unknown developer)'; }

		/*** Linux porter ***/
		if (isset ($arResult['query']['results'][$sArrayName]['printouts']
			['Ported to Linux by'][0]['fulltext']))
		{
			$arReturn[1] .= ' | '; /*** Correct here. ***/
			$sAdd = $arResult['query']['results'][$sArrayName]['printouts']
				['Ported to Linux by'][0]['fulltext'];
			$sAdd = str_replace ('Developer:', '', $sAdd);
			$arReturn[1] .= 'Linux porter: ' . $sAdd;
		} /*** No else. ***/

		/*** OS X porter ***/
		if (isset ($arResult['query']['results'][$sArrayName]['printouts']
			['Ported to OS X by'][0]['fulltext']))
		{
			$arReturn[1] .= ' | '; /*** Correct here. ***/
			$sAdd = $arResult['query']['results'][$sArrayName]['printouts']
				['Ported to OS X by'][0]['fulltext'];
			$sAdd = str_replace ('Developer:', '', $sAdd);
			$arReturn[1] .= 'OS X porter: ' . $sAdd;
		} /*** No else. ***/

		/*** XDG ***/
		if (isset ($arResult['query']['results'][$sArrayName]['printouts']
			['Linux XDG support'][0]))
		{
			$arReturn[1] .= ' | '; /*** Correct here. ***/
			$sAdd = $arResult['query']['results'][$sArrayName]['printouts']
				['Linux XDG support'][0];
			$arReturn[1] .= 'XDG: ' . $sAdd;
		} /*** No else. ***/

		$arReturn[1] .= ' (source: PCGW)';

		return ($arReturn);
	} else {
		$arReturn[0] = -1;
		return ($arReturn);
	}
}
/***********************************************/
function SimilarNick ($sTargetUser)
/***********************************************/
{
	/*** Store all nicks. ***/
	$arNicks = array();
	$arNicksUpper = array();
	$query = "SELECT DISTINCT(log_nick) FROM `log` WHERE (log_identified='1')" .
		" AND (log_channel LIKE '#%') AND (log_channel NOT LIKE '#botwar%')" .
		" ORDER BY log_nick;";
	$result = mysqli_query ($GLOBALS['link'], $query);
	$iNicks = mysqli_num_rows ($result);
	while ($row = mysqli_fetch_assoc ($result))
	{
		$log_nick = $row['log_nick'];
		array_push ($arNicks, $log_nick);
		array_push ($arNicksUpper, strtoupper ($log_nick));
	}

	/*** Store all scores. ***/
	$arSimilar = array();
	$arLevenshtein = array();
	foreach ($arNicks as $key=>$value)
	{
		$iSimilar = similar_text ($sTargetUser, $value, $percent);
		array_push ($arSimilar, $percent); /*** more is better ***/
		$iLevenshtein = levenshtein ($sTargetUser, $value);
		array_push ($arLevenshtein, $iLevenshtein); /*** less is better ***/
	}
	$arSimilarUpper = array();
	$arLevenshteinUpper = array();
	foreach ($arNicksUpper as $key=>$value)
	{
		$iSimilarUpper = similar_text (strtoupper ($sTargetUser),
			$value, $percent);
		array_push ($arSimilarUpper, $percent); /*** more is better ***/
		$iLevenshteinUpper = levenshtein (strtoupper ($sTargetUser), $value);
		array_push ($arLevenshteinUpper,
			$iLevenshteinUpper); /*** less is better ***/
	}

	/*** Four best scores. ***/
	$iBestSimilar = 0; $sBestSimilar = '';
	$iBestSimilarUpper = 0; $sBestSimilarUpper = '';
	$iBestLevenshtein = 100; $sBestLevenshtein = '';
	$iBestLevenshteinUpper = 100; $sBestLevenshteinUpper = '';
	for ($iTemp = 0; $iTemp < $iNicks; $iTemp++)
	{
		if ($arSimilar[$iTemp] > $iBestSimilar)
		{
			$iBestSimilar = $arSimilar[$iTemp];
			$sBestSimilar = $arNicks[$iTemp];
		}
		if ($arSimilarUpper[$iTemp] > $iBestSimilarUpper)
		{
			$iBestSimilarUpper = $arSimilarUpper[$iTemp];
			/*** No Upper for $arNicks! ***/
			$sBestSimilarUpper = $arNicks[$iTemp];
		}
		if ($arLevenshtein[$iTemp] < $iBestLevenshtein)
		{
			$iBestLevenshtein = $arLevenshtein[$iTemp];
			$sBestLevenshtein = $arNicks[$iTemp];
		}
		if ($arLevenshteinUpper[$iTemp] < $iBestLevenshteinUpper)
		{
			$iBestLevenshteinUpper = $arLevenshteinUpper[$iTemp];
			/*** No Upper for $arNicks! ***/
			$sBestLevenshteinUpper = $arNicks[$iTemp];
		}
	}

	/*** Return. ***/
	if (($sBestSimilarUpper == '') && ($sBestLevenshteinUpper == '') &&
		($sBestSimilar == '') && ($sBestLevenshtein == ''))
	{
		return (FALSE);
	} else {
		$arReturn['1'] = $sBestSimilarUpper;
		$arReturn['2'] = $sBestLevenshteinUpper;
		$arReturn['3'] = $sBestSimilar;
		$arReturn['4'] = $sBestLevenshtein;
		return ($arReturn);
	}
}
/***********************************************/
function MsgSave ($sNickFrom, $sNickTo, $sMsgText)
/***********************************************/
{
	$sMsgText = mysqli_real_escape_string ($GLOBALS['link'], $sMsgText);
	$sDateTime = DateTime();
	$query = "INSERT INTO `messages` VALUES (NULL, '" .
		$sNickFrom . "', '" .
		$sNickTo . "', '" .
		$sMsgText . "', '" .
		$sDateTime . "', '" .
		'0' . "');";
	$result = mysqli_query ($GLOBALS['link'], $query);
	if ($result == FALSE)
	{
		print ('[ WARN ] This query failed: ' . $query . "\n");
		return (0);
	} else {
		$iLastMsg = MaxId ('message_id', 'messages');
		return ($iLastMsg);
	}
}
/***********************************************/
function MsgList ($sNick)
/***********************************************/
{
	$query = "SELECT message_id, message_nickto, message_date FROM `messages`" .
		" WHERE (message_nickfrom='" . $sNick . "') AND (message_delivered=0);";
	$result = mysqli_query ($GLOBALS['link'], $query);
	$iMsgs = mysqli_num_rows ($result);
	if ($iMsgs == 0)
	{
		$sReturn = 'All your messages have been delivered.';
	} else {
		if ($iMsgs == 1)
		{
			$sReturn = 'One undelivered message:';
		} else {
			$sReturn = 'Undelivered messages:';
		}
		while ($row = mysqli_fetch_assoc ($result))
		{
			$message_id = $row['message_id'];
			$message_nickto = $row['message_nickto'];
			$message_date = $row['message_date'];
			$sReturn .= ' ' . $message_id . ' (to "' . $message_nickto .
				'" on ' . $message_date . ')';
		}
	}
	return ($sReturn);
}
/***********************************************/
function MsgDel ($iMsgNr, $sNick)
/***********************************************/
{
	$query = "SELECT message_nickfrom FROM `messages` WHERE (message_id='" .
		$iMsgNr . "') AND (message_delivered=0);";
	$result = mysqli_query ($GLOBALS['link'], $query);
	$iMsgs = mysqli_num_rows ($result);
	if ($iMsgs == 0)
	{
		return (-1); /*** Message not found (or already delivered). ***/
	} else {
		$row = mysqli_fetch_assoc ($result);
		$message_nickfrom = $row['message_nickfrom'];
		if ($message_nickfrom != $sNick)
		{
			return (-2); /*** No permission. ***/
		} else {
			$query = "DELETE FROM `messages` WHERE (message_id='" . $iMsgNr . "');";
			$result = mysqli_query ($GLOBALS['link'], $query);
			if (mysqli_affected_rows ($GLOBALS['link']) == 1)
			{
				return (0); /*** Deleted. ***/
			} else {
				return (-3); /*** Failed. ***/
			}
		}
	}
}
/***********************************************/
function MsgShow ($iMsgNr)
/***********************************************/
{
	$query = "SELECT message_nickfrom, message_nickto, message_text," .
		" message_date FROM `messages` WHERE (message_id='" . $iMsgNr .
		"') AND (message_delivered=0);";
	$result = mysqli_query ($GLOBALS['link'], $query);
	$iMsgs = mysqli_num_rows ($result);
	if ($iMsgs == 0)
	{
		return (FALSE);
	} else {
		$row = mysqli_fetch_assoc ($result);
		$message_nickfrom = $row['message_nickfrom'];
		$message_nickto = $row['message_nickto'];
		$message_text = $row['message_text'];
		$message_date = $row['message_date'];
		$sReturn = 'From "' . $message_nickfrom . '" to "' . $message_nickto .
			'": ' . $message_text . ' (' . $message_date . ')';
		return ($sReturn);
	}
}
/***********************************************/
function MsgRelay ($sChannel, $sNick)
/***********************************************/
{
	$sNickE = mysqli_real_escape_string ($GLOBALS['link'], $sNick);

	$query = "SELECT message_nickfrom, message_text, message_date FROM" .
		" messages WHERE (message_nickto='" . $sNick . "') AND" .
		" (message_delivered=0);";
	$result = mysqli_query ($GLOBALS['link'], $query);
	$iMsgs = mysqli_num_rows ($result);
	if ($iMsgs != 0)
	{
		$iRowCount = 0;
		while ($row = mysqli_fetch_assoc ($result))
		{
			$iRowCount++;

			$message_nickfrom = $row['message_nickfrom'];
			$message_text = $row['message_text'];
			$message_date = $row['message_date'];
			Say ($sChannel, ColorThis ('msg') . ' ' . $sNick . ': <' .
				$message_nickfrom . '> ' . $message_text .
				' (' . $message_date . ')');

			/*** Prevent the bot from flooding; wait 1 second. ***/
			if ($iRowCount < $iMsgs) { sleep (1); }
		}
		$query_update = "UPDATE `messages` SET message_delivered=1 WHERE" .
			" (message_nickto='" . $sNick . "');";
		$result_update = mysqli_query ($GLOBALS['link'], $query_update);
	}
}
/***********************************************/
function GetQuestionAnswer ($sQAWord)
/***********************************************/
{
	$GLOBALS['qa_question'] = '-';
	$GLOBALS['qa_answer'] = '-';

	/*
	 * Obtain the number of (matching) lines
	 * in the $GLOBALS['qa_file'] file.
	 */
	$iLineCount = 0;
	$handle = fopen ($GLOBALS['qa_file'], 'r');
	if ($handle == FALSE)
	{
		$GLOBALS['qa_question'] = FALSE;
		$GLOBALS['qa_answer'] = 'Could not open "' . $GLOBALS['qa_file'] . '"!';
	} else {
		while (!feof ($handle))
		{
			$sLine = fgets ($handle);
			if (!feof ($handle))
			{
				$arQA = explode ('|', $sLine);
				if (($sQAWord == '') || (($sQAWord != '') && (strpos (strtolower
					($arQA[0]), strtolower ($sQAWord)) !== FALSE)))
				{
					$iLineCount++;
				}
			}
		}
	}
	fclose ($handle);

	/*** No (matching) line found. ***/
	if ($GLOBALS['qa_question'] != FALSE)
	{
		if ($iLineCount == 0)
		{
			$GLOBALS['qa_question'] = FALSE;
			if ($sQAWord == '')
			{
				$GLOBALS['qa_answer'] = 'No questions found!';
			} else {
				$GLOBALS['qa_answer'] = 'No question contains that word.';
			}
		}
	}

	/*** Retrieve the line. ***/
	if ($GLOBALS['qa_question'] != FALSE)
	{
		$iQuestionNr = rand (1, $iLineCount);
		$iLineCount = 0;
		$handle = fopen ($GLOBALS['qa_file'], 'r');
		if ($handle == FALSE)
		{
			$GLOBALS['qa_question'] = FALSE;
			$GLOBALS['qa_answer'] = 'Could not open "' . $GLOBALS['qa_file'] . '"!';
		} else {
			while (!feof ($handle))
			{
				$sLine = fgets ($handle);
				if (!feof ($handle))
				{
					$arQA = explode ('|', $sLine);
					if (($sQAWord == '') || (($sQAWord != '') && (strpos (strtolower
						($arQA[0]), strtolower ($sQAWord)) !== FALSE)))
					{
						$iLineCount++;
						if ($iLineCount == $iQuestionNr)
						{
							$GLOBALS['qa_question'] = $arQA[0];
							$sAnswer = $arQA[1];
							$arSearch = array ('\r', '\n', chr(10), chr(13));
							$arReplace = array ('', '', '', '');
							$sAnswer = str_replace ($arSearch, $arReplace, $sAnswer);
							$GLOBALS['qa_answer'] = $sAnswer;
							break; /*** Because there's no point to continue. ***/
						}
					}
				}
			}
		}
		fclose ($handle);
	}

	/*** Fork to wait 10 seconds. ***/
	if ($GLOBALS['qa_question'] != FALSE)
	{
		$pid = pcntl_fork();
		if ($pid == -1)
		{
			$GLOBALS['qa_question'] = FALSE;
			$GLOBALS['qa_answer'] = 'Could not fork to wait!';
		} else if ($pid) { /*** parent ***/
			$GLOBALS['qa_wait'] = 1;
			ConnectToMySQL(); /*** When the child exits, it closes the link. ***/
		} else { /*** child ***/
			sleep (10);
			exit (0);
		}
	}
}
/***********************************************/
function XkcdLine ($iXkcd)
/***********************************************/
{
	$query = "SELECT xkcd_title FROM `xkcd` WHERE xkcd_nr='" . $iXkcd . "';";
	$result = mysqli_query ($GLOBALS['link'], $query);
	if (mysqli_num_rows ($result) == 0)
	{
		return (''); /*** No not return FALSE here. Just ''. ***/
	} else {
		$row = mysqli_fetch_assoc ($result);
		return ($row['xkcd_title'] . ' http://xkcd.com/' . $iXkcd . '/');
	}
}
/***********************************************/
function XkcdShow ($sRecipient, $iXkcd, $sXkcd)
/***********************************************/
{
	$iXkcdLastO = XkcdUpdate();

	$sResult = ''; $iTooMany = 0;
	if (strcmp ($sXkcd, "last") == 0)
	{
		$sResult = XkcdLine ($iXkcdLastO);
	} else if ($sXkcd != '') {
		$query = "SELECT xkcd_nr FROM `xkcd` WHERE (xkcd_title LIKE '%" .
			$sXkcd . "%') OR (xkcd_alt LIKE '%" . $sXkcd . "%');";
		$result = mysqli_query ($GLOBALS['link'], $query);
		if (mysqli_num_rows ($result) > 0)
		{
			while ($row = mysqli_fetch_assoc ($result))
			{
				$xkcd_nr = $row['xkcd_nr'];
				$sAdd = '';
				if ($sResult != '') { $sAdd = ' | '; }
				$sAdd .= XkcdLine ($xkcd_nr);
				if (strlen ($sResult . $sAdd) <= 400)
				{
					$sResult .= $sAdd;
				} else {
					$iTooMany = 1;
				}
			}
		} else { /*** Do nothing. ***/ }
	} else if ($iXkcd != 0) {
		$sResult = XkcdLine ($iXkcd);
	}

	if ($sResult != '')
	{
		Say ($sRecipient, ColorThis ('xkcd') . ' ' . $sResult);
		if ($iTooMany == 1)
		{
			Say ($sRecipient, '(There are more hits. Be more specific to narrow' .
				' your search results.)');
		}
	} else {
		Say ($sRecipient, ColorThis ('xkcd') . ' No results.');
	}
}
/***********************************************/
function XkcdUpdate ()
/***********************************************/
{
	/*** Get the last number: on-line. ***/
	$jsn = GetPage ('http://xkcd.com/info.0.json', 0);
	$arResult = json_decode ($jsn, TRUE);
	if (isset ($arResult['num']))
	{
		$iXkcdLastO = intval ($arResult['num']);
	} else { $iXkcdLastO = 0; }

	/*** Get the last number: database. ***/
	$query = "SELECT MAX(xkcd_nr) AS last FROM `xkcd`;";
	$result = mysqli_query ($GLOBALS['link'], $query);
	$row = mysqli_fetch_assoc ($result);
	$iXkcdLastD = intval ($row['last']);

	/*** Get more comics. ***/
	if ($iXkcdLastD < $iXkcdLastO)
	{
		/*** This takes a while. ***/
		if ($iXkcdLastD == 0)
		{
			print ('[ INFO ] First call of XkcdUpdate(). Retrieving ' .
				$iXkcdLastO . ' comics. This can take five minutes or more!' . "\n");
		}

		for ($iXkcdNr = $iXkcdLastD + 1; $iXkcdNr <= $iXkcdLastO; $iXkcdNr++)
		{
			if ($iXkcdNr != 404) /*** There is no comic number 404. ***/
			{
				$sURL = 'http://xkcd.com/' . $iXkcdNr . '/info.0.json';
				$jsn = GetPage ($sURL, 0);
				$arResult = json_decode ($jsn, TRUE);
				if ((isset ($arResult['num'])) &&
					(isset ($arResult['title'])) &&
					(isset ($arResult['alt'])))
				{
					$iXkcdNum = intval ($arResult['num']);
					$sXkcdT = $arResult['title'];
					$sXkcdT = mysqli_real_escape_string ($GLOBALS['link'], $sXkcdT);
					$sXkcdAlt = $arResult['alt'];
					$sXkcdAlt = mysqli_real_escape_string ($GLOBALS['link'], $sXkcdAlt);

					$query = "INSERT INTO `xkcd` VALUES (NULL, '" .
						$iXkcdNum . "', '" .
						$sXkcdT . "', '" .
						$sXkcdAlt . "');";
					$result = mysqli_query ($GLOBALS['link'], $query);
					if ($result == FALSE)
					{
						print ('[ WARN ] This query failed (' .
							mysqli_error ($GLOBALS['link']) . '): ' . $query . "\n");
					}
				} else {
					print ('[ WARN ] Strange xkcd result for: ' . $sURL . "\n");
				}
			}
		}
	}

	return ($iXkcdLastO);
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
$oldqtime = time();

/*
 * This needs to be here (too), because the first time it can take five
 * minutes or more, which would cause a PING timeout if connected.
 */
XkcdUpdate();

do {
	$iJoined = 0;
	$iNoCloak = 0;
	$GLOBALS['qa_wait'] = 0;
	Connect();
	Nick ($GLOBALS['botname']);
	User ($GLOBALS['botname']);
	$sString = '';
	$currenttime = $PingTimeout = time();

	while ((feof ($GLOBALS['socket']) == FALSE) && ($currenttime < $PingTimeout + 600))
	{
		pcntl_signal_dispatch(); /*** For ControlC(). ***/

		if ($GLOBALS['debug'] == 0)
		{
			$currenttime = time();
			/*** Every 15 seconds. ***/
			if ($currenttime > $oldtime + 15)
			{
				$oldtime = $currenttime;
				if ($iJoined == 2)
				{
					CheckTweets();
					/*** CheckNews(); ***/
					CheckEvents();
					CheckNewReleases();
					CheckNewHumbleTitles();
				}
			}
		}

		if ($GLOBALS['qa_wait'] == 1)
		{
			$currenttime = time();
			/*** Every second. ***/
			if ($currenttime > $oldqtime + 1)
			{
				$oldqtime = $currenttime;
				$iQAValue = pcntl_waitpid (0, $status, WNOHANG);
				if (($iQAValue != -1) && ($iQAValue != 0))
				{
					$GLOBALS['qa_wait'] = 0;
					Say ($GLOBALS['channel'], ColorThis ('answer') . ' ' .
						$GLOBALS['qa_answer']);
					if ($GLOBALS['qa_winnick'] != '')
					{
						Say ($GLOBALS['channel'], ColorThis ('winner') . ' ' .
							$GLOBALS['qa_winnick']);
						$GLOBALS['qa_winnick'] = '';
					}
				}
			}
		}

		$sGot = fgets ($GLOBALS['socket'], $GLOBALS['maxlinelength']);
		if ($sGot != FALSE) { $sString .= $sGot; }

		if (substr ($sString, -2) == "\r\n")
		{
			if (strstr ($sString, ' ') == FALSE)
			{
				print ('[ WARN ] Strange: ' . $sString . "\n");
			}
			$ex = explode (' ', $sString);
			$ex = preg_replace ('/\r|\n/', '', $ex);
			if (!isset ($ex[1]))
			{
				print ('[ WARN ] Strange: ' . $sString . "\n");
			} else if ($ex[1] == '437') { /*** Nick taken, reclaim it. ***/
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
				/*** CapReq(); ***/
				Write ('JOIN ' . $GLOBALS['channel']);
				$iJoined = 1;
			} else if ($ex[1] == '353') { /*** User listing. ***/
				$sChannel = $ex[4];
				if (!isset ($GLOBALS['folks'][$sChannel]))
					{ $GLOBALS['folks'][$sChannel] = array(); }
				$sUsers = substr (GetPart ($sString, 6), 1);
				$arUsers = explode (' ', $sUsers);
				foreach ($arUsers as $sNick) { AddUser ($sChannel, $sNick); }
			} else if ($ex[1] == '366') { /*** End of user listing. ***/
				$iJoined = 2;
				print('[ INFO ] Connection complete, begin normal operation.' . "\n");
			} else if ($ex[0] == 'PING') {
				$PingTimeout = $currenttime;
				Write ('PONG ' . substr ($sString, 5));
			} else if ($iJoined == 2) {
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
						$sSaid = substr (GetPart ($sString, 4), 1);
						if (strstr(GetFirst ($sString), '@user/'))
 						{
							$iIdentified = 1;
						} else {
							$iIdentified = 0;
						}
						LogLine ($sNick, $sIdent, $sHost,
							$ex[1], $sRecipient, '', $iIdentified, $sSaid);
						if ((!in_array ($sNick, $GLOBALS['ignore'])) &&
							($ex[1] != 'NOTICE'))
						{
							ExtractURLInfo ($sRecipient, $sSaid);
							ExtractThanks ($sRecipient, $sNick, $sSaid);
							if ($GLOBALS['qa_wait'] == 1)
							{
								ExtractAnswers ($sNick, $sSaid);
							}
							$exsay = explode (' ', $sSaid);
							switch ($exsay[0])
							{
								case '!t':
									if (isset ($exsay[1]))
									{
										if (is_numeric ($exsay[1]))
										{
											$sTweetId = $exsay[1];
											$arTweet = GetTweetsArray ('', 0, $sTweetId);
											if (isset ($arTweet['errors']))
											{
												Say ($sRecipient, '(failed; "' .
													$arTweet['errors'][0]['message'] . '")');
											} else {
												ShowTweet ($sRecipient, $arTweet);
											}
										} else {
											$sTwitterName = $exsay[1];
											if (isset ($exsay[2]))
											{
												$iLastAmount = intval ($exsay[2]);
												if ($iLastAmount > 3) { $iLastAmount = 3; }
												if ($iLastAmount < 1) { $iLastAmount = 1; }
											} else {
												$iLastAmount = 1;
											}
											$arTweets = GetTweetsArray
												($sTwitterName, $iLastAmount, '');
											if (isset ($arTweets['errors']))
											{
												Say ($sRecipient, '(failed; "' .
													$arTweets['errors'][0]['message'] . '")');
											} else if (empty ($arTweets)) {
												Say ($sRecipient, '(failed; no such user?)');
											} else {
												LastTweets ($sRecipient, $arTweets);
											}
										}
									} else {
										Say ($sRecipient, 'Usage: !t <Twitter name>' .
											' [1-3] | !t <Tweet id>');
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
															$sSay = 'has NOT been added/updated'; break;
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
										} else if ($exsay[1] == $GLOBALS['botname']) {
											Say ($sRecipient, $GLOBALS['botsteam']);
										} else {
											$sTargetUser = FixString ($exsay[1]);

											/*** Everybody's a comedian. ***/
											$arComedian = array ('nothing', 'something',
												'everything', 'anything', 'this', 'that',
												'you', 'it', 'god', 'jesus');
											if (in_array (strtolower ($sTargetUser), $arComedian))
											{
												Say ($sRecipient, 'No.');
												break;
											}

											if ($sTargetUser == 'me') { $sTargetUser = $sNick; }
											$arCustomURL = GetCustomURL ($sTargetUser);

											/*** Helping the user. ***/
											if ($arCustomURL['with'] != FALSE)
											{
												$sTargetUser = $sTargetUser . $arCustomURL['with'];
												Say ($sRecipient, 'I have added an "' .
													$arCustomURL['with'] . '" to your request.');
											}

											switch ($arCustomURL['url'])
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
																Say ($sRecipient, 'When I see "' .
																	$sTargetUser . '" I will ask about that.');
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
												case 'none':
													Say ($sRecipient, ColorThis ('steam') . ' Sorry, ' .
														$sTargetUser .
														' prefers not to share Steam information.');
													break;
												default:
													GiveSteamInfoAPI ($sRecipient, $sTargetUser,
														$arCustomURL['url']);
													break;
											}
										}
									} else {
										Say ($sRecipient, $GLOBALS['susage']);
									}
									break;
								case '!seen':
									if (isset ($exsay[1]))
									{
										$sTargetUser = FixString ($exsay[1]);
										if (($sTargetUser == $sNick) || ($sTargetUser == 'me'))
										{
											Say ($sRecipient, ColorThis ('seen') .
												' You\'re here, silly.');
										} else if ($sTargetUser == $GLOBALS['botname']) {
											Say ($sRecipient, ColorThis ('seen') .
												' Reporting for duty.');
										} else {
											$arLastSeen = LastSeen ($sTargetUser);
											if ($arLastSeen == FALSE)
											{
												$arSimilar = SimilarNick ($sTargetUser);
												if ($arSimilar == FALSE)
												{
													$sSuggestions = '';
												} else {
													$sSuggestions = ' (Did you mean ';
													$iHaveSug = 0;
													if ($arSimilar[1] != '')
													{
														$iHaveSug = 1;
														$sSuggestions .= '"' . $arSimilar[1] . '"';
													}
													if (($arSimilar[2] != '') && ($arSimilar[2] !=
														$arSimilar[1]))
													{
														if ($iHaveSug == 1) { $sSuggestions .= ' or '; }
														$iHaveSug = 1;
														$sSuggestions .= '"' . $arSimilar[2] . '"';
													}
													if (($arSimilar[3] != '') && ($arSimilar[3] !=
														$arSimilar[1]) && ($arSimilar[3] != $arSimilar[2]))
													{
														if ($iHaveSug == 1) { $sSuggestions .= ' or '; }
														$iHaveSug = 1;
														$sSuggestions .= '"' . $arSimilar[3] . '"';
													}
													if (($arSimilar[4] != '') && ($arSimilar[4] !=
														$arSimilar[1]) && ($arSimilar[4] != $arSimilar[2])
														&& ($arSimilar[4] != $arSimilar[3]))
													{
														if ($iHaveSug == 1) { $sSuggestions .= ' or '; }
														$iHaveSug = 1;
														$sSuggestions .= '"' . $arSimilar[4] . '"';
													}
													$sSuggestions .= '?)';
												}
												Say ($sRecipient, ColorThis ('seen') .
													' I have never seen (an identified) "' .
													$sTargetUser . '" talk.' . $sSuggestions);
											} else {
												Say ($sRecipient, ColorThis ('seen') .
													' I last saw ' . $sTargetUser . ' talk in ' .
													$arLastSeen['log_channel'] . ', ' .
													$arLastSeen['tas'] . ' ("' .
													$arLastSeen['log_text'] . '").');
											}
										}
									} else {
										Say ($sRecipient, 'Usage: !seen <IRC nick>');
									}
									break;
								case '!time':
									if (isset ($exsay[1]))
									{
										$sInTime = FixString ($exsay[1]);
										$sOutTime = ReturnTime ($sInTime);
										Say ($sRecipient, ColorThis ('time') . ' (' . $sInTime .
											') ' . $sOutTime);
									} else {
										Say ($sRecipient, 'Usage: !time <"UTC"|two-letter' .
											' country code|timezone name>');
									}
									break;
								case '!events':
									$sEvents = UpcomingEvents();
									Say ($sRecipient, ColorThis ('events') . ' ' . $sEvents);
									break;
								case '!help':
									Say ($sRecipient, 'See README.txt: ' .
										$GLOBALS['botsource']);
									break;
								case '!steamstatus':
								case '!issteamdown':
								case '!issteamup':
									if (($exsay[0] == '!issteamdown') ||
										($exsay[0] == '!issteamup'))
										{ Say ($sRecipient, 'Please use: !steamstatus'); }
									SteamStat ($sRecipient);
									break;
								case '!id':
									$sCustomName = 'CustomName';
									$sHighlight = '';
									if (isset ($exsay[1]))
									{
										$sCustomName = FixString ($exsay[1]);
										if (($sRecipient[0] == '#') &&
											(UserInChannel ($sRecipient, $sCustomName)))
										{ $sHighlight = $sCustomName . ': '; }
									}
									Say ($sRecipient, ColorThis ('id') .
										' ' . $sHighlight . 'To get your own custom Steam URL (' .
										' steamcommunity.com/id/' . $sCustomName . ' ), do the' .
										' following: visit your Steam profile, click the "Edit' .
										' Profile" button, and then enter "' . $sCustomName .
										'" on the "Custom URL:" line.');
									break;
								case '!pcgw':
									if ($sSaid != '!pcgw')
									{
										$sSearch = substr ($sSaid, 6);
										$sPCGWUrl = PCGWUrl ($sSearch);
										$sGameName = PCGWGameName ($sSearch);
										if ($sSearch != $sGameName)
										{
											if ($sGameName == FALSE) { $sGameName = '?'; }
											$sSearched = $sSearch . ' -> ' . $sGameName;
										} else {
											$sSearched = $sSearch;
										}
										Say ($sRecipient, ColorThis ('pcgw') . ' (' . $sSearched .
											') ' . $sPCGWUrl);
									} else {
										Say ($sRecipient, 'http://pcgamingwiki.com/ | Usage:' .
											' !pcgw [game name]');
									}
									break;
								case '!game':
									if ($sSaid != '!game')
									{
										$sSearch = substr ($sSaid, 6);
										$arPCGWInfo = PCGWInfo ($sSearch);
										$sGameName = PCGWGameName ($sSearch);
										if ($sSearch != $sGameName)
										{
											if ($sGameName == FALSE) { $sGameName = '?'; }
											$sSearched = $sSearch . ' -> ' . $sGameName;
										} else {
											$sSearched = $sSearch;
										}
										if ($arPCGWInfo[0] == -1)
										{
											Say ($sRecipient, ColorThis ('game') . ' (' .
												$sSearched . ') Not found.');
										} else if ($arPCGWInfo[0] == -2) {
											Say ($sRecipient, ColorThis ('game') . ' (' .
												$sSearched . ') ' . $arPCGWInfo[1]);
										} else {
											Say ($sRecipient, ColorThis ('game') . ' (' .
												$sSearched . ') ' . $arPCGWInfo[0]);
											Say ($sRecipient, ColorThis ('game') . ' ' .
												$arPCGWInfo[1]);
										}
									} else {
										Say ($sRecipient, 'Usage: !game <game name>');
									}
									break;
								case '!mumble':
									Say ($sRecipient, ColorThis ('mumble') . ' ' .
										$GLOBALS['mumble-page']);
									break;
								case '!msg':
									if ($iIdentified == 1)
									{
										if (isset ($exsay[1]))
										{
											if ($exsay[1] == 'tell')
											{
												if ((isset ($exsay[2])) && (isset ($exsay[3])))
												{
													$sNickFrom = $sNick;
													$sNickTo = FixString ($exsay[2]);
													$sMsgText = GetPart ($sSaid, 4);
													if (($sRecipient[0] == '#') &&
														(UserInChannel ($sRecipient, $sNickTo)))
													{
														Say ($sRecipient, 'User "' . $sNickTo .
															'" is in this IRC channel.');
													} else {
														$iMsg = MsgSave ($sNickFrom, $sNickTo, $sMsgText);
														switch ($iMsg)
														{
															case 0:
																Say ($sRecipient, ColorThis ('msg') . ' ' .
																	'Sorry, something failed.');
																break;
															default:
																Say ($sRecipient, ColorThis ('msg') . ' ' .
																	'I will relay your message (' . $iMsg .
																	') when I see "' . $sNickTo . '".');
																break;
														}
													}
												} else {
													Say ($sRecipient, $GLOBALS['msgusage']);
												}
											} else if ($exsay[1] == 'list') {
												$sMsgList = MsgList ($sNick);
												Say ($sRecipient, ColorThis ('msg') . ' ' .
													$sMsgList);
											} else if ($exsay[1] == 'show') {
												if (isset ($exsay[2]))
												{
													$iMsgNr = intval ($exsay[2]);
													$sMsgShow = MsgShow ($iMsgNr);
													if ($sMsgShow == FALSE)
													{
														Say ($sRecipient, ColorThis ('msg') . ' ' .
															'There is no message ' . $iMsgNr . '.');
													} else {
														Say ($sRecipient, ColorThis ('msg') . ' ' .
															$sMsgShow);
													}
												} else {
													Say ($sRecipient, $GLOBALS['msgusage']);
												}
											} else if ($exsay[1] == 'delete') {
												if (isset ($exsay[2]))
												{
													$iMsgNr = intval ($exsay[2]);
													$iMsgDel = MsgDel ($iMsgNr, $sNick);
													switch ($iMsgDel)
													{
														case 0:
															Say ($sRecipient, ColorThis ('msg') . ' ' .
																'Deleted message ' . $iMsgNr . '.');
															break;
														case -1:
															Say ($sRecipient, ColorThis ('msg') . ' ' .
																'There is no message ' . $iMsgNr . '.');
															break;
														case -2:
															Say ($sRecipient, ColorThis ('msg') . ' ' .
																'Message ' . $iMsgNr . ' was not created' .
																' by you.');
															break;
														case -3:
															Say ($sRecipient, ColorThis ('msg') . ' ' .
																'Sorry, something failed.');
															break;
													}
												} else {
													Say ($sRecipient, $GLOBALS['msgusage']);
												}
											} else {
												Say ($sRecipient, $GLOBALS['msgusage']);
											}
										} else {
											Say ($sRecipient, $GLOBALS['msgusage']);
										}
									} else {
										Say ($sRecipient, $sNick . ': you are not' .
											' identified to NickServ. Use "/msg NickServ' .
											' IDENTIFY [' . $sNick . '] <password>". |' .
											' Register your nickname with "/msg NickServ' .
											' REGISTER <password> <email>".');
									}
									break;
								case '!question':
									if ($sRecipient[0] != '#')
									{
										Say ($sRecipient,
											'Sorry, !question is for channels only.');
									} else if ($GLOBALS['qa_wait'] == 1) {
										Say ($sRecipient, ColorThis ('question') . ' ' .
											'Please wait for the answer.');
									} else {
										if (isset ($exsay[2]))
										{
											Say ($sRecipient, 'Usage: !question [word]');
										} else {
											$sQAWord = '';
											if (isset ($exsay[1]))
												{ $sQAWord = FixString ($exsay[1]); }
											GetQuestionAnswer ($sQAWord);
											if ($GLOBALS['qa_question'] == FALSE)
											{
												Say ($sRecipient, ColorThis ('question') . ' ' .
													$GLOBALS['qa_answer']);
											} else {
												Say ($sRecipient, ColorThis ('question') . ' ' .
													$GLOBALS['qa_question']);
												$GLOBALS['qa_winnick'] = '';
											}
										}
									}
									break;
								case '!xkcd':
									if (isset ($exsay[1]))
									{
										$iXkcd = 0; $sXkcd = '';
										if (is_numeric ($exsay[1]))
										{
											$iXkcd = intval ($exsay[1]);
										} else if (strcmp ($exsay[1], "last") == 0) {
											$sXkcd = "last";
										} else {
											$sXkcd = FixString ($exsay[1]);
										}
										if ($iXkcd == 404)
										{
											Say ($sRecipient, 'Randall Munroe deliberately did' .
												' not create comic number 404. (404 is the "Not' .
												' Found" HTTP response status code)');
										} else {
											XkcdShow ($sRecipient, $iXkcd, $sXkcd);
										}
									} else {
										Say ($sRecipient, 'Usage: !xkcd <number>' .
											' | !xkcd <word> | !xkcd last');
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
							MsgRelay ($sChannel, $sNick);
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
			$sString = '';
		}
		usleep (1000);
	}
	print('[ WARNING ] Connection Lost.' . "\n");
	fclose ($GLOBALS['socket']);
	$GLOBALS['socket'] = FALSE;
} while (1);
?>
