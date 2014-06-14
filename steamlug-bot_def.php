<?php
	error_reporting (E_ALL);
	ini_set ('display_errors', '1');
	date_default_timezone_set ('UTC');
	ini_set ('mbstring.internal_encoding', 'UTF-8');
	mb_internal_encoding ('UTF-8');

/***********************************************/
function ConnectToMySQL ()
/***********************************************/
{
	$GLOBALS['link'] = mysqli_init();
	if ($GLOBALS['link'] == FALSE)
	{
		print ('[FAILED] Could not use mysqli_init!' . "\n");
		exit();
	}
	if (!mysqli_real_connect ($GLOBALS['link'], $GLOBALS['db_host'],
		$GLOBALS['db_user'], $GLOBALS['db_pass'], $GLOBALS['db_dtbs']))
	{
		print ('[FAILED] Error with mysqli_real_connect: ' .
			mysqli_connect_error() . "\n");
		exit();
	}
}
/***********************************************/
function BaseString ($sUrl, $sMethod, $arOauth)
/***********************************************/
{
	$arEntry = array();
	ksort ($arOauth);
	foreach ($arOauth as $key=>$value)
	{
		$arEntry[] = $key . '=' . rawurlencode ($value);
	}
	$sResult = $sMethod . '&' . rawurlencode ($sUrl) .
		'&' . rawurlencode (implode ('&', $arEntry));

	return ($sResult);
}
/***********************************************/
function Authorization ($arOauth)
/***********************************************/
{
	$arEntry = array();
	foreach ($arOauth as $key=>$value)
	{
		$arEntry[] = $key . '="' . rawurlencode ($value) . '"';
	}
	$sResult = 'Authorization: OAuth ' . implode (', ', $arEntry);

	return ($sResult);
}
/***********************************************/
function GetTweetsArray ($sName, $iAmount)
/***********************************************/
{
	$sUrl = 'https://api.twitter.com/1.1/statuses/user_timeline.json';

	$arOauth = array (
		'screen_name' => $sName,
		'count' => $iAmount,
		'oauth_consumer_key' => $GLOBALS['twitter_api_key'],
		'oauth_nonce' => time(),
		'oauth_signature_method' => 'HMAC-SHA1',
		'oauth_token' => $GLOBALS['twitter_oauth_token'],
		'oauth_timestamp' => time(),
		'oauth_version' => '1.0');

	$sBase = BaseString ($sUrl, 'GET', $arOauth);
	$sKey = rawurlencode ($GLOBALS['twitter_api_secret']) .
		'&' . rawurlencode ($GLOBALS['twitter_oauth_secret']);
	$oauth_signature = base64_encode (hash_hmac ('sha1',
		$sBase, $sKey, TRUE));
	$arOauth['oauth_signature'] = $oauth_signature;

	$header = array (Authorization ($arOauth), 'Expect:');
	$options = array (
		CURLOPT_HTTPHEADER => $header,
		CURLOPT_HEADER => FALSE,
		CURLOPT_URL => $sUrl . '?screen_name=' .
			rawurlencode ($sName) . '&count=' . $iAmount,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_SSL_VERIFYPEER => FALSE);

	$feed = curl_init();
	curl_setopt_array ($feed, $options);
	$jsn = curl_exec ($feed);
	curl_close ($feed);

	return (json_decode ($jsn, TRUE));
}
/***********************************************/
function TweetsToMySQL ($arTweets)
/***********************************************/
{
	foreach ($arTweets as $key=>$value)
	{
		$tweet_id = $value['id_str'];
		$tweet_date = date ('Y-m-d H:i:s',
			strtotime ($value['created_at'] . 'UTC'));
		$tweet_text = $value['text'];
		$tweet_text = mysqli_real_escape_string ($GLOBALS['link'], $tweet_text);
		$tweet_user = $value['user']['screen_name'];

		$query = "INSERT IGNORE INTO `tweets` VALUES ('" .
			$tweet_id . "', '" .
			$tweet_date . "', '" .
			$tweet_text . "', '" .
			$tweet_user . "');";
		$result = mysqli_query ($GLOBALS['link'], $query);
	}
}
/***********************************************/
function GetPage ($sUrl)
/***********************************************/
{
	$ch = curl_init ($sUrl);
	curl_setopt ($ch, CURLOPT_HTTPGET, TRUE);
	curl_setopt ($ch, CURLOPT_POST, FALSE);
	curl_setopt ($ch, CURLOPT_HEADER, FALSE);
	curl_setopt ($ch, CURLOPT_NOBODY, FALSE);
	curl_setopt ($ch, CURLOPT_VERBOSE, FALSE);
	curl_setopt ($ch, CURLOPT_REFERER, '');
	curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt ($ch, CURLOPT_MAXREDIRS, 4);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt ($ch, CURLOPT_USERAGENT, $GLOBALS['useragent']);
	curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 10);
	$sPage = curl_exec ($ch);
	curl_close ($ch);

	return ($sPage);
}
/***********************************************/
function GetGroupNews ($sGroup)
/***********************************************/
{
	ini_set ('user_agent', $GLOBALS['useragent']);
	$xmlXML = simplexml_load_file ('http://steamcommunity.com/groups/' .
		$sGroup . '/rss/', NULL, LIBXML_NOCDATA);
	if ($xmlXML === FALSE)
	{
		print ('[ WARN ] Could not retrieve XML data!' . "\n");
	}
	$arXML = json_decode (json_encode ((array)$xmlXML), TRUE);

	return ($arXML['channel']['item']);
}
/***********************************************/
function NewsToMySQL ($arNews, $sGroup)
/***********************************************/
{
	foreach ($arNews as $key=>$value)
	{
		$sTitle = (string)$value['title'];
		$sTitle = mysqli_real_escape_string ($GLOBALS['link'], $sTitle);

		$sText = (string)$value['description'];
		$sText = html_entity_decode (strip_tags ($sText), ENT_QUOTES, 'utf-8');
		$sText = preg_replace ('/\r|\n/', ' ', $sText);
		$sText = preg_replace ('/\s+/', ' ', $sText);
		$sText = mysqli_real_escape_string ($GLOBALS['link'], $sText);

		$sLink = (string)$value['link'];
/***
		$sGuid = (string)$value['guid'];
		if ($sLink != $sGuid)
		{
			print ('[ WARN ] Apparently, link and guid are different!' . "\n");
			print ($sLink . "\n");
			print ($sGuid . "\n");
		}
***/

		$iDateTime = strtotime ((string)$value['pubDate']);
		$sDateTime = date ('Y-m-d H:i:s', $iDateTime);

		/*** Prevent duplicates. Here we do not use INSERT IGNORE ***/
		/*** because of the AUTO_INCREMENT column. ***/
		if (Found ('news', 'news_link', $sLink) == FALSE)
		{
			$query = "INSERT INTO `news` VALUES (NULL, '" .
				$sGroup . "', '" .
				$sTitle . "', '" .
				$sText . "', '" .
				$sLink . "', '" .
				$sDateTime . "');";
			$result = mysqli_query ($GLOBALS['link'], $query);
		}
	}
}
/***********************************************/
function GetEvents ()
/***********************************************/
{
	ini_set ('user_agent', $GLOBALS['useragent']);
	$xmlXML = simplexml_load_file ('https://steamlug.org/feed/events',
		NULL, LIBXML_NOCDATA);
	if ($xmlXML === FALSE)
	{
		print ('[ WARN ] Could not retrieve XML data!' . "\n");
	}
	$arXML = json_decode (json_encode ((array)$xmlXML), TRUE);

	return ($arXML['channel']['item']);
}
/***********************************************/
function EventsToMySQL ($arEvents)
/***********************************************/
{
	/*** If it's just one entry... ***/
	if (isset ($arEvents['title']))
	{
		$arSingle = $arEvents;
		$arEvents = array();
		array_push ($arEvents, $arSingle);
	}
	foreach ($arEvents as $key=>$value)
	{
		$sTitle = (string)$value['title'];
		$sTitle = mysqli_real_escape_string ($GLOBALS['link'], $sTitle);

		$sText = (string)$value['description'];
		$sText = html_entity_decode (strip_tags ($sText), ENT_QUOTES, 'utf-8');
		$sText = preg_replace ('/\r|\n/', ' ', $sText);
		$sText = preg_replace ('/\s+/', ' ', $sText);
		$sText = mysqli_real_escape_string ($GLOBALS['link'], $sText);

		$sLink = (string)$value['link'];
		$sGuid = (string)$value['guid'];
/***
		if ($sLink != $sGuid)
		{
			print ('[ WARN ] Apparently, link and guid are different!' . "\n");
			print ($sLink . "\n");
			print ($sGuid . "\n");
		}
***/

		$iDateTime = strtotime ((string)$value['pubDate']);
		$sDateTime = date ('Y-m-d H:i:s', $iDateTime);

		$sCategory = (string)$value['category'];

		/*** Extract event datetime. ***/
		$regex = '/[0-9]{4}-[0-9]{2}-[0-9]{2} at [0-9]{2}:[0-9]{2} UTC/';
		$arMatches = array();
		$sEDateTime = '1000-01-01 00:00:00'; /*** Fallback. ***/
		if (preg_match_all ($regex, $sText, $arMatches) == 1)
		{
			$arSearch = array (' at ', ' UTC');
			$arReplace = array (' ', ':00');
			$sEDateTime = str_replace ($arSearch, $arReplace, $arMatches[0][0]);
		} else {
			GetLog ('Could not extract 1 datetime from: ' . $sText);
		}

		/*** Prevent duplicates. Here we do not use INSERT IGNORE ***/
		/*** because of the AUTO_INCREMENT column. ***/
		if (Found ('events', 'event_guid', $sGuid) == FALSE)
		{
			$query = "INSERT INTO `events` VALUES (NULL, '" .
				$sCategory . "', '" .
				$sTitle . "', '" .
				$sText . "', '" .
				$sLink . "', '" .
				$sGuid . "', '" .
				$sDateTime . "', '" .
				$sEDateTime . "');";
			$result = mysqli_query ($GLOBALS['link'], $query);
		}
	}
}
/***********************************************/
function Found ($sTable, $sColumn, $sValue)
/***********************************************/
{
	$query_found = "SELECT COUNT(*) AS found FROM `" . $sTable . "` WHERE" .
		" (" . $sColumn . "='" . $sValue . "');";
	$result_found = mysqli_query ($GLOBALS['link'], $query_found);
	$row_found = mysqli_fetch_assoc ($result_found);
	if ($row_found['found'] == 1)
		{ return (TRUE); }
			else { return (FALSE); }
}
/***********************************************/
function EmptyTable ($sTable)
/***********************************************/
{
	$query = "SELECT COUNT(*) AS count FROM `" . $sTable . "`;";
	$result = mysqli_query ($GLOBALS['link'], $query);
	$row = mysqli_fetch_assoc ($result);

	switch ($row['count'])
	{
		case 0: return (TRUE); break;
		default: return (FALSE); break;
	}
}
/***********************************************/
function GetNewReleases ()
/***********************************************/
{
	$jsn = GetPage ('http://store.steampowered.com/api/getappsincategory/?category=cat_newreleases');

	return (json_decode ($jsn, TRUE));
}
/***********************************************/
function GetAppDetails ($sID)
/***********************************************/
{
	$jsn = GetPage ('http://store.steampowered.com/api/appdetails/?appids=' .
		$sID);

	return (json_decode ($jsn, TRUE));
}
/***********************************************/
function NewReleasesToMySQL ($arNewReleases)
/***********************************************/
{
	if (EmptyTable ('newreleases') == TRUE)
		{ $iSay = 1; } else { $iSay = 0; }

	/*** Not using tabs: topsellers, specials, under_ten ***/
	if (($arNewReleases['status'] == 1) &&
		(isset ($arNewReleases['tabs']['viewall']['items'])) &&
		(isset ($arNewReleases['tabs']['dlc']['items'])))
	{
		$arIDs = array();
		foreach ($arNewReleases['tabs']['viewall']['items'] as $key=>$value)
			{ array_push ($arIDs, $value['id']); }
		foreach ($arNewReleases['tabs']['dlc']['items'] as $key=>$value)
			{ array_push ($arIDs, $value['id']); }
		$arIDs = array_unique ($arIDs);

		/*** Fill temporary table. ***/
		$query = "TRUNCATE TABLE `newreleases_temp`;";
		$result = mysqli_query ($GLOBALS['link'], $query);
		if ($result == FALSE)
		{
			GetLog ('This query failed: ' . $query);
		}
		$query = "INSERT INTO `newreleases_temp` VALUES ";
		foreach ($arIDs as $key=>$value)
		{
			$query .= "('" . $value . "'), ";
		}
		$query = substr ($query, 0, -2) . ";";
		$result = mysqli_query ($GLOBALS['link'], $query);
		if ($result == FALSE)
		{
			GetLog ('This query failed: ' . $query);
		}

		$query = "SELECT `newreleases_temp`.newrelease_id AS id FROM" .
			" `newreleases_temp` LEFT JOIN `newreleases` ON" .
			" (`newreleases`.newrelease_id = `newreleases_temp`.newrelease_id)" .
			" WHERE (`newreleases`.newrelease_id IS NULL);";
		$result = mysqli_query ($GLOBALS['link'], $query);
		if ($result == FALSE)
		{
			GetLog ('This query failed: ' . $query);
		}
		while ($row = mysqli_fetch_assoc ($result))
		{
			$sID = $row['id'];

			$arDetails = GetAppDetails ($sID);
			if (($arDetails[$sID]['success'] == 1) &&
				(isset ($arDetails[$sID]['data']['type'])) &&
				(isset ($arDetails[$sID]['data']['name'])) &&
				(isset ($arDetails[$sID]['data']['platforms']['windows'])) &&
				(isset ($arDetails[$sID]['data']['platforms']['mac'])) &&
				(isset ($arDetails[$sID]['data']['platforms']['linux'])))
			{
				if (isset ($arDetails[$sID]['data']['fullgame']['name']))
				{
					$sFullGame = $arDetails[$sID]['data']['fullgame']['name'];
					$sFullGame = mysqli_real_escape_string ($GLOBALS['link'], $sFullGame);
				} else { $sFullGame = ''; }
				$sType = $arDetails[$sID]['data']['type'];
				$sName = $arDetails[$sID]['data']['name'];
				$sName = mysqli_real_escape_string ($GLOBALS['link'], $sName);
				if ($arDetails[$sID]['data']['platforms']['windows'] == '1')
					{ $iWindows = 1; } else { $iWindows = 0; }
				if ($arDetails[$sID]['data']['platforms']['mac'] == '1')
					{ $iMac = 1; } else { $iMac = 0; }
				if ($arDetails[$sID]['data']['platforms']['linux'] == '1')
					{ $iLinux = 1; } else { $iLinux = 0; }
				if ($iLinux == 0) { $iSaid = 1; } else { $iSaid = $iSay; }
				$query_insert = "INSERT INTO `newreleases` VALUES ('" .
					$sID . "', '" .
					$sType . "', '" .
					$sName . "', '" .
					$sFullGame . "', '" .
					$iWindows . "', '" .
					$iMac . "', '" .
					$iLinux . "', '" .
					$iSaid . "');";
				$result_insert = mysqli_query ($GLOBALS['link'], $query_insert);
				if ($result_insert == FALSE)
				{
					GetLog ('This query failed: ' . $query_insert);
				}
			} else {
				GetLog ('Unexpected Steam data (for ' . $sID . '): ' .
					implode ($arDetails, '|'));

				/*** We don't want to keep fetching data. ***/
				$query_insert = "INSERT INTO `newreleases` VALUES ('" .
					$sID . "', 'failed', 'failed', '', 0, 0, 0, 1);";
				$result_insert = mysqli_query ($GLOBALS['link'], $query_insert);
				if ($result_insert == FALSE)
				{
					GetLog ('This query failed: ' . $query_insert);
				}
			}
		}
	} else {
		GetLog ('Unexpected Steam data: ' . implode ($arNewReleases, '|'));
	}
}
/***********************************************/
function DateTime ()
/***********************************************/
{
	return (date ('Y-m-d H:i:s', time()));
}
/***********************************************/
function GetLog ($sText)
/***********************************************/
{
	$sText = mysqli_real_escape_string ($GLOBALS['link'], $sText);
	$sDateTime = DateTime();
	$query = "INSERT INTO `getlog` VALUES (NULL, '" .
		$sText . "', '" .
		$sDateTime . "');";
	$result = mysqli_query ($GLOBALS['link'], $query);
	if ($result == FALSE)
	{
		print ('[ WARN ] This query failed: ' . $query . "\n");
	}
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

	/*** For gzip compressed data. ***/
	if (($sData[0] == chr(0x1f)) && ($sData[1] == chr(0x8b)))
	{
		$sData = gzinflate (substr ($sData, 10, -8));
	}

	return ($sData);
}
/***********************************************/

	ConnectToMySQL();
?>
