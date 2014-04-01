<?php
/***********************************************/
function NickHover ($sNick, $sIdent, $sHost)
/***********************************************/
{
	$sNickHover = '<span title="' . $sNick . '!' . $sIdent .
		'@' . $sHost . '">' . $sNick . '</span>';
	return ($sNickHover);
}
/***********************************************/

	require_once ('steamlug-bot_settings.php');
	require_once ('steamlug-bot_def.php');

print ('
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="Refresh" content="5">
<title>Log</title>
<style type="text/css">
	body { margin:10px; background-color:#ddd; font-size:14px; font-family:Arial,Helvetica,sans-serif; }
	.log_div { width:1200px; height:600px; overflow-y:scroll; margin:0 auto; border:1px solid #000; }
	.log_column { float:left; display:block; padding:5px; }
</style>
</head>
<body>
<div class="log_div" id="log_div">
');

	$query = "SELECT * FROM (SELECT * FROM `log` ORDER BY log_datetime DESC" .
		" LIMIT 200) AS tbl ORDER BY log_datetime, log_id;";
	$result = mysqli_query ($GLOBALS['link'], $query);
	if (mysqli_num_rows ($result) > 0)
	{
		while ($row = mysqli_fetch_assoc ($result))
		{
			$sCom = $row['log_command'];
			$sGrey = array ('QUIT', 'JOIN', 'NICK');
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
			print ('<span class="log_column" style="width:700px;">' .
				$sText . '</span>');
			print ('<span style="display:block; clear:both;"></span>');
			print ('</div>');
		}
	}

print ('
</div>
<script type="text/javascript">
	var objDiv = document.getElementById("log_div");
	objDiv.scrollTop = objDiv.scrollHeight;
</script>
</body>
</html>
');

	mysqli_close ($GLOBALS['link']);
?>
