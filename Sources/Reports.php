<?php

/**
 * ProjectGLS
 *
 * @copyright 2013 ProjectGLS
 * @license http://next.mmobrowser.com/projectgls/license.txt
 *
 * This file is exclusively for generating reports to help assist forum
 * administrators keep track of their forum configuration and state. The
 * core report generation is done in two areas. Firstly, a report "generator"
 * will fill context with relevant data. Secondly, the choice of sub-template
 * will determine how this data is shown to the user
 *
 * Functions ending with "Report" are responsible for generating data for reporting.
 * They are all called from ReportsMain.
 * Never access the context directly, but use the data handling functions to do so.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 1.0 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...'); 

/**
 * Handling function for generating reports.
 * Requires the admin_forum permission.
 * Loads the Reports template and language files.
 * Decides which type of report to generate, if this isn't passed
 * through the querystring it will set the report_type sub-template to
 * force the user to choose which type.
 * When generating a report chooses which sub_template to use.
 * Depends on the cal_enabled setting, and many of the other cal_
 * settings.
 * Will call the relevant report generation function.
 * If generating report will call finishTables before returning.
 * Accessed through ?action=admin;area=reports.
 */
function ReportsMain()
{
	global $txt, $context, $scripturl;

	// Only admins, only EVER admins!
	isAllowedTo('admin_forum');

	// Let's get our things running...
	loadTemplate('Reports');
	loadLanguage('Reports');

	$context['page_title'] = $txt['generate_reports'];

	// These are the types of reports which exist - and the functions to generate them.
	$context['report_types'] = array(
		'member_groups' => 'MemberGroupsReport',
		'group_perms' => 'GroupPermissionsReport',
		'staff' => 'StaffReport',
	);

	call_integration_hook('integrate_report_types');

	$is_first = 0;
	foreach ($context['report_types'] as $k => $temp)
		$context['report_types'][$k] = array(
			'id' => $k,
			// @todo what is $type? It is never set!
			'title' => isset($txt['gr_type_' . $k]) ? $txt['gr_type_' . $k] : $type['id'],
			'description' => isset($txt['gr_type_desc_' . $k]) ? $txt['gr_type_desc_' . $k] : null,
			'function' => $temp,
			'is_first' => $is_first++ == 0,
		);

	// If they haven't choosen a report type which is valid, send them off to the report type chooser!
	if (empty($_REQUEST['rt']) || !isset($context['report_types'][$_REQUEST['rt']]))
	{
		$context['sub_template'] = 'report_type';
		return;
	}
	$context['report_type'] = $_REQUEST['rt'];

	// What are valid templates for showing reports?
	$reportTemplates = array(
		'main' => array(
			'layers' => null,
		),
	);

	// Specific template? Use that instead of main!
	if (isset($_REQUEST['st']) && isset($reportTemplates[$_REQUEST['st']]))
		$context['sub_template'] = $_REQUEST['st'];

	// Make the page title more descriptive.
	$context['page_title'] .= ' - ' . (isset($txt['gr_type_' . $context['report_type']]) ? $txt['gr_type_' . $context['report_type']] : $context['report_type']);

	// Build the reports button array.
	$context['report_buttons'] = array(
		'generate_reports' => array('text' => 'generate_reports', 'image' => 'print.png', 'lang' => true, 'url' => $scripturl . '?action=admin;area=reports', 'active' => true),
		'print' => array('text' => 'print', 'image' => 'print.png', 'lang' => true, 'url' => $scripturl . '?action=admin;area=reports;rt=' . $context['report_type']. ';st=print', 'custom' => 'target="_blank"'),
	);

	// Allow mods to add additional buttons here
	call_integration_hook('integrate_report_buttons');

	// Now generate the data.
	$context['report_types'][$context['report_type']]['function']();

	// Finish the tables before exiting - this is to help the templates a little more.
	finishTables();
}

/**
 * Show what the membergroups are made of.
 * functions ending with "Report" are responsible for generating data
 * for reporting.
 * they are all called from ReportsMain.
 * never access the context directly, but use the data handling
 * functions to do so.
 */
function MemberGroupsReport()
{
	global $txt, $settings, $smcFunc;

	// Standard settings.
	$mgSettings = array(
		'name' => '',
		'#sep#1' => $txt['member_group_settings'],
		'color' => $txt['member_group_color'],
		'min_posts' => $txt['member_group_min_posts'],
		'max_messages' => $txt['member_group_max_messages'],
		'icons' => $txt['member_group_icons'],
		'#sep#2' => $txt['member_group_access'],
	);

	// Add all the membergroup settings, plus we'll be adding in columns!
	setKeys('cols', $mgSettings);

	// Only one table this time!
	newTable($txt['gr_type_member_groups'], '-', 'all', 100, 'center', 200, 'left');

	// Get the shaded column in.
	addData($mgSettings);

	// Now start cycling the membergroups!
	$request = $smcFunc['db_query']('', '
		SELECT mg.id_group, mg.group_name, mg.online_color, mg.min_posts, mg.max_messages, mg.icons,
			CASE WHEN p.permission IS NOT NULL OR mg.id_group = {int:admin_group} THEN 1 ELSE 0 END AS can_moderate
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}permissions AS p ON (p.id_group = mg.id_group AND p.permission = {string:moderate_board})
		ORDER BY mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
		array(
			'admin_group' => 1,
			'default_profile' => 1,
			'newbie_group' => 4,
			'moderate_board' => 'moderate_board',
		)
	);

	// Cache them so we get regular members too.
	$rows = array(
		array(
			'id_group' => -1,
			'group_name' => $txt['membergroups_guests'],
			'online_color' => '',
			'min_posts' => -1,
			'max_messages' => null,
			'icons' => ''
		),
		array(
			'id_group' => 0,
			'group_name' => $txt['membergroups_members'],
			'online_color' => '',
			'min_posts' => -1,
			'max_messages' => null,
			'icons' => ''
		),
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$rows[] = $row;
	$smcFunc['db_free_result']($request);

	foreach ($rows as $row)
	{
		$row['icons'] = explode('#', $row['icons']);

		$group = array(
			'name' => $row['group_name'],
			'color' => empty($row['online_color']) ? '-' : '<span style="color: ' . $row['online_color'] . ';">' . $row['online_color'] . '</span>',
			'min_posts' => $row['min_posts'] == -1 ? 'N/A' : $row['min_posts'],
			'max_messages' => $row['max_messages'],
			'icons' => !empty($row['icons'][0]) && !empty($row['icons'][1]) ? str_repeat('<img src="' . $settings['images_url'] . '/' . $row['icons'][1] . '" alt="*" />', $row['icons'][0]) : '',
		);

		addData($group);
	}
}

/**
 * Show the large variety of group permissions assigned to each membergroup.
 * functions ending with "Report" are responsible for generating data
 * for reporting.
 * they are all called from ReportsMain.
 * never access the context directly, but use the data handling
 * functions to do so.
 */
function GroupPermissionsReport()
{
	global $txt, $modSettings, $smcFunc;

	if (isset($_REQUEST['groups']))
	{
		if (!is_array($_REQUEST['groups']))
			$_REQUEST['groups'] = explode(',', $_REQUEST['groups']);
		foreach ($_REQUEST['groups'] as $k => $dummy)
			$_REQUEST['groups'][$k] = (int) $dummy;
		$_REQUEST['groups'] = array_diff($_REQUEST['groups'], array(3));

		$clause = 'id_group IN ({array_int:groups})';
	}
	else
		$clause = '1=1';

	// Get all the possible membergroups, except admin!
	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE ' . $clause . '
			AND id_group != {int:admin_group}' . (empty($modSettings['permission_enable_postgroups']) ? '
			AND min_posts = {int:min_posts}' : '') . '
		ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
		array(
			'admin_group' => 1,
			'min_posts' => -1,
			'newbie_group' => 4,
			'groups' => isset($_REQUEST['groups']) ? $_REQUEST['groups'] : array(),
		)
	);
	if (!isset($_REQUEST['groups']) || in_array(-1, $_REQUEST['groups']) || in_array(0, $_REQUEST['groups']))
		$groups = array('col' => '', -1 => $txt['membergroups_guests'], 0 => $txt['membergroups_members']);
	else
		$groups = array('col' => '');
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$groups[$row['id_group']] = $row['group_name'];
	$smcFunc['db_free_result']($request);

	// Make sure that every group is represented!
	setKeys('rows', $groups);

	// Create the table first.
	newTable($txt['gr_type_group_perms'], '-', 'all', 100, 'center', 200, 'left');

	// Show all the groups
	addData($groups);

	// Now the big permission fetch!
	$request = $smcFunc['db_query']('', '
		SELECT id_group, add_deny, permission
		FROM {db_prefix}permissions
		WHERE ' . $clause . (empty($modSettings['permission_enable_deny']) ? '
			AND add_deny = {int:not_denied}' : '') . '
		ORDER BY permission',
		array(
			'not_denied' => 1,
			'groups' => isset($_REQUEST['groups']) ? $_REQUEST['groups'] : array(),
		)
	);
	$lastPermission = null;
	$curData = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// If this is a new permission flush the last row.
		if ($row['permission'] != $lastPermission)
		{
			// Send the data!
			if ($lastPermission !== null)
				addData($curData);

			// Add the permission name in the left column.
			$curData = array('col' => isset($txt['group_perms_name_' . $row['permission']]) ? $txt['group_perms_name_' . $row['permission']] : $row['permission']);

			$lastPermission = $row['permission'];
		}

		// Good stuff - add the permission to the list!
		if ($row['add_deny'])
			$curData[$row['id_group']] = '<span style="color: darkgreen;">' . $txt['group_perms_allow'] . '</span>';
		else
			$curData[$row['id_group']] = '<span style="color: red;">' . $txt['group_perms_deny'] . '</span>';
	}
	$smcFunc['db_free_result']($request);

	// Flush the last data!
	addData($curData);
}

/**
 * Report for showing all the forum staff members - quite a feat!
 * functions ending with "Report" are responsible for generating data
 * for reporting.
 * they are all called from ReportsMain.
 * never access the context directly, but use the data handling
 * functions to do so.
 */
function StaffReport()
{
	global $sourcedir, $txt, $smcFunc;

	require_once($sourcedir . '/Subs-Members.php');

	// Get a list of global moderators (i.e. members with moderation powers).
	$global_mods = array_intersect(membersAllowedTo('moderate_board', 0), membersAllowedTo('approve_posts', 0), membersAllowedTo('remove_any', 0), membersAllowedTo('modify_any', 0));

	// How about anyone else who is special?
	$allStaff = array_merge(membersAllowedTo('admin_forum'), membersAllowedTo('manage_membergroups'), membersAllowedTo('manage_permissions'), $global_mods);

	// Make sure everyone is there once - no admin less important than any other!
	$allStaff = array_unique($allStaff);

	// This is a bit of a cop out - but we're protecting their forum, really!
	if (count($allStaff) > 300)
		fatal_lang_error('report_error_too_many_staff');

	// Get all the possible membergroups!
	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name, online_color
		FROM {db_prefix}membergroups',
		array(
		)
	);
	$groups = array(0 => $txt['full_member']);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$groups[$row['id_group']] = empty($row['online_color']) ? $row['group_name'] : '<span style="color: ' . $row['online_color'] . '">' . $row['group_name'] . '</span>';
	$smcFunc['db_free_result']($request);

	// All the fields we'll show.
	$staffSettings = array(
		'position' => $txt['report_staff_position'],
		'moderates' => $txt['report_staff_moderates'],
		'posts' => $txt['report_staff_posts'],
		'last_login' => $txt['report_staff_last_login'],
	);

	// Do it in columns, it's just easier.
	setKeys('cols');

	// Get each member!
	$request = $smcFunc['db_query']('', '
		SELECT id_member, real_name, id_group, posts, last_login
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:staff_list})
		ORDER BY real_name',
		array(
			'staff_list' => $allStaff,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Each member gets their own table!.
		newTable($row['real_name'], '', 'left', 'auto', 'left', 200, 'center');

		// First off, add in the side key.
		addData($staffSettings);

		// Create the main data array.
		$staffData = array(
			'position' => isset($groups[$row['id_group']]) ? $groups[$row['id_group']] : $groups[0],
			'posts' => $row['posts'],
			'last_login' => timeformat($row['last_login']),
			'moderates' => array(),
		);

		// What do they moderate?
		if (in_array($row['id_member'], $global_mods))
			$staffData['moderates'] = '<em>' . $txt['report_staff_all_boards'] . '</em>';
		else
			$staffData['moderates'] = '<em>' . $txt['report_staff_no_boards'] . '</em>';

		// Next add the main data.
		addData($staffData);
	}
	$smcFunc['db_free_result']($request);
}

/**
 * This function creates a new table of data, most functions will only use it once.
 * The core of this file, it creates a new, but empty, table of data in
 * context, ready for filling using addData().
 * Fills the context variable current_table with the ID of the table created.
 * Keeps track of the current table count using context variable table_count.
 *
 * @param string $title = '' Title to be displayed with this data table.
 * @param string $default_value = '' Value to be displayed if a key is missing from a row.
 * @param string $shading = 'all' Should the left, top or both (all) parts of the table beshaded?
 * @param string $width_normal = 'auto' width of an unshaded column (auto means not defined).
 * @param string $align_normal = 'center' alignment of data in an unshaded column.
 * @param string $width_shaded = 'auto' width of a shaded column (auto means not defined).
 * @param string $align_shaded = 'auto' alignment of data in a shaded column.
 */
function newTable($title = '', $default_value = '', $shading = 'all', $width_normal = 'auto', $align_normal = 'center', $width_shaded = 'auto', $align_shaded = 'auto')
{
	global $context;

	// Set the table count if needed.
	if (empty($context['table_count']))
		$context['table_count'] = 0;

	// Create the table!
	$context['tables'][$context['table_count']] = array(
		'title' => $title,
		'default_value' => $default_value,
		'shading' => array(
			'left' => $shading == 'all' || $shading == 'left',
			'top' => $shading == 'all' || $shading == 'top',
		),
		'width' => array(
			'normal' => $width_normal,
			'shaded' => $width_shaded,
		),
		'align' => array(
			'normal' => $align_normal,
			'shaded' => $align_shaded,
		),
		'data' => array(),
	);

	$context['current_table'] = $context['table_count'];

	// Increment the count...
	$context['table_count']++;
}

/**
 * Adds an array of data into an existing table.
 * if there are no existing tables, will create one with default
 * attributes.
 * if custom_table isn't specified, it will use the last table created,
 * if it is specified and doesn't exist the function will return false.
 * if a set of keys have been specified, the function will check each
 * required key is present in the incoming data. If this data is missing
 * the current tables default value will be used.
 * if any key in the incoming data begins with '#sep#', the function
 * will add a separator accross the table at this point.
 * once the incoming data has been sanitized, it is added to the table.
 *
 * @param array $inc_data
 * @param int $custom_table = null
 */
function addData($inc_data, $custom_table = null)
{
	global $context;

	// No tables? Create one even though we are probably already in a bad state!
	if (empty($context['table_count']))
		newTable();

	// Specific table?
	if ($custom_table !== null && !isset($context['tables'][$custom_table]))
		return false;
	elseif ($custom_table !== null)
		$table = $custom_table;
	else
		$table = $context['current_table'];

	// If we have keys, sanitise the data...
	if (!empty($context['keys']))
	{
		// Basically, check every key exists!
		foreach ($context['keys'] as $key => $dummy)
		{
			$data[$key] = array(
				'v' => empty($inc_data[$key]) ? $context['tables'][$table]['default_value'] : $inc_data[$key],
			);
			// Special "hack" the adding separators when doing data by column.
			if (substr($key, 0, 5) == '#sep#')
				$data[$key]['separator'] = true;
		}
	}
	else
	{
		$data = $inc_data;
		foreach ($data as $key => $value)
		{
			$data[$key] = array(
				'v' => $value,
			);
			if (substr($key, 0, 5) == '#sep#')
				$data[$key]['separator'] = true;
		}
	}

	// Is it by row?
	if (empty($context['key_method']) || $context['key_method'] == 'rows')
	{
		// Add the data!
		$context['tables'][$table]['data'][] = $data;
	}
	// Otherwise, tricky!
	else
	{
		foreach ($data as $key => $item)
			$context['tables'][$table]['data'][$key][] = $item;
	}
}

/**
 * Add a separator row, only really used when adding data by rows.
 *
 * @param string $title = ''
 * @param string $custom_table = null
 *
 * @return boolean returns false if there are no tables
 */
function addSeparator($title = '', $custom_table = null)
{
	global $context;

	// No tables - return?
	if (empty($context['table_count']))
		return;

	// Specific table?
	if ($custom_table !== null && !isset($context['tables'][$table]))
		return false;
	elseif ($custom_table !== null)
		$table = $custom_table;
	else
		$table = $context['current_table'];

	// Plumb in the separator
	$context['tables'][$table]['data'][] = array(0 => array(
		'separator' => true,
		'v' => $title
	));
}

/**
 * This does the necessary count of table data before displaying them.
 * is (unfortunately) required to create some useful variables for templates.
 * foreach data table created, it will count the number of rows and
 * columns in the table.
 * will also create a max_width variable for the table, to give an
 * estimate width for the whole table * * if it can.
 */
function finishTables()
{
	global $context;

	if (empty($context['tables']))
		return;

	// Loop through each table counting up some basic values, to help with the templating.
	foreach ($context['tables'] as $id => $table)
	{
		$context['tables'][$id]['id'] = $id;
		$context['tables'][$id]['row_count'] = count($table['data']);
		$curElement = current($table['data']);
		$context['tables'][$id]['column_count'] = count($curElement);

		// Work out the rough width - for templates like the print template. Without this we might get funny tables.
		if ($table['shading']['left'] && $table['width']['shaded'] != 'auto' && $table['width']['normal'] != 'auto')
			$context['tables'][$id]['max_width'] = $table['width']['shaded'] + ($context['tables'][$id]['column_count'] - 1) * $table['width']['normal'];
		elseif ($table['width']['normal'] != 'auto')
			$context['tables'][$id]['max_width'] = $context['tables'][$id]['column_count'] * $table['width']['normal'];
		else
			$context['tables'][$id]['max_width'] = 'auto';
	}
}

/**
 * Set the keys in use by the tables - these ensure entries MUST exist if the data isn't sent.
 *
 * sets the current set of "keys" expected in each data array passed to
 * addData. It also sets the way we are adding data to the data table.
 * method specifies whether the data passed to addData represents a new
 * column, or a new row.
 * keys is an array whose keys are the keys for data being passed to
 * addData().
 * if reverse is set to true, then the values of the variable "keys"
 * are used as oppossed to the keys(!
 *
 * @param string $method = 'rows' rows or cols
 * @param array $keys = array()
 * @param bool $reverse = false
 */
function setKeys($method = 'rows', $keys = array(), $reverse = false)
{
	global $context;

	// Do we want to use the keys of the keys as the keys? :P
	if ($reverse)
		$context['keys'] = array_flip($keys);
	else
		$context['keys'] = $keys;

	// Rows or columns?
	$context['key_method'] = $method == 'rows' ? 'rows' : 'cols';
}

?>