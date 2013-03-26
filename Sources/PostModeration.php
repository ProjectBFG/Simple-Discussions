<?php

/**
 * This file's job is to handle things related to post moderation.
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
 * This is a handling function for all things post moderation.
 */
function PostModerationMain()
{
	global $sourcedir;

	 // @todo We'll shift these later bud.
	loadLanguage('ModerationCenter');
	loadTemplate('ModerationCenter');

	// Probably need this...
	require_once($sourcedir . '/ModerationCenter.php');

	// Allowed sub-actions, you know the drill by now!
	$subactions = array(
		'approve' => 'ApproveMessage',
		'replies' => 'UnapprovedPosts',
		'topics' => 'UnapprovedPosts',
	);

	// Pick something valid...
	if (!isset($_REQUEST['sa']) || !isset($subactions[$_REQUEST['sa']]))
		$_REQUEST['sa'] = 'replies';

	$subactions[$_REQUEST['sa']]();
}

/**
 * View all unapproved posts.
 */
function UnapprovedPosts()
{
	global $txt, $scripturl, $context, $user_info, $smcFunc;

	$context['current_view'] = isset($_GET['sa']) && $_GET['sa'] == 'topics' ? 'topics' : 'replies';
	$context['page_title'] = $txt['mc_unapproved_posts'];

	$toAction = array();
	// Check if we have something to do?
	if (isset($_GET['approve']))
		$toAction[] = (int) $_GET['approve'];
	// Just a deletion?
	elseif (isset($_GET['delete']))
		$toAction[] = (int) $_GET['delete'];
	// Lots of approvals?
	elseif (isset($_POST['item']))
		foreach ($_POST['item'] as $item)
			$toAction[] = (int) $item;

	// What are we actually doing.
	if (isset($_GET['approve']) || (isset($_POST['do']) && $_POST['do'] == 'approve'))
		$curAction = 'approve';
	elseif (isset($_GET['delete']) || (isset($_POST['do']) && $_POST['do'] == 'delete'))
		$curAction = 'delete';

	// Right, so we have something to do?
	if (!empty($toAction) && isset($curAction))
	{
		checkSession('request');

		// Now for each message work out whether it's actually a topic.
		$request = $smcFunc['db_query']('', '
			SELECT m.id_msg, m.id_member, m.subject, t.id_topic, t.id_first_msg, t.id_member_started
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			WHERE m.id_msg IN ({array_int:message_list})
				AND m.approved = {int:not_approved}',
			array(
				'message_list' => $toAction,
				'not_approved' => 0,
			)
		);
		$toAction = array();
		$details = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// If it's not within what our view is ignore it...
			if (($row['id_msg'] == $row['id_first_msg'] && $context['current_view'] != 'topics') || ($row['id_msg'] != $row['id_first_msg'] && $context['current_view'] != 'replies'))
				continue;

			$can_add = false;
			// If we're approving this is simple.
			if ($curAction == 'approve')
			{
				$can_add = true;
			}
			// Delete requires more permission checks...
			elseif ($curAction == 'delete')
			{
				// Own post is easy!
				if ($row['id_member'] == $user_info['id'])
					$can_add = true;
				// Is it a reply to their own topic?
				elseif ($row['id_member'] == $row['id_member_started'] && $row['id_msg'] != $row['id_first_msg'])
					$can_add = true;
				// Someone elses?
				elseif ($row['id_member'] != $user_info['id'])
					$can_add = true;
			}

			if ($can_add)
				$anItem = $context['current_view'] == 'topics' ? $row['id_topic'] : $row['id_msg'];
			$toAction[] = $anItem;

			// All clear. What have we got now, what, what?
			$details[$anItem] = array();
			$details[$anItem]["subject"] = $row['subject'];
			$details[$anItem]["topic"] = $row['id_topic'];
			$details[$anItem]["member"] = ($context['current_view'] == 'topics') ? $row['id_member_started'] : $row['id_member'];
		}
		$smcFunc['db_free_result']($request);

		// If we have anything left we can actually do the approving (etc).
		if (!empty($toAction))
		{
			if ($curAction == 'approve')
			{
				approveMessages ($toAction, $details, $context['current_view']);
			}
			else
			{
				removeMessages ($toAction, $details, $context['current_view']);
			}
		}
	}

	// How many unapproved posts are there?
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic AND t.id_first_msg != m.id_msg)
		WHERE m.approved = {int:not_approved}
			' . $approve_query,
		array(
			'not_approved' => 0,
		)
	);
	list ($context['total_unapproved_posts']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// What about topics?  Normally we'd use the table alias t for topics but lets use m so we don't have to redo our approve query.
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(m.id_topic)
		FROM {db_prefix}topics AS m
		WHERE m.approved = {int:not_approved}
			' . $approve_query,
		array(
			'not_approved' => 0,
		)
	);
	list ($context['total_unapproved_topics']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$context['page_index'] = constructPageIndex($scripturl . '?action=moderate;area=postmod;sa=' . $context['current_view'] . (isset($_REQUEST['brd']) ? ';brd=' . (int) $_REQUEST['brd'] : ''), $_GET['start'], $context['current_view'] == 'topics' ? $context['total_unapproved_topics'] : $context['total_unapproved_posts'], 10);
	$context['start'] = $_GET['start'];

	// We have enough to make some pretty tabs!
	$context[$context['moderation_menu_name']]['tab_data'] = array(
		'title' => $txt['mc_unapproved_posts'],
		'description' => $txt['mc_unapproved_posts_desc'],
	);

	// Update the tabs with the correct number of posts.
	$context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['posts']['label'] .= ' (' . $context['total_unapproved_posts'] . ')';
	$context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['topics']['label'] .= ' (' . $context['total_unapproved_topics'] . ')';

	// Get all unapproved posts.
	$request = $smcFunc['db_query']('', '
		SELECT m.id_msg, m.id_topic, m.subject, m.body, m.id_member,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.smileys_enabled,
			t.id_member_started, t.id_first_msg
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.approved = {int:not_approved}
			AND t.id_first_msg ' . ($context['current_view'] == 'topics' ? '=' : '!=') . ' m.id_msg
			' . $approve_query . '
		LIMIT ' . $context['start'] . ', 10',
		array(
			'not_approved' => 0,
		)
	);
	$context['unapproved_items'] = array();
	for ($i = 1; $row = $smcFunc['db_fetch_assoc']($request); $i++)
	{
		// Can delete is complicated, let's solve it first... is it their own post?
		if ($row['id_member'] == $user_info['id'])
			$can_delete = true;
		// Is it a reply to their own topic?
		elseif ($row['id_member'] == $row['id_member_started'] && $row['id_msg'] != $row['id_first_msg'])
			$can_delete = true;
		// Someone elses?
		elseif ($row['id_member'] != $user_info['id'])
			$can_delete = true;
		else
			$can_delete = false;

		$context['unapproved_items'][] = array(
			'id' => $row['id_msg'],
			'alternate' => $i % 2,
			'counter' => $context['start'] + $i,
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
			'subject' => $row['subject'],
			'body' => parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']),
			'time' => timeformat($row['poster_time']),
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
			),
			'topic' => array(
				'id' => $row['id_topic'],
			),
			'can_delete' => $can_delete,
		);
	}
	$smcFunc['db_free_result']($request);

	$context['sub_template'] = 'unapproved_posts';
}

/**
 * Approve a post, just the one.
 */
function ApproveMessage()
{
	global $user_info, $topic, $sourcedir, $smcFunc;

	checkSession('get');

	$_REQUEST['msg'] = (int) $_REQUEST['msg'];

	require_once($sourcedir . '/Subs-Post.php');

	isAllowedTo('approve_posts');

	$request = $smcFunc['db_query']('', '
		SELECT t.id_member_started, t.id_first_msg, m.id_member, m.subject, m.approved
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
		WHERE m.id_msg = {int:id_msg}
			AND m.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
			'id_msg' => $_REQUEST['msg'],
		)
	);
	list ($starter, $first_msg, $poster, $subject, $approved) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// If it's the first in a topic then the whole topic gets approved!
	if ($first_msg == $_REQUEST['msg'])
	{
		approveTopics($topic, !$approved);

		if ($starter != $user_info['id'])
			logAction(($approved ? 'un' : '') . 'approve_topic', array('topic' => $topic, 'subject' => $subject, 'member' => $starter));
	}
	else
	{
		approvePosts($_REQUEST['msg'], !$approved);

		if ($poster != $user_info['id'])
			logAction(($approved ? 'un' : '') . 'approve', array('topic' => $topic, 'subject' => $subject, 'member' => $poster));
	}

	redirectexit('topic=' . $topic . '.msg' . $_REQUEST['msg']. '#msg' . $_REQUEST['msg']);
}

/**
 * Approve a batch of posts (or topics in their own right)
 *
 * @param array $messages
 * @param array $messageDetails
 * @param (string) $current_view = replies
 */
function approveMessages($messages, $messageDetails, $current_view = 'replies')
{
	global $sourcedir;

	require_once($sourcedir . '/Subs-Post.php');
	if ($current_view == 'topics')
	{
		approveTopics($messages);
		// and tell the world about it
		foreach ($messages as $topic)
		{
			logAction('approve_topic', array('topic' => $topic, 'subject' => $messageDetails[$topic]['subject'], 'member' => $messageDetails[$topic]['member']));
		}
	}
	else
	{
		approvePosts($messages);
		// and tell the world about it again
		foreach ($messages as $post)
		{
			logAction('approve', array('topic' => $messageDetails[$post]['topic'], 'subject' => $messageDetails[$post]['subject'], 'member' => $messageDetails[$post]['member']));
		}
	}
}

/**
 * This is a helper function - basically approve everything!
 */
function approveAllData()
{
	global $smcFunc, $sourcedir;

	// Start with messages and topics.
	$request = $smcFunc['db_query']('', '
		SELECT id_msg
		FROM {db_prefix}messages
		WHERE approved = {int:not_approved}',
		array(
			'not_approved' => 0,
		)
	);
	$msgs = array();
	while ($row = $smcFunc['db_fetch_row']($request))
		$msgs[] = $row[0];
	$smcFunc['db_free_result']($request);

	if (!empty($msgs))
	{
		require_once($sourcedir . '/Subs-Post.php');
		approvePosts($msgs);
	}
}

/**
 * Remove a batch of messages (or topics)
 *
 * @param array $messages
 * @param array $messageDetails
 * @param string $current_view = replies
 */
function removeMessages($messages, $messageDetails, $current_view = 'replies')
{
	global $sourcedir, $modSettings;

	// @todo something's not right, removeMessage() does check permissions,
	// removeTopics() doesn't
	require_once($sourcedir . '/RemoveTopic.php');
	if ($current_view == 'topics')
	{
		removeTopics($messages);
		// and tell the world about it
		foreach ($messages as $topic)
			// Note, only log topic ID in native form if it's not gone forever.
			logAction('remove', array('topic' => $topic, 'subject' => $messageDetails[$topic]['subject'], 'member' => $messageDetails[$topic]['member']));
	}
	else
	{
		foreach ($messages as $post)
		{
			removeMessage($post);
			logAction('delete', array('topic' => $messageDetails[$post]['topic'], 'subject' => $messageDetails[$post]['subject'], 'member' => $messageDetails[$post]['member']));
		}
	}
}
?>