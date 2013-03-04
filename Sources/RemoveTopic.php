<?php

/**
 * The contents of this file handle the deletion of topics, posts, and related
 * paraphernalia.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/*	The contents of this file handle the deletion of topics, posts, and related
	paraphernalia.  It has the following functions:

*/

/**
 * Completely remove an entire topic.
 * Redirects to the index when completed.
 */
function RemoveTopic2()
{
	global $user_info, $topic, $sourcedir, $smcFunc, $context, $modSettings;

	// Make sure they aren't being lead around by someone. (:@)
	checkSession('get');

	// This file needs to be included for sendNotifications().
	require_once($sourcedir . '/Subs-Post.php');

	// Trying to fool us around, are we?
	if (empty($topic))
		redirectexit();

	$request = $smcFunc['db_query']('', '
		SELECT t.id_member_started, ms.subject, t.approved
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($starter, $subject, $approved) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if ($starter == $user_info['id'] && !allowedTo('remove_any'))
		isAllowedTo('remove_own');
	else
		isAllowedTo('remove_any');

	// Can they see the topic?
	if ($modSettings['postmod_active'] && !$approved && $starter != $user_info['id'])
		isAllowedTo('approve_posts');

	// Notify people that this topic has been removed.
	sendNotifications($topic, 'remove');

	removeTopics($topic);

	// Note, only log topic ID in native form if it's not gone forever.
	if (allowedTo('remove_any') || (allowedTo('remove_own') && $starter == $user_info['id']))
		logAction('remove', array('topic' => $topic, 'subject' => $subject, 'member' => $starter));

	redirectexit();
}

/**
 * Remove just a single post.
 * On completion redirect to the topic or to the index.
 */
function DeleteMessage()
{
	global $user_info, $topic, $modSettings, $smcFunc;

	checkSession('get');

	$_REQUEST['msg'] = (int) $_REQUEST['msg'];

	// Is $topic set?
	if (empty($topic) && isset($_REQUEST['topic']))
		$topic = (int) $_REQUEST['topic'];

	$request = $smcFunc['db_query']('', '
		SELECT t.id_member_started, m.id_member, m.subject, m.poster_time, m.approved
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = {int:id_msg} AND m.id_topic = {int:current_topic})
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
			'id_msg' => $_REQUEST['msg'],
		)
	);
	list ($starter, $poster, $subject, $post_time, $approved) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Verify they can see this!
	if ($modSettings['postmod_active'] && !$approved && !empty($poster) && $poster != $user_info['id'])
		isAllowedTo('approve_posts');

	if ($poster == $user_info['id'])
	{
		if (!allowedTo('delete_own'))
		{
			if ($starter == $user_info['id'] && !allowedTo('delete_any'))
				isAllowedTo('delete_replies');
			elseif (!allowedTo('delete_any'))
				isAllowedTo('delete_own');
		}
		elseif (!allowedTo('delete_any') && ($starter != $user_info['id'] || !allowedTo('delete_replies')) && !empty($modSettings['edit_disable_time']) && $post_time + $modSettings['edit_disable_time'] * 60 < time())
			fatal_lang_error('modify_post_time_passed', false);
	}
	elseif ($starter == $user_info['id'] && !allowedTo('delete_any'))
		isAllowedTo('delete_replies');
	else
		isAllowedTo('delete_any');

	// If the full topic was removed go back to the index.
	$full_topic = removeMessage($_REQUEST['msg']);

	if (allowedTo('delete_any') && (!allowedTo('delete_own') || $poster != $user_info['id']))
		logAction('delete', array('topic' => $topic, 'subject' => $subject, 'member' => $poster));

	// We want to redirect back to recent action.
	if (isset($_REQUEST['recent']))
		redirectexit('action=recent');
	elseif (isset($_REQUEST['profile'], $_REQUEST['start'], $_REQUEST['u']))
		redirectexit('action=profile;u=' . $_REQUEST['u'] . ';area=showposts;start=' . $_REQUEST['start']);
	elseif ($full_topic)
		redirectexit();
	else
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
}

/**
 * So long as you are sure... all old posts will be gone.
 * Used in ManageMaintenance.php to prune old topics.
 */
function RemoveOldTopics2()
{
	global $modSettings, $smcFunc;

	isAllowedTo('admin_forum');
	checkSession('post', 'admin');

	// This should exist, but we can make sure.
	$_POST['delete_type'] = isset($_POST['delete_type']) ? $_POST['delete_type'] : 'nothing';

	// Custom conditions.
	$condition = '';
	$condition_params = array(
		'poster_time' => time() - 3600 * 24 * $_POST['maxdays'],
	);

	// Just moved notice topics?
	if ($_POST['delete_type'] == 'moved')
	{
		$condition .= '
			AND t.locked = {int:locked}';
		$condition_params['locked'] = 1;
	}
	// Otherwise, maybe locked topics only?
	elseif ($_POST['delete_type'] == 'locked')
	{
		$condition .= '
			AND t.locked = {int:locked}';
		$condition_params['locked'] = 1;
	}

	// Exclude stickies?
	if (isset($_POST['delete_old_not_sticky']))
	{
		$condition .= '
			AND t.is_sticky = {int:is_sticky}';
		$condition_params['is_sticky'] = 0;
	}

	// All we're gonna do here is grab the id_topic's and send them to removeTopics().
	$request = $smcFunc['db_query']('', '
		SELECT t.id_topic
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
		WHERE
			m.poster_time < {int:poster_time}' . $condition,
		$condition_params
	);
	$topics = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$topics[] = $row['id_topic'];
	$smcFunc['db_free_result']($request);

	removeTopics($topics, false, true);

	// Log an action into the moderation log.
	logAction('pruned', array('days' => $_POST['maxdays']));

	redirectexit('action=admin;area=maintain;sa=topics;done=purgeold');
}

/**
 * Removes the passed id_topic's. (permissions are NOT checked here!).
 *
 * @param array/int $topics The topics to remove (can be an id or an array of ids).
 * @param bool $decreasePostCount if true users' post count will be reduced
 */
function removeTopics($topics, $decreasePostCount = true)
{
	global $sourcedir, $modSettings, $smcFunc;

	// Nothing to do?
	if (empty($topics))
		return;
	// Only a single topic.
	if (is_numeric($topics))
		$topics = array($topics);

	// Decrease the post counts.
	if ($decreasePostCount)
	{
		$requestMembers = $smcFunc['db_query']('', '
			SELECT m.id_member, COUNT(*) AS posts
			FROM {db_prefix}messages AS m
			WHERE m.id_topic IN ({array_int:topics})
				AND m.approved = {int:is_approved}
			GROUP BY m.id_member',
			array(
				'topics' => $topics,
				'is_approved' => 1,
			)
		);
		if ($smcFunc['db_num_rows']($requestMembers) > 0)
		{
			while ($rowMembers = $smcFunc['db_fetch_assoc']($requestMembers))
				updateMemberData($rowMembers['id_member'], array('posts' => 'posts - ' . $rowMembers['posts']));
		}
		$smcFunc['db_free_result']($requestMembers);
	}

	// Still topics left to delete?
	if (empty($topics))
		return;

	// Delete possible search index entries.
	if (!empty($modSettings['search_custom_index_config']))
	{
		$customIndexSettings = unserialize($modSettings['search_custom_index_config']);

		$words = array();
		$messages = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_msg, body
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})',
			array(
				'topics' => $topics,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();

			$words = array_merge($words, text2words($row['body'], $customIndexSettings['bytes_per_word'], true));
			$messages[] = $row['id_msg'];
		}
		$smcFunc['db_free_result']($request);
		$words = array_unique($words);

		if (!empty($words) && !empty($messages))
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_search_words
				WHERE id_word IN ({array_int:word_list})
					AND id_msg IN ({array_int:message_list})',
				array(
					'word_list' => $words,
					'message_list' => $messages,
				)
			);
	}

	// Delete anything related to the topic.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}messages
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_topics
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_notify
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}topics
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_search_subjects
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);

	// Maybe there's a mod that wants to delete topic related data of its own
 	call_integration_hook('integrate_remove_topics', array($topics));

	// Update the totals...
	updateStats('message');
	updateStats('topic');
}

/**
 * Remove a specific message (including permission checks).
 * - normally, local and global should be the localCookies and globalCookies settings, respectively.
 * - uses boardurl to determine these two things.
 *
 * @param int $message The message id
 * @param bool $decreasePostCount if true users' post count will be reduced
 * @return array an array to set the cookie on with domain and path in it, in that order
 */
function removeMessage($message, $decreasePostCount = true)
{
	global $sourcedir, $modSettings, $user_info, $smcFunc, $context;

	if (empty($message) || !is_numeric($message))
		return false;

	$request = $smcFunc['db_query']('', '
		SELECT
			m.id_member, m.poster_time, m.subject,' . (empty($modSettings['search_custom_index_config']) ? '' : ' m.body,') . '
			m.approved, t.id_topic, t.id_first_msg, t.id_last_msg, t.num_replies,
			t.id_member_started AS id_member_poster,
			b.count_posts
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
		WHERE m.id_msg = {int:id_msg}
		LIMIT 1',
		array(
			'id_msg' => $message,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		return false;
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

        // Check permissions to delete this message.
        if ($row['id_member'] == $user_info['id'])
        {
                if (!allowedTo('delete_own'))
                {
                        if ($row['id_member_poster'] == $user_info['id'] && !allowedTo('delete_any'))
                                isAllowedTo('delete_replies');
                        elseif (!allowedTo('delete_any'))
                                isAllowedTo('delete_own');
                }
                elseif (!allowedTo('delete_any') && ($row['id_member_poster'] != $user_info['id'] || !allowedTo('delete_replies')) && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + $modSettings['edit_disable_time'] * 60 < time())
                        fatal_lang_error('modify_post_time_passed', false);
        }
        elseif ($row['id_member_poster'] == $user_info['id'] && !allowedTo('delete_any'))
                isAllowedTo('delete_replies');
        else
                isAllowedTo('delete_any');

        if ($modSettings['postmod_active'] && !$row['approved'] && $row['id_member'] != $user_info['id'] && !allowedTo('delete_own'))
                isAllowedTo('approve_posts');

	// Close any moderation reports for this message.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_reported
		SET closed = {int:is_closed}
		WHERE id_msg = {int:id_msg}',
		array(
			'is_closed' => 1,
			'id_msg' => $message,
		)
	);
	if ($smcFunc['db_affected_rows']() != 0)
	{
		require_once($sourcedir . '/ModerationCenter.php');
		updateSettings(array('last_mod_report_action' => time()));
		recountOpenReports();
	}

	// Delete the *whole* topic, but only if the topic consists of one message.
	if ($row['id_first_msg'] == $message)
	{
		
                // Check permissions to delete a whole topic.
                if ($row['id_member'] != $user_info['id'])
                        isAllowedTo('remove_any');
                elseif (!allowedTo('remove_any'))
                        isAllowedTo('remove_own');

		// ...if there is only one post.
		if (!empty($row['num_replies']))
			fatal_lang_error('delFirstPost', false);

		removeTopics($row['id_topic']);
		return true;
	}

	// This is the last post.
	if ($row['id_last_msg'] == $message)
	{
		// Find the last message, set it, and decrease the post count.
		$request = $smcFunc['db_query']('', '
			SELECT id_msg, id_member
			FROM {db_prefix}messages
			WHERE id_topic = {int:id_topic}
				AND id_msg != {int:id_msg}
			ORDER BY ' . ($modSettings['postmod_active'] ? 'approved DESC, ' : '') . 'id_msg DESC
			LIMIT 1',
			array(
				'id_topic' => $row['id_topic'],
				'id_msg' => $message,
			)
		);
		$row2 = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET
				id_last_msg = {int:id_last_msg},
				id_member_updated = {int:id_member_updated}' . (!$modSettings['postmod_active'] || $row['approved'] ? ',
				num_replies = CASE WHEN num_replies = {int:no_replies} THEN 0 ELSE num_replies - 1 END' : ',
				unapproved_posts = CASE WHEN unapproved_posts = {int:no_unapproved} THEN 0 ELSE unapproved_posts - 1 END') . '
			WHERE id_topic = {int:id_topic}',
			array(
				'id_last_msg' => $row2['id_msg'],
				'id_member_updated' => $row2['id_member'],
				'no_replies' => 0,
				'no_unapproved' => 0,
				'id_topic' => $row['id_topic'],
			)
		);
	}
	// Only decrease post counts.
	else
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET ' . ($row['approved'] ? '
				num_replies = CASE WHEN num_replies = {int:no_replies} THEN 0 ELSE num_replies - 1 END' : '
				unapproved_posts = CASE WHEN unapproved_posts = {int:no_unapproved} THEN 0 ELSE unapproved_posts - 1 END') . '
			WHERE id_topic = {int:id_topic}',
			array(
				'no_replies' => 0,
				'no_unapproved' => 0,
				'id_topic' => $row['id_topic'],
			)
		);

	// If the poster was registered and the board this message was on incremented
	// the member's posts when it was posted, decrease his or her post count.
	if (!empty($row['id_member']) && $decreasePostCount && empty($row['count_posts']) && $row['approved'])
		updateMemberData($row['id_member'], array('posts' => '-'));

        // Remove the message!
        $smcFunc['db_query']('', '
                DELETE FROM {db_prefix}messages
                WHERE id_msg = {int:id_msg}',
                array(
                        'id_msg' => $message,
                )
        );

        if (!empty($modSettings['search_custom_index_config']))
        {
                $customIndexSettings = unserialize($modSettings['search_custom_index_config']);
                $words = text2words($row['body'], $customIndexSettings['bytes_per_word'], true);
                if (!empty($words))
                        $smcFunc['db_query']('', '
                                DELETE FROM {db_prefix}log_search_words
                                WHERE id_word IN ({array_int:word_list})
                                        AND id_msg = {int:id_msg}',
                                array(
                                        'word_list' => $words,
                                        'id_msg' => $message,
                                )
                        );
        }

        // Allow mods to remove message related data of their own (likes, maybe?)
        call_integration_hook('integrate_remove_message', array($message));

	// Update the pesky statistics.
	updateStats('message');
	updateStats('topic');

	return false;
}

/**
 * Take a load of messages from one place and stick them in a topic
 *
 * @param array $msgs
 * @param integer $from_topic
 * @param integer $target_topic
 */
function mergePosts($msgs = array(), $from_topic, $target_topic)
{
	global $context, $smcFunc, $modSettings, $sourcedir;

	//!!! This really needs to be rewritten to take a load of messages from ANY topic, it's also inefficient.

	// Is it an array?
	if (!is_array($msgs))
		$msgs = array($msgs);

	// Lets make sure they are int.
	foreach ($msgs as $key => $msg)
		$msgs[$key] = (int) $msg;

	// Get the source information.
	$request = $smcFunc['db_query']('', '
		SELECT t.id_first_msg, t.num_replies, t.unapproved_posts
		FROM {db_prefix}topics AS t
		WHERE t.id_topic = {int:from_topic}',
		array(
			'from_topic' => $from_topic,
		)
	);
	list ($from_first_msg, $from_replies, $from_unapproved_posts) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Get some target topic stats.
	$request = $smcFunc['db_query']('', '
		SELECT t.id_first_msg, t.num_replies, t.unapproved_posts
		FROM {db_prefix}topics AS t
		WHERE t.id_topic = {int:target_topic}',
		array(
			'target_topic' => $target_topic,
		)
	);
	list ($target_first_msg, $target_replies, $target_unapproved_posts) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

        // Lets get the members that need their post count restored.
        $request = $smcFunc['db_query']('', '
                SELECT id_member
                FROM {db_prefix}messages
                WHERE id_msg IN ({array_int:messages})
                        AND approved = {int:is_approved}',
                array(
                        'messages' => $msgs,
                        'is_approved' => 1,
                )
        );

        while ($row = $smcFunc['db_fetch_assoc']($request))
                updateMemberData($row['id_member'], array('posts' => '+'));

	// Time to move the messages.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET
			id_topic = {int:target_topic}
		WHERE id_msg IN({array_int:msgs})',
		array(
			'target_topic' => $target_topic,
			'msgs' => $msgs,
		)
	);

	// Fix the id_first_msg and id_last_msg for the target topic.
	$target_topic_data = array(
		'num_replies' => 0,
		'unapproved_posts' => 0,
		'id_first_msg' => 9999999999,
	);
	$request = $smcFunc['db_query']('', '
		SELECT MIN(id_msg) AS id_first_msg, MAX(id_msg) AS id_last_msg, COUNT(*) AS message_count, approved
		FROM {db_prefix}messages
		WHERE id_topic = {int:target_topic}
		GROUP BY id_topic, approved
		ORDER BY approved ASC
		LIMIT 2',
		array(
			'target_topic' => $target_topic,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($row['id_first_msg'] < $target_topic_data['id_first_msg'])
			$target_topic_data['id_first_msg'] = $row['id_first_msg'];
		$target_topic_data['id_last_msg'] = $row['id_last_msg'];
		if (!$row['approved'])
			$target_topic_data['unapproved_posts'] = $row['message_count'];
		else
			$target_topic_data['num_replies'] = max(0, $row['message_count'] - 1);
	}
	$smcFunc['db_free_result']($request);

	// In some cases we merged the only post in a topic so the topic data is left behind in the topic table.
	$request = $smcFunc['db_query']('', '
		SELECT id_topic
		FROM {db_prefix}messages
		WHERE id_topic = {int:from_topic}',
		array(
			'from_topic' => $from_topic,
		)
	);

	// Remove the topic if it doesn't have any messages.
	$topic_exists = true;
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		removeTopics($from_topic, false, true);
		$topic_exists = false;
	}
	$smcFunc['db_free_result']($request);

	// Recycled topic.
	if ($topic_exists == true)
	{
		// Fix the id_first_msg and id_last_msg for the source topic.
		$source_topic_data = array(
			'num_replies' => 0,
			'unapproved_posts' => 0,
			'id_first_msg' => 9999999999,
		);
		$request = $smcFunc['db_query']('', '
			SELECT MIN(id_msg) AS id_first_msg, MAX(id_msg) AS id_last_msg, COUNT(*) AS message_count, approved, subject
			FROM {db_prefix}messages
			WHERE id_topic = {int:from_topic}
			GROUP BY id_topic, approved
			ORDER BY approved ASC
			LIMIT 2',
			array(
				'from_topic' => $from_topic,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($row['id_first_msg'] < $source_topic_data['id_first_msg'])
				$source_topic_data['id_first_msg'] = $row['id_first_msg'];
			$source_topic_data['id_last_msg'] = $row['id_last_msg'];
			if (!$row['approved'])
				$source_topic_data['unapproved_posts'] = $row['message_count'];
			else
				$source_topic_data['num_replies'] = max(0, $row['message_count'] - 1);
		}
		$smcFunc['db_free_result']($request);

		// Update the topic details for the source topic.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET
				id_first_msg = {int:id_first_msg},
				id_last_msg = {int:id_last_msg},
				num_replies = {int:num_replies},
				unapproved_posts = {int:unapproved_posts}
			WHERE id_topic = {int:from_topic}',
			array(
				'id_first_msg' => $source_topic_data['id_first_msg'],
				'id_last_msg' => $source_topic_data['id_last_msg'],
				'num_replies' => $source_topic_data['num_replies'],
				'unapproved_posts' => $source_topic_data['unapproved_posts'],
				'from_topic' => $from_topic,
			)
		);
	}

	// Finally get around to updating the destination topic, now all indexes etc on the source are fixed.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET
			id_first_msg = {int:id_first_msg},
			id_last_msg = {int:id_last_msg},
			num_replies = {int:num_replies},
			unapproved_posts = {int:unapproved_posts}
		WHERE id_topic = {int:target_topic}',
		array(
			'id_first_msg' => $target_topic_data['id_first_msg'],
			'id_last_msg' => $target_topic_data['id_last_msg'],
			'num_replies' => $target_topic_data['num_replies'],
			'unapproved_posts' => $target_topic_data['unapproved_posts'],
			'target_topic' => $target_topic,
		)
	);

	// Need it to update some stats.
	require_once($sourcedir . '/Subs-Post.php');

	// Update stats.
	updateStats('topic');
	updateStats('message');

	// Subject cache?
	$cache_updates = array();
	if ($target_first_msg != $target_topic_data['id_first_msg'])
		$cache_updates[] = $target_topic_data['id_first_msg'];
	if (!empty($source_topic_data['id_first_msg']) && $from_first_msg != $source_topic_data['id_first_msg'])
		$cache_updates[] = $source_topic_data['id_first_msg'];

	if (!empty($cache_updates))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, subject
			FROM {db_prefix}messages
			WHERE id_msg IN ({array_int:first_messages})',
			array(
				'first_messages' => $cache_updates,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			updateStats('subject', $row['id_topic'], $row['subject']);
		$smcFunc['db_free_result']($request);
	}
}

?>