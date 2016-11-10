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
 * Contains base class which runs basic Model tasks.
 */

require_once($MODEL_PATH.'/include.php');

class Model
{
	public $Name= '';

	/// @attention Should be updated in constructors of children
	public $Proc= '';
	public $User= '';

	// @attention On OpenBSD 5.9 ps limits the user name string to 7 chars, hence _e2guardian becomes _e2guard
	// @todo Find a way to increase the terminal COLUMNS size, using "env COLUMNS=10000" does not work
	protected $psCmd= '/bin/ps arwwx -o pid,start,%cpu,time,%mem,rss,vsz,stat,pri,nice,tty,user,group,command | /usr/bin/grep -v -e grep | /usr/bin/grep -E <PROC>';

	public $StartCmd= '';

	/// Max number of iterations to try while starting or stopping processes.
	const PROC_STAT_TIMEOUT= 100;
	
	/// Apache password file pathname.
	protected $passwdFile= '/var/www/conf/.htpasswd';
	
	/**
	 * Argument lists and descriptions of commands.
	 *
	 * @todo Should we implement $Commands using Interfaces in OOP?
	 *
	 * @param array argv Array of arg types in order.
	 * @param string desc Description of the shell function.
	 */
	public $Commands= array();

	private $NVPS= '=';
	private $COMC= '#';

	public $LogFile= '';
	public $TmpLogsDir= '';

	protected $rcConfLocal= '/etc/rc.conf.local';

	public $PfRulesFile= '/etc/pf.conf';
	
	function __construct()
	{
		$this->Proc= $this->Name;

		$this->TmpLogsDir= '/var/tmp/pffw/logs/'.get_class($this).'/';

		$this->Commands= array_merge(
			$this->Commands,
			array(
				'IsRunning'	=>	array(
					'argv'	=>	array(),
					'desc'	=>	_('Check if process running'),
					),

				'Start'	=>	array(
					'argv'	=>	array(),
					'desc'	=>	_('Start '.get_class($this)),
					),
				
				'Stop'	=>	array(
					'argv'	=>	array(),
					'desc'	=> _('Stop '.get_class($this)),
					),
				
				'GetProcList'	=>	array(
					'argv'	=>	array(),
					'desc'	=>	_('Get process list'),
					),

				'GetIntIf'=>	array(
					'argv'	=>	array(),
					'desc'	=>	_('Get int_if'),
					),
				
				'GetExtIf'=>	array(
					'argv'	=>	array(),
					'desc'	=>	_('Get ext_if'),
					),
				
				'CheckAuthentication'	=>	array(
					'argv'	=>	array(NAME, SHA1STR),
					'desc'	=>	_('Check authentication'),
					),
				
				'SetPassword'	=>	array(
					'argv'	=>	array(NAME, SHA1STR),
					'desc'	=>	_('Set user password'),
					),

				'SetLogLevel'=>	array(
					'argv'	=>	array(NAME),
					'desc'	=>	_('Set log level'),
					),

				'SetHelpBox'=>	array(
					'argv'	=>	array(NAME),
					'desc'	=>	_('Set help boxes'),
					),

				'SetSessionTimeout'=>	array(
					'argv'	=>	array(NUM),
					'desc'	=>	_('Set session timeout'),
					),

				'SetDefaultLocale'=>	array(
					'argv'	=>	array(NAME),
					'desc'	=>	_('Set default locale'),
					),

				'SetForceHTTPs'=>	array(
					'argv'	=>	array(NAME),
					'desc'	=>	_('Set force HTTPs'),
					),

				'SetMaxAnchorNesting'=>	array(
					'argv'	=>	array(NUM),
					'desc'	=>	_('Set max anchor nesting'),
					),

				'SetPfctlTimeout'=>	array(
					'argv'	=>	array(NUM),
					'desc'	=>	_('Set pfctl timeout'),
					),

				'SetReloadRate'=>	array(
					'argv'	=>	array(NUM),
					'desc'	=>	_('Set reload rate'),
					),

				'GetPhyIfs'		=>	array(
					'argv'	=>	array(),
					'desc'	=>	_('List physical interfaces'),
					),

				'GetDefaultLogFile'	=>	array(
					'argv'	=>	array(),
					'desc'	=>	_('Get log file'),
					),

				'SelectLogFile'	=>	array(
					'argv'	=>	array(FILEPATH|EMPTYSTR),
					'desc'	=>	_('Select log file'),
					),

				'GetLogFilesList'	=>	array(
					'argv'	=>	array(),
					'desc'	=>	_('Get log files list'),
					),

				'GetLogStartDate'	=>	array(
					'argv'	=>	array(FILEPATH),
					'desc'	=>	_('Get log start date'),
					),

				'GetFileLineCount'	=>	array(
					'argv'	=>	array(FILEPATH, REGEXP|NONE),
					'desc'	=>	_('Gets line count'),
					),

				'GetLogs'	=>	array(
					'argv'	=>	array(FILEPATH, NUM, NUM, REGEXP|NONE),
					'desc'	=>	_('Get lines'),
					),

				'GetLiveLogs'	=>	array(
					'argv'	=>	array(FILEPATH, NUM, REGEXP|NONE),
					'desc'	=>	_('Get tail'),
					),

				'GetAllStats'=>	array(
					'argv'	=>	array(FILEPATH, NAME|EMPTYSTR),
					'desc'	=>	_('Get all stats'),
					),

				'GetStats'=>	array(
					'argv'	=>	array(FILEPATH, SERIALARRAY, NAME|EMPTYSTR),
					'desc'	=>	_('Get stats'),
					),

				'GetProcStatLines'	=>	array(
					'argv'	=>	array(FILEPATH|NONE),
					'desc'	=>	_('Get stat lines'),
					),

				'PrepareFileForDownload'	=>	array(
					'argv'	=>	array(FILEPATH),
					'desc'	=>	_('Prepare file for download'),
					),
				)
			);
	}

	/**
	 * Checks if the given process(es) is running.
	 *
	 * Uses ps with grep.
	 *
	 * @param string $proc Module process name.
	 * @return bool TRUE if there is any process running, FALSE otherwise.
	 */
	function IsRunning($proc= '')
	{
		if ($proc == '') {
			$proc= $this->Proc;
		}
	
		/// @todo Should use pid files instead of ps, if possible at all
		$cmd= preg_replace('/<PROC>/', escapeshellarg($proc), $this->psCmd);
		exec($cmd, $output, $retval);
		if ($retval === 0) {
			return count($this->SelectProcesses($output)) > 0;
		}
		Error(implode("\n", $output));
		pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "No such process: $proc");
		return FALSE;
	}
	
	/**
	 * Gets the list of processes running.
	 * 
	 * @return mixed List of processes on success, FALSE on fail.
	 */
	function GetProcList()
	{
		$cmd= preg_replace('/<PROC>/', escapeshellarg($this->Proc), $this->psCmd);
		exec($cmd, $output, $retval);
		if ($retval === 0) {
			return Output(serialize($this->SelectProcesses($output)));
		}
		Error(implode("\n", $output));
		pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "Process list failed for $this->Proc");
		return FALSE;
	}

	/**
	 * Selects user processes from ps output.
	 *
	 * @param array $psout ps output obtained elsewhere.
	 * @return array Parsed ps output of user processes.
	 */
	function SelectProcesses($psout)
	{
		//   PID STARTED  %CPU      TIME %MEM   RSS   VSZ STAT  PRI  NI TTY      USER     GROUP    COMMAND
		//     1  5:10PM   0.0   0:00.03  0.0   388   412 Is     10   0 ??       root     wheel    /sbin/init
		// Skip processes running on terminals, e.g. vi, tail, man
		// Select based on daemon user
		$re= "/^\s*(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(\d+)\s+(\d+)\s+\?\?\s+($this->User)\s+(\S+)\s+(.+)$/";
		
		$processes= array();
		foreach ($psout as $line) {
			if (preg_match($re, $line, $match)) {
				// Skip processes initiated by this WUI
				if (!preg_match('/\b(pffwc\.php|grep|kill|pkill)\b/', $match[13])) {
					$processes[]= array(
						$match[1],
						$match[2],
						$match[3],
						$match[4],
						$match[5],
						$match[6],
						$match[7],
						$match[8],
						$match[9],
						$match[10],
						$match[11],
						$match[12],
						$match[13],
						);
				}
			}
		}
		return $processes;
	}

	/**
	 * Start module process(es).
	 *
	 * Tries PROC_STAT_TIMEOUT times.
	 *
	 * @todo Actually should stop retrying on error?
	 *
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function Start()
	{
		global $TmpFile;

		$this->RunShellCommand($this->StartCmd);
		
		$count= 0;
		while ($count++ < self::PROC_STAT_TIMEOUT) {
			if ($this->IsRunning()) {
				return TRUE;
			}
			/// @todo Check $TmpFile for error messages, if so break out instead
			exec('/bin/sleep .1');
		}

		// Check one last time due to the last sleep in the loop
		if ($this->IsRunning()) {
			return TRUE;
		}
		
		// Start command is redirected to tmp file
		$output= file_get_contents($TmpFile);
		Error($output);
		pffwc_syslog(LOG_ERR, __FILE__, __FUNCTION__, __LINE__, "Start failed with: $output");
		return FALSE;
	}

	/**
	 * Stops module process(es)
	 *
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function Stop()
	{
		return $this->Pkill($this->Proc);
	}
		
	/**
	 * Kills the given process(es).
	 *
	 * Used to kill processes without a model definition, hence the $proc param.
	 * Tries PROC_STAT_TIMEOUT times.
	 *
	 * @todo Actually should stop retrying on error?
	 *
	 * @param string $proc Process name
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function Pkill($proc)
	{
		global $TmpFile;
		
		$cmd= '/usr/bin/pkill -x '.$proc;
		
		$count= 0;
		while ($count++ < self::PROC_STAT_TIMEOUT) {
			if (!$this->IsRunning($proc)) {
				return TRUE;
			}
			$this->RunShellCommand("$cmd > $TmpFile 2>&1");
			/// @todo Check $TmpFile for error messages, if so break out instead
			exec('/bin/sleep .1');
		}

		// Check one last time due to the last sleep in the loop
		if (!$this->IsRunning($proc)) {
			return TRUE;
		}
		
		// Pkill command is redirected to the tmp file
		$output= file_get_contents($TmpFile);
		Error($output);
		pffwc_syslog(LOG_ERR, __FILE__, __FUNCTION__, __LINE__, "Pkill failed for $proc with: $output");
		return FALSE;
	}

	/**
	 * Get int_if.
	 * 
	 * @return string Internal interface name.
	 */
	function GetIntIf()
	{
		return Output($this->_getIntIf());
	}

	function _getIntIf()
	{
		return $this->GetNVP($this->PfRulesFile, 'int_if');
	}

	/**
	 * Get ext_if.
	 * 
	 * @return string External interface name.
	 */
	function GetExtIf()
	{
		return Output($this->_getExtIf());
	}

	function _getExtIf()
	{
		return $this->GetNVP($this->PfRulesFile, 'ext_if');
	}

	/**
	 * Checks the given user:password pair against the one in .htpasswd file.
	 * 
	 * Note that the passwords in .htpasswd are double encrypted.
	 *
	 * @param string $user User name.
	 * @param string $passwd SHA encrypted password.
	 * @return bool TRUE if passwd matches, FALSE otherwise.
	 */
	function CheckAuthentication($user, $passwd)
	{
		/// @warning Args should never be empty, htpasswd expects 2 args
		$passwd= $passwd == '' ? "''" : $passwd;

		/// Passwords in htpasswd file are SHA encrypted.
		exec("/usr/local/bin/htpasswd -bn -s '' $passwd 2>&1", $output, $retval);
		if ($retval === 0) {
			$htpasswd= ltrim($output[0], ':');
		
			/// @warning Have to trim newline chars, or passwds do not match
			$passwdfile= file($this->passwdFile, FILE_IGNORE_NEW_LINES);
			
			// Do not use preg_match() here. If there is more than one line (passwd) for a user in passwdFile,
			// this array method ensures that only one password apply to each user, the last one in passwdFile.
			// This should never happen actually, but in any case.
			$passwdlist= array();
			foreach ($passwdfile as $nvp) {
				list($u, $p)= explode(':', $nvp, 2);
				$passwdlist[$u]= $p;
			}

			if ($passwdlist[$user] === $htpasswd) {
				return TRUE;
			}
		}
		Error(_('Authentication failed'));
		return FALSE;
	}

	/**
	 * Sets user's password in .htpasswd file.
	 * 
	 * @param string $user User name.
	 * @param string $passwd SHA encrypted password.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function SetPassword($user, $passwd)
	{
		/// Passwords in htpasswd file are SHA encrypted.
		exec("/usr/local/bin/htpasswd -b -s $this->passwdFile $user $passwd 2>&1", $output, $retval);
		if ($retval === 0) {
			return TRUE;
		}
		$errout= implode("\n", $output);
		Error($errout);
		pffwc_syslog(LOG_ERR, __FILE__, __FUNCTION__, __LINE__, "Set password failed: $errout");
		return FALSE;
	}

	/**
	 * Sets global log level.
	 * 
	 * @param string $level Level to set to.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function SetLogLevel($level)
	{
		global $ROOT, $TEST_DIR_SRC;

		// Append semi-colon to new value, this setting is a PHP line
		return $this->SetNVP($ROOT . $TEST_DIR_SRC . '/lib/setup.php', '\$LOG_LEVEL', $level.';');
	}

	/**
	 * Enables or disables help boxes.
	 * 
	 * @param bool $bool TRUE to enable, FALSE otherwise.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function SetHelpBox($bool)
	{
		global $ROOT, $TEST_DIR_SRC;
		
		// Append semi-colon to new value, this setting is a PHP line
		return $this->SetNVP($ROOT . $TEST_DIR_SRC . '/View/lib/setup.php', '\$ShowHelpBox', $bool.';');
	}
	
	/**
	 * Sets session timeout.
	 * 
	 * If the given values is less than 10, we set the timeout to 10 seconds.
	 * 
	 * @param int $timeout Timeout in seconds.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function SetSessionTimeout($timeout)
	{
		global $ROOT, $TEST_DIR_SRC;

		if ($timeout < 10) {
			$timeout= 10;
		}
		
		// Append semi-colon to new value, this setting is a PHP line
		return $this->SetNVP($ROOT . $TEST_DIR_SRC . '/View/lib/setup.php', '\$SessionTimeout', $timeout.';');
	}

	/**
	 * Sets default locale.
	 * 
	 * @param string $locale Locale.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function SetDefaultLocale($locale)
	{
		global $ROOT, $TEST_DIR_SRC;

		// Append semi-colon to new value, this setting is a PHP line
		return $this->SetNVP($ROOT . $TEST_DIR_SRC . '/lib/setup.php', '\$DefaultLocale', $locale.';');
	}

	/**
	 * Enables or disables HTTPs.
	 * 
	 * @param bool $bool TRUE to enable, FALSE to disable HTTPs.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function SetForceHTTPs($bool)
	{
		global $ROOT, $TEST_DIR_SRC;
		
		// Append semi-colon to new value, this setting is a PHP line
		return $this->SetNVP($ROOT . $TEST_DIR_SRC . '/lib/setup.php', '\$ForceHTTPs', $bool.';');
	}

	/**
	 * Sets the max number of nested anchors allowed.
	 * 
	 * @param int $max Number of nested anchors allowed.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function SetMaxAnchorNesting($max)
	{
		global $ROOT, $TEST_DIR_SRC;
		
		// Append semi-colon to new value, this setting is a PHP line
		return $this->SetNVP($ROOT . $TEST_DIR_SRC . '/lib/setup.php', '\$MaxAnchorNesting', $max.';');
	}

	/**
	 * Sets pfctl timeout.
	 * 
	 * Note that setting this value to 0 effectively fails all pfctl calls.
	 * 
	 * @param int $timeout Timeout waiting pfctl output in seconds.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function SetPfctlTimeout($timeout)
	{
		global $ROOT, $TEST_DIR_SRC;
		
		// Append semi-colon to new value, this setting is a PHP line
		return $this->SetNVP($ROOT . $TEST_DIR_SRC . '/lib/setup.php', '\$PfctlTimeout', $timeout.';');
	}
	
	/**
	 * Sets default reload rate.
	 * 
	 * @param int $rate Reload rate in seconds.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function SetReloadRate($rate)
	{
		global $ROOT, $TEST_DIR_SRC;
		
		// Append semi-colon to new value, this setting is a PHP line
		return $this->SetNVP($ROOT . $TEST_DIR_SRC . '/View/lib/setup.php', '\$DefaultReloadRate', $rate.';');
	}
	
	/**
	 * Runs the given shell command and returns its output as string.
	 *
	 * @todo Fix return value checks in some references, RunShellCommand() does not return FALSE
	 *
	 * @param string $cmd Command string to run.
	 * @return string Command result in a string.
	 */
	function RunShellCommand($cmd)
	{
		/// @attention Do not use shell_exec() here, because it is disabled when PHP is running in safe_mode
		/// @warning Not all shell commands return 0 on success, such as grep, date...
		/// Hence, do not check return value
		exec($cmd, $output);
		if (is_array($output)) {
			return implode("\n", $output);
		}
		return '';
	}

	/**
	 * Returns files with the given filepath pattern.
	 *
	 * $filepath does not have to be just directory path, and may contain wildcards.
	 *
	 * @param string $filepath File pattern to match.
	 * @return string List of file names, without path.
	 */
	function GetFiles($filepath)
	{
		return $this->RunShellCommand("ls -1 $filepath");
	}

	/**
	 * Reads file contents.
	 *
	 * @param string $file Config file.
	 * @return mixed File contents in a string or FALSE on fail.
	 */
	function GetFile($file)
	{
		if (file_exists($file)) {
			return file_get_contents($file);
		}
		return FALSE;
	}

	/**
	 * Deletes the given file or directory.
	 *
	 * @param string $path File or dir to delete.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function DeleteFile($path)
	{
		if (file_exists($path)) {
			exec("/bin/rm -rf $path 2>&1", $output, $retval);
			if ($retval === 0) {
				return TRUE;
			}
			else {
				$errout= implode("\n", $output);
				Error($errout);
				pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "Failed deleting: $path, $errout");
			}
		}
		else {
			pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "File path does not exist: $path");
		}
		return FALSE;
	}

	/**
	 * Writes contents to file.
	 *
	 * @param string $file Config filename.
	 * @param string $contents Contents to write.
	 * @return mixed Output of file_put_contents() or FALSE on fail.
	 */
	function PutFile($file, $contents)
	{
		if (file_exists($file)) {
			return file_put_contents($file, $contents, LOCK_EX);
		}
		return FALSE;
	}

	/**
	 * Changes value of NVP.
	 *
	 * @param string $file Config file.
	 * @param string $name Name of NVP.
	 * @param mixed $newvalue New value to set.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function SetNVP($file, $name, $newvalue)
	{
		if (copy($file, $file.'.bak')) {
			if (($value= $this->GetNVP($file, $name)) !== FALSE) {
				/// @warning Backslash should be escaped first, or causes double escapes
				$value= Escape($value, '\/$^*()."');
				$re= "^(\h*$name\b\h*$this->NVPS\h*)($value)(\h*$this->COMC.*|\h*)$";

				$contents= preg_replace("/$re/m", '${1}'.$newvalue.'${3}', file_get_contents($file), 1, $count);
				if ($contents !== NULL && $count == 1) {
					file_put_contents($file, $contents);
					return TRUE;
				}
				else {
					pffwc_syslog(LOG_ERR, __FILE__, __FUNCTION__, __LINE__, "Cannot set new value $file, $name, $newvalue");
				}
			}
			else {
				pffwc_syslog(LOG_ERR, __FILE__, __FUNCTION__, __LINE__, "Cannot find NVP: $file, $name");
			}
		}
		else {
			pffwc_syslog(LOG_ERR, __FILE__, __FUNCTION__, __LINE__, "Cannot copy file $file");
		}
		return FALSE;
	}

	/**
	 * Reads value of NVP.
	 *
	 * @param string $file Config file.
	 * @param string $name Name of NVP.
	 * @param string $trimchars Chars to trim in the results.
	 * @return mixed Value of NVP or NULL on failure.
	 */
	function GetNVP($file, $name, $trimchars= '')
	{
		return $this->SearchFile($file, "/^\h*$name\b\h*$this->NVPS\h*([^$this->COMC'\"\n]*|'[^'\n]*'|\"[^\"\n]*\"|[^$this->COMC\n]*)(\h*|\h*$this->COMC.*)$/m", 1, $trimchars);
	}

	/**
	 * Searches the given file with the given regex.
	 *
	 * @param string $file Config file.
	 * @param string $re Regex to search the file with, should have end markers.
	 * @param int $set There may be multiple parentheses in $re, which one to return.
	 * @param string $trimchars If given, these chars are trimmed on the left or right.
	 * @return mixed String found or FALSE if no match.
	 */
	function SearchFile($file, $re, $set= 1, $trimchars= '')
	{
		/// @todo What to do with multiple matching NVPs
		if (preg_match($re, file_get_contents($file), $match)) {
			$retval= $match[$set];
			if ($trimchars !== '') {
				$retval= trim($retval, $trimchars);
			}
			return rtrim($retval);
		}
		return FALSE;
	}

	/**
	 * Multi-searches a given file with a given regexp.
	 *
	 * @param string $file Config file.
	 * @param string $re Regexp to search the file with, should have end markers.
	 * @param int $set There may be multiple parentheses in $re, which one to return.
	 * @return mixed String found or FALSE on fail.
	 */
	function SearchFileAll($file, $re, $set= 1)
	{
		/// @todo What to do multiple matching NVPs
		if (preg_match_all($re, file_get_contents($file), $match)) {
			return implode("\n", array_values($match[$set]));
		}
		return FALSE;
	}

	/**
	 * Searches a needle and replaces with a value in the given file.
	 *
	 * @param string $file Config file.
	 * @param string $matchre Match re.
	 * @param string $replacere Replace re.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function ReplaceRegexp($file, $matchre, $replacere)
	{
		if (copy($file, $file.'.bak')) {
			$contents= preg_replace($matchre, $replacere, file_get_contents($file), 1, $count);
			if ($contents !== NULL && $count === 1) {
				file_put_contents($file, $contents);
				return TRUE;
			}
			else {
				pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "Cannot replace in $file");
			}
		}
		else {
			pffwc_syslog(LOG_ERR, __FILE__, __FUNCTION__, __LINE__, "Cannot copy file $file");
		}
		return FALSE;
	}

	/**
	 * Appends a string to a file.
	 *
	 * @param string $file Config file pathname.
	 * @param string $line Line to add.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function AppendToFile($file, $line)
	{
		if (copy($file, $file.'.bak')) {
			$contents= file_get_contents($file).$line."\n";
			file_put_contents($file, $contents);
			return TRUE;
		}
		else {
			pffwc_syslog(LOG_ERR, __FILE__, __FUNCTION__, __LINE__, "Cannot copy file $file");
		}
		return FALSE;
	}

	/**
	 * Extracts physical interface names from ifconfig output.
	 *
	 * Removes non-physical interfaces from the output.
	 * 
	 * @return string Names of physical interfaces.
	 */
	function GetPhyIfs()
	{
		return Output($this->_getPhyIfs());
	}

	function _getPhyIfs()
	{
		return $this->RunShellCommand("/sbin/ifconfig -a | /usr/bin/grep ': flags=' | /usr/bin/sed 's/: flags=.*//g' | /usr/bin/grep -v -e lo -e pflog -e pfsync -e enc -e tun");
	}

	/**
	 * Gets the log file of the module.
	 * 
	 * @return string Name of log file.
	 */
	function GetDefaultLogFile()
	{
		return Output($this->LogFile);
	}

	/**
	 * Gets the log file under the tmp folder.
	 *
	 * Updates the tmp file if the original file is modified.
	 * Updates the stat info of the file in the tmp statistics file, which is used to check file modificaton.
	 *
	 * @param string $file Original file name.
	 * @return string Pathname of the log file.
	 */
	function SelectLogFile($file)
	{
		if ($file === '') {
			$file= $this->LogFile;
		}

		$file= $this->GetTmpLogFileName($file);
		
		if (!file_exists($file) || $this->IsLogFileModified($file)) {
			if ($this->UpdateTmpLogFile($file)) {
				// Update stats to update file stat info only
				$this->UpdateStats($file, $stats, $briefstats);
			}
			else {
				$file= $this->LogFile;
				pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "Logfile tmp copy update failed, defaulting to: $file");
			}
		}
		
		return Output($file);
	}

	/**
	 * Checks if the given log file has been updated.
	 *
	 * Compares the full stat info of the orig and tmp files. 
	 * We unset the last access time before the diff, because it is updated by the stat() call itself too.
	 *
	 * @param string $logfile Log file.
	 * @return bool TRUE if modified, FALSE otherwise.
	 */
	function IsLogFileModified($logfile)
	{
		$origfile= $this->GetOrigFileName($logfile);
		
		if ($this->GetStatsFileInfo($logfile, $linecount, $filestat)) {
			if (file_exists($origfile)) {
				$newfilestat= stat($origfile);

				$diff= array_diff($newfilestat, $filestat);
				unset($diff['8']);
				unset($diff['atime']);
				if (count($diff) === 0) {
					pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "Logfile not modified: $logfile, linecount $linecount");
					return FALSE;
				}
			}
		}
		return TRUE;
	}

	/**
	 * Gets the name of the file in the tmp folder.
	 *
	 * @param string $file File pathname.
	 * @return string Pathname of the tmp file.
	 */
	function GetTmpLogFileName($file)
	{
		if (preg_match('/(.*)\.gz$/', $file, $match)) {
			$file= $this->TmpLogsDir.basename($match[1]);
		}
		else {
			$file= $this->TmpLogsDir.basename($file);
		}
		return $file;
	}

	/**
	 * Copies the original log file to tmp folder.
	 *
	 * @param string $file File pathname.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function UpdateTmpLogFile($file)
	{
		$origfile= $this->GetOrigFileName($file);
		
		if ($this->CopyLogFileToTmp($origfile, $this->TmpLogsDir)) {
			return TRUE;
		}
		pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "Copy failed: $file");
		return FALSE;
	}

	/**
	 * Gets line count of the given log file.
	 *
	 * @param string $file Log file pathname.
	 * @param string $re Regexp to get count of a restricted result set.
	 * @return int Line count.
	 */
	function GetFileLineCount($file, $re= '')
	{
		return Output($this->_getFileLineCount($file, $re));
	}

	function _getFileLineCount($file, $re= '')
	{
	}

	/**
	 * Gets lines in log file.
	 *
	 * @param string $file Log file pathname.
	 * @param int $end Head option, start line.
	 * @param int $count Tail option, page line count.
	 * @param string $re Regexp to restrict the result set.
	 * @return array Log lines.
	 */
	function GetLogs($file, $end, $count, $re= '')
	{
	}

	/**
	 * Gets logs for live logs pages.
	 *
	 * Used to extract lines in last section of the log file or
	 * of the lines grep'd.
	 *
	 * Difference from the archives method is that this one always gets
	 * the tail of the log or grepped lines.
	 *
	 * @param string $file Log file.
	 * @param int $count Tail lenght, page line count.
	 * @param string $re Regexp to restrict the result set.
	 * @return array Log lines.
	 */
	function GetLiveLogs($file, $count, $re= '')
	{
	}

	/**
	 * Gets log files list with start dates.
	 *
	 * Searches the logs directory for all possible archives according to
	 * the default file name.
	 * 
	 * @return array File names with start dates.
	 */
	function GetLogFilesList()
	{
		$file= $this->LogFile;
		$filelist= explode("\n", $this->GetFiles("$file*"));
		asort($filelist);

		$result= array();
		foreach ($filelist as $filepath) {
			$result[$filepath]= $this->_getLogStartDate($filepath);
		}
		return Output(serialize($result));
	}

	/**
	 * Extracts the datetime of the first line in the log file.
	 *
	 * Works only on uncompressed log files.
	 *
	 * @param string $file Log file pathname.
	 * @return string DateTime or a message if the archive is compressed.
	 */
	function GetLogStartDate($file)
	{
		return Output($this->_getLogStartDate($file));
	}

	function _getLogStartDate($file)
	{
		if (preg_match('/.*\.gz$/', $file)) {
			$tmpfile= $this->GetTmpLogFileName($file);
			// Log file may have been rotated, shifting compressed archive file numbers,
			// hence modification check
			if (file_exists($tmpfile) && !$this->IsLogFileModified($tmpfile)) {
				$file= $tmpfile;
			}
		}
		
		if (!preg_match('/.*\.gz$/', $file)) {
			$logline= $this->GetFileFirstLine($file);
			
			$this->ParseLogLine($logline, $cols);
			return $cols['Date'].' '.$cols['Time'];
		}
		return _('Compressed');
	}

	/**
	 * Gets first line of file.
	 *
	 * Used to get the start date of log files.
	 *
	 * @param string $file Log file pathname.
	 * @return string First line in file.
	 */
	function GetFileFirstLine($file)
	{
		$cmd= preg_replace('/<LF>/', $file, $this->CmdLogStart);
		return $this->RunShellCommand($cmd);
	}

	/**
	 * Parses the given log line.
	 *
	 * @param string $logline Log line.
	 * @param array $cols Parsed fields.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function ParseLogLine($logline, &$cols)
	{
	}
	
	/**
	 * Further processes parser output fields.
	 *
	 * Used by statistics collector functions.
	 *
	 * @attention This cannot be done in the parser. Because details of the Link
	 * field is lost, which are needed on log pages.
	 *
	 * @param array $cols Updated parser output.
	 */
	function PostProcessCols(&$cols)
	{
	}

	/**
	 * Prepares file for download over WUI.
	 */
	function PrepareFileForDownload($file)
	{
		$tmpdir= '/var/tmp/pffw/downloads';
		$retval= 0;
		if (!file_exists($tmpdir)) {
			exec("/bin/mkdir -p $tmpdir 2>&1", $output, $retval);
		}
		
		if ($retval === 0) {
			exec("/bin/rm -f $tmpdir/* 2>&1", $output, $retval);
			if ($retval === 0) {
				$tmpfile= "$tmpdir/".basename($file);
				exec("/bin/cp $file $tmpfile 2>&1", $output, $retval);
				if ($retval === 0) {
					exec("/sbin/chown www:www $tmpfile 2>&1", $output, $retval);
					if ($retval === 0) {
						return Output($tmpfile);
					}
				}
			}
		}
		$errout= implode("\n", $output);
		Error($errout);
		pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "FAILED: $errout");
		return FALSE;
	}

	/**
	 * Gets the original file name for the given log file.
	 *
	 * @param string $logfile Log file.
	 * @return string Log file name.
	 */
	function GetOrigFileName($logfile)
	{
		$origfilename= basename($logfile);
		if (basename($this->LogFile) !== $origfilename) {
			$origfilename.= '.gz';
		}
		$origfile= dirname($this->LogFile).'/'.$origfilename;
		
		return $origfile;
	}

	/**
	 * Collects text statistics for the proc stats general page.
	 *
	 * Builds the shell command to count with grep first.
	 * Counts the number of lines in the grep output.
	 * 
	 * @param string $logfile Log file.
	 * @return array Statistics.
	 */
	function GetProcStatLines($logfile)
	{
		global $StatsConf;

		$stats= array();
		foreach ($StatsConf[$this->Name] as $stat => $conf) {
			if (isset($conf['Cmd'])) {
				$cmd= $conf['Cmd'];
				if (isset($conf['Needle'])) {
					$cmd.= ' | /usr/bin/grep -a -E <NDL>';
					$cmd= preg_replace('/<NDL>/', escapeshellarg($conf['Needle']), $cmd);
				}
				$cmd.= ' | /usr/bin/wc -l';
			}
			else if (isset($conf['Needle'])) {
				$cmd= '/usr/bin/grep -a -E <NDL> <LF> | /usr/bin/wc -l';
				$cmd= preg_replace('/<NDL>/', escapeshellarg($conf['Needle']), $cmd);
			}
			if ($logfile == '') {
				$logfile= $this->LogFile;
			}
			$cmd= preg_replace('/<LF>/', $logfile, $cmd);
			
			$stats[$conf['Title']]= trim($this->RunShellCommand($cmd));
		}
		return Output(serialize($stats));
	}

	/**
	 * Uncompresses gzipped log file to tmp dir.
	 * 
	 * @param string $file Log file.
	 * @param string $tmpdir Tmp folder to copy to.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function CopyLogFileToTmp($file, $tmpdir)
	{
		exec("/bin/mkdir -p $tmpdir 2>&1", $output, $retval);
		if ($retval === 0) {
			exec("/bin/cp $file $tmpdir 2>&1", $output, $retval);
			if ($retval === 0) {
				$tmpfile= $tmpdir.basename($file);
				if (preg_match('/(.*)\.gz$/', $tmpfile, $match)) {
					// Delete the old uncompressed file, otherwise gunzip fails
					$this->DeleteFile($match[1]);
					
					exec("/usr/bin/gunzip $tmpfile 2>&1", $output, $retval);
					if ($retval === 0) {
						return TRUE;
					}
					else {
						pffwc_syslog(LOG_ERR, __FILE__, __FUNCTION__, __LINE__, 'gunzip failed: '.$tmpdir.basename($file));
					}
				}
				else {
					return TRUE;
				}
			}
			else {
				pffwc_syslog(LOG_ERR, __FILE__, __FUNCTION__, __LINE__, 'cp failed: '.$file);
			}
		}
		else {
			pffwc_syslog(LOG_ERR, __FILE__, __FUNCTION__, __LINE__, 'mkdir failed: '.$tmpdir);
		}
		Error(implode("\n", $output));
		return FALSE;
	}

	/**
	 * Builds generic grep command and extracts log lines.
	 *
	 * @param string $logfile Log file pathname.
	 * @param int $tail Tail len to get the new log lines to update the stats with.
	 * @return string Log lines.
	 */
	function GetStatsLogLines($logfile, $tail= -1)
	{
		global $StatsConf;

		$cmd= $StatsConf[$this->Name]['Total']['Cmd'];

		if ($tail > -1) {
			$cmd.= " | /usr/bin/tail -$tail";
		}

		$cmd= preg_replace('/<LF>/', $logfile, $cmd);

		return $this->RunShellCommand($cmd);
	}
	
	/**
	 * Gets both brief and full statistics.
	 *
	 * @param string $logfile Log file pathname.
	 * @param bool $collecthours Flag to get hour statistics also.
	 * @return array Statistics in serialized arrays.
	 */
	function GetAllStats($logfile, $collecthours= '')
	{
		$date= serialize(array('Month' => '', 'Day' => ''));
		/// @attention We need $stats return value of GetStats() because of $collecthours constraint
		$stats= $this->_getStats($logfile, $date, $collecthours);
		
		// Do not get $stats here, just $briefstats
		$this->GetSavedStats($logfile, $dummy, $briefstats);
		$briefstats= serialize($briefstats);
		
		// Use serialized stats as array elements to prevent otherwise extra unserialize() for $stats,
		// which is already serialized by GetStat() above.
		// They are ordinary strings now, this serialize() should be quite fast
		return Output(serialize(
				array(
					'stats' 	=> $stats,
					'briefstats'=> $briefstats,
					)
				)
			);
	}
	
	/**
	 * Main statistics collector, as module data set.
	 *
	 * @param string $logfile Log file pathname.
	 * @param array $date Datetime struct.
	 * @param bool $collecthours Flag to collect hour statistics also.
	 * @return array Statistics data set collected.
	 */
	function GetStats($logfile, $date, $collecthours= '')
	{
		return Output($this->_getStats($logfile, $date, $collecthours));
	}

	function _getStats($logfile, $date, $collecthours= '')
	{
		$date= unserialize($date);

		$stats= array();
		$briefstats= array();
		$uptodate= FALSE;
		
		if ($this->IsLogFileModified($logfile)) {
			$this->UpdateTmpLogFile($logfile);
		}
		else {
			$uptodate= $this->GetSavedStats($logfile, $stats, $briefstats);
		}
			
		if (!$uptodate) {
			$this->UpdateStats($logfile, $stats, $briefstats);
		}
				
		if (isset($stats['Date'])) {
			if ($collecthours === '') {
				foreach ($stats['Date'] as $day => $daystats) {
					unset($stats['Date'][$day]['Hours']);
				}
			}

			$re= $this->GetDateRegexp($date);
			foreach ($stats['Date'] as $day => $daystats) {
				if (!preg_match("/$re/", $day)) {
					unset($stats['Date'][$day]);
				}
			}

			$re= $this->GetHourRegexp($date);
			foreach ($stats['Date'] as $day => $daystats) {
				if (isset($daystats['Hours'])) {
					foreach ($daystats['Hours'] as $hour => $hourstats) {
						if (!preg_match("/$re/", $hour)) {
							unset($stats['Date'][$day]['Hours'][$hour]);
						}
					}
				}
			}
		}
		return serialize($stats);
	}

	/**
	 * Gets the number of lines added to log files since last tmp file update.
	 *
	 * @param string $logfile Log file pathname.
	 * @param int $count Number of new log lines.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function CountDiffLogLines($logfile, &$count)
	{
		$count= -1;
			
		if ($this->GetStatsFileInfo($logfile, $oldlinecount, $oldfilestat)) {
			
			$newlinecount= $this->_getFileLineCount($logfile);
			$origfile= $this->GetOrigFileName($logfile);

			if (($newlinecount >= $oldlinecount) && !preg_match('/\.gz$/', $origfile)) {
				pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "Logfile modified: $logfile, linecount $oldlinecount->$newlinecount");

				$count= $newlinecount - $oldlinecount;
				pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "Logfile has grown by $count lines: $logfile");
				return TRUE;
			}
			else {
				// Logs probably rotated, recollect the stats
				// Also stats for compressed files are always recollected on rotation, otherwise stats would be merged with the old stats
				pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "Assuming log file rotation: $logfile");
			}
		}
		else {
			pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "Cannot get file info: $logfile");
		}

		return FALSE;
	}

	/**
	 * Updates statistics incrementally, both brief and full.
	 *
	 * @param string $logfile Log file pathname.
	 * @param array $stats Full stats.
	 * @param array $briefstats Brief stats.
	 */
	function UpdateStats($logfile, &$stats, &$briefstats)
	{
		global $StatsConf;

		$stats= array();
		$briefstats= array();

		// Line count should be obtained here, see SaveStats() for explanation
		$linecount= $this->_getFileLineCount($logfile);

		if ($this->CountDiffLogLines($logfile, $tail)) {
			$this->GetSavedStats($logfile, $stats, $briefstats);
		}
		
		$statsdefs= $StatsConf[$this->Name];
		
		if (isset($statsdefs)) {
			$lines= $this->GetStatsLogLines($logfile, $tail);
			
			if ($lines !== '') {
				$lines= explode("\n", $lines);

				foreach ($lines as $line) {
					unset($values);
					$this->ParseLogLine($line, $values);
	 				// Post-processing modifies link and/or datetime values.
					$this->PostProcessCols($values);

					$this->CollectDayStats($statsdefs, $values, $line, $stats);
				
					$briefstatsdefs= $statsdefs['Total']['BriefStats'];
					
					if (isset($briefstatsdefs)) {
						if (!isset($briefstatsdefs['Date'])) {
							// Always collect Date field
							$briefstatsdefs['Date'] = _('Requests by date');
						}

						// Collect the fields listed under BriefStats
						foreach ($briefstatsdefs as $name => $title) {
							$value= $values[$name];
							if (isset($value)) {
								$briefstats[$name][$value]+= 1;
							}
						}
					}
				}
			}
		}

		$this->SaveStats($logfile, $stats, $briefstats, $linecount);
	}

	/**
	 * Generates date regexp to be used by statistics functions.
	 *
	 * Used to match date indeces of stats array to get stats for date ranges.
	 *
	 * @param array $date Date struct.
	 * @return string Regexp.
	 */
	function GetDateRegexp($date)
	{
	}

	/**
	 * Generates hour regexp to be used by statistics functions.
	 *
	 * @param array $date Date struct.
	 * @return string Regexp.
	 */
	function GetHourRegexp($date)
	{
		if ($date['Hour'] == '') {
			$re= '.*';
		}
		else {
			$re= $date['Hour'];
		}
		return $re;
	}

	/**
	 * Gets saved statistics for the given log file.
	 *
	 * @param string $logfile Log file.
	 * @param array $stats Statistics.
	 * @param array $briefstats Brief statistics.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function GetSavedStats($logfile, &$stats, &$briefstats)
	{
		$statsfile= $this->GetStatsFileName($logfile);
		if (($filecontents= $this->GetFile($statsfile)) !== FALSE) {
			if ($serialized_stats= preg_replace("|^(<filestat>.*</filestat>\s)|m", '', $filecontents)) {
				$allstats= unserialize($serialized_stats);
				if (isset($allstats['stats']) && isset($allstats['briefstats'])) {
					$stats= $allstats['stats'];
					$briefstats= $allstats['briefstats'];
					return TRUE;
				}
				else {
					pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "Missing stats in file: $statsfile");
				}
			}
			else {
				pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "filestat removal failed in file: $statsfile");
			}
		}
		return FALSE;
	}

	/**
	 * Gets previous line count and stat() from statistics file.
	 *
	 * @param string $logfile Log file.
	 * @param int $linecount Previous line count.
	 * @param array $filestat Previous stat() output.
	 * @return bool TRUE on success, FALSE on fail.
	 */
	function GetStatsFileInfo($logfile, &$linecount, &$filestat)
	{
		/// @todo Should check file format too, and delete the stats file if corrupted
		
		$linecount= 0;
		$filestat= array();
		
		$statsfile= $this->GetStatsFileName($logfile);
		if (file_exists($statsfile)) {
			$filestatline= $this->RunShellCommand("/usr/bin/head -1 $statsfile");
			if (preg_match('|^<filestat>(.*)</filestat>$|', $filestatline, $match)) {
				$fileinfo= unserialize($match[1]);
				
				$linecount= $fileinfo['linecount'];
				$filestat= $fileinfo['stat'];
				return TRUE;
			}
			else {
				pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "filestat missing in: $statsfile");
			}
		}
		else {
			pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "No such file: $statsfile");
		}
		return FALSE;
	}

	/**
	 * Gets name of the tmp statistics file for the given log file.
	 *
	 * @param string $logfile Log file.
	 * @return string Statistics file name.
	 */
	function GetStatsFileName($logfile)
	{
		$origfilename= basename($this->GetOrigFileName($logfile));
		
		$statsdir= '/var/tmp/pffw/stats/'.get_class($this);
		$statsfile= "$statsdir/$origfilename";

		return $statsfile;
	}

	/**
	 * Saves collected statistics with the current line count and stat() output.
	 *
	 * @attention Line count should be obtained before statistics collection, otherwise
	 * new lines appended during stats processing may be skipped, hence the $linecount param.
	 *
	 * @param string $logfile Log file.
	 * @param array $stats Statistics.
	 * @param array $briefstats Brief statistics.
	 * @param int $linecount Line count.
	 */
	function SaveStats($logfile, $stats, $briefstats, $linecount)
	{
		$origfile= $this->GetOrigFileName($logfile);
		$statsfile= $this->GetStatsFileName($logfile);
		
		$savestats=
			'<filestat>'.
			serialize(
				array(
					'linecount'	=> $linecount,
					'stat'		=> stat($origfile),
					)
			).
			'</filestat>'."\n".
			serialize(
				array(
					'stats' 	=> $stats,
					'briefstats'=> $briefstats,
					)
			);
		
		$statsdir= dirname($statsfile);
		if (!file_exists($statsdir)) {
			exec('/bin/mkdir -p '.$statsdir);
		}
		
		exec('/usr/bin/touch '.$statsfile);
		$this->PutFile($statsfile, $savestats);
		pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, "Saved stats to: $statsfile");
	}
	
	/**
	 * Day statistics collector.
	 *
	 * $statsdefs has all the information to collect what data.
	 *
	 * If parsed log time does not have an appropriate hour/min, then 12:00 is assumed.
	 *
	 * @todo How is it possible that Time does not have hour/min? Should have a module
	 * Time field processor as well?
	 * 
	 * @param array $statsdefs Module stats section of $StatsConf.
	 * @param array $values Log fields parsed by caller function.
	 * @param string $line Current log line needed to search for keywords.
	 * @param array $stats Statistics data set collected.
	 *
	 */
	function CollectDayStats($statsdefs, $values, $line, &$stats)
	{
		$re= '/^(\d+):(\d+):(\d+)$/';
		if (preg_match($re, $values['Time'], $match)) {
			$hour= $match[1];
			$min= $match[2];
		}
		else {
			// Should be unreachable
			$hour= '12';
			$min= '00';
			pffwc_syslog(LOG_DEBUG, __FILE__, __FUNCTION__, __LINE__, 'There was no Time in log values, defaulting to 12:00');
		}

		$daystats= &$stats['Date'][$values['Date']];
		$this->IncStats($line, $values, $statsdefs, $daystats);

		$this->CollectHourStats($statsdefs, $hour, $min, $values, $line, $daystats);
	}

	/**
	 * Hour statistics collector.
	 *
	 * $statsdefs has all the information to collect what data.
	 *
	 * $daystats is the subsection of the main $stats array for the current date.
	 *
	 * @param array $statsdefs Module stats section of $StatsConf.
	 * @param string $hour Hour to collect stats for.
	 * @param string $min Min to collect stats for, passed to CollectMinuteStats().
	 * @param array $values Log fields parsed by caller function.
	 * @param string $line Current log line needed to search for keywords.
	 * @param array $daystats Statistics data set collected.
	 */
	function CollectHourStats($statsdefs, $hour, $min, $values, $line, &$daystats)
	{
		$hourstats= &$daystats['Hours'][$hour];
		$this->IncStats($line, $values, $statsdefs, $hourstats);

		$this->CollectMinuteStats($statsdefs, $min, $values, $line, $hourstats);
	}
	
	/**
	 * Increments stats for the given values.
	 * 
	 * @param string $line Current log line needed to search for keywords.
	 * @param array $values Log fields parsed by caller function.
	 * @param array $statsdefs Module stats section of $StatsConf.
	 * @param array $stats Statistics data set collected.
	 */
	function IncStats($line, $values, $statsdefs, &$stats)
	{
		$stats['Sum']+= 1;

		if (isset($statsdefs['Total']['Counters'])) {
			foreach ($statsdefs['Total']['Counters'] as $counter => $conf) {
				$value= $values[$conf['Field']];
				if (isset($value)) {
					$stats[$counter]['Sum']+= $value;

					foreach ($conf['NVPs'] as $name => $title) {
						if (isset($values[$name])) {
							$stats[$counter][$name][$values[$name]]+= $value;
						}
					}
				}
			}
		}

		foreach ($statsdefs as $stat => $conf) {
			if (isset($conf['Needle'])) {
				if (preg_match('/'.$conf['Needle'].'/', $line)) {
					$stats[$stat]['Sum']+= 1;

					foreach ($conf['NVPs'] as $name => $title) {
						if (isset($values[$name])) {
							$stats[$stat][$name][$values[$name]]+= 1;
						}
					}
				}
			}
		}
	}

	/**
	 * Minute statistics collector.
	 *
	 * $statsdefs has all the information to collect what data.
	 *
	 * $hourstats is the subsection of the $stats array for the current hour.
	 * 
	 * @param array $statsdefs Module stats section of $StatsConf.
	 * @param string $min Min to collect stats for, passed to CollectMinuteStats().
	 * @param array $values Log fields parsed by caller function.
	 * @param string $line Current log line needed to search for keywords.
	 * @param array $hourstats Statistics data set collected.
	 */
	function CollectMinuteStats($statsdefs, $min, $values, $line, &$hourstats)
	{
		$minstats= &$hourstats['Mins'][$min];
		$minstats['Sum']+= 1;

		if (isset($statsdefs['Total']['Counters'])) {
			foreach ($statsdefs['Total']['Counters'] as $counter => $conf) {
				if (isset($values[$conf['Field']])) {
					$minstats[$counter]+= $values[$conf['Field']];
				}
			}
		}

		foreach ($statsdefs as $stat => $conf) {
			if (isset($conf['Needle'])) {
				if (preg_match('/'.$conf['Needle'].'/', $line)) {
					$minstats[$stat]+= 1;
				}
			}
		}
	}
}
?>
