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
 * Authentication and related library functions.
 */

require_once($VIEW_PATH.'/lib/setup.php');

if (!isset($_SESSION)) {
	session_name('pffw');
	session_start();
}

if (filter_has_var(INPUT_GET, 'logout')) {
	LogUserOut();
}

if (filter_has_var(INPUT_POST, 'Locale')) {
	$_SESSION['Locale'] = filter_input(INPUT_POST, 'Locale');
	// To refresh the page after language change
	header('Location: '.$_SERVER['REQUEST_URI']);
	exit;
}

if (!isset($_SESSION['Locale'])) {
	$_SESSION['Locale']= $DefaultLocale;
}
putenv('LC_ALL='.$_SESSION['Locale']);
putenv('LANG='.$_SESSION['Locale']);

$Domain= 'pffw';
bindtextdomain($Domain, $VIEW_PATH.'/locale');
bind_textdomain_codeset($Domain, $LOCALES[$_SESSION['Locale']]['Codeset']);
textdomain($Domain);

/**
 * Wrapper for syslog().
 *
 * Web interface related syslog messages.
 * A global $LOG_LEVEL is set in setup.php.
 *
 * @param int $prio Log priority checked against $LOG_LEVEL.
 * @param string $file Source file the function is in.
 * @param string $func Function where the log is taken.
 * @param int $line Line number within the function.
 * @param string $msg Log message.
 */
function pffwwui_syslog($prio, $file, $func, $line, $msg)
{
	global $LOG_LEVEL, $LOG_PRIOS;

	try {
		openlog('pffwwui', LOG_PID, LOG_LOCAL0);
		
		if ($prio <= $LOG_LEVEL) {
			$useratip= $_SESSION['USER'].'@'.filter_input(INPUT_SERVER, 'REMOTE_ADDR');
			$func= $func == '' ? 'NA' : $func;
			$log= "$LOG_PRIOS[$prio] $useratip $file: $func ($line): $msg\n";
			if (!syslog($prio, $log)) {
				if (!fwrite(STDERR, $log)) {
					echo $log;
				}
			}
		}
		closelog();
	}
	catch (Exception $e) {
		echo 'Caught exception: ',  $e->getMessage(), "\n";
		echo "pffwwui_syslog() failed: $prio, $file, $func, $line, $msg\n";
		// No need to closelog(), it is optional
	}
}

/**
 * Logs user out by setting session USER var to loggedout.
 *
 * Redirects to the main index page, which asks for re-authentication.
 *
 * @param string $reason Reason for log message.
 */
function LogUserOut($reason= 'User logged out')
{
	pffwwui_syslog(LOG_INFO, __FILE__, __FUNCTION__, __LINE__, $reason);

	// Save USER to check if the user changes in the next session
	$_SESSION['PREVIOUS_USER']= $_SESSION['USER'];

	$_SESSION['USER']= 'loggedout';
	/// @warning Relogin page should not time out
	$_SESSION['Timeout']= -1;
	session_write_close();

	header('Location: /index.php');
	exit;
}

/**
 * Authenticates session user with the password supplied.
 *
 * Passwords are sha1 encrypted before passed to Controller,
 * so the password string is never passed around plain text.
 * This means double encryption in the password file,
 * because Model encrypts again while storing into the file.
 *
 * @param string $passwd Password submitted by user.
 */
function Authentication($passwd)
{
	global $ALL_USERS, $SessionTimeout, $View;

	pffwwui_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, 'Login attempt');

	if (!in_array($_SESSION['USER'], $ALL_USERS)) {
		pffwwui_syslog(LOG_NOTICE, __FILE__, __FUNCTION__, __LINE__, 'Not a valid user');
		// Throttle authentication failures
		exec('/bin/sleep 5');
		LogUserOut('Authentication failed');
	}

	if (!$View->Controller($Output, 'CheckAuthentication', $_SESSION['USER'], sha1($passwd))) {
		pffwwui_syslog(LOG_NOTICE, __FILE__, __FUNCTION__, __LINE__, 'Password mismatch');
		// Throttle authentication failures
		exec('/bin/sleep 5');
		LogUserOut('Authentication failed');
	}
	pffwwui_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, 'Authentication succeeded');

	// Update session timeout now, otherwise in the worst case scenario, vars.php may log user out on very close session timeout
	$_SESSION['Timeout']= time() + $SessionTimeout;

	// Reset the pf session if the user changes
	if (isset($_SESSION['PREVIOUS_USER']) && $_SESSION['PREVIOUS_USER'] !== $_SESSION['USER']) {
		/// @todo Should we reset everything else, other than USER and Timeout?
		unset($_SESSION['pf']);
	}
	
	header('Location: /pf/index.php');
	exit;
}

/**
 * HTML Header.
 *
 * @param string $color Page background, Login page uses gray.
 */
function HTMLHeader($color= 'white', $reloadRate= 0)
{
	global $LOCALES;
	?>
	<!DOCTYPE html>
	<html>
		<head>
			<title><?php echo _MENU('PF Firewall') ?></title>
			<meta http-equiv="content-type" content="text/html; charset=<?php echo $LOCALES[$_SESSION['Locale']]['Codeset'] ?>" />
			<meta name="description" content="PF Firewall" />
			<meta name="author" content="Soner Tari"/>
			<meta name="keywords" content="PF, Firewall, :)" />
			<link rel="stylesheet" href="../pffw.css" type="text/css" media="screen" />
			<?php
			if ($reloadRate !== 0) {
				?>
				<meta http-equiv="refresh" content="<?php echo $reloadRate ?>" />
				<?php
			}
			?>
		</head>
		<body style="background: <?php echo $color ?>">
			<table>
			<?php
}

function HTMLFooter()
{
			?>
			</table>
		</body>
	</html>
	<?php
}

/**
 * Sets session submenu variable.
 *
 * @param string $default Default submenu selected.
 * @return string Selected submenu.
 */
function SetSubmenu2($default)
{
	global $View, $Menu, $TopMenu;
	
	if (filter_has_var(INPUT_GET, 'submenu') && array_key_exists(filter_input(INPUT_GET, 'submenu'), $Menu[$View->Model][$TopMenu]['SubMenu'])) {
		$submenu= filter_input(INPUT_GET, 'submenu');
	} elseif ($_SESSION['submenu']) {
		$submenu= $_SESSION['submenu'];
	} else {
		$submenu= $default;
	}

	return $submenu;
}

/**
 * Sets session submenu variable.
 *
 * @param string $default Default submenu selected
 * @return string Selected submenu
 */
function SetSubmenu($default)
{
	global $View, $Menu, $TopMenu;

	$page= basename($_SERVER['PHP_SELF']);

	if (filter_has_var(INPUT_GET, 'submenu')) {
		$submenu= filter_input(INPUT_GET, 'submenu');
		if (array_key_exists($submenu, $Menu[$View->Model][$TopMenu]['SubMenu'])) {
			$_SESSION[$View->Model][$TopMenu]['submenu']= $submenu;
		}
		else {
			pffwwui_syslog(LOG_NOTICE, __FILE__, __FUNCTION__, __LINE__, "No such submenu for $View->Model>$TopMenu: " . $submenu);
			echo _TITLE('Resource not available').": $TopMenu.php?submenu=" . $submenu;
			exit(1);
		}
	}

	if ($_SESSION[$View->Model][$TopMenu]['submenu']) {
		$submenu= $_SESSION[$View->Model][$TopMenu]['submenu'];
	}
	else {
		$submenu= $default;
	}

	$_SESSION[$View->Model][$TopMenu]['submenu']= $submenu;
	return $submenu;
}
?>
