<?php

/**
 * ProjectGLS
 *
 * @copyright 2013 ProjectGLS
 * @license http://next.mmobrowser.com/projectgls/license.txt
 * @version 1.0 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...'); 

function getSidebarSettings()
{
	global $smcFunc;
	
	$request = $smcFunc['db_query']('', '
		SELECT name, value
		FROM {db_prefix}sidebar_settings',
		array(
		)
	);
	$sidebar_settings = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$sidebar_settings[$row['name']] = $row['value'];
	$smcFunc['db_free_result']($request);
	
	return $sidebar_settings;
}