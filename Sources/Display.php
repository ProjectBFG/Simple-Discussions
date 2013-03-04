<?php

/**
 * This is perhaps the most important and probably most accessed file in all
 * of SMF.  This file controls topic and message display.
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
 * The central part of the topic display.
 * This function loads the posts in a topic up so they can be displayed.
 * It uses the main sub template of the Display template.
 * It requires a topic, and can go to the previous or next topic from it.
 * It jumps to the correct post depending on a number/time/IS_MSG passed.
 * It depends on the messages_per_page, defaultMaxMessages and enableAllMessages settings.
 * It is accessed by ?topic=id_topic.START.
 */
function Display()
{
	global $scripturl, $txt, $modSettings, $context, $settings;
	global $options, $sourcedir, $user_info, $topic;
	global $messages_request, $topicinfo, $language, $smcFunc;

	// What are you gonna display if these are empty?!
	if (empty($topic))
		fatal_lang_error('no_board', false);

	// Load the proper template and/or sub template.
	loadTemplate('Display');

	// Not only does a prefetch make things slower for the server, but it makes it impossible to know if they read it.
	if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
	{
		ob_end_clean();
		header('HTTP/1.1 403 Prefetch Forbidden');
		die;
	}

	// How much are we sticking on each page?
	$context['messages_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];

	// Let's do some work on what to search index.
	if (count($_GET) > 2)
		foreach ($_GET as $k => $v)
		{
			if (!in_array($k, array('topic', 'start', session_name())))
				$context['robot_no_index'] = true;
		}

	if (!empty($_REQUEST['start']) && (!is_numeric($_REQUEST['start']) || $_REQUEST['start'] % $context['messages_per_page'] != 0))
		$context['robot_no_index'] = true;
        
        $context['current_topic'] = $topic;

	// Add 1 to the number of views of this topic (except for robots).
	if (!$user_info['possibly_robot'] && (empty($_SESSION['last_read_topic']) || $_SESSION['last_read_topic'] != $topic))
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET num_views = num_views + 1
			WHERE id_topic = {int:current_topic}',
			array(
				'current_topic' => $topic,
			)
		);

		$_SESSION['last_read_topic'] = $topic;
	}

	$topic_parameters = array(
		'current_member' => $user_info['id'],
		'current_topic' => $topic,
	);
	$topic_selects = array();
	$topic_tables = array();
	call_integration_hook('integrate_display_topic', array($topic_selects, $topic_tables, $topic_parameters));

	// @todo Why isn't this cached?
	// @todo if we get id_board in this query and cache it, we can save a query on posting
	// Get all the important topic info.
	$request = $smcFunc['db_query']('', '
		SELECT
			t.num_replies, t.num_views, t.locked, ms.subject, t.is_sticky,
			t.id_member_started, t.id_first_msg, t.id_last_msg, t.approved, t.unapproved_posts, t.id_redirect_topic,
			' . ($user_info['is_guest'] ? 't.id_last_msg + 1' : 'IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1') . ' AS new_from
			' . (!empty($topic_selects) ? implode(',', $topic_selects) : '') . '
			' . (!$user_info['is_guest'] ? ', IFNULL(lt.disregarded, 0) as disregarded' : '') . '
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)' . ($user_info['is_guest'] ? '' : '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = {int:current_topic} AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_member = {int:current_member})') . '
			' . (!empty($topic_tables) ? implode("\n\t", $topic_tables) : '') . '
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
			$topic_parameters
	);

	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('not_a_topic', false);
	$topicinfo = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Is this a moved topic that we are redirecting to?
	if (!empty($topicinfo['id_redirect_topic']))
		redirectexit('topic=' . $topicinfo['id_redirect_topic'] . '.0');

	$context['real_num_replies'] = $context['num_replies'] = $topicinfo['num_replies'];
	$context['topic_first_message'] = $topicinfo['id_first_msg'];
	$context['topic_last_message'] = $topicinfo['id_last_msg'];
	$context['topic_disregarded'] = isset($topicinfo['disregarded']) ? $topicinfo['disregarded'] : 0;

	// Add up unapproved replies to get real number of replies...
	if ($modSettings['postmod_active'] && allowedTo('approve_posts'))
		$context['real_num_replies'] += $topicinfo['unapproved_posts'] - ($topicinfo['approved'] ? 0 : 1);

	// If this topic has unapproved posts, we need to work out how many posts the user can see, for page indexing.
	if ($modSettings['postmod_active'] && $topicinfo['unapproved_posts'] && !$user_info['is_guest'] && !allowedTo('approve_posts'))
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(id_member) AS my_unapproved_posts
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
				AND id_member = {int:current_member}
				AND approved = 0',
			array(
				'current_topic' => $topic,
				'current_member' => $user_info['id'],
			)
		);
		list ($myUnapprovedPosts) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$context['total_visible_posts'] = $context['num_replies'] + $myUnapprovedPosts + ($topicinfo['approved'] ? 1 : 0);
	}
	elseif ($user_info['is_guest'])
		$context['total_visible_posts'] = $context['num_replies'] + ($topicinfo['approved'] ? 1 : 0);
	else
		$context['total_visible_posts'] = $context['num_replies'] + $topicinfo['unapproved_posts'] + ($topicinfo['approved'] ? 1 : 0);

	// When was the last time this topic was replied to?  Should we warn them about it?
	$request = $smcFunc['db_query']('', '
		SELECT poster_time
		FROM {db_prefix}messages
		WHERE id_msg = {int:id_last_msg}
		LIMIT 1',
		array(
			'id_last_msg' => $topicinfo['id_last_msg'],
		)
	);

	list ($lastPostTime) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$context['oldTopicError'] = !empty($modSettings['oldTopicDays']) && $lastPostTime + $modSettings['oldTopicDays'] * 86400 < time() && empty($topicinfo['is_sticky']);

	// The start isn't a number; it's information about what to do, where to go.
	if (!is_numeric($_REQUEST['start']))
	{
		// Redirect to the page and post with new messages, originally by Omar Bazavilvazo.
		if ($_REQUEST['start'] == 'new')
		{
			// Guests automatically go to the last post.
			if ($user_info['is_guest'])
			{
				$context['start_from'] = $context['total_visible_posts'] - 1;
				$_REQUEST['start'] = empty($options['view_newest_first']) ? $context['start_from'] : 0;
			}
			else
			{
				// Find the earliest unread message in the topic. (the use of topics here is just for both tables.)
				$request = $smcFunc['db_query']('', '
					SELECT IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from
					FROM {db_prefix}topics AS t
						LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = {int:current_topic} AND lt.id_member = {int:current_member})
						LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_member = {int:current_member})
					WHERE t.id_topic = {int:current_topic}
					LIMIT 1',
					array(
						'current_member' => $user_info['id'],
						'current_topic' => $topic,
					)
				);
				list ($new_from) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				// Fall through to the next if statement.
				$_REQUEST['start'] = 'msg' . $new_from;
			}
		}

		// Start from a certain time index, not a message.
		if (substr($_REQUEST['start'], 0, 4) == 'from')
		{
			$timestamp = (int) substr($_REQUEST['start'], 4);
			if ($timestamp === 0)
				$_REQUEST['start'] = 0;
			else
			{
				// Find the number of messages posted before said time...
				$request = $smcFunc['db_query']('', '
					SELECT COUNT(*)
					FROM {db_prefix}messages
					WHERE poster_time < {int:timestamp}
						AND id_topic = {int:current_topic}' . ($modSettings['postmod_active'] && $topicinfo['unapproved_posts'] && !allowedTo('approve_posts') ? '
						AND (approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR id_member = {int:current_member}') . ')' : ''),
					array(
						'current_topic' => $topic,
						'current_member' => $user_info['id'],
						'is_approved' => 1,
						'timestamp' => $timestamp,
					)
				);
				list ($context['start_from']) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				// Handle view_newest_first options, and get the correct start value.
				$_REQUEST['start'] = empty($options['view_newest_first']) ? $context['start_from'] : $context['total_visible_posts'] - $context['start_from'] - 1;
			}
		}

		// Link to a message...
		elseif (substr($_REQUEST['start'], 0, 3) == 'msg')
		{
			$virtual_msg = (int) substr($_REQUEST['start'], 3);
			if (!$topicinfo['unapproved_posts'] && $virtual_msg >= $topicinfo['id_last_msg'])
				$context['start_from'] = $context['total_visible_posts'] - 1;
			elseif (!$topicinfo['unapproved_posts'] && $virtual_msg <= $topicinfo['id_first_msg'])
				$context['start_from'] = 0;
			else
			{
				// Find the start value for that message......
				$request = $smcFunc['db_query']('', '
					SELECT COUNT(*)
					FROM {db_prefix}messages
					WHERE id_msg < {int:virtual_msg}
						AND id_topic = {int:current_topic}' . ($modSettings['postmod_active'] && $topicinfo['unapproved_posts'] && !allowedTo('approve_posts') ? '
						AND (approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR id_member = {int:current_member}') . ')' : ''),
					array(
						'current_member' => $user_info['id'],
						'current_topic' => $topic,
						'virtual_msg' => $virtual_msg,
						'is_approved' => 1,
						'no_member' => 0,
					)
				);
				list ($context['start_from']) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);
			}

			// We need to reverse the start as well in this case.
			$_REQUEST['start'] = empty($options['view_newest_first']) ? $context['start_from'] : $context['total_visible_posts'] - $context['start_from'] - 1;
		}
	}

	// Check if spellchecking is both enabled and actually working. (for quick reply.)
	$context['show_spellchecking'] = !empty($modSettings['enableSpellChecking']) && function_exists('pspell_new');

	// Do we need to show the visual verification image?
	$context['require_verification'] = !$user_info['is_mod'] && !$user_info['is_admin'] && !empty($modSettings['posts_require_captcha']) && ($user_info['posts'] < $modSettings['posts_require_captcha'] || ($user_info['is_guest'] && $modSettings['posts_require_captcha'] == -1));
	if ($context['require_verification'])
	{
		require_once($sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'post',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	// Are we showing signatures - or disabled fields?
	$context['signature_enabled'] = substr($modSettings['signature_settings'], 0, 1) == 1;
	$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();

	// Censor the title...
	censorText($topicinfo['subject']);
	$context['page_title'] = $topicinfo['subject'];

	// Is this topic sticky, or can it even be?
	$topicinfo['is_sticky'] = empty($modSettings['enableStickyTopics']) ? '0' : $topicinfo['is_sticky'];

	// Default this topic to not marked for notifications... of course...
	$context['is_marked_notify'] = false;

	// Did we report a post to a moderator just now?
	$context['report_sent'] = isset($_GET['reportsent']);

	// Let's get nosey, who is viewing this topic?
	if (!empty($settings['display_who_viewing']))
	{
		// Start out with no one at all viewing it.
		$context['view_members'] = array();
		$context['view_members_list'] = array();
		$context['view_num_hidden'] = 0;

		// Search for members who have this topic set in their GET data.
		$request = $smcFunc['db_query']('', '
			SELECT
				lo.id_member, lo.log_time, mem.real_name, mem.member_name, mem.show_online,
				mg.online_color, mg.id_group, mg.group_name
			FROM {db_prefix}log_online AS lo
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lo.id_member)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_id_group} THEN mem.id_post_group ELSE mem.id_group END)
			WHERE INSTR(lo.url, {string:in_url_string}) > 0 OR lo.session = {string:session}',
			array(
				'reg_id_group' => 0,
				'in_url_string' => 's:5:"topic";i:' . $topic . ';',
				'session' => $user_info['is_guest'] ? 'ip' . $user_info['ip'] : session_id(),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (empty($row['id_member']))
				continue;

			if (!empty($row['online_color']))
				$link = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '" style="color: ' . $row['online_color'] . ';">' . $row['real_name'] . '</a>';
			else
				$link = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';

			$is_buddy = in_array($row['id_member'], $user_info['buddies']);
			if ($is_buddy)
				$link = '<strong>' . $link . '</strong>';

			// Add them both to the list and to the more detailed list.
			if (!empty($row['show_online']) || allowedTo('moderate_forum'))
				$context['view_members_list'][$row['log_time'] . $row['member_name']] = empty($row['show_online']) ? '<em>' . $link . '</em>' : $link;
			$context['view_members'][$row['log_time'] . $row['member_name']] = array(
				'id' => $row['id_member'],
				'username' => $row['member_name'],
				'name' => $row['real_name'],
				'group' => $row['id_group'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => $link,
				'is_buddy' => $is_buddy,
				'hidden' => empty($row['show_online']),
			);

			if (empty($row['show_online']))
				$context['view_num_hidden']++;
		}

		// The number of guests is equal to the rows minus the ones we actually used ;).
		$context['view_num_guests'] = $smcFunc['db_num_rows']($request) - count($context['view_members']);
		$smcFunc['db_free_result']($request);

		// Sort the list.
		krsort($context['view_members']);
		krsort($context['view_members_list']);
	}

	// If all is set, but not allowed... just unset it.
	$can_show_all = !empty($modSettings['enableAllMessages']) && $context['total_visible_posts'] > $context['messages_per_page'] && $context['total_visible_posts'] < $modSettings['enableAllMessages'];
	if (isset($_REQUEST['all']) && !$can_show_all)
		unset($_REQUEST['all']);
	// Otherwise, it must be allowed... so pretend start was -1.
	elseif (isset($_REQUEST['all']))
		$_REQUEST['start'] = -1;

	// Construct the page index, allowing for the .START method...
	$context['page_index'] = constructPageIndex($scripturl . '?topic=' . $topic . '.%1$d', $_REQUEST['start'], $context['total_visible_posts'], $context['messages_per_page'], true);
	$context['start'] = $_REQUEST['start'];

	// This is information about which page is current, and which page we're on - in case you don't like the constructed page index. (again, wireles..)
	$context['page_info'] = array(
		'current_page' => $_REQUEST['start'] / $context['messages_per_page'] + 1,
		'num_pages' => floor(($context['total_visible_posts'] - 1) / $context['messages_per_page']) + 1,
	);

	// Figure out all the link to the next/prev/first/last/etc. for wireless mainly.
	$context['links'] = array(
		'first' => $_REQUEST['start'] >= $context['messages_per_page'] ? $scripturl . '?topic=' . $topic . '.0' : '',
		'prev' => $_REQUEST['start'] >= $context['messages_per_page'] ? $scripturl . '?topic=' . $topic . '.' . ($_REQUEST['start'] - $context['messages_per_page']) : '',
		'next' => $_REQUEST['start'] + $context['messages_per_page'] < $context['total_visible_posts'] ? $scripturl . '?topic=' . $topic. '.' . ($_REQUEST['start'] + $context['messages_per_page']) : '',
		'last' => $_REQUEST['start'] + $context['messages_per_page'] < $context['total_visible_posts'] ? $scripturl . '?topic=' . $topic. '.' . (floor($context['total_visible_posts'] / $context['messages_per_page']) * $context['messages_per_page']) : '',
		'up' => $scripturl
	);

	// If they are viewing all the posts, show all the posts, otherwise limit the number.
	if ($can_show_all)
	{
		if (isset($_REQUEST['all']))
		{
			// No limit! (actually, there is a limit, but...)
			$context['messages_per_page'] = -1;
			$context['page_index'] .= empty($modSettings['compactTopicPagesEnable']) ? '<strong>' . $txt['all'] . '</strong> ' : '[<strong>' . $txt['all'] . '</strong>] ';

			// Set start back to 0...
			$_REQUEST['start'] = 0;
		}
		// They aren't using it, but the *option* is there, at least.
		else
			$context['page_index'] .= '&nbsp;<a href="' . $scripturl . '?topic=' . $topic . '.0;all">' . $txt['all'] . '</a> ';
	}

	// Information about the current topic...
	$context['is_locked'] = $topicinfo['locked'];
	$context['is_sticky'] = $topicinfo['is_sticky'];
	$context['is_very_hot'] = $topicinfo['num_replies'] >= $modSettings['hotTopicVeryPosts'];
	$context['is_hot'] = $topicinfo['num_replies'] >= $modSettings['hotTopicPosts'];
	$context['is_approved'] = $topicinfo['approved'];

	// Did this user start the topic or not?
	$context['user']['started'] = $user_info['id'] == $topicinfo['id_member_started'] && !$user_info['is_guest'];
	$context['topic_starter_id'] = $topicinfo['id_member_started'];

	// Set the topic's information for the template.
	$context['subject'] = $topicinfo['subject'];
	$context['num_views'] = $topicinfo['num_views'];
	$context['num_views_text'] = $context['num_views'] == 1 ? $txt['read_one_time'] : sprintf($txt['read_many_times'], $context['num_views']);
	$context['mark_unread_time'] = !empty($virtual_msg) ? $virtual_msg : $topicinfo['new_from'];

	// Set a canonical URL for this page.
	$context['canonical_url'] = $scripturl . '?topic=' . $topic . '.' . $context['start'];

	// For quick reply we need a response prefix in the default forum language.
	if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix', 600)))
	{
		if ($language === $user_info['language'])
			$context['response_prefix'] = $txt['response_prefix'];
		else
		{
			loadLanguage('index', $language, false);
			$context['response_prefix'] = $txt['response_prefix'];
			loadLanguage('index');
		}
		cache_put_data('response_prefix', $context['response_prefix'], 600);
	}

	// Calculate the fastest way to get the messages!
	$ascending = empty($options['view_newest_first']);
	$start = $_REQUEST['start'];
	$limit = $context['messages_per_page'];
	$firstIndex = 0;
	if ($start >= $context['total_visible_posts'] / 2 && $context['messages_per_page'] != -1)
	{
		$ascending = !$ascending;
		$limit = $context['total_visible_posts'] <= $start + $limit ? $context['total_visible_posts'] - $start : $limit;
		$start = $context['total_visible_posts'] <= $start + $limit ? 0 : $context['total_visible_posts'] - $start - $limit;
		$firstIndex = $limit - 1;
	}

	// Get each post and poster in this topic.
	$request = $smcFunc['db_query']('display_get_post_poster', '
		SELECT id_msg, id_member, approved
		FROM {db_prefix}messages
		WHERE id_topic = {int:current_topic}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : (!empty($modSettings['db_mysql_group_by_fix']) ? '' : '
		GROUP BY id_msg') . '
		HAVING (approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR id_member = {int:current_member}') . ')') . '
		ORDER BY id_msg ' . ($ascending ? '' : 'DESC') . ($context['messages_per_page'] == -1 ? '' : '
		LIMIT ' . $start . ', ' . $limit),
		array(
			'current_member' => $user_info['id'],
			'current_topic' => $topic,
			'is_approved' => 1,
			'blank_id_member' => 0,
		)
	);

	$messages = array();
	$all_posters = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!empty($row['id_member']))
			$all_posters[$row['id_msg']] = $row['id_member'];
		$messages[] = $row['id_msg'];
	}
	$smcFunc['db_free_result']($request);
	$posters = array_unique($all_posters);

	call_integration_hook('integrate_display_message_list', array($messages, $posters));

	// Guests can't mark topics read or for notifications, just can't sorry.
	if (!$user_info['is_guest'] && !empty($messages))
	{
		$mark_at_msg = max($messages);
		if ($mark_at_msg >= $topicinfo['id_last_msg'])
			$mark_at_msg = $modSettings['maxMsgID'];
		if ($mark_at_msg >= $topicinfo['new_from'])
		{
			$smcFunc['db_insert']($topicinfo['new_from'] == 0 ? 'ignore' : 'replace',
				'{db_prefix}log_topics',
				array(
					'id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'disregarded' => 'int',
				),
				array(
					$user_info['id'], $topic, $mark_at_msg, $topicinfo['disregarded'],
				),
				array('id_member', 'id_topic')
			);
		}

		// Check for notifications on this topic.
		$request = $smcFunc['db_query']('', '
			SELECT sent, id_topic
			FROM {db_prefix}log_notify
			WHERE id_topic = {int:current_topic}
				AND id_member = {int:current_member}
			LIMIT 2',
			array(
				'current_member' => $user_info['id'],
				'current_topic' => $topic,
			)
		);
		$do_once = true;
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Find if this topic is marked for notification...
			if (!empty($row['id_topic']))
				$context['is_marked_notify'] = true;

			// Only do this once, but mark the notifications as "not sent yet" for next time.
			if (!empty($row['sent']) && $do_once)
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}log_notify
					SET sent = {int:is_not_sent}
					WHERE id_topic = {int:current_topic}
						AND id_member = {int:current_member}',
					array(
						'current_member' => $user_info['id'],
						'current_topic' => $topic,
						'is_not_sent' => 0,
					)
				);
				$do_once = false;
			}
		}
	}

	// If there _are_ messages here... (probably an error otherwise :!)
	if (!empty($messages))
	{
		$msg_parameters = array(
			'message_list' => $messages,
			'new_from' => $topicinfo['new_from'],
		);
		$msg_selects = array();
		$msg_tables = array();
		call_integration_hook('integrate_query_message', array($msg_selects, $msg_tables, $msg_parameters));

		// What?  It's not like it *couldn't* be only guests in this topic...
		if (!empty($posters))
			loadMemberData($posters);
		$messages_request = $smcFunc['db_query']('', '
			SELECT
				id_msg, subject, poster_time, poster_ip, id_member, modified_time, modified_name, body,
				smileys_enabled, poster_name, poster_email, approved,
				id_msg_modified < {int:new_from} AS is_read
				' . (!empty($msg_selects) ? implode(',', $msg_selects) : '') . '
			FROM {db_prefix}messages
				' . (!empty($msg_tables) ? implode("\n\t", $msg_tables) : '') . '
			WHERE id_msg IN ({array_int:message_list})
			ORDER BY id_msg' . (empty($options['view_newest_first']) ? '' : ' DESC'),
			$msg_parameters
		);

		// Go to the last message if the given time is beyond the time of the last message.
		if (isset($context['start_from']) && $context['start_from'] >= $topicinfo['num_replies'])
			$context['start_from'] = $topicinfo['num_replies'];

		// Since the anchor information is needed on the top of the page we load these variables beforehand.
		$context['first_message'] = isset($messages[$firstIndex]) ? $messages[$firstIndex] : $messages[0];
		if (empty($options['view_newest_first']))
			$context['first_new_message'] = isset($context['start_from']) && $_REQUEST['start'] == $context['start_from'];
		else
			$context['first_new_message'] = isset($context['start_from']) && $_REQUEST['start'] == $topicinfo['num_replies'] - $context['start_from'];
	}
	else
	{
		$messages_request = false;
		$context['first_message'] = 0;
		$context['first_new_message'] = false;
	}

	// Set the callback.  (do you REALIZE how much memory all the messages would take?!?)
	// This will be called from the template.
	$context['get_message'] = 'prepareDisplayContext';

	// Now set all the wonderful, wonderful permissions... like moderation ones...
	$common_permissions = array(
		'can_approve' => 'approve_posts',
		'can_ban' => 'manage_bans',
		'can_sticky' => 'make_sticky',
		'can_merge' => 'merge_any',
		'can_split' => 'split_any',
		'can_mark_notify' => 'mark_any_notify',
		'can_send_topic' => 'send_topic',
		'can_send_pm' => 'pm_send',
		'can_send_email' => 'send_email_to_members',
		'can_report_moderator' => 'report_any',
		'can_moderate_forum' => 'moderate_forum',
		'can_issue_warning' => 'issue_warning',
		'can_restore_topic' => 'move_any',
		'can_restore_msg' => 'move_any',
	);
	foreach ($common_permissions as $contextual => $perm)
		$context[$contextual] = allowedTo($perm);

	// Permissions with _any/_own versions.  $context[YYY] => ZZZ_any/_own.
	$anyown_permissions = array(
		'can_move' => 'move',
		'can_lock' => 'lock',
		'can_delete' => 'remove',
		'can_reply' => 'post_reply',
		'can_reply_unapproved' => 'post_unapproved_replies',
	);
	foreach ($anyown_permissions as $contextual => $perm)
		$context[$contextual] = allowedTo($perm . '_any') || ($context['user']['started'] && allowedTo($perm . '_own'));

	// Cleanup all the permissions with extra stuff...
	$context['can_mark_notify'] &= !$context['user']['is_guest'];
	$context['can_sticky'] &= !empty($modSettings['enableStickyTopics']);
	$context['can_reply'] &= empty($topicinfo['locked']) || allowedTo('moderate_board');
	$context['can_reply_unapproved'] &= $modSettings['postmod_active'] && (empty($topicinfo['locked']) || allowedTo('moderate_board'));
	$context['can_issue_warning'] &= in_array('w', $context['admin_features']) && $modSettings['warning_settings'][0] == 1;
	// Handle approval flags...
	$context['can_reply_approved'] = $context['can_reply'];
	$context['can_reply'] |= $context['can_reply_unapproved'];
	$context['can_quote'] = $context['can_reply'] && (empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC'])));
	$context['can_mark_unread'] = !$user_info['is_guest'] && $settings['show_mark_read'];
	$context['can_disregard'] = !$user_info['is_guest'] && $modSettings['enable_disregard'];

	$context['can_send_topic'] = (!$modSettings['postmod_active'] || $topicinfo['approved']) && allowedTo('send_topic');
	$context['can_print'] = empty($modSettings['disable_print_topic']);

	// Start this off for quick moderation - it will be or'd for each post.
	$context['can_remove_post'] = allowedTo('delete_any') || (allowedTo('delete_replies') && $context['user']['started']);

	// Check if the draft functions are enabled and that they have permission to use them (for quick reply.)
	$context['drafts_save'] = !empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_post_enabled']) && allowedTo('post_draft') && $context['can_reply'];
	$context['drafts_autosave'] = !empty($context['drafts_save']) && !empty($modSettings['drafts_autosave_enabled']) && allowedTo('post_autosave_draft');
	if (!empty($context['drafts_save']))
		loadLanguage('Drafts');

	// Load up the "double post" sequencing magic.
	if (!empty($options['display_quick_reply']))
	{
		checkSubmitOnce('register');
		$context['name'] = isset($_SESSION['guest_name']) ? $_SESSION['guest_name'] : '';
		$context['email'] = isset($_SESSION['guest_email']) ? $_SESSION['guest_email'] : '';
		if (!empty($options['use_editor_quick_reply']) && $context['can_reply'])
		{
			// Needed for the editor.
			require_once($sourcedir . '/Subs-Editor.php');

			// Now create the editor.
			$editorOptions = array(
				'id' => 'message',
				'value' => '',
				'labels' => array(
					'post_button' => $txt['post'],
				),
				// add height and width for the editor
				'height' => '250px',
				'width' => '100%',
				// We do XML preview here.
				'preview_type' => 0,
			);
			create_control_richedit($editorOptions);

			// Store the ID.
			$context['post_box_name'] = $editorOptions['id'];
		}
	}

	// Build the normal button array.
	$context['normal_buttons'] = array(
		'reply' => array('test' => 'can_reply', 'text' => 'reply', 'image' => 'reply.png', 'lang' => true, 'url' => $scripturl . '?action=post;topic=' . $context['current_topic'] . '.' . $context['start'] . ';last_msg=' . $context['topic_last_message'], 'active' => true),
		'notify' => array('test' => 'can_mark_notify', 'text' => $context['is_marked_notify'] ? 'unnotify' : 'notify', 'image' => ($context['is_marked_notify'] ? 'un' : '') . 'notify.png', 'lang' => true, 'custom' => 'onclick="return confirm(\'' . ($context['is_marked_notify'] ? $txt['notification_disable_topic'] : $txt['notification_enable_topic']) . '\');"', 'url' => $scripturl . '?action=notify;sa=' . ($context['is_marked_notify'] ? 'off' : 'on') . ';topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
		'mark_unread' => array('test' => 'can_mark_unread', 'text' => 'mark_unread', 'image' => 'markunread.png', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=topic;t=' . $context['mark_unread_time'] . ';topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
		'disregard' => array('test' => 'can_disregard', 'text' => ($context['topic_disregarded'] ? 'un' : '') . 'disregard', 'image' => ($context['topic_disregarded'] ? 'un' : '') . 'disregard.png', 'lang' => true, 'url' => $scripturl . '?action=disregardtopic;topic=' . $context['current_topic'] . '.' . $context['start'] . ';sa=' . ($context['topic_disregarded'] ? 'off' : 'on') . ';' . $context['session_var'] . '=' . $context['session_id']),
		'send' => array('test' => 'can_send_topic', 'text' => 'send_topic', 'image' => 'sendtopic.png', 'lang' => true, 'url' => $scripturl . '?action=emailuser;sa=sendtopic;topic=' . $context['current_topic'] . '.0'),
	);

	// Build the mod button array
	$context['mod_buttons'] = array(
		'delete' => array('test' => 'can_delete', 'text' => 'remove_topic', 'image' => 'admin_rem.png', 'lang' => true, 'custom' => 'onclick="return confirm(\'' . $txt['are_sure_remove_topic'] . '\');"', 'url' => $scripturl . '?action=removetopic2;topic=' . $context['current_topic'] . '.0;' . $context['session_var'] . '=' . $context['session_id']),
		'lock' => array('test' => 'can_lock', 'text' => empty($context['is_locked']) ? 'set_lock' : 'set_unlock', 'image' => 'admin_lock.png', 'lang' => true, 'url' => $scripturl . '?action=lock;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
		'sticky' => array('test' => 'can_sticky', 'text' => empty($context['is_sticky']) ? 'set_sticky' : 'set_nonsticky', 'image' => 'admin_sticky.png', 'lang' => true, 'url' => $scripturl . '?action=sticky;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']),
		'merge' => array('test' => 'can_merge', 'text' => 'merge', 'image' => 'merge.png', 'lang' => true, 'url' => $scripturl . '?action=mergetopics;from=' . $context['current_topic']),
	);

	// Restore topic. eh?  No monkey business.
	if ($context['can_restore_topic'])
		$context['mod_buttons'][] = array('text' => 'restore_topic', 'image' => '', 'lang' => true, 'url' => $scripturl . '?action=restoretopic;topics=' . $context['current_topic'] . ';' . $context['session_var'] . '=' . $context['session_id']);

	// Allow adding new mod buttons easily.
	// Note: $context['normal_buttons'] and $context['mod_buttons'] are added for backward compatibility with 2.0, but are deprecated and should not be used
	call_integration_hook('integrate_display_buttons', array($context['normal_buttons']));
	// Note: integrate_mod_buttons is no more necessary and deprecated, but is kept for backward compatibility with 2.0
	call_integration_hook('integrate_mod_buttons', array($context['mod_buttons']));
}

/**
 * Callback for the message display.
 * It actually gets and prepares the message context.
 * This function will start over from the beginning if reset is set to true, which is
 * useful for showing an index before or after the posts.
 *
 * @param bool $reset default false.
 */
function prepareDisplayContext($reset = false)
{
	global $settings, $txt, $modSettings, $scripturl, $options, $user_info, $smcFunc;
	global $memberContext, $context, $messages_request, $topic, $topicinfo;

	static $counter = null;

	// If the query returned false, bail.
	if ($messages_request == false)
		return false;

	// Remember which message this is.  (ie. reply #83)
	if ($counter === null || $reset)
		$counter = empty($options['view_newest_first']) ? $context['start'] : $context['total_visible_posts'] - $context['start'];

	// Start from the beginning...
	if ($reset)
		return @$smcFunc['db_data_seek']($messages_request, 0);

	// Attempt to get the next message.
	$message = $smcFunc['db_fetch_assoc']($messages_request);
	if (!$message)
	{
		$smcFunc['db_free_result']($messages_request);
		return false;
	}

	// If you're a lazy bum, you probably didn't give a subject...
	$message['subject'] = $message['subject'] != '' ? $message['subject'] : $txt['no_subject'];

	// Are you allowed to remove at least a single reply?
	$context['can_remove_post'] |= allowedTo('delete_own') && (empty($modSettings['edit_disable_time']) || $message['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()) && $message['id_member'] == $user_info['id'];

	// If it couldn't load, or the user was a guest.... someday may be done with a guest table.
	if (!loadMemberContext($message['id_member'], true))
	{
		// Notice this information isn't used anywhere else....
		$memberContext[$message['id_member']]['name'] = $message['poster_name'];
		$memberContext[$message['id_member']]['id'] = 0;
		$memberContext[$message['id_member']]['group'] = $txt['guest_title'];
		$memberContext[$message['id_member']]['link'] = $message['poster_name'];
		$memberContext[$message['id_member']]['email'] = $message['poster_email'];
		$memberContext[$message['id_member']]['show_email'] = showEmailAddress(true, 0);
		$memberContext[$message['id_member']]['is_guest'] = true;
	}
	else
	{
		$memberContext[$message['id_member']]['can_view_profile'] = allowedTo('profile_view_any') || ($message['id_member'] == $user_info['id'] && allowedTo('profile_view_own'));
		$memberContext[$message['id_member']]['is_topic_starter'] = $message['id_member'] == $context['topic_starter_id'];
		$memberContext[$message['id_member']]['can_see_warning'] = !isset($context['disabled_fields']['warning_status']) && $memberContext[$message['id_member']]['warning_status'] && ($context['user']['can_mod'] || (!$user_info['is_guest'] && !empty($modSettings['warning_show']) && ($modSettings['warning_show'] > 1 || $message['id_member'] == $user_info['id'])));
	}

	$memberContext[$message['id_member']]['ip'] = $message['poster_ip'];
	$memberContext[$message['id_member']]['show_profile_buttons'] = $settings['show_profile_buttons'] && (!empty($memberContext[$message['id_member']]['can_view_profile']) || (!empty($memberContext[$message['id_member']]['website']['url']) && !isset($context['disabled_fields']['website'])) || (in_array($memberContext[$message['id_member']]['show_email'], array('yes', 'yes_permission_override', 'no_through_forum'))) || $context['can_send_pm']);

	// Do the censor thang.
	censorText($message['body']);
	censorText($message['subject']);

	// Run BBC interpreter on the message.
	$message['body'] = parse_bbc($message['body'], $message['smileys_enabled'], $message['id_msg']);

	// Compose the memory eat- I mean message array.
	$output = array(
		'alternate' => $counter % 2,
		'id' => $message['id_msg'],
		'href' => $scripturl . '?topic=' . $topic . '.msg' . $message['id_msg'] . '#msg' . $message['id_msg'],
		'link' => '<a href="' . $scripturl . '?topic=' . $topic . '.msg' . $message['id_msg'] . '#msg' . $message['id_msg'] . '" rel="nofollow">' . $message['subject'] . '</a>',
		'member' => &$memberContext[$message['id_member']],
		'subject' => $message['subject'],
		'time' => timeformat($message['poster_time']),
		'timestamp' => forum_time(true, $message['poster_time']),
		'counter' => $counter,
		'modified' => array(
			'time' => timeformat($message['modified_time']),
			'timestamp' => forum_time(true, $message['modified_time']),
			'name' => $message['modified_name']
		),
		'body' => $message['body'],
		'new' => empty($message['is_read']),
		'approved' => $message['approved'],
		'first_new' => isset($context['start_from']) && $context['start_from'] == $counter,
		'is_ignored' => !empty($modSettings['enable_buddylist']) && !empty($options['posts_apply_ignore_list']) && in_array($message['id_member'], $context['user']['ignoreusers']),
		'can_approve' => !$message['approved'] && $context['can_approve'],
		'can_unapprove' => !empty($modSettings['postmod_active']) && $context['can_approve'] && $message['approved'],
		'can_modify' => (!$context['is_locked'] || allowedTo('moderate_board')) && (allowedTo('modify_any') || (allowedTo('modify_replies') && $context['user']['started']) || (allowedTo('modify_own') && $message['id_member'] == $user_info['id'] && (empty($modSettings['edit_disable_time']) || !$message['approved'] || $message['poster_time'] + $modSettings['edit_disable_time'] * 60 > time()))),
		'can_remove' => allowedTo('delete_any') || (allowedTo('delete_replies') && $context['user']['started']) || (allowedTo('delete_own') && $message['id_member'] == $user_info['id'] && (empty($modSettings['edit_disable_time']) || $message['poster_time'] + $modSettings['edit_disable_time'] * 60 > time())),
		'can_see_ip' => allowedTo('moderate_forum') || ($message['id_member'] == $user_info['id'] && !empty($user_info['id'])),
	);

	// Is this user the message author?
	$output['is_message_author'] = $message['id_member'] == $user_info['id'];
	if (!empty($output['modified']['name']))
		$output['modified']['last_edit_text'] = sprintf($txt['last_edit_by'], $output['modified']['time'], $output['modified']['name']);

	call_integration_hook('integrate_prepare_display_context', array($output, $message));

	if (empty($options['view_newest_first']))
		$counter++;
	else
		$counter--;

	return $output;
}

/**
 * In-topic quick moderation.
 */
function QuickInTopicModeration()
{
	global $sourcedir, $topic, $user_info, $smcFunc, $modSettings, $context, $scripturl;

	// Check the session = get or post.
	checkSession('request');

	require_once($sourcedir . '/RemoveTopic.php');

	if (empty($_REQUEST['msgs']))
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);

	$messages = array();
	foreach ($_REQUEST['msgs'] as $dummy)
		$messages[] = (int) $dummy;

	// We are restoring messages. We handle this in another place.
	if (isset($_REQUEST['restore_selected']))
		redirectexit('action=restoretopic;msgs=' . implode(',', $messages) . ';' . $context['session_var'] . '=' . $context['session_id']);
	if (isset($_REQUEST['split_selection']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT subject
			FROM {db_prefix}messages
			WHERE id_msg = {int:message}
			LIMIT 1',
			array(
				'message' => min($messages),
			)
		);
		list($subname) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		$_SESSION['split_selection'][$topic] = $messages;
		redirectexit('action=splittopics;sa=selectTopics;topic=' . $topic . '.0;subname_enc=' .urlencode($subname) . ';' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Allowed to delete any message?
	if (allowedTo('delete_any'))
		$allowed_all = true;
	// Allowed to delete replies to their messages?
	elseif (allowedTo('delete_replies'))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member_started
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_topic' => $topic,
			)
		);
		list ($starter) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$allowed_all = $starter == $user_info['id'];
	}
	else
		$allowed_all = false;

	// Make sure they're allowed to delete their own messages, if not any.
	if (!$allowed_all)
		isAllowedTo('delete_own');

	// Allowed to remove which messages?
	$request = $smcFunc['db_query']('', '
		SELECT id_msg, subject, id_member, poster_time
		FROM {db_prefix}messages
		WHERE id_msg IN ({array_int:message_list})
			AND id_topic = {int:current_topic}' . (!$allowed_all ? '
			AND id_member = {int:current_member}' : '') . '
		LIMIT ' . count($messages),
		array(
			'current_member' => $user_info['id'],
			'current_topic' => $topic,
			'message_list' => $messages,
		)
	);
	$messages = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!$allowed_all && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + $modSettings['edit_disable_time'] * 60 < time())
			continue;

		$messages[$row['id_msg']] = array($row['subject'], $row['id_member']);
	}
	$smcFunc['db_free_result']($request);

	// Get the first message in the topic - because you can't delete that!
	$request = $smcFunc['db_query']('', '
		SELECT id_first_msg, id_last_msg
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($first_message, $last_message) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Delete all the messages we know they can delete. ($messages)
	foreach ($messages as $message => $info)
	{
		// Just skip the first message - if it's not the last.
		if ($message == $first_message && $message != $last_message)
			continue;
		// If the first message is going then don't bother going back to the topic as we're effectively deleting it.
		elseif ($message == $first_message)
			$topicGone = true;

		removeMessage($message);

		// Log this moderation action ;).
		if (allowedTo('delete_any') && (!allowedTo('delete_own') || $info[1] != $user_info['id']))
			logAction('delete', array('topic' => $topic, 'subject' => $info[0], 'member' => $info[1]));
	}

	redirectexit(!empty($topicGone) ? $scripturl : 'topic=' . $topic . '.' . $_REQUEST['start']);
}

?>