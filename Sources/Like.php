<?php

/**
 *
 * @version 1.0 Alpha 1
 */

function Like()
{
	global $scripturl, $context, $smcFunc, $user_info, $db_show_debug, $txt;
	
	$context['template_layers'] = array();
	loadTemplate('Like');
	loadLanguage('Like');
		
	$id_msg = (int) $_POST['id'];
	$context['like_info'] = array(
		'id' => $id_msg,
		'msg' => '',
		'like_count' => '',
		'liked' => array(),
	);
	$db_show_debug = false;
	// $context['rate_errors'] = array();

	// Check if we have the ID of the message.
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(id_msg)
		FROM {db_prefix}messages
		WHERE id_msg = {int:message_id}
		LIMIT 1',
		array(
			'message_id' => $id_msg,
		)
	);
	if ($smcFunc['db_fetch_row']($request) === 0) {
		$context['like_info']['msg'] = $txt['no_posts_selected'];
		return;
	}
	$smcFunc['db_free_result']($request);

	// Check if the user is liking himself
	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}messages
		WHERE id_member = {int:member_id}
			AND id_msg = {int:message_id}
		LIMIT 1',
		array(
			'member_id' => $user_info['id'],
			'message_id' => $id_msg,
		)
	);
	if ($smcFunc['db_fetch_row']($request) >= 1) {
		$context['like_info']['msg'] = $txt['cannot_like_self'];
		return;
	}
	$smcFunc['db_free_result']($request);
		
	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}likes
		WHERE id_member = {int:member_id}
			AND id_msg = {int:message_id}
		LIMIT 1',
		array(
			'member_id' => $user_info['id'],
			'message_id' => $id_msg,
		)
	);
	if ($smcFunc['db_fetch_row']($request) >= 1)
	{
		updateLikes($id_msg, $user_info['id'], 'm');

		$context['like_info']['msg'] = $txt['succesfull_disliked'];
		$context['like_info']['like_count'] = getLikesCount($id_msg);
		$context['like_info']['liked'] = getLikes($id_msg);
	}
	else
	{		
		updateLikes($id_msg, $user_info['id'], 'p');
		
		$context['like_info']['msg'] = $txt['succesfull_liked'];
		$context['like_info']['like_count'] = getLikesCount($id_msg);
		$context['like_info']['liked'] = getLikes($id_msg);
	}
}

function updateLikes($msg, $member, $act)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET likes = likes ' . ($act == 'm' && getLikesCount($msg) > 0 ? '-' : '+') . ' 1
		WHERE id_msg = {int:message_id}',
		array(
			'message_id' => (int) $msg,
		)
	);
	$smcFunc['db_free_result']($request);
	
	if ($act === 'm')
	{	
		$request = $smcFunc['db_query']('', '
			DELETE FROM {db_prefix}likes
			WHERE id_msg = {int:message_id}
				AND id_member = {int:member_id}',
			array(
				'message_id' => (int) $msg,
				'member_id' => (int) $member,
			)
		);
		$smcFunc['db_free_result']($request);		
	}
	elseif ($act === 'p')
	{
		$smcFunc['db_insert']('', '{db_prefix}likes',
			array(
				'id_msg' => 'int', 'id_member' => 'int', 'time' => 'int',
			),
			array(
				$msg, $member, time(),
			),
			array('id_like')
		);
	}
	
	return;
}

function getLikesCount($msg)
{
	global $smcFunc;
	
	$request = $smcFunc['db_query']('', '
		SELECT likes
		FROM {db_prefix}messages
		WHERE id_msg = {int:message_id}
		LIMIT 1',
		array(
			'message_id' => (int) $msg,
		)
	);
	list($num_likes) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);
	
	return $num_likes;

}

function getLikes($msg)
{
	global $smcFunc, $memberContext;
	
	$request = $smcFunc['db_query']('', '
		SELECT id_like, id_msg, id_member, time
		FROM {db_prefix}likes
		WHERE id_msg = {int:message_id}',
		array(
			'message_id' => (int) $msg,
		)
	);
	$likes = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		loadMemberData($row['id_member']);
		loadMemberContext($row['id_member']);
		$likes[] = array(
			'id_like' => $row['id_like'],
			'id_msg' => $row['id_msg'],
			'link' => &$memberContext[$row['id_member']]['link'],
			'time' => timeformat($row['time']),
		);
	
	}
	$smcFunc['db_free_result']($request);

	return $likes;
}

function getTotalTopicLike($topic)
{
	global $smcFunc;
	
	$request = $smcFunc['db_query']('', '
		SELECT SUM(likes)
		FROM {db_prefix}messages
		WHERE id_topic = {int:topic_id}
		LIMIT 1',
		array(
			'topic_id' => (int) $topic,
		)
	);
	list ($total) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);
	
	return $total;

}

?>