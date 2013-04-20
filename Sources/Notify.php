<?php

/**
 * ProjectGLS
 *
 * @copyright 2013 ProjectGLS
 * @license http://next.mmobrowser.com/projectgls/license.txt
 *
 * This file contains just the functions that turn on and off notifications
 * to topics.
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
 * Turn off/on notification for a particular topic.
 * Must be called with a topic specified in the URL.
 * The sub-action can be 'on', 'off', or nothing for what to do.
 * Requires the mark_any_notify permission.
 * Upon successful completion of action will direct user back to topic.
 * Accessed via ?action=notify.
 *
 * @uses Notify template, main sub-template
 */
function Notify()
{
	global $scripturl, $txt, $topic, $user_info, $context, $smcFunc;

	// Make sure they aren't a guest or something - guests can't really receive notifications!
	is_not_guest();
	isAllowedTo('mark_any_notify');

	// Make sure the topic has been specified.
	if (empty($topic))
		fatal_lang_error('not_a_topic', false);

	// What do we do?  Better ask if they didn't say..
	if (empty($_GET['sa']))
	{
		// Load the template, but only if it is needed.
		loadTemplate('Notify');

		// Find out if they have notification set for this topic already.
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}log_notify
			WHERE id_member = {int:current_member}
				AND id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_member' => $user_info['id'],
				'current_topic' => $topic,
			)
		);
		$context['notification_set'] = $smcFunc['db_num_rows']($request) != 0;
		$smcFunc['db_free_result']($request);

		// Set the template variables...
		$context['topic_href'] = $scripturl . '?topic=' . $topic . '.' . $_REQUEST['start'];
		$context['start'] = $_REQUEST['start'];
		$context['page_title'] = $txt['notification'];

		return;
	}
	elseif ($_GET['sa'] == 'on')
	{
		checkSession('get');

		// Attempt to turn notifications on.
		$smcFunc['db_insert']('ignore',
			'{db_prefix}log_notify',
			array('id_member' => 'int', 'id_topic' => 'int'),
			array($user_info['id'], $topic),
			array('id_member', 'id_topic')
		);
	}
	else
	{
		checkSession('get');

		// Just turn notifications off.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_member = {int:current_member}
				AND id_topic = {int:current_topic}',
			array(
				'current_member' => $user_info['id'],
				'current_topic' => $topic,
			)
		);
	}

	// Send them back to the topic.
	redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
}

/**
 * Turn off/on unread replies subscription for a topic
 * Must be called with a topic specified in the URL.
 * The sub-action can be 'on', 'off', or nothing for what to do.
 * Requires the mark_any_notify permission.
 * Upon successful completion of action will direct user back to topic.
 * Accessed via ?action=disregardtopic.
 */
function TopicDisregard()
{
	global $smcFunc, $user_info, $topic, $modSettings;

	// Let's do something only if the function is enabled
	if (!$user_info['is_guest'] && $modSettings['enable_disregard'])
	{
		checkSession('get');

		if (isset($_GET['sa']))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_member, id_topic, id_msg, disregarded
				FROM {db_prefix}log_topics
				WHERE id_member = {int:current_user}
					AND id_topic = {int:current_topic}',
				array(
					'current_user' => $user_info['id'],
					'current_topic' => $topic,
				)
			);
			$log = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);
			if (empty($log))
			{
				$insert = true;
				$log['disregarded'] = $_GET['sa'] == 'on' ? 1 : 0;
			}
			else
			{
				$insert = false;
				$log = array(
					'id_member' => $user_info['id'],
					'id_topic' => $topic,
					'id_msg' => 0,
					'disregarded' => $_GET['sa'] == 'on' ? 1 : 0,
				);
			}

			$smcFunc['db_insert']($insert ? 'insert' : 'replace',
				'{db_prefix}log_topics',
				array(
					'id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'disregarded' => 'int',
				),
				$log,
				array('id_member', 'id_topic')
			);
		}
	}

	// Back to the topic.
	redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
}

?>