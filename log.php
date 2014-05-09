<?php
/***********************************************/
function NickHover ($sNick, $sIdent, $sHost)
/***********************************************/
{
	if ($sNick != $GLOBALS['botname'])
	{
		$sNickHover = '<span title="' . $sNick . '!' . $sIdent .
			'@' . $sHost . '">' . $sNick . '</span>';
	} else {
		$sNickHover = $sNick;
	}
	return ($sNickHover);
}
/***********************************************/
function Lines ($result)
/***********************************************/
{
	while ($row = mysqli_fetch_assoc ($result))
	{
		$sCom = $row['log_command'];
		$sGrey = array ('QUIT', 'JOIN', 'NICK', 'PART', 'KICK', 'MODE', 'TOPIC');
		print ('<div style="background-color:#');
		if ($row['log_nick'] == $GLOBALS['botname'])
		{
			print ('fff');
		} else if (in_array ($sCom, $sGrey)) {
			print ('bbb');
		} else {
			print ('ddd');
		}
		print (';">');
		print ('<span class="log_column">' . $row['log_datetime'] . '</span>');
		print ('<span class="log_column" style="width:130px;' .
			' text-align:right;">' . NickHover ($row['log_nick'],
			$row['log_ident'], $row['log_host']) . '</span>');
		print ('<span class="log_column" style="width:80px;">' .
			$sCom . '</span>');
		print ('<span class="log_column" style="width:80px;">' .
			$row['log_channel'] . '</span>');
		$sText = htmlspecialchars ($row['log_text']);
		if ($row['log_person'] != '')
			{ $sText = $sText . ' ' . $row['log_person']; }
		$sText = str_replace (chr(1),
			'<span style="color:#fff;">[CTCP]</span>', $sText);
		if ($row['log_identified'] == 1)
			{ $sText = '<span style="color:#006400;">+</span>' . $sText; }
		print ('<span class="log_column" style="width:700px;">' .
			$sText . '</span>');
		print ('<span style="display:block; clear:both;"></span>');
		print ('</div>' . "\n");
	}
}
/***********************************************/
function GetLines ($result)
/***********************************************/
{
	$iNumRows = mysqli_num_rows ($result);
	$iRow = 0;
	while ($row = mysqli_fetch_assoc ($result))
	{
		$iRow++;
		$sWarn = '';
		$sDateTime = $row['getlog_datetime'];
		if ($iRow == $iNumRows)
		{
			$iThen = strtotime ($sDateTime);
			$iNow = strtotime (DateTime());
			$iDiffSec = $iNow - $iThen;
			if ($iDiffSec > 60) { $sWarn = ' log_warn'; }
		}
		$sText = htmlspecialchars ($row['getlog_text']);

		print ('<div style="background-color:#');
		if ($row['getlog_text'] != $GLOBALS['getlog_done'])
		{
			print ('fff');
		} else {
			print ('bbb');
		}
		print (';">');
		print ('<span class="log_column' . $sWarn . '">' .
			$sDateTime . '</span>');
		print ('<span class="log_column" style="width:700px;">' .
			$sText . '</span>');
		print ('<span style="display:block; clear:both;"></span>');
		print ('</div>' . "\n");
	}
}
/***********************************************/
function NotDone ()
/***********************************************/
{
	$query = "SELECT COUNT(*) AS count FROM `getlog` WHERE" .
		" (getlog_text != '" . $GLOBALS['getlog_done'] . "');";
	$result = mysqli_query ($GLOBALS['link'], $query);
	$row = mysqli_fetch_assoc ($result);

	return ($row['count']);
}
/***********************************************/

	require_once ('steamlug-bot_settings.php');
	require_once ('steamlug-bot_def.php');

print ('<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="Refresh" content="5">
<title>Log</title>
<style type="text/css">
	body { margin:10px; background-color:#ddd; font-size:14px; font-family:Arial,Helvetica,sans-serif; }
	.log_div { width:1200px; overflow-y:scroll; margin:0 auto; border:1px solid #000; }
	.log1_div { height:380px; }
	.log2_div { height:100px; margin-top:10px; }
	.log3_div { height:100px; margin-top:10px; }
	.log_column { float:left; display:block; padding:5px; }
	.log_notdone { display:block; padding:5px; }
	.log_warn { color:#f00; }
</style>
</head>
<body>
');

	/*** General log. ***/
	print ('<div class="log_div log1_div" id="log1_div">' . "\n");
	$query = "SELECT * FROM (SELECT * FROM `log` ORDER BY log_datetime DESC" .
		" LIMIT 400) AS tbl ORDER BY log_datetime, log_id;";
	$result = mysqli_query ($GLOBALS['link'], $query);
	if (mysqli_num_rows ($result) > 0) { Lines ($result); }
	print ('</div>' . "\n");

	/*** Just replies from the bot. ***/
	print ('<div class="log_div log2_div" id="log2_div">' . "\n");
	$query = "SELECT * FROM (SELECT * FROM `log` WHERE (log_nick='" .
		$GLOBALS['botname'] . "') AND (log_command IN ('NOTICE', 'PRIVMSG'))" .
		" AND (log_channel != 'NickServ') ORDER BY log_datetime DESC, log_id" .
		" DESC LIMIT 100) AS tbl ORDER BY log_datetime, log_id;";
	$result = mysqli_query ($GLOBALS['link'], $query);
	if (mysqli_num_rows ($result) > 0) { Lines ($result); }
	print ('</div>' . "\n");

	/*** The get_data.php log. ***/
	print ('<div class="log_div log3_div" id="log3_div">' . "\n");
	$query = "SELECT * FROM (SELECT * FROM `getlog` ORDER BY getlog_datetime" .
		" DESC LIMIT 400) AS tbl ORDER BY getlog_datetime, getlog_id;";
	$result = mysqli_query ($GLOBALS['link'], $query);
	if (mysqli_num_rows ($result) > 0) { GetLines ($result); }
	$sWarn = '';
	$iNotDone = NotDone();
	if ($iNotDone != 0) { $sWarn = ' log_warn'; }
	print ('<span class="log_notdone' . $sWarn . '">Warnings: ' .
		$iNotDone . '</span>');
	print ('</div>' . "\n");

print ('<script type="text/javascript">
	var objDiv = document.getElementById("log1_div");
	objDiv.scrollTop = objDiv.scrollHeight;
	var objDiv = document.getElementById("log2_div");
	objDiv.scrollTop = objDiv.scrollHeight;
	var objDiv = document.getElementById("log3_div");
	objDiv.scrollTop = objDiv.scrollHeight;
</script>
</body>
</html>');

	mysqli_close ($GLOBALS['link']);
?>
