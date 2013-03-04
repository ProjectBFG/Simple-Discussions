<?php

/**
 * ManagePermissions handles all possible permission stuff.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Dispaches to the right function based on the given subaction.
 * Checks the permissions, based on the sub-action.
 * Called by ?action=managepermissions.
 *
 * @uses ManagePermissions language file.
 */

function ModifyPermissions()
{
	global $txt, $scripturl, $context;

	loadLanguage('ManagePermissions+ManageMembers');
	loadTemplate('ManagePermissions');

	// Format: 'sub-action' => array('function_to_call', 'permission_needed'),
	$subActions = array(
		'index' => array('PermissionIndex', 'manage_permissions'),
		'modify' => array('ModifyMembergroup', 'manage_permissions'),
		'modify2' => array('ModifyMembergroup2', 'manage_permissions'),
		'quick' => array('SetQuickGroups', 'manage_permissions'),
		/* @todoy: yeni bir emre kadar kaldýrýldý
		'postmod' => array('ModifyPostModeration', 'manage_permissions', 'disabled' => !in_array('pm', $context['admin_features'])), */
		'settings' => array('GeneralPermissionSettings', 'admin_forum'),
	);

	call_integration_hook('integrate_manage_permissions', array($subActions));

	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) && empty($subActions[$_REQUEST['sa']]['disabled']) ? $_REQUEST['sa'] : (allowedTo('manage_permissions') ? 'index' : 'settings');
	isAllowedTo($subActions[$_REQUEST['sa']][1]);

	// Create the tabs for the template.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['permissions_title'],
		'description' => '',
		'tabs' => array(
			'index' => array(
				'description' => $txt['permissions_groups'],
			),
			/* @todoy: yeni bir emre kadar kaldýrýldý
			'postmod' => array(
				'description' => $txt['permissions_post_moderation_desc'],
			), */
			'settings' => array(
				'description' => $txt['permission_settings_desc'],
			),
		),
	);

	$subActions[$_REQUEST['sa']][0]();
}

/**
 * Sets up the permissions by membergroup index page.
 * Called by ?action=managepermissions
 * Creates an array of all the groups with the number of members and permissions.
 *
 * @uses ManagePermissions language file.
 * @uses ManagePermissions template file.
 * @uses ManageBoards template, permission_index sub-template.
 */
function PermissionIndex()
{
	global $txt, $scripturl, $context, $settings, $modSettings, $smcFunc;

	$context['page_title'] = $txt['permissions_title'];

	// Load all the permissions. We'll need them in the template.
	loadAllPermissions();

	// Are we going to show the advanced options?
	$context['show_advanced_options'] = empty($context['admin_preferences']['app']);

	// Determine the number of ungrouped members.
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}members
		WHERE id_group = {int:regular_group}',
		array(
			'regular_group' => 0,
		)
	);
	list ($num_members) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Fill the context variable with 'Guests' and 'Regular Members'.
	$context['groups'] = array(
		-1 => array(
			'id' => -1,
			'name' => $txt['membergroups_guests'],
			'num_members' => $txt['membergroups_guests_na'],
			'allow_delete' => false,
			'allow_modify' => true,
			'can_search' => false,
			'href' => '',
			'link' => '',
			'is_post_group' => false,
			'color' => '',
			'icons' => '',
			'children' => array(),
			'num_permissions' => array(
				'allowed' => 0,
				// Can't deny guest permissions!
				'denied' => '(' . $txt['permissions_none'] . ')'
			),
			'access' => false
		),
		0 => array(
			'id' => 0,
			'name' => $txt['membergroups_members'],
			'num_members' => $num_members,
			'allow_delete' => false,
			'allow_modify' => true,
			'can_search' => false,
			'href' => $scripturl . '?action=moderate;area=viewgroups;sa=members;group=0',
			'is_post_group' => false,
			'color' => '',
			'icons' => '',
			'children' => array(),
			'num_permissions' => array(
				'allowed' => 0,
				'denied' => 0
			),
			'access' => false
		),
	);

	$postGroups = array();
	$normalGroups = array();

	// Query the database defined membergroups.
	$query = $smcFunc['db_query']('', '
		SELECT id_group, id_parent, group_name, min_posts, online_color, icons
		FROM {db_prefix}membergroups' . (empty($modSettings['permission_enable_postgroups']) ? '
		WHERE min_posts = {int:min_posts}' : '') . '
		ORDER BY id_parent = {int:not_inherited} DESC, min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
		array(
			'min_posts' => -1,
			'not_inherited' => -2,
			'newbie_group' => 4,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($query))
	{
		// If it's inherited, just add it as a child.
		if ($row['id_parent'] != -2)
		{
			if (isset($context['groups'][$row['id_parent']]))
				$context['groups'][$row['id_parent']]['children'][$row['id_group']] = $row['group_name'];
			continue;
		}

		$row['icons'] = explode('#', $row['icons']);
		$context['groups'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'num_members' => $row['id_group'] != 3 ? 0 : $txt['membergroups_guests_na'],
			'allow_delete' => $row['id_group'] > 4,
			'allow_modify' => $row['id_group'] > 1,
			'can_search' => $row['id_group'] != 3,
			'href' => $scripturl . '?action=moderate;area=viewgroups;sa=members;group=' . $row['id_group'],
			'is_post_group' => $row['min_posts'] != -1,
			'color' => empty($row['online_color']) ? '' : $row['online_color'],
			'icons' => !empty($row['icons'][0]) && !empty($row['icons'][1]) ? str_repeat('<img src="' . $settings['images_url'] . '/' . $row['icons'][1] . '" alt="*" />', $row['icons'][0]) : '',
			'children' => array(),
			'num_permissions' => array(
				'allowed' => $row['id_group'] == 1 ? '(' . $txt['permissions_all'] . ')' : 0,
				'denied' => $row['id_group'] == 1 ? '(' . $txt['permissions_none'] . ')' : 0
			),
			'access' => false,
		);

		if ($row['min_posts'] == -1)
			$normalGroups[$row['id_group']] = $row['id_group'];
		else
			$postGroups[$row['id_group']] = $row['id_group'];
	}
	$smcFunc['db_free_result']($query);

	// Get the number of members in this post group.
	if (!empty($postGroups))
	{
		$query = $smcFunc['db_query']('', '
			SELECT id_post_group AS id_group, COUNT(*) AS num_members
			FROM {db_prefix}members
			WHERE id_post_group IN ({array_int:post_group_list})
			GROUP BY id_post_group',
			array(
				'post_group_list' => $postGroups,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($query))
			$context['groups'][$row['id_group']]['num_members'] += $row['num_members'];
		$smcFunc['db_free_result']($query);
	}

	if (!empty($normalGroups))
	{
		// First, the easy one!
		$query = $smcFunc['db_query']('', '
			SELECT id_group, COUNT(*) AS num_members
			FROM {db_prefix}members
			WHERE id_group IN ({array_int:normal_group_list})
			GROUP BY id_group',
			array(
				'normal_group_list' => $normalGroups,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($query))
			$context['groups'][$row['id_group']]['num_members'] += $row['num_members'];
		$smcFunc['db_free_result']($query);

		// This one is slower, but it's okay... careful not to count twice!
		$query = $smcFunc['db_query']('', '
			SELECT mg.id_group, COUNT(*) AS num_members
			FROM {db_prefix}membergroups AS mg
				INNER JOIN {db_prefix}members AS mem ON (mem.additional_groups != {string:blank_string}
					AND mem.id_group != mg.id_group
					AND FIND_IN_SET(mg.id_group, mem.additional_groups) != 0)
			WHERE mg.id_group IN ({array_int:normal_group_list})
			GROUP BY mg.id_group',
			array(
				'normal_group_list' => $normalGroups,
				'blank_string' => '',
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($query))
			$context['groups'][$row['id_group']]['num_members'] += $row['num_members'];
		$smcFunc['db_free_result']($query);
	}

	foreach ($context['groups'] as $id => $data)
	{
		if ($data['href'] != '')
			$context['groups'][$id]['link'] = '<a href="' . $data['href'] . '">' . $data['num_members'] . '</a>';
	}

        $request = $smcFunc['db_query']('', '
                SELECT id_group, COUNT(*) AS num_permissions, add_deny
                FROM {db_prefix}permissions
                ' . (empty($context['hidden_permissions']) ? '' : ' WHERE permission NOT IN ({array_string:hidden_permissions})') . '
                GROUP BY id_group, add_deny',
                array(
                        'hidden_permissions' => !empty($context['hidden_permissions']) ? $context['hidden_permissions'] : array(),
                )
        );
        while ($row = $smcFunc['db_fetch_assoc']($request))
                if (isset($context['groups'][(int) $row['id_group']]) && (!empty($row['add_deny']) || $row['id_group'] != -1))
                        $context['groups'][(int) $row['id_group']]['num_permissions'][empty($row['add_deny']) ? 'denied' : 'allowed'] = $row['num_permissions'];
        $smcFunc['db_free_result']($request);

	// Load the proper template.
	$context['sub_template'] = 'permission_index';
	createToken('admin-mpq');
}
/**
 * Handles permission modification actions from the upper part of the
 * permission manager index.
 */
function SetQuickGroups()
{
	global $context, $smcFunc;

	checkSession();
	validateToken('admin-mpq', 'quick');

	loadIllegalPermissions();
	loadIllegalGuestPermissions();

	// Make sure only one of the quick options was selected.
	if ((!empty($_POST['predefined']) && ((isset($_POST['copy_from']) && $_POST['copy_from'] != 'empty') || !empty($_POST['permissions']))) || (!empty($_POST['copy_from']) && $_POST['copy_from'] != 'empty' && !empty($_POST['permissions'])))
		fatal_lang_error('permissions_only_one_option', false);

	if (empty($_POST['group']) || !is_array($_POST['group']))
		$_POST['group'] = array();

	// Only accept numeric values for selected membergroups.
	foreach ($_POST['group'] as $id => $group_id)
		$_POST['group'][$id] = (int) $group_id;
	$_POST['group'] = array_unique($_POST['group']);

	// Clear out any cached authority.
	updateSettings(array('settings_updated' => time()));

	// No groups where selected.
	if (empty($_POST['group']))
		redirectexit('action=admin;area=permissions');

	// Set a predefined permission profile.
	if (!empty($_POST['predefined']))
	{
		// Make sure it's a predefined permission set we expect.
		if (!in_array($_POST['predefined'], array('restrict', 'standard', 'moderator', 'maintenance')))
			redirectexit('action=admin;area=permissions');

		foreach ($_POST['group'] as $group_id)
			setPermissionLevel($_POST['predefined'], $group_id);
	}
	// Set a permission profile based on the permissions of a selected group.
	elseif ($_POST['copy_from'] != 'empty')
	{
		// Just checking the input.
		if (!is_numeric($_POST['copy_from']))
			redirectexit('action=admin;area=permissions');

		// Make sure the group we're copying to is never included.
		$_POST['group'] = array_diff($_POST['group'], array($_POST['copy_from']));

		// No groups left? Too bad.
		if (empty($_POST['group']))
			redirectexit('action=admin;area=permissions');

		// Retrieve current permissions of group.
		$request = $smcFunc['db_query']('', '
			SELECT permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group = {int:copy_from}',
			array(
				'copy_from' => $_POST['copy_from'],
			)
		);
		$target_perm = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$target_perm[$row['permission']] = $row['add_deny'];
		$smcFunc['db_free_result']($request);

		$inserts = array();
		foreach ($_POST['group'] as $group_id)
			foreach ($target_perm as $perm => $add_deny)
			{
				// No dodgy permissions please!
				if (!empty($context['illegal_permissions']) && in_array($perm, $context['illegal_permissions']))
					continue;
				if ($group_id == -1 && in_array($perm, $context['non_guest_permissions']))
					continue;

				if ($group_id != 1 && $group_id != 3)
					$inserts[] = array($perm, $group_id, $add_deny);
			}

		// Delete the previous permissions...
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:group_list})
				' . (empty($context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
			array(
				'group_list' => $_POST['group'],
				'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : array(),
			)
		);

		if (!empty($inserts))
		{
			// ..and insert the new ones.
			$smcFunc['db_insert']('',
				'{db_prefix}permissions',
				array(
					'permission' => 'string', 'id_group' => 'int', 'add_deny' => 'int',
				),
				$inserts,
				array('permission', 'id_group')
			);
		}

		// Update any children out there!
		updateChildPermissions($_POST['group']);
	}
	// Set or unset a certain permission for the selected groups.
	elseif (!empty($_POST['permissions']))
	{
		// Unpack two variables that were transported.
		list ($permissionType, $permission) = explode('/', $_POST['permissions']);

		// Check whether our input is within expected range.
		if (!in_array($_POST['add_remove'], array('add', 'clear', 'deny')) || !in_array($permissionType, array('membergroup')))
			redirectexit('action=admin;area=permissions');

		if ($_POST['add_remove'] == 'clear')
		{
			if ($permissionType == 'membergroup')
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}permissions
					WHERE id_group IN ({array_int:current_group_list})
						AND permission = {string:current_permission}
						' . (empty($context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
					array(
						'current_group_list' => $_POST['group'],
						'current_permission' => $permission,
						'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : array(),
					)
				);
		}
		// Add a permission (either 'set' or 'deny').
		else
		{
			$add_deny = $_POST['add_remove'] == 'add' ? '1' : '0';
			$permChange = array();
			foreach ($_POST['group'] as $groupID)
			{
				if ($groupID == -1 && in_array($permission, $context['non_guest_permissions']))
					continue;

				if ($permissionType == 'membergroup' && $groupID != 1 && $groupID != 3 && (empty($context['illegal_permissions']) || !in_array($permission, $context['illegal_permissions'])))
					$permChange[] = array($permission, $groupID, $add_deny);
			}

			if (!empty($permChange))
			{
				if ($permissionType == 'membergroup')
					$smcFunc['db_insert']('replace',
						'{db_prefix}permissions',
						array('permission' => 'string', 'id_group' => 'int', 'add_deny' => 'int'),
						$permChange,
						array('permission', 'id_group')
					);
			}
		}

		// Another child update!
		updateChildPermissions($_POST['group']);
	}

	redirectexit('action=admin;area=permissions');
}

/**
 * Initializes the necessary to modify a membergroup's permissions.
 */
function ModifyMembergroup()
{
	global $context, $txt, $modSettings, $smcFunc, $sourcedir;

	if (!isset($_GET['group']))
		fatal_lang_error('no_access', false);

	$context['group']['id'] = (int) $_GET['group'];

	// Are they toggling the view?
	if (isset($_GET['view']))
	{
		$context['admin_preferences']['pv'] = $_GET['view'] == 'classic' ? 'classic' : 'simple';

		// Update the users preferences.
		require_once($sourcedir . '/Subs-Admin.php');
		updateAdminPreferences();
	}

	$context['view_type'] = !empty($context['admin_preferences']['pv']) && $context['admin_preferences']['pv'] == 'classic' ? 'classic' : 'simple';

	// It's not likely you'd end up here with this setting disabled.
	if ($_GET['group'] == 1)
		redirectexit('action=admin;area=permissions');

	loadAllPermissions($context['view_type']);

	if ($context['group']['id'] > 0)
	{
		$result = $smcFunc['db_query']('', '
			SELECT group_name, id_parent
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT 1',
			array(
				'current_group' => $context['group']['id'],
			)
		);
		list ($context['group']['name'], $parent) = $smcFunc['db_fetch_row']($result);
		$smcFunc['db_free_result']($result);

		// Cannot edit an inherited group!
		if ($parent != -2)
			fatal_lang_error('cannot_edit_permissions_inherited');
	}
	elseif ($context['group']['id'] == -1)
		$context['group']['name'] = $txt['membergroups_guests'];
	else
		$context['group']['name'] = $txt['membergroups_members'];

	$context['permission_type'] = 'membergroup';

	// Fetch the current permissions.
	$permissions = array(
		'membergroup' => array('allowed' => array(), 'denied' => array())
	);

	// General permissions?
	if ($context['permission_type'] == 'membergroup')
	{
		$result = $smcFunc['db_query']('', '
			SELECT permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group = {int:current_group}',
			array(
				'current_group' => $_GET['group'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($result))
			$permissions['membergroup'][empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['permission'];
		$smcFunc['db_free_result']($result);
	}

	// Loop through each permission and set whether it's checked.
	foreach ($context['permissions'] as $permissionType => $tmp)
	{
		foreach ($tmp['columns'] as $position => $permissionGroups)
		{
			foreach ($permissionGroups as $permissionGroup => $permissionArray)
			{
				foreach ($permissionArray['permissions'] as $perm)
				{
					// Create a shortcut for the current permission.
					$curPerm = &$context['permissions'][$permissionType]['columns'][$position][$permissionGroup]['permissions'][$perm['id']];
					if ($tmp['view'] == 'classic')
					{
						if ($perm['has_own_any'])
						{
							$curPerm['any']['select'] = in_array($perm['id'] . '_any', $permissions[$permissionType]['allowed']) ? 'on' : (in_array($perm['id'] . '_any', $permissions[$permissionType]['denied']) ? 'denied' : 'off');
							$curPerm['own']['select'] = in_array($perm['id'] . '_own', $permissions[$permissionType]['allowed']) ? 'on' : (in_array($perm['id'] . '_own', $permissions[$permissionType]['denied']) ? 'denied' : 'off');
						}
						else
							$curPerm['select'] = in_array($perm['id'], $permissions[$permissionType]['denied']) ? 'denied' : (in_array($perm['id'], $permissions[$permissionType]['allowed']) ? 'on' : 'off');
					}
					else
					{
						$curPerm['select'] = in_array($perm['id'], $permissions[$permissionType]['denied']) ? 'denied' : (in_array($perm['id'], $permissions[$permissionType]['allowed']) ? 'on' : 'off');
					}
				}
			}
		}
	}
	$context['sub_template'] = 'modify_group';
	$context['page_title'] = $txt['permissions_modify_group'];

	createToken('admin-mp');
}

/**
 * This function actually saves modifications to a membergroup's board permissions.
 */
function ModifyMembergroup2()
{
	global $modSettings, $smcFunc, $context;

	checkSession();
	validateToken('admin-mp');

	loadIllegalPermissions();

	$_GET['group'] = (int) $_GET['group'];

	// Verify this isn't inherited.
	if ($_GET['group'] == -1 || $_GET['group'] == 0)
		$parent = -2;
	else
	{
		$result = $smcFunc['db_query']('', '
			SELECT id_parent
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT 1',
			array(
				'current_group' => $_GET['group'],
			)
		);
		list ($parent) = $smcFunc['db_fetch_row']($result);
		$smcFunc['db_free_result']($result);
	}

	if ($parent != -2)
		fatal_lang_error('cannot_edit_permissions_inherited');

	$givePerms = array('membergroup' => array());

	// Guest group, we need illegal, guest permissions.
	if ($_GET['group'] == -1)
	{
		loadIllegalGuestPermissions();
		$context['illegal_permissions'] = array_merge($context['illegal_permissions'], $context['non_guest_permissions']);
	}

	// Prepare all permissions that were set or denied for addition to the DB.
	if (isset($_POST['perm']) && is_array($_POST['perm']))
	{
		foreach ($_POST['perm'] as $perm_type => $perm_array)
		{
			if (is_array($perm_array))
			{
				foreach ($perm_array as $permission => $value)
					if ($value == 'on' || $value == 'deny')
					{
						// Don't allow people to escalate themselves!
						if (!empty($context['illegal_permissions']) && in_array($permission, $context['illegal_permissions']))
							continue;

						$givePerms[$perm_type][] = array($_GET['group'], $permission, $value == 'deny' ? 0 : 1);
					}
			}
		}
	}

	// Insert the general permissions.
	if ($_GET['group'] != 3)
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}permissions
			WHERE id_group = {int:current_group}
			' . (empty($context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
			array(
				'current_group' => $_GET['group'],
				'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : array(),
			)
		);

		if (!empty($givePerms['membergroup']))
		{
			$smcFunc['db_insert']('replace',
				'{db_prefix}permissions',
				array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
				$givePerms['membergroup'],
				array('id_group', 'permission')
			);
		}
	}

	// Update any inherited permissions as required.
	updateChildPermissions($_GET['group']);

	// Clear cached privs.
	updateSettings(array('settings_updated' => time()));

	redirectexit('action=admin;area=permissions');
}

/**
 * A screen to set some general settings for permissions.
 *
 * @param bool $return_config = false
 */
function GeneralPermissionSettings($return_config = false)
{
	global $context, $modSettings, $sourcedir, $txt, $scripturl, $smcFunc;

	// All the setting variables
	$config_vars = array(
		array('title', 'settings'),
			// Inline permissions.
			array('permissions', 'manage_permissions'),
		'',
			// A few useful settings
			array('check', 'permission_enable_deny', 0, $txt['permission_settings_enable_deny']),
			array('check', 'permission_enable_postgroups', 0, $txt['permission_settings_enable_postgroups']),
	);

	call_integration_hook('integrate_modify_permission_settings', array($config_vars));

	if ($return_config)
		return $config_vars;

	$context['page_title'] = $txt['permission_settings_title'];
	$context['sub_template'] = 'show_settings';

	// Needed for the inline permission functions, and the settings template.
	require_once($sourcedir . '/ManageServer.php');

	// Don't let guests have these permissions.
	$context['post_url'] = $scripturl . '?action=admin;area=permissions;save;sa=settings';
	$context['permissions_excluded'] = array(-1);

	// Saving the settings?
	if (isset($_GET['save']))
	{
		checkSession('post');
		call_integration_hook('integrate_save_permission_settings');
		saveDBSettings($config_vars);

		// Clear all deny permissions...if we want that.
		if (empty($modSettings['permission_enable_deny']))
		{
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}permissions
				WHERE add_deny = {int:denied}',
				array(
					'denied' => 0,
				)
			);
		}

		// Make sure there are no postgroup based permissions left.
		if (empty($modSettings['permission_enable_postgroups']))
		{
			// Get a list of postgroups.
			$post_groups = array();
			$request = $smcFunc['db_query']('', '
				SELECT id_group
				FROM {db_prefix}membergroups
				WHERE min_posts != {int:min_posts}',
				array(
					'min_posts' => -1,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$post_groups[] = $row['id_group'];
			$smcFunc['db_free_result']($request);

			// Remove'em.
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:post_group_list})',
				array(
					'post_group_list' => $post_groups,
				)
			);
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}membergroups
				SET id_parent = {int:not_inherited}
				WHERE id_parent IN ({array_int:post_group_list})',
				array(
					'post_group_list' => $post_groups,
					'not_inherited' => -2,
				)
			);
		}

		redirectexit('action=admin;area=permissions;sa=settings');
	}

	// We need this for the in-line permissions
	createToken('admin-mp');

	prepareDBSettingContext($config_vars);
}

/**
 * Set the permission level for a specific profile, group, or group for a profile.
 * @internal
 *
 * @param string $level
 * @param int $group
 */
function setPermissionLevel($level, $group)
{
	global $smcFunc, $context;

	loadIllegalPermissions();
	loadIllegalGuestPermissions();

	// Levels by group... restrict, standard, moderator, maintenance.
	$groupLevels = array(
		'group' => array('inherit' => array())
	);

	// Restrictive - ie. guests.
	$groupLevels['global']['restrict'] = array(
		'search_posts',
		'view_stats',
		'who_view',
		'send_topic',
	);

	// Standard - ie. members.  They can do anything Restrictive can.
	$groupLevels['global']['standard'] = array_merge($groupLevels['global']['restrict'], array(
		'view_mlist',
		'pm_read',
		'pm_send',
		'send_email_to_members',
		'profile_view_any',
		'profile_extra_own',
		'profile_remove_own',
		'lock_own',
		'remove_own',
		'profile_view_own',
		'profile_identity_own',
		'post_new',
		'post_reply_own',
		'post_reply_any',
		'delete_own',
		'modify_own',
		'mark_any_notify',
		'mark_notify',
		'report_any',
	));

	// Maintenance - wannabe admins.  They can do almost everything.
	$groupLevels['global']['maintenance'] = array_merge($groupLevels['global']['standard'], array(
		'access_mod_center',
		'issue_warning',
		'manage_smileys',
		'moderate_forum',
		'manage_membergroups',
		'manage_bans',
		'admin_forum',
		'manage_permissions',
		'edit_news',
		'profile_identity_any',
		'profile_extra_any',
		'profile_title_any',
		'make_sticky',
		'delete_any',
		'modify_any',
		'lock_any',
		'remove_any',
		'move_any',
		'merge_any',
		'split_any',
		'approve_posts',
	));

	// Make sure we're not granting someone too many permissions!
	foreach ($groupLevels['global'][$level] as $k => $permission)
	{
		if (!empty($context['illegal_permissions']) && in_array($permission, $context['illegal_permissions']))
			unset($groupLevels['global'][$level][$k]);

		if ($group == -1 && in_array($permission, $context['non_guest_permissions']))
			unset($groupLevels['global'][$level][$k]);
	}

	// Reset all cached permissions.
	updateSettings(array('settings_updated' => time()));

	// Setting group permissions.
	if ($group !== 'null')
	{
		$group = (int) $group;

		if (empty($groupLevels['global'][$level]))
			return;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}permissions
			WHERE id_group = {int:current_group}
			' . (empty($context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
			array(
				'current_group' => $group,
				'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : array(),
			)
		);

		$groupInserts = array();
		foreach ($groupLevels['global'][$level] as $permission)
			$groupInserts[] = array($group, $permission);

		$smcFunc['db_insert']('insert',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'permission' => 'string'),
			$groupInserts,
			array('id_group')
		);
	}
	// $profile and $group are both null!
	else
		fatal_lang_error('no_access', false);
}

/**
 * Load permissions into $context['permissions'].
 * @internal
 *
 * @param string $loadType options: 'classic' or 'simple'
 */
function loadAllPermissions($loadType = 'classic')
{
	global $context, $txt, $modSettings;

	// List of all the groups dependant on the currently selected view - for the order so it looks pretty, yea?
	// Note to Mod authors - you don't need to stick your permission group here if you don't mind SMF sticking it the last group of the page.
	$permissionGroups = array(
		'membergroup' => array(
			'simple' => array(
				'view_basic_info',
				'disable_censor',
				'use_pm_system',
				'edit_profile',
				'delete_account',
				'moderate_general',
				'administrate',
				'make_posts',
				'make_unapproved_posts',
				'participate',
				'modify',
				'notification',
				'moderate',
			),
			'classic' => array(
				'general',
				'pm',
				'maintenance',
				'member_admin',
				'profile',
				'general_board',
				'topic',
				'post',
				'notification',
			),
		),
	);

	/*   The format of this list is as follows:
		'membergroup' => array(
			'permissions_inside' => array(has_multiple_options, classic_view_group, simple_view_group(_own)*, simple_view_group_any*),
		);
	*/
	$permissionList = array(
		'membergroup' => array(
			'view_stats' => array(false, 'general', 'view_basic_info'),
			'view_mlist' => array(false, 'general', 'view_basic_info'),
			'who_view' => array(false, 'general', 'view_basic_info'),
			'search_posts' => array(false, 'general', 'view_basic_info'),
			'disable_censor' => array(false, 'general', 'disable_censor'),
			'pm_read' => array(false, 'pm', 'use_pm_system'),
			'pm_send' => array(false, 'pm', 'use_pm_system'),
			'pm_draft' => array(false, 'pm', 'use_pm_system'),
			'pm_autosave_draft' => array(false, 'pm', 'use_pm_system'),
			'send_email_to_members' => array(false, 'pm', 'use_pm_system'),
			'admin_forum' => array(false, 'maintenance', 'administrate'),
			'manage_smileys' => array(false, 'maintenance', 'administrate'),
			'edit_news' => array(false, 'maintenance', 'administrate'),
			'access_mod_center' => array(false, 'maintenance', 'moderate_general'),
			'moderate_forum' => array(false, 'member_admin', 'moderate_general'),
			'manage_membergroups' => array(false, 'member_admin', 'administrate'),
			'manage_permissions' => array(false, 'member_admin', 'administrate'),
			'manage_bans' => array(false, 'member_admin', 'administrate'),
			'send_mail' => array(false, 'member_admin', 'administrate'),
			'issue_warning' => array(false, 'member_admin', 'moderate_general'),
			'profile_view' => array(true, 'profile', 'view_basic_info', 'view_basic_info'),
			'profile_identity' => array(true, 'profile', 'edit_profile', 'moderate_general'),
			'profile_extra' => array(true, 'profile', 'edit_profile', 'moderate_general'),
			'profile_title' => array(true, 'profile', 'edit_profile', 'moderate_general'),
			'profile_remove' => array(true, 'profile', 'delete_account', 'moderate_general'),
			'moderate_board' => array(false, 'general_board', 'moderate'),
			'approve_posts' => array(false, 'general_board', 'moderate'),
			'post_new' => array(false, 'topic', 'make_posts'),
			'post_draft' => array(false, 'topic', 'make_posts'),
			'post_autosave_draft' => array(false, 'topic', 'make_posts'),
			'post_unapproved_topics' => array(false, 'topic', 'make_unapproved_posts'),
			'post_unapproved_replies' => array(true, 'topic', 'make_unapproved_posts', 'make_unapproved_posts'),
			'post_reply' => array(true, 'topic', 'make_posts', 'make_posts'),
			'merge_any' => array(false, 'topic', 'moderate'),
			'split_any' => array(false, 'topic', 'moderate'),
			'send_topic' => array(false, 'topic', 'moderate'),
			'make_sticky' => array(false, 'topic', 'moderate'),
			'move' => array(true, 'topic', 'moderate', 'moderate'),
			'lock' => array(true, 'topic', 'moderate', 'moderate'),
			'remove' => array(true, 'topic', 'modify', 'moderate'),
			'modify_replies' => array(false, 'topic', 'moderate'),
			'delete_replies' => array(false, 'topic', 'moderate'),
			'announce_topic' => array(false, 'topic', 'moderate'),
			'delete' => array(true, 'post', 'modify', 'moderate'),
			'modify' => array(true, 'post', 'modify', 'moderate'),
			'report_any' => array(false, 'post', 'participate'),
			'mark_any_notify' => array(false, 'notification', 'notification'),
			'mark_notify' => array(false, 'notification', 'notification'),
		),
	);

	// All permission groups that will be shown in the left column on classic view.
	$leftPermissionGroups = array(
		'general',
		'maintenance',
		'member_admin',
		'topic',
		'post',
	);

	// We need to know what permissions we can't give to guests.
	loadIllegalGuestPermissions();

	// Some permissions are hidden if features are off.
	$hiddenPermissions = array();
	$relabelPermissions = array(); // Permissions to apply a different label to.
	$relabelGroups = array(); // As above but for groups.
	if (!in_array('w', $context['admin_features']))
		$hiddenPermissions[] = 'issue_warning';

	// Post moderation?
	if (!$modSettings['postmod_active'])
	{
		$hiddenPermissions[] = 'approve_posts';
		$hiddenPermissions[] = 'post_unapproved_topics';
		$hiddenPermissions[] = 'post_unapproved_replies';
	}
	// If we show them on classic view we change the name.
	else
	{
		// Relabel the topics permissions
		$relabelPermissions['post_new'] = 'auto_approve_topics';

		// Relabel the reply permissions
		$relabelPermissions['post_reply'] = 'auto_approve_replies';
	}

	// Provide a practical way to modify permissions.
	call_integration_hook('integrate_load_permissions', array(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions));

	$context['permissions'] = array();
	$context['hidden_permissions'] = array();
	foreach ($permissionList as $permissionType => $permissionList)
	{
		$context['permissions'][$permissionType] = array(
			'id' => $permissionType,
			'view' => $loadType,
			'columns' => array()
		);
		foreach ($permissionList as $permission => $permissionArray)
		{
			// If this is a guest permission we don't do it if it's the guest group.
			if (isset($context['group']['id']) && $context['group']['id'] == -1 && in_array($permission, $context['non_guest_permissions']))
				continue;

			// What groups will this permission be in?
			$own_group = $permissionArray[($loadType == 'classic' ? 1 : 2)];
			$any_group = $loadType == 'simple' && !empty($permissionArray[3]) ? $permissionArray[3] : ($loadType == 'simple' && $permissionArray[0] ? $permissionArray[2] : '');

			// First, Do these groups actually exist - if not add them.
			if (!isset($permissionGroups[$permissionType][$loadType][$own_group]))
				$permissionGroups[$permissionType][$loadType][$own_group] = true;
			if (!empty($any_group) && !isset($permissionGroups[$permissionType][$loadType][$any_group]))
				$permissionGroups[$permissionType][$loadType][$any_group] = true;

			// What column should this be located into?
			$position = $loadType == 'classic' && !in_array($own_group, $leftPermissionGroups) ? 1 : 0;

			// If the groups have not yet been created be sure to create them.
			$bothGroups = array('own' => $own_group);
			$bothGroups = array();

			// For guests, just reset the array.
			if (!isset($context['group']['id']) || !($context['group']['id'] == -1 && $any_group))
				$bothGroups['own'] = $own_group;

			if ($any_group)
			{
				$bothGroups['any'] = $any_group;

			}

			foreach ($bothGroups as $group)
				if (!isset($context['permissions'][$permissionType]['columns'][$position][$group]))
					$context['permissions'][$permissionType]['columns'][$position][$group] = array(
						'type' => $permissionType,
						'id' => $group,
						'name' => $loadType == 'simple' ? (isset($txt['permissiongroup_simple_' . $group]) ? $txt['permissiongroup_simple_' . $group] : '') : $txt['permissiongroup_' . $group],
						'icon' => isset($txt['permissionicon_' . $group]) ? $txt['permissionicon_' . $group] : $txt['permissionicon'],
						'hidden' => false,
						'permissions' => array()
					);

			// This is where we set up the permission dependant on the view.
			if ($loadType == 'classic')
			{
				$context['permissions'][$permissionType]['columns'][$position][$own_group]['permissions'][$permission] = array(
					'id' => $permission,
					'name' => !isset($relabelPermissions[$permission]) ? $txt['permissionname_' . $permission] : $txt[$relabelPermissions[$permission]],
					'note' => isset($txt['permissionnote_' . $permission]) ? $txt['permissionnote_' . $permission] : '',
					'has_own_any' => $permissionArray[0],
					'own' => array(
						'id' => $permission . '_own',
						'name' => $permissionArray[0] ? $txt['permissionname_' . $permission . '_own'] : ''
					),
					'any' => array(
						'id' => $permission . '_any',
						'name' => $permissionArray[0] ? $txt['permissionname_' . $permission . '_any'] : ''
					),
					'hidden' => in_array($permission, $hiddenPermissions),
				);
			}
			else
			{
				foreach ($bothGroups as $group_type => $group)
				{
					$context['permissions'][$permissionType]['columns'][$position][$group]['permissions'][$permission . ($permissionArray[0] ? '_' . $group_type : '')] = array(
						'id' => $permission . ($permissionArray[0] ? '_' . $group_type : ''),
						'name' => isset($txt['permissionname_simple_' . $permission . ($permissionArray[0] ? '_' . $group_type : '')]) ? $txt['permissionname_simple_' . $permission . ($permissionArray[0] ? '_' . $group_type : '')] : $txt['permissionname_' . $permission],
						'hidden' => in_array($permission, $hiddenPermissions),
					);
				}
			}

			if (in_array($permission, $hiddenPermissions))
			{
				if ($permissionArray[0])
				{
					$context['hidden_permissions'][] = $permission . '_own';
					$context['hidden_permissions'][] = $permission . '_any';
				}
				else
					$context['hidden_permissions'][] = $permission;
			}
		}
		ksort($context['permissions'][$permissionType]['columns']);

		// Check we don't leave any empty groups - and mark hidden ones as such.
		foreach ($context['permissions'][$permissionType]['columns'] as $column => $groups)
			foreach ($groups as $id => $group)
			{
				if (empty($group['permissions']))
					unset($context['permissions'][$permissionType]['columns'][$column][$id]);
				else
				{
					$foundNonHidden = false;
					foreach ($group['permissions'] as $permission)
						if (empty($permission['hidden']))
							$foundNonHidden = true;
					if (!$foundNonHidden)
						$context['permissions'][$permissionType]['columns'][$column][$id]['hidden'] = true;
				}
			}
	}
}

/**
 * Initialize a form with inline permissions settings.
 * It loads a context variables for each permission.
 * This function is used by several settings screens to set specific permissions.
 * @internal
 *
 * @param array $permissions
 * @param array $excluded_groups = array()
 *
 * @uses ManagePermissions language
 * @uses ManagePermissions template.
 */
function init_inline_permissions($permissions, $excluded_groups = array())
{
	global $context, $txt, $modSettings, $smcFunc;

	loadLanguage('ManagePermissions');
	loadTemplate('ManagePermissions');
	$context['can_change_permissions'] = allowedTo('manage_permissions');

	// Nothing to initialize here.
	if (!$context['can_change_permissions'])
		return;

	// Load the permission settings for guests
	foreach ($permissions as $permission)
		$context[$permission] = array(
			-1 => array(
				'id' => -1,
				'name' => $txt['membergroups_guests'],
				'is_postgroup' => false,
				'status' => 'off',
			),
			0 => array(
				'id' => 0,
				'name' => $txt['membergroups_members'],
				'is_postgroup' => false,
				'status' => 'off',
			),
		);

	$request = $smcFunc['db_query']('', '
		SELECT id_group, CASE WHEN add_deny = {int:denied} THEN {string:deny} ELSE {string:on} END AS status, permission
		FROM {db_prefix}permissions
		WHERE id_group IN (-1, 0)
			AND permission IN ({array_string:permissions})',
		array(
			'denied' => 0,
			'permissions' => $permissions,
			'deny' => 'deny',
			'on' => 'on',
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context[$row['permission']][$row['id_group']]['status'] = $row['status'];
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT mg.id_group, mg.group_name, mg.min_posts, IFNULL(p.add_deny, -1) AS status, p.permission
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}permissions AS p ON (p.id_group = mg.id_group AND p.permission IN ({array_string:permissions}))
		WHERE mg.id_group NOT IN (1, 3)
			AND mg.id_parent = {int:not_inherited}' . (empty($modSettings['permission_enable_postgroups']) ? '
			AND mg.min_posts = {int:min_posts}' : '') . '
		ORDER BY mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
		array(
			'not_inherited' => -2,
			'min_posts' => -1,
			'newbie_group' => 4,
			'permissions' => $permissions,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Initialize each permission as being 'off' until proven otherwise.
		foreach ($permissions as $permission)
			if (!isset($context[$permission][$row['id_group']]))
				$context[$permission][$row['id_group']] = array(
					'id' => $row['id_group'],
					'name' => $row['group_name'],
					'is_postgroup' => $row['min_posts'] != -1,
					'status' => 'off',
				);

		$context[$row['permission']][$row['id_group']]['status'] = empty($row['status']) ? 'deny' : ($row['status'] == 1 ? 'on' : 'off');
	}
	$smcFunc['db_free_result']($request);

	// Some permissions cannot be given to certain groups. Remove the groups.
	foreach ($excluded_groups as $group)
	{
		foreach ($permissions as $permission)
		{
			if (isset($context[$permission][$group]))
				unset($context[$permission][$group]);
		}
	}
}

/**
 * Show a collapsible box to set a specific permission.
 * The function is called by templates to show a list of permissions settings.
 * Calls the template function template_inline_permissions().
 *
 * @param string $permission
 */
function theme_inline_permissions($permission)
{
	global $context;

	$context['current_permission'] = $permission;
	$context['member_groups'] = $context[$permission];

	template_inline_permissions();
}

/**
 * Save the permissions of a form containing inline permissions.
 * @internal
 *
 * @param array $permissions
 */
function save_inline_permissions($permissions)
{
	global $context, $smcFunc;

	// No permissions? Not a great deal to do here.
	if (!allowedTo('manage_permissions'))
		return;

	// Almighty session check, verify our ways.
	checkSession();
	validateToken('admin-mp');

	// Check they can't do certain things.
	loadIllegalPermissions();

	$insertRows = array();
	foreach ($permissions as $permission)
	{
		if (!isset($_POST[$permission]))
			continue;

		foreach ($_POST[$permission] as $id_group => $value)
		{
			if (in_array($value, array('on', 'deny')) && (empty($context['illegal_permissions']) || !in_array($permission, $context['illegal_permissions'])))
				$insertRows[] = array((int) $id_group, $permission, $value == 'on' ? 1 : 0);
		}
	}

	// Remove the old permissions...
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}permissions
		WHERE permission IN ({array_string:permissions})
		' . (empty($context['illegal_permissions']) ? '' : ' AND permission NOT IN ({array_string:illegal_permissions})'),
		array(
			'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : array(),
			'permissions' => $permissions,
		)
	);

	// ...and replace them with new ones.
	if (!empty($insertRows))
		$smcFunc['db_insert']('insert',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
			$insertRows,
			array('id_group', 'permission')
		);

	// Do a full child update.
	updateChildPermissions(array());

	// Just in case we cached this.
	updateSettings(array('settings_updated' => time()));
}

/**
 * This function updates the permissions of any groups based off this group.
 *
 * @param mixed $parents (array or int)
 */
function updateChildPermissions($parents)
{
	global $smcFunc;

	// All the parent groups to sort out.
	if (!is_array($parents))
		$parents = array($parents);

	// Find all the children of this group.
	$request = $smcFunc['db_query']('', '
		SELECT id_parent, id_group
		FROM {db_prefix}membergroups
		WHERE id_parent != {int:not_inherited}
			' . (empty($parents) ? '' : 'AND id_parent IN ({array_int:parent_list})'),
		array(
			'parent_list' => $parents,
			'not_inherited' => -2,
		)
	);
	$children = array();
	$parents = array();
	$child_groups = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$children[$row['id_parent']][] = $row['id_group'];
		$child_groups[] = $row['id_group'];
		$parents[] = $row['id_parent'];
	}
	$smcFunc['db_free_result']($request);

	$parents = array_unique($parents);

	// Not a sausage, or a child?
	if (empty($children))
		return false;

	// Fetch all the parent permissions.
	$request = $smcFunc['db_query']('', '
		SELECT id_group, permission, add_deny
		FROM {db_prefix}permissions
		WHERE id_group IN ({array_int:parent_list})',
		array(
			'parent_list' => $parents,
		)
	);
	$permissions = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		foreach ($children[$row['id_group']] as $child)
			$permissions[] = array($child, $row['permission'], $row['add_deny']);
	$smcFunc['db_free_result']($request);

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}permissions
		WHERE id_group IN ({array_int:child_groups})',
		array(
			'child_groups' => $child_groups,
		)
	);

	// Finally insert.
	if (!empty($permissions))
	{
		$smcFunc['db_insert']('insert',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
			$permissions,
			array('id_group', 'permission')
		);
	}
}

/**
 * Load permissions someone cannot grant.
 */
function loadIllegalPermissions()
{
	global $context;

	$context['illegal_permissions'] = array();
	if (!allowedTo('admin_forum'))
		$context['illegal_permissions'][] = 'admin_forum';
	if (!allowedTo('manage_membergroups'))
		$context['illegal_permissions'][] = 'manage_membergroups';
	if (!allowedTo('manage_permissions'))
		$context['illegal_permissions'][] = 'manage_permissions';

	call_integration_hook('integrate_load_illegal_permissions');
}

/**
 * Loads the permissions that can not be given to guests.
 * Stores the permissions in $context['non_guest_permissions'].
*/
function loadIllegalGuestPermissions()
{
	global $context;

	$context['non_guest_permissions'] = array(
		'delete_replies',
		'pm_read',
		'pm_send',
		'profile_identity',
		'profile_extra',
		'profile_title',
		'profile_remove',
		'profile_view_own',
		'mark_any_notify',
		'mark_notify',
		'admin_forum',
		'manage_smileys',
		'edit_news',
		'access_mod_center',
		'moderate_forum',
		'issue_warning',
		'manage_membergroups',
		'manage_permissions',
		'manage_bans',
		'move_own',
		'modify_replies',
		'send_mail',
		'approve_posts',
		'post_draft',
		'post_autosave_draft',
	);

	call_integration_hook('integrate_load_illegal_guest_permissions');
}

/* @todoy: yeni bir emre kadar kaldýrýldý
/**
 * Present a nice way of applying post moderation.
 
function ModifyPostModeration()
{
	global $context, $txt, $smcFunc, $modSettings;

	// Just in case.
	checkSession('get');

	$context['page_title'] = $txt['permissions_post_moderation'];
	$context['sub_template'] = 'postmod_permissions';
	$context['current_profile'] = isset($_REQUEST['pid']) ? (int) $_REQUEST['pid'] : 1;

	// Load all the permission profiles.
	loadPermissionProfiles();

	// Mappings, our key => array(can_do_moderated, can_do_all)
	$mappings = array(
		'new_topic' => array('post_new', 'post_unapproved_topics'),
		'replies_own' => array('post_reply_own', 'post_unapproved_replies_own'),
		'replies_any' => array('post_reply_any', 'post_unapproved_replies_any'),
	);

	call_integration_hook('integrate_post_moderation_mapping', array($mappings));

	// Start this with the guests/members.
	$context['profile_groups'] = array(
		-1 => array(
			'id' => -1,
			'name' => $txt['membergroups_guests'],
			'color' => '',
			'new_topic' => 'disallow',
			'replies_own' => 'disallow',
			'replies_any' => 'disallow',
			'children' => array(),
		),
		0 => array(
			'id' => 0,
			'name' => $txt['membergroups_members'],
			'color' => '',
			'new_topic' => 'disallow',
			'replies_own' => 'disallow',
			'replies_any' => 'disallow',
			'children' => array(),
		),
	);

	// Load the groups.
	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name, online_color, id_parent
		FROM {db_prefix}membergroups
		WHERE id_group != {int:admin_group}
			' . (empty($modSettings['permission_enable_postgroups']) ? ' AND min_posts = {int:min_posts}' : '') . '
		ORDER BY id_parent ASC',
		array(
			'admin_group' => 1,
			'min_posts' => -1,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($row['id_parent'] == -2)
		{
			$context['profile_groups'][$row['id_group']] = array(
				'id' => $row['id_group'],
				'name' => $row['group_name'],
				'color' => $row['online_color'],
				'new_topic' => 'disallow',
				'replies_own' => 'disallow',
				'replies_any' => 'disallow',
				'children' => array(),
			);
		}
		elseif (isset($context['profile_groups'][$row['id_parent']]))
			$context['profile_groups'][$row['id_parent']]['children'][] = $row['group_name'];
	}
	$smcFunc['db_free_result']($request);

	// What are the permissions we are querying?
	$all_permissions = array();
	foreach ($mappings as $perm_set)
		$all_permissions = array_merge($all_permissions, $perm_set);

	// If we're saving the changes then do just that - save them.
	if (!empty($_POST['save_changes']) && ($context['current_profile'] == 1 || $context['current_profile'] > 4))
	{
		validateToken('admin-mppm');

		// Start by deleting all the permissions relevant.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}board_permissions
			WHERE id_profile = {int:current_profile}
				AND permission IN ({array_string:permissions})
				AND id_group IN ({array_int:profile_group_list})',
			array(
				'profile_group_list' => array_keys($context['profile_groups']),
				'current_profile' => $context['current_profile'],
				'permissions' => $all_permissions,
			)
		);

		// Do it group by group.
		$new_permissions = array();
		foreach ($context['profile_groups'] as $id => $group)
		{
			foreach ($mappings as $index => $data)
			{
				if (isset($_POST[$index][$group['id']]))
				{
					if ($_POST[$index][$group['id']] == 'allow')
					{
						// Give them both sets for fun.
						$new_permissions[] = array($context['current_profile'], $group['id'], $data[0], 1);
						$new_permissions[] = array($context['current_profile'], $group['id'], $data[1], 1);
					}
					elseif ($_POST[$index][$group['id']] == 'moderate')
						$new_permissions[] = array($context['current_profile'], $group['id'], $data[1], 1);
				}
			}
		}

		// Insert new permissions.
		if (!empty($new_permissions))
			$smcFunc['db_insert']('',
				'{db_prefix}board_permissions',
				array('id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
				$new_permissions,
				array('id_profile', 'id_group', 'permission')
			);
	}

	// Now get all the permissions!
	$request = $smcFunc['db_query']('', '
		SELECT id_group, permission, add_deny
		FROM {db_prefix}board_permissions
		WHERE id_profile = {int:current_profile}
			AND permission IN ({array_string:permissions})
			AND id_group IN ({array_int:profile_group_list})',
		array(
			'profile_group_list' => array_keys($context['profile_groups']),
			'current_profile' => $context['current_profile'],
			'permissions' => $all_permissions,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		foreach ($mappings as $key => $data)
		{
			foreach ($data as $index => $perm)
			{
				if ($perm == $row['permission'])
				{
					// Only bother if it's not denied.
					if ($row['add_deny'])
					{
						// Full allowance?
						if ($index == 0)
							$context['profile_groups'][$row['id_group']][$key] = 'allow';
						// Otherwise only bother with moderate if not on allow.
						elseif ($context['profile_groups'][$row['id_group']][$key] != 'allow')
							$context['profile_groups'][$row['id_group']][$key] = 'moderate';
					}
				}
			}
		}
	}
	$smcFunc['db_free_result']($request);

	createToken('admin-mppm');

}
*/
?>