<?php
/*
 * Copyright (C) 2004-2016 Soner Tari
 *
 * This file is part of PFFW.
 *
 * PFFW is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PFFW is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PFFW.  If not, see <http://www.gnu.org/licenses/>.
 */

/** @file
 * All live statistics pages include this file.
 * Statistics configuration is in $Modules.
 */

require_once('../lib/vars.php');

$Reload= TRUE;
SetRefreshInterval();

$View->Controller($Output, 'GetDefaultLogFile');
$LogFile= $Output[0];

$DateArray['Month']= exec('/bin/date +%m');
$DateArray['Day']= exec('/bin/date +%d');
$DateArray['Hour']= exec('/bin/date +%H');

$GraphType= 'Horizontal';

if (count($_POST)) {
	$GraphType= filter_input(INPUT_POST, 'GraphType');
	$_SESSION[$View->Model]['GraphType']= $GraphType;
}
else if ($_SESSION[$View->Model]['GraphType']) {
	$GraphType= $_SESSION[$View->Model]['GraphType'];
}

$Hour= $DateArray['Hour'];
$Date= $View->FormatDate($DateArray);

$ViewStatsConf= $StatsConf[$View->Model];

$View->Controller($Output, 'GetStats', $LogFile, serialize($DateArray), 'COLLECT');
$Stats= unserialize($Output[0]);
$DateStats= $Stats['Date'];

require_once($VIEW_PATH . '/header.php');
?>
<table>
	<tr>
		<td>
			<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="post">
				<?php echo _TITLE('Refresh interval').':' ?>
				<input type="text" name="RefreshInterval" style="width: 20px;" maxlength="2" value="<?php echo $_SESSION[$View->Model]['ReloadRate'] ?>" />
				<?php echo _TITLE('secs') ?>
				<select name="GraphType">
					<option <?php echo ($GraphType == 'Vertical') ? 'selected' : '' ?> value="<?php echo 'Vertical' ?>"><?php echo _CONTROL('Vertical') ?></option>
					<option <?php echo ($GraphType == 'Horizontal') ? 'selected' : '' ?> value="<?php echo 'Horizontal' ?>"><?php echo _CONTROL('Horizontal') ?></option>
				</select>
				<input type="submit" name="Apply" value="<?php echo _CONTROL('Apply') ?>"/>
			</form>
		</td>
		<td>
			<strong><?php echo _TITLE('Date').': '.$Date.', '.$Hour.':'.date('i') ?></strong>
		</td>
	</tr>
</table>
<?php
foreach ($ViewStatsConf as $Name => $Conf) {
	if (isset($Conf['Color'])) {
		PrintMinutesGraphNVPSet($DateStats[$Date]['Hours'][$Hour], $Name, $Conf, $GraphType);
	}
}

if (isset($ViewStatsConf['Total']['Counters'])) {
	foreach ($ViewStatsConf['Total']['Counters'] as $Name => $Conf) {
		PrintMinutesGraphNVPSet($DateStats[$Date]['Hours'][$Hour], $Name, $Conf, $GraphType);
	}
}

require_once($VIEW_PATH . '/footer.php');
?>
