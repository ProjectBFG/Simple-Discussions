<?php

function Rate()
{
	global $scripturl, $context, $smcFunc, $user_info;
	
	$context['template_layers'] = array();
	loadTemplate('Rate');
	
	
	$id_msg = (int) $_POST['id'];
	$rating = $_POST['rating'];
	$rating_types = array('like', 'dislike');
	$context['message'] = '';
	// $context['rate_errors'] = array();
	
	if (in_array($rating, $rating_types))
	{		
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
			$context['message'] = 'msg id yok olm napiyon sen :@';
			return;
		}
		$smcFunc['db_free_result']($request);
	
		// Check if the user is rating himself
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
			$context['message'] = 'kendine niye oy veriyon amk?';
			return;
		}
		$smcFunc['db_free_result']($request);


			
		$request = $smcFunc['db_query']('', '
			SELECT action
			FROM {db_prefix}rates
			WHERE id_member = {int:member_id}
				AND id_msg = {int:message_id}
			LIMIT 1',
			array(
				'member_id' => $user_info['id'],
				'message_id' => $id_msg,
			)
		);
		list($action) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	
		if (!empty($action))
		{
			if ($action === $rating)
				$context['message'] = 'ayni rating pampa :(';
			else
			{
				$context['message'] = 'ayni degil ya la :)';

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}rates
					SET action = {string:action}
					WHERE id_member = {int:member_id}
						AND id_msg = {int:message_id}
					LIMIT 1',
					array(
						'action' => $rating,
						'member_id' => $user_info['id'],
						'message_id' => $id_msg,
					)
				);

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}messages
					SET likes = likes ' . ($rating == 'like' ? '+' : '-') . ' 1
					WHERE id_msg = {int:message_id}
					LIMIT 1',
					array(
						'action' => $rating,
						'member_id' => $user_info['id'],
						'message_id' => $id_msg,
					)
				);
				
				$value = getCurrentRating($id_msg);
				
				$context['message'] .= ' new value: ' . $value;
			}
		}
		else
		{
			$smcFunc['db_insert']('', '{db_prefix}rates',
				array(
					'id_msg' => 'int', 'id_member' => 'int',
					'action' => 'string', 'time' => 'int',
				),
				array(
					$id_msg, $user_info['id'],
					$rating, time(),
				),
				array('id_rate')
			);
			
			$request = $smcFunc['db_query']('', '
				UPDATE {db_prefix}messages
				SET likes = likes + 1
				WHERE id_msg = {int:message_id}',
				array(
					'message_id' => $id_msg,
				)
			);
			
			$value = getCurrentRating($id_msg);
			
			$context['message'] = 'ratingi ekledik pampa :) new value: ' . $value;
		}
	}
}

function getCurrentRating($msg)
{
	global $smcFunc;
	
	$request = $smcFunc['db_query']('', '
		SELECT likes
		FROM {db_prefix}messages
		WHERE id_msg = {int:message_id}
		LIMIT 1',
		array(
			'message_id' => $msg,
		)
	);
	list($likes) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);
	
	return $likes;

}

?>