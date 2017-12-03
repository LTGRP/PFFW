<?php
/*
 * Copyright (C) 2004-2017 Soner Tari
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

require_once('../lib/vars.php');

$Menu = array(
    'info' => array(
        'Name' => _MENU('Info'),
        'Perms' => $ALL_USERS,
		),
    'logs' => array(
        'Name' => _MENU('Logs'),
        'Perms' => $ALL_USERS,
		'SubMenu' => array(
			'archives' => _MENU('Archives'),
			'live' => _MENU('Live'),
			),
		),
	);

$LogConf = array(
    'monitoring' => array(
        'Fields' => array(
            'Date' => _TITLE('Date'),
            'Time' => _TITLE('Time'),
            'Process' => _TITLE('Process'),
            'Prio' => _TITLE('Prio'),
            'Log' => _TITLE('Log'),
    		),
		),
	);

class Monitoring extends View
{
	public $Model= 'monitoring';
	
	function __construct()
	{
		$this->Module= basename(dirname($_SERVER['PHP_SELF']));
		$this->Caption= _TITLE('Symon');
		$this->LogsHelpMsg= _HELPWINDOW('All monitoring processes write to the same log file.');
	}
}

$View= new Monitoring();
?>
