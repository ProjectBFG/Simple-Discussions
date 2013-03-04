<?php

/**
 * Find and retrieve information about recently posted topics, messages, and the like.
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
 * Find the ten most recent posts.
 */
function RecentPosts()
{
	global $txt, $scripturl, $user_info, $context, $modSettings, $sourcedir, $smcFunc;

	loadTemplate('Recent');
	$context['page_title'] = $txt['recent_posts'];

	if (isset($_REQUEST['start']) && $_REQUEST['start'] > 95)
		$_REQUEST['start'] = 95;

	$query_parameters = array();

        $query_parameters['max_id_msg'] = max(0, $modSettings['maxMsgID'] - 100 - $_REQUEST['start'] * 6);

        $context['page_index'] = constructPageIndex($scripturl . '?action=recent', $_REQUEST['start'], min(100, $modSettings['totalMessages']), 10, false);

	$key = 'recent-' . $user_info['id'] . '-' . md5(serialize(array_diff_key($query_parameters, array('max_id_msg' => 0)))) . '-' . (int) $_REQUEST['start'];
	if (empty($modSettings['cache_enable']) || ($messages = cache_get_data($key, 120)) == null)
	{
		$done = false;
		while (!$done)
		{
			// Find the 10 most recent messages they can *view*.
			// @todo SLOW This query is really slow still, probably?
			$request = $smcFunc['db_query']('', '
				SELECT m.id_msg
				FROM {db_prefix}messages AS m
				WHERE m.approved = {int:is_approved}
				ORDER BY m.id_msg DESC
				LIMIT {int:offset}, {int:limit}',
				array_merge($query_parameters, array(
					'is_approved' => 1,
					'offset' => $_REQUEST['start'],
					'limit' => 10,
				))
			);
			// If we don't have 10 results, try again with an unoptimized version covering all rows, and cache the result.
			if (isset($query_parameters['max_id_msg']) && $smcFunc['db_num_rows']($request) < 10)
			{
				$smcFunc['db_free_result']($request);
				$cache_results = true;
				unset($query_parameters['max_id_msg']);
			}
			else
				$done = true;
		}
		$messages = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$messages[] = $row['id_msg'];
		$smcFunc['db_free_result']($request);
		if (!empty($cache_results))
			cache_put_data($key, $messages, 120);
	}

	// Nothing here... Or at least, nothing you can see...
	if (empty($messages))
	{
		$context['posts'] = array();
		return;
	}

	// Get all the most recent posts.
	$request = $smcFunc['db_query']('', '
		SELECT
			m.id_msg, m.subject, m.smileys_enabled, m.poster_time, m.body, m.id_topic,
			t.num_replies, m.id_member, m2.id_member AS id_first_member,
			IFNULL(mem2.real_name, m2.poster_name) AS first_poster_name, t.id_first_msg,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, t.id_last_msg
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}messages AS m2 ON (m2.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = m2.id_member)
		WHERE m.id_msg IN ({array_int:message_list})
		ORDER BY m.id_msg DESC
		LIMIT ' . count($messages),
		array(
			'message_list' => $messages,
		)
	);
	$counter = $_REQUEST['start'] + 1;
	$context['posts'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Censor everything.
		censorText($row['body']);
		censorText($row['subject']);

		// BBC-atize the message.
		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// And build the array.
		$context['posts'][$row['id_msg']] = array(
			'id' => $row['id_msg'],
			'counter' => $counter++,
			'alternate' => $counter % 2,
			'topic' => $row['id_topic'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>',
			'start' => $row['num_replies'],
			'subject' => $row['subject'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'first_poster' => array(
				'id' => $row['id_first_member'],
				'name' => $row['first_poster_name'],
				'href' => empty($row['id_first_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_first_member'],
				'link' => empty($row['id_first_member']) ? $row['first_poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_first_member'] . '">' . $row['first_poster_name'] . '</a>'
			),
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'message' => $row['body'],
			'can_reply' => false,
			'can_mark_notify' => false,
			'can_delete' => false,
			'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] == $row['id_msg']) && (empty($modSettings['edit_disable_time']) || $row['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()),
		);
	}
	$smcFunc['db_free_result']($request);

	// There might be - and are - different permissions between any and own.
	$permissions = array(
		'own' => array(
			'post_reply_own' => 'can_reply',
			'delete_own' => 'can_delete',
		),
		'any' => array(
			'post_reply_any' => 'can_reply',
			'mark_any_notify' => 'can_mark_notify',
			'delete_any' => 'can_delete',
		)
	);

	$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));
	foreach ($context['posts'] as $counter => $dummy)
	{
		// Some posts - the first posts - can't just be deleted.
		$context['posts'][$counter]['can_delete'] &= $context['posts'][$counter]['delete_possible'];

		// And some cannot be quoted...
		$context['posts'][$counter]['can_quote'] = $context['posts'][$counter]['can_reply'] && $quote_enabled;
	}
}

/**
 * Find unread topics and replies.
 */
function UnreadTopics()
{
	global $txt, $scripturl, $sourcedir;
	global $user_info, $context, $settings, $modSettings, $smcFunc, $options;

	// Guests can't have unread things, we don't know anything about them.
	is_not_guest();

	// Prefetching + lots of MySQL work = bad mojo.
	if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
	{
		ob_end_clean();
		header('HTTP/1.1 403 Forbidden');
		die;
	}

	$context['showCheckboxes'] = !empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $settings['show_mark_read'];

	$context['showing_all_topics'] = isset($_GET['all']);
	$context['start'] = (int) $_REQUEST['start'];
	$context['topics_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['topics_per_page']) ? $options['topics_per_page'] : $modSettings['defaultMaxTopics'];
	if ($_REQUEST['action'] == 'unread')
		$context['page_title'] = $context['showing_all_topics'] ? $txt['unread_topics_all'] : $txt['unread_topics_visit'];
	else
		$context['page_title'] = $txt['unread_replies'];

	if ($context['showing_all_topics'] && !empty($context['load_average']) && !empty($modSettings['loadavg_allunread']) && $context['load_average'] >= $modSettings['loadavg_allunread'])
		fatal_lang_error('loadavg_allunread_disabled', false);
	elseif ($_REQUEST['action'] != 'unread' && !empty($context['load_average']) && !empty($modSettings['loadavg_unreadreplies']) && $context['load_average'] >= $modSettings['loadavg_unreadreplies'])
		fatal_lang_error('loadavg_unreadreplies_disabled', false);
	elseif (!$context['showing_all_topics'] && $_REQUEST['action'] == 'unread' && !empty($context['load_average']) && !empty($modSettings['loadavg_unread']) && $context['load_average'] >= $modSettings['loadavg_unread'])
		fatal_lang_error('loadavg_unread_disabled', false);

	// Parameters for the main query.
	$query_parameters = array();

        $context['querystring_board_limits'] = ';start=%1$d';
        
	$sort_methods = array(
		'subject' => 'ms.subject',
		'starter' => 'IFNULL(mems.real_name, ms.poster_name)',
		'replies' => 't.num_replies',
		'views' => 't.num_views',
		'first_post' => 't.id_topic',
		'last_post' => 't.id_last_msg'
	);

	// The default is the most logical: newest first.
	if (!isset($_REQUEST['sort']) || !isset($sort_methods[$_REQUEST['sort']]))
	{
		$context['sort_by'] = 'last_post';
		$_REQUEST['sort'] = 't.id_last_msg';
		$ascending = isset($_REQUEST['asc']);

		$context['querystring_sort_limits'] = $ascending ? ';asc' : '';
	}
	// But, for other methods the default sort is ascending.
	else
	{
		$context['sort_by'] = $_REQUEST['sort'];
		$_REQUEST['sort'] = $sort_methods[$_REQUEST['sort']];
		$ascending = !isset($_REQUEST['desc']);

		$context['querystring_sort_limits'] = ';sort=' . $context['sort_by'] . ($ascending ? '' : ';desc');
	}
	$context['sort_direction'] = $ascending ? 'up' : 'down';

	if (!$context['showing_all_topics'])
		$txt['unread_topics_visit_none'] = strtr($txt['unread_topics_visit_none'], array('?action=unread;all' => '?action=unread;all' . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits']));

	loadTemplate('Recent');
	$context['sub_template'] = $_REQUEST['action'] == 'unread' ? 'unread' : 'replies';

	$is_topics = $_REQUEST['action'] == 'unread';

	// This part is the same for each query.
	$select_clause = '
				ms.subject AS first_subject, ms.poster_time AS first_poster_time, ms.id_topic,
				t.num_replies, t.num_views, ms.id_member AS id_first_member, ml.id_member AS id_last_member,
				ml.poster_time AS last_poster_time, IFNULL(mems.real_name, ms.poster_name) AS first_poster_name,
				IFNULL(meml.real_name, ml.poster_name) AS last_poster_name, ml.subject AS last_subject,
				t.is_sticky, t.locked, ml.modified_time AS last_modified_time,
				IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from, SUBSTRING(ml.body, 1, 385) AS last_body,
				SUBSTRING(ms.body, 1, 385) AS first_body, ml.smileys_enabled AS last_smileys, ms.smileys_enabled AS first_smileys, t.id_first_msg, t.id_last_msg';

	if ($context['showing_all_topics'])
	{
                $request = $smcFunc['db_query']('', '
                        SELECT MIN(lmr.id_msg)
                        FROM {db_prefix}log_mark_read AS lmr 
                        WHERE lmr.id_member = {int:current_member}',
                        array(
                                'current_member' => $user_info['id'],
                        )
                );
                list ($earliest_msg) = $smcFunc['db_fetch_row']($request);
                $smcFunc['db_free_result']($request);

		// This is needed in case of topics marked unread.
		if (empty($earliest_msg))
			$earliest_msg = 0;
		else
		{
			// Using caching, when possible, to ignore the below slow query.
			if (isset($_SESSION['cached_log_time']) && $_SESSION['cached_log_time'][0] + 45 > time())
				$earliest_msg2 = $_SESSION['cached_log_time'][1];
			else
			{
				// This query is pretty slow, but it's needed to ensure nothing crucial is ignored.
				$request = $smcFunc['db_query']('', '
					SELECT MIN(id_msg)
					FROM {db_prefix}log_topics
					WHERE id_member = {int:current_member}',
					array(
						'current_member' => $user_info['id'],
					)
				);
				list ($earliest_msg2) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				// In theory this could be zero, if the first ever post is unread, so fudge it ;)
				if ($earliest_msg2 == 0)
					$earliest_msg2 = -1;

				$_SESSION['cached_log_time'] = array(time(), $earliest_msg2);
			}

			$earliest_msg = min($earliest_msg2, $earliest_msg);
		}
	}

	// @todo Add modified_time in for log_time check?

	if ($modSettings['totalMessages'] > 100000 && $context['showing_all_topics'])
	{
		$smcFunc['db_query']('', '
			DROP TABLE IF EXISTS {db_prefix}log_topics_unread',
			array(
			)
		);

		// Let's copy things out of the log_topics table, to reduce searching.
		$have_temp_table = $smcFunc['db_query']('', '
			CREATE TEMPORARY TABLE {db_prefix}log_topics_unread (
				PRIMARY KEY (id_topic)
			)
			SELECT lt.id_topic, lt.id_msg
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic)
			WHERE lt.id_member = {int:current_member}' . (empty($earliest_msg) ? '' : '
				AND t.id_last_msg > {int:earliest_msg}') . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . ($modSettings['enable_disregard'] ? '
				AND lt.disregarded != 1' : ''),
			array_merge($query_parameters, array(
				'current_member' => $user_info['id'],
				'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
				'is_approved' => 1,
				'db_error_skip' => true,
			))
		) !== false;
	}
	else
		$have_temp_table = false;

	if ($context['showing_all_topics'] && $have_temp_table)
	{
                // @todoy: duzelt
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*), MIN(t.id_last_msg)
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_member = {int:current_member})
			WHERE ' . (!empty($earliest_msg) ? '
				t.id_last_msg > {int:earliest_msg}' : '') . '
				AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < t.id_last_msg' . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . ($modSettings['enable_disregard'] ? '
				AND lt.disregarded != 1' : ''),
			array_merge($query_parameters, array(
				'current_member' => $user_info['id'],
				'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
				'is_approved' => 1,
			))
		);
		list ($num_topics, $min_message) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Make sure the starting place makes sense and construct the page index.
                $context['page_index'] = constructPageIndex($scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . $context['querystring_board_limits'] . $context['querystring_sort_limits'], $_REQUEST['start'], $num_topics, $context['topics_per_page'], true);
		$context['current_page'] = (int) $_REQUEST['start'] / $context['topics_per_page'];

		$context['links'] = array(
			'first' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'] : '',
			'prev' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] - $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'next' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] + $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'last' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], floor(($num_topics - 1) / $context['topics_per_page']) * $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'up' => $scripturl,
		);
		$context['page_info'] = array(
			'current_page' => $_REQUEST['start'] / $context['topics_per_page'] + 1,
			'num_pages' => floor(($num_topics - 1) / $context['topics_per_page']) + 1
		);

		if ($num_topics == 0)
		{
			$context['topics'] = array();
			if ($context['querystring_board_limits'] == ';start=%1$d')
				$context['querystring_board_limits'] = '';
			else
				$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}
		else
			$min_message = (int) $min_message;

		$request = $smcFunc['db_query']('substring', '
			SELECT ' . $select_clause . '
			FROM {db_prefix}messages AS ms
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ms.id_topic AND t.id_first_msg = ms.id_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_member = {int:current_member})
			WHERE t.id_last_msg >= {int:min_message}
				AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < t.id_last_msg' . ($modSettings['postmod_active'] ? '
				AND ms.approved = {int:is_approved}' : '') . ($modSettings['enable_disregard'] ? '
				AND lt.disregarded != 1' : '') . '
			ORDER BY {raw:sort}
			LIMIT {int:offset}, {int:limit}',
			array_merge($query_parameters, array(
				'current_member' => $user_info['id'],
				'min_message' => $min_message,
				'is_approved' => 1,
				'sort' => $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
				'offset' => $_REQUEST['start'],
				'limit' => $context['topics_per_page'],
			))
		);
	}
	elseif ($is_topics)
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*), MIN(t.id_last_msg)
			FROM {db_prefix}topics AS t' . (!empty($have_temp_table) ? '
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})') . '
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_member = {int:current_member})
			WHERE ' . ($context['showing_all_topics'] && !empty($earliest_msg) ? '
				t.id_last_msg > {int:earliest_msg}' : (!$context['showing_all_topics'] && empty($_SESSION['first_login']) ? '
				t.id_last_msg > {int:id_msg_last_visit}' : '')) . '
				AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < t.id_last_msg' . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . ($modSettings['enable_disregard'] ? '
				AND lt.disregarded != 1' : ''),
			array_merge($query_parameters, array(
				'current_member' => $user_info['id'],
				'earliest_msg' => !empty($earliest_msg) ? $earliest_msg : 0,
				'id_msg_last_visit' => $_SESSION['id_msg_last_visit'],
				'is_approved' => 1,
			))
		);
		list ($num_topics, $min_message) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Make sure the starting place makes sense and construct the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . $context['querystring_board_limits'] . $context['querystring_sort_limits'], $_REQUEST['start'], $num_topics, $context['topics_per_page'], true);
		$context['current_page'] = (int) $_REQUEST['start'] / $context['topics_per_page'];

		$context['links'] = array(
			'first' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'] : '',
			'prev' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] - $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'next' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] + $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'last' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], floor(($num_topics - 1) / $context['topics_per_page']) * $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'up' => $scripturl,
		);
		$context['page_info'] = array(
			'current_page' => $_REQUEST['start'] / $context['topics_per_page'] + 1,
			'num_pages' => floor(($num_topics - 1) / $context['topics_per_page']) + 1
		);

		if ($num_topics == 0)
		{
			$context['topics'] = array();
			if ($context['querystring_board_limits'] == ';start=%d')
				$context['querystring_board_limits'] = '';
			else
				$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}
		else
			$min_message = (int) $min_message;

		$request = $smcFunc['db_query']('substring', '
			SELECT ' . $select_clause . '
			FROM {db_prefix}messages AS ms
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ms.id_topic AND t.id_first_msg = ms.id_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)' . (!empty($have_temp_table) ? '
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})') . '
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_member = {int:current_member})
			WHERE t.id_last_msg >= {int:min_message}
				AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < ml.id_msg' . ($modSettings['postmod_active'] ? '
				AND ms.approved = {int:is_approved}' : '') . ($modSettings['enable_disregard'] ? '
				AND lt.disregarded != 1' : '') . '
			ORDER BY {raw:order}
			LIMIT {int:offset}, {int:limit}',
			array_merge($query_parameters, array(
				'current_member' => $user_info['id'],
				'min_message' => $min_message,
				'is_approved' => 1,
				'order' => $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
				'offset' => $_REQUEST['start'],
				'limit' => $context['topics_per_page'],
			))
		);
	}
	else
	{
		if ($modSettings['totalMessages'] > 100000)
		{
			$smcFunc['db_query']('', '
				DROP TABLE IF EXISTS {db_prefix}topics_posted_in',
				array(
				)
			);

			$smcFunc['db_query']('', '
				DROP TABLE IF EXISTS {db_prefix}log_topics_posted_in',
				array(
				)
			);

			$sortKey_joins = array(
				'ms.subject' => '
					INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)',
				'IFNULL(mems.real_name, ms.poster_name)' => '
					INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
					LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)',
			);

			// The main benefit of this temporary table is not that it's faster; it's that it avoids locks later.
			$have_temp_table = $smcFunc['db_query']('', '
				CREATE TEMPORARY TABLE {db_prefix}topics_posted_in (
					id_topic mediumint(8) unsigned NOT NULL default {string:string_zero},
					id_last_msg int(10) unsigned NOT NULL default {string:string_zero},
					id_msg int(10) unsigned NOT NULL default {string:string_zero},
					PRIMARY KEY (id_topic)
				)
				SELECT t.id_topic, t.id_last_msg, IFNULL(lmr.id_msg, 0) AS id_msg' . (!in_array($_REQUEST['sort'], array('t.id_last_msg', 't.id_topic')) ? ', ' . $_REQUEST['sort'] . ' AS sort_key' : '') . '
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_member = {int:current_member})' . (isset($sortKey_joins[$_REQUEST['sort']]) ? $sortKey_joins[$_REQUEST['sort']] : '') . '
				WHERE m.id_member = {int:current_member}' . ($modSettings['postmod_active'] ? '
					AND t.approved = {int:is_approved}' : '') . ($modSettings['enable_disregard'] ? '
				AND lt.disregarded != 1' : '') . '
				GROUP BY m.id_topic',
				array(
					'current_member' => $user_info['id'],
					'is_approved' => 1,
					'string_zero' => '0',
					'db_error_skip' => true,
				)
			) !== false;

			// If that worked, create a sample of the log_topics table too.
			if ($have_temp_table)
				$have_temp_table = $smcFunc['db_query']('', '
					CREATE TEMPORARY TABLE {db_prefix}log_topics_posted_in (
						PRIMARY KEY (id_topic)
					)
					SELECT lt.id_topic, lt.id_msg
					FROM {db_prefix}log_topics AS lt
						INNER JOIN {db_prefix}topics_posted_in AS pi ON (pi.id_topic = lt.id_topic)
					WHERE lt.id_member = {int:current_member}',
					array(
						'current_member' => $user_info['id'],
						'db_error_skip' => true,
					)
				) !== false;
		}

		if (!empty($have_temp_table))
		{
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(*)
				FROM {db_prefix}topics_posted_in AS pi
					LEFT JOIN {db_prefix}log_topics_posted_in AS lt ON (lt.id_topic = pi.id_topic)
				WHERE IFNULL(lt.id_msg, pi.id_msg) < pi.id_last_msg',
				array_merge($query_parameters, array(
				))
			);
			list ($num_topics) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}
		else
		{
			$request = $smcFunc['db_query']('unread_fetch_topic_count', '
				SELECT COUNT(DISTINCT t.id_topic), MIN(t.id_last_msg)
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic)
					LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_member = {int:current_member})
				WHERE m.id_member = {int:current_member}
					AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < t.id_last_msg' . ($modSettings['postmod_active'] ? '
					AND t.approved = {int:is_approved}' : '') . ($modSettings['enable_disregard'] ? '
					AND lt.disregarded != 1' : ''),
				array_merge($query_parameters, array(
					'current_member' => $user_info['id'],
					'is_approved' => 1,
				))
			);
			list ($num_topics, $min_message) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}

		// Make sure the starting place makes sense and construct the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=' . $_REQUEST['action'] . $context['querystring_board_limits'] . $context['querystring_sort_limits'], $_REQUEST['start'], $num_topics, $context['topics_per_page'], true);
		$context['current_page'] = (int) $_REQUEST['start'] / $context['topics_per_page'];

		$context['links'] = array(
			'first' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits'] : '',
			'prev' => $_REQUEST['start'] >= $context['topics_per_page'] ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] - $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'next' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], $_REQUEST['start'] + $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'last' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? $scripturl . '?action=' . $_REQUEST['action'] . ($context['showing_all_topics'] ? ';all' : '') . sprintf($context['querystring_board_limits'], floor(($num_topics - 1) / $context['topics_per_page']) * $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
			'up' => $scripturl,
		);
		$context['page_info'] = array(
			'current_page' => $_REQUEST['start'] / $context['topics_per_page'] + 1,
			'num_pages' => floor(($num_topics - 1) / $context['topics_per_page']) + 1
		);

		if ($num_topics == 0)
		{
			$context['topics'] = array();
			if ($context['querystring_board_limits'] == ';start=%d')
				$context['querystring_board_limits'] = '';
			else
				$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}

		if (!empty($have_temp_table))
			$request = $smcFunc['db_query']('', '
				SELECT t.id_topic
				FROM {db_prefix}topics_posted_in AS t
					LEFT JOIN {db_prefix}log_topics_posted_in AS lt ON (lt.id_topic = t.id_topic)
				WHERE IFNULL(lt.id_msg, t.id_msg) < t.id_last_msg
				ORDER BY {raw:order}
				LIMIT {int:offset}, {int:limit}',
				array_merge($query_parameters, array(
					'order' => (in_array($_REQUEST['sort'], array('t.id_last_msg', 't.id_topic')) ? $_REQUEST['sort'] : 't.sort_key') . ($ascending ? '' : ' DESC'),
					'offset' => $_REQUEST['start'],
					'limit' => $context['topics_per_page'],
				))
			);
		else
			$request = $smcFunc['db_query']('unread_replies', '
				SELECT DISTINCT t.id_topic
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic AND m.id_member = {int:current_member})' . (strpos($_REQUEST['sort'], 'ms.') === false ? '' : '
					INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)') . (strpos($_REQUEST['sort'], 'mems.') === false ? '' : '
					LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)') . '
					LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
					LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_member = {int:current_member})
				WHERE t.id_last_msg >= {int:min_message}
					AND (IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0))) < t.id_last_msg
					AND t.approved = {int:is_approved}' . ($modSettings['enable_disregard'] ? '
					AND lt.disregarded != 1' : '') . '
				ORDER BY {raw:order}
				LIMIT {int:offset}, {int:limit}',
				array_merge($query_parameters, array(
					'current_member' => $user_info['id'],
					'min_message' => (int) $min_message,
					'is_approved' => 1,
					'order' => $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
					'offset' => $_REQUEST['start'],
					'limit' => $context['topics_per_page'],
					'sort' => $_REQUEST['sort'],
				))
			);

		$topics = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$topics[] = $row['id_topic'];
		$smcFunc['db_free_result']($request);

		// Sanity... where have you gone?
		if (empty($topics))
		{
			$context['topics'] = array();
			if ($context['querystring_board_limits'] == ';start=%d')
				$context['querystring_board_limits'] = '';
			else
				$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
			return;
		}

		$request = $smcFunc['db_query']('substring', '
			SELECT ' . $select_clause . '
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_topic = t.id_topic AND ms.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_member = {int:current_member})
			WHERE t.id_topic IN ({array_int:topic_list})
			ORDER BY ' . $_REQUEST['sort'] . ($ascending ? '' : ' DESC') . '
			LIMIT ' . count($topics),
			array(
				'current_member' => $user_info['id'],
				'topic_list' => $topics,
			)
		);
	}

	$context['topics'] = array();
	$topic_ids = array();

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$topic_ids[] = $row['id_topic'];

		if (!empty($settings['message_index_preview']))
		{
			// Limit them to 128 characters - do this FIRST because it's a lot of wasted censoring otherwise.
			$row['first_body'] = strip_tags(strtr(parse_bbc($row['first_body'], $row['first_smileys'], $row['id_first_msg']), array('<br />' => '&#10;')));
			if ($smcFunc['strlen']($row['first_body']) > 128)
				$row['first_body'] = $smcFunc['substr']($row['first_body'], 0, 128) . '...';
			$row['last_body'] = strip_tags(strtr(parse_bbc($row['last_body'], $row['last_smileys'], $row['id_last_msg']), array('<br />' => '&#10;')));
			if ($smcFunc['strlen']($row['last_body']) > 128)
				$row['last_body'] = $smcFunc['substr']($row['last_body'], 0, 128) . '...';

			// Censor the subject and message preview.
			censorText($row['first_subject']);
			censorText($row['first_body']);

			// Don't censor them twice!
			if ($row['id_first_msg'] == $row['id_last_msg'])
			{
				$row['last_subject'] = $row['first_subject'];
				$row['last_body'] = $row['first_body'];
			}
			else
			{
				censorText($row['last_subject']);
				censorText($row['last_body']);
			}
		}
		else
		{
			$row['first_body'] = '';
			$row['last_body'] = '';
			censorText($row['first_subject']);

			if ($row['id_first_msg'] == $row['id_last_msg'])
				$row['last_subject'] = $row['first_subject'];
			else
				censorText($row['last_subject']);
		}

		// Decide how many pages the topic should have.
		$topic_length = $row['num_replies'] + 1;
		$messages_per_page = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
		if ($topic_length > $messages_per_page)
		{
			$tmppages = array();
			$tmpa = 1;
			for ($tmpb = 0; $tmpb < $topic_length; $tmpb += $messages_per_page)
			{
				$tmppages[] = '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.' . $tmpb . ';topicseen">' . $tmpa . '</a>';
				$tmpa++;
			}
			// Show links to all the pages?
			if (count($tmppages) <= 5)
				$pages = '&#171; ' . implode(' ', $tmppages);
			// Or skip a few?
			else
				$pages = '&#171; ' . $tmppages[0] . ' ' . $tmppages[1] . ' ... ' . $tmppages[count($tmppages) - 2] . ' ' . $tmppages[count($tmppages) - 1];

			if (!empty($modSettings['enableAllMessages']) && $topic_length < $modSettings['enableAllMessages'])
				$pages .= ' &nbsp;<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0;all">' . $txt['all'] . '</a>';
			$pages .= ' &#187;';
		}
		else
			$pages = '';

		// And build the array.
		$context['topics'][$row['id_topic']] = array(
			'id' => $row['id_topic'],
			'first_post' => array(
				'id' => $row['id_first_msg'],
				'member' => array(
					'name' => $row['first_poster_name'],
					'id' => $row['id_first_member'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_first_member'],
					'link' => !empty($row['id_first_member']) ? '<a class="preview" href="' . $scripturl . '?action=profile;u=' . $row['id_first_member'] . '" title="' . $txt['profile_of'] . ' ' . $row['first_poster_name'] . '">' . $row['first_poster_name'] . '</a>' : $row['first_poster_name']
				),
				'time' => timeformat($row['first_poster_time']),
				'timestamp' => forum_time(true, $row['first_poster_time']),
				'subject' => $row['first_subject'],
				'preview' => $row['first_body'],
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0;topicseen',
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0;topicseen">' . $row['first_subject'] . '</a>'
			),
			'last_post' => array(
				'id' => $row['id_last_msg'],
				'member' => array(
					'name' => $row['last_poster_name'],
					'id' => $row['id_last_member'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_last_member'],
					'link' => !empty($row['id_last_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_last_member'] . '">' . $row['last_poster_name'] . '</a>' : $row['last_poster_name']
				),
				'time' => timeformat($row['last_poster_time']),
				'timestamp' => forum_time(true, $row['last_poster_time']),
				'subject' => $row['last_subject'],
				'preview' => $row['last_body'],
				'href' => $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . ';topicseen#msg' . $row['id_last_msg'],
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . ';topicseen#msg' . $row['id_last_msg'] . '" rel="nofollow">' . $row['last_subject'] . '</a>'
			),
			'new_from' => $row['new_from'],
			'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . ';topicseen#new',
			'href' => $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['new_from']) . ';topicseen' . ($row['num_replies'] == 0 ? '' : 'new'),
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['new_from']) . ';topicseen#msg' . $row['new_from'] . '" rel="nofollow">' . $row['first_subject'] . '</a>',
			'is_sticky' => !empty($modSettings['enableStickyTopics']) && !empty($row['is_sticky']),
			'is_locked' => !empty($row['locked']),
			'is_hot' => $row['num_replies'] >= $modSettings['hotTopicPosts'],
			'is_very_hot' => $row['num_replies'] >= $modSettings['hotTopicVeryPosts'],
			'is_posted_in' => false,
			'subject' => $row['first_subject'],
			'pages' => $pages,
			'replies' => comma_format($row['num_replies']),
			'views' => comma_format($row['num_views'])
		);

		$context['topics'][$row['id_topic']]['first_post']['started_by'] = sprintf($txt['topic_started_by'], $context['topics'][$row['id_topic']]['first_post']['member']['link']);
		determineTopicClass($context['topics'][$row['id_topic']]);
	}
	$smcFunc['db_free_result']($request);

	if ($is_topics && !empty($modSettings['enableParticipation']) && !empty($topic_ids))
	{
		$result = $smcFunc['db_query']('', '
			SELECT id_topic
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member = {int:current_member}
			GROUP BY id_topic
			LIMIT {int:limit}',
			array(
				'current_member' => $user_info['id'],
				'topic_list' => $topic_ids,
				'limit' => count($topic_ids),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			if (empty($context['topics'][$row['id_topic']]['is_posted_in']))
			{
				$context['topics'][$row['id_topic']]['is_posted_in'] = true;
				$context['topics'][$row['id_topic']]['class'] = 'my_' . $context['topics'][$row['id_topic']]['class'];
			}
		}
		$smcFunc['db_free_result']($result);
	}

	$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
	$context['topics_to_mark'] = implode('-', $topic_ids);

	if ($settings['show_mark_read'])
	{
		// Build the recent button array.
		if ($is_topics)
		{
			$context['recent_buttons'] = array(
				'markread' => array('text' => 'mark_as_read', 'image' => 'markread.png', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=all' . $context['querystring_board_limits'] . ';' . $context['session_var'] . '=' . $context['session_id']),
			);

			if ($context['showCheckboxes'])
				$context['recent_buttons']['markselectread'] = array(
					'text' => 'quick_mod_markread',
					'image' => 'markselectedread.png',
					'lang' => true,
					'url' => 'javascript:document.quickModForm.submit();',
				);

			if (!empty($context['topics']) && !$context['showing_all_topics'])
				$context['recent_buttons']['readall'] = array('text' => 'unread_topics_all', 'image' => 'markreadall.png', 'lang' => true, 'url' => $scripturl . '?action=unread;all' . $context['querystring_board_limits'], 'active' => true);
		}
		elseif (!$is_topics && isset($context['topics_to_mark']))
		{
			$context['recent_buttons'] = array(
				'markread' => array('text' => 'mark_as_read', 'image' => 'markread.png', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=unreadreplies;topics=' . $context['topics_to_mark'] . ';' . $context['session_var'] . '=' . $context['session_id']),
			);

			if ($context['showCheckboxes'])
				$context['recent_buttons']['markselectread'] = array(
					'text' => 'quick_mod_markread',
					'image' => 'markselectedread.png',
					'lang' => true,
					'url' => 'javascript:document.quickModForm.submit();',
				);
		}

		// Allow mods to add additional buttons here
		call_integration_hook('integrate_recent_buttons');
	}

	// Allow helpdesks and bug trackers and what not to add their own unread data (just add a template_layer to show custom stuff in the template!)
 	call_integration_hook('integrate_unread_list');
}

?>