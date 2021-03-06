<?php

/**
 * ProjectGLS
 *
 * @copyright 2013 ProjectGLS
 * @license http://next.mmobrowser.com/projectgls/license.txt
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 1.0 Alpha 1
 */

function template_main()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Let them know, if their report was a success!
	if ($context['report_sent'])
	{
		echo '
			<div class="infobox">
				', $txt['report_sent'], '
			</div>';
	}

	// Show the anchor for the top and for the first message. If the first message is new, say so.
	echo '

			<a id="msg', $context['first_message'], '"></a>', $context['first_new_message'] ? '<a id="new"></a>' : '';
			
	// H1 SEO Title	
	echo '
			<div class="pull-right">
				', $context['page_index'], '
			</div>
			<h1><a href="',$scripturl,'?topic=',$context['current_topic'],'.0">', $context['subject'], '</a></h1>';

	// Show the page index... "Pages: [1]".
	echo '
			<div class="clearfix"></div>
			', template_button_strip($context['normal_buttons'], 'right', array(), 'visible-lg'), '
			<div class="clearfix"></div>';

	// Show the topic information - subject, etc.
	echo '
			<div id="forumposts">
					<h3 class="catbg" id="top_subject">
						', $txt['topic'], ': ', $context['subject'], '&nbsp;<span>(', $context['num_views_text'], ')</span>
					</h3>';
	if (!empty($settings['display_who_viewing']))
	{
		echo '
				<p id="whoisviewing">';

		// Show just numbers...?
		if ($settings['display_who_viewing'] == 1)
				echo count($context['view_members']), ' ', count($context['view_members']) == 1 ? $txt['who_member'] : $txt['members'];
		// Or show the actual people viewing the topic?
		else
			echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . ((empty($context['view_num_hidden']) || $context['can_moderate_forum']) ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');

		// Now show how many guests are here too.
		echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_topic'], '
				</p>';
	}

	echo '
				<form action="', $scripturl, '?action=quickmod2;topic=', $context['current_topic'], '.', $context['start'], '" method="post" accept-charset="', $context['character_set'], '" name="quickModForm" id="quickModForm" style="margin: 0;" onsubmit="return oQuickModify.bInEditMode ? oQuickModify.modifySave(\'' . $context['session_id'] . '\', \'' . $context['session_var'] . '\') : false">';

	$ignoredMsgs = array();
	$removableMessageIDs = array();
	$alternate = false;

	// Get all the messages...
	while ($message = $context['get_message']())
	{
		$ignoring = false;
		$alternate = !$alternate;
		if ($message['can_remove'])
			$removableMessageIDs[] = $message['id'];

		// Are we ignoring this message?
		if (!empty($message['is_ignored']))
		{
			$ignoring = true;
			$ignoredMsgs[] = $message['id'];
		}

		// Show the message anchor and a "new" anchor if this message is new.
		echo '
				<div class="panel panel-post-windowbg', $message['approved'] ? ($message['alternate'] == 0 ? '' : '2') : 'approvebg', '">', $message['id'] != $context['first_message'] ? '
					<a id="msg' . $message['id'] . '"></a>' . ($message['first_new'] ? '<a id="new"></a>' : '') : '', '
					<div class="panel-heading">
						<a href="#" data-toggle="popover" title="', $message['member']['name'], '" data-content="testing', $message['member']['id'], '" class="post_popover" data-trigger="hover">' ,$message['member']['name'], '</a> (', (!empty($message['member']['group']) ? $message['member']['group'] . ', ' : '') ,'<a href="', $message['href'], '" rel="nofollow" title="', !empty($message['counter']) ? sprintf($txt['reply_number'], $message['counter']) : '', ' - ', $message['subject'], '">', $message['time'], '</a>)';
						
		// Show the quickbuttons, for various operations on posts.
		// if ($message['can_approve'] || $context['can_reply'] || $message['can_modify'] || $message['can_remove'] || $context['can_split'] || $context['can_restore_msg'])
		if ($message['can_approve'] || $context['can_reply'] || $message['can_modify'] || $message['can_remove'] || $context['can_split'])
			echo '
						<div class="btn-group pull-right">
							
							<span class="btn btn-default" id="like_post_', $message['id'], '" style="cursor: pointer; margin: 0;" onclick="like(', $message['id'], ')"><span class="glyphicon glyphicon-thumbs-', $message['like_button'], '"></span></span>';

		/* Can they reply? Have they turned on quick reply?
		// if ($context['can_quote'] && !empty($options['display_quick_reply']))
			echo '
							<a class="btn btn-default" href="', $scripturl, '?action=post;quote=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';last_msg=', $context['topic_last_message'], '" onclick="return oQuickReply.quote(', $message['id'], ');">', $txt['quote'], '</a>';

		// So... quick reply is off, but they *can* reply?
		// elseif ($context['can_quote'])
			echo '
							<a class="btn btn-default" href="', $scripturl, '?action=post;quote=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';last_msg=', $context['topic_last_message'], '">', $txt['quote'], '</a>';*/

		// Can the user modify the contents of this post?  Show the modify inline image.
		if ($message['can_modify'])
			echo '
							<span class="btn btn-default" id="modify_button_', $message['id'], '" style="cursor: pointer; margin: 0;" onclick="oQuickModify.modifyMsg(\'', $message['id'], '\')"><span class="glyphicon glyphicon-edit"></span> ', $txt['quick_edit'], '</span>';
							
		// Can the user modify the contents of this post?
		// if ($message['can_modify'] || $message['can_remove'] || ($context['can_split'] && !empty($context['real_num_replies'])) || $context['can_restore_msg'] || $message['can_approve'] || $message['can_unapprove'])
		if ($message['can_modify'] || $message['can_remove'] || ($context['can_split'] && !empty($context['real_num_replies'])) || $message['can_approve'] || $message['can_unapprove'])
			echo '
							<div class="btn-group">
								<span class="btn btn-default dropdown-toggle" data-toggle="dropdown" href="#">', $txt['post_options'], '<span class="caret"></span></span>
								<ul class="dropdown-menu">';

			// Can the user modify the contents of this post?
			if ($message['can_modify'])
				echo '
									<li><a href="', $scripturl, '?action=post;msg=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], '" class="modify_button">', $txt['modify'], '</a></li>';

			// How about... even... remove it entirely?!
			if ($message['can_remove'])
				echo '
									<li><a href="', $scripturl, '?action=deletemsg;topic=', $context['current_topic'], '.', $context['start'], ';msg=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['remove_message'], '?\');"><span class="glyphicon glyphicon-remove"></span> ', $txt['remove'], '</a></li>';

			// What about splitting it off the rest of the topic?
			if ($context['can_split'] && !empty($context['real_num_replies']))
				echo '
									<li><a href="', $scripturl, '?action=splittopics;topic=', $context['current_topic'], '.0;at=', $message['id'], '" class="split_button">', $txt['split'], '</a></li>';

			// Can we restore topics?
			// if ($context['can_restore_msg'])
				// echo '
									// <li><a href="', $scripturl, '?action=restoretopic;msgs=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '" class="restore_button">', $txt['restore_message'], '</a></li>';

			// Maybe we can approve it, maybe we should?
			if ($message['can_approve'])
				echo '
									<li><a href="', $scripturl, '?action=moderate;area=postmod;sa=approve;topic=', $context['current_topic'], '.', $context['start'], ';msg=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '"  class="approve_button">', $txt['approve'], '</a></li>';

			// Maybe we can unapprove it?
			if ($message['can_unapprove'])
				echo '
									<li><a href="', $scripturl, '?action=moderate;area=postmod;sa=approve;topic=', $context['current_topic'], '.', $context['start'], ';msg=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '"  class="unapprove_button">', $txt['unapprove'], '</a></li>';

			// if ($message['can_modify'] || $message['can_remove'] || $context['can_split'] || $context['can_restore_msg'] || $message['can_approve'] || $message['can_unapprove'])
			if ($message['can_modify'] || $message['can_remove'] || $context['can_split'] || $message['can_approve'] || $message['can_unapprove'])
				echo '
								</ul>
							</div>';
								
		// Show a checkbox for quick moderation?
		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $message['can_remove'])
			echo '
							<span class="inline_mod_check" style="display: none;" id="in_topic_mod_check_', $message['id'], '"></span>';
		
			// if ($message['can_approve'] || $context['can_reply'] || $message['can_modify'] || $message['can_remove'] || $context['can_split'] || $context['can_restore_msg'])
			if ($message['can_approve'] || $context['can_reply'] || $message['can_modify'] || $message['can_remove'] || $context['can_split'])
				echo '
						</div>
						<div class="clearfix"></div>';
		echo '
					</div>
					<div class="panel-body">';

		// Ignoring this user? Hide the post.
		if ($ignoring)
			echo '
						<div id="msg_', $message['id'], '_ignored_prompt">
							', $txt['ignoring_user'], '
							<a href="#" id="msg_', $message['id'], '_ignored_link" style="display: none;">', $txt['show_ignore_user_post'], '</a>
						</div>';

		echo '
						<a href="#" id="subject_', $message['id'], '"></a>';

		// Show the post itself, finally!
		echo '
						<div class="post">';

		if (!$message['approved'] && $message['member']['id'] != 0 && $message['member']['id'] == $context['user']['id'])
			echo '
							<div class="approve_post">
								', $txt['post_awaiting_approval'], '
							</div>';
		echo '
							<div class="inner" id="msg_', $message['id'], '"', $ignoring ? ' style="display:none;"' : '', '>', $message['body'], '</div>
						</div>
						<div class="col-md-8">';

		// Are there any custom profile fields for above the signature?
		if (!empty($message['member']['custom_fields']))
		{
			$shown = false;
			foreach ($message['member']['custom_fields'] as $custom)
			{
				if ($custom['placement'] != 2 || empty($custom['value']))
					continue;
				if (empty($shown))
				{
					$shown = true;
					echo '
							<div class="custom_fields_above_signature">
								<ul class="reset nolist">';
				}
				echo '
									<li>', $custom['value'], '</li>';
			}
			if ($shown)
				echo '
								</ul>
							</div>';
		}

		// Show the member's signature?
		if (!empty($message['member']['signature']) && empty($options['show_no_signatures']) && $context['signature_enabled'])
			echo '
							<div class="signature" id="msg_', $message['id'], '_signature"', $ignoring ? ' style="display:none;"' : '', '>', $message['member']['signature'], '</div>';

		echo '
						</div>
						<div class="col-md-4">
							<div id="like_', $message['id'], '">
									<div id="like_info_', $message['id'], '"></div>
									<div id="like_count_', $message['id'], '">', sprintf($txt['like_info_display'], $message['like_count']), '</div>';
			if (!empty($message['liked']))
			{
				echo '
									<div id="liked_', $message['id'], '">
										<ul>';
						foreach ($message['liked'] as $member)
						{
							echo '
											<li>', $member['link'], '</li>';
						}
								echo '
										</ul>
									</div>';
			}
			echo '
								</div>
							</div>
					</div>
				</div>
				<hr class="post_separator" />';
	}

	echo '
				</form>
			</div>';

	// Show the page index... "Pages: [1]".
	echo '
			<div class="hidden-xs">
				', template_button_strip($context['normal_buttons'], 'right'), '
				', !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . '<a href="#top" class="btn pull-left">' . $txt['go_up'] . '</a>' : '', '
			</div>';

	echo '
			<div class="hidden-xs" id="moderationbuttons">', template_button_strip($context['mod_buttons'], 'bottom', array('id' => 'moderationbuttons_strip')), '</div>';

	if ($context['can_reply'] && !empty($options['display_quick_reply']))
	{
		echo '
			<a id="quickreply"></a>
			<div class="panel panel-sd" id="quickreplybox">
				<div class="panel-heading">
					<span class="glyphicon glyphicon-arrow-down" data-toggle="collapse" data-target="#quickReplyOptions" id="quickReplyExpand"></span>
					', $txt['quick_reply'], '
				</div>
				<div id="quickReplyOptions" class="panel-body collapse fade in">
						<p class="smalltext lefttext">', $txt['quick_reply_desc'], '</p>
						', $context['is_locked'] ? '<p class="alert smalltext">' . $txt['quick_reply_warning'] . '</p>' : '',
						$context['oldTopicError'] ? '<p class="alert smalltext">' . sprintf($txt['error_old_topic'], $modSettings['oldTopicDays']) . '</p>' : '', '
						', $context['can_reply_approved'] ? '' : '<em>' . $txt['wait_for_approval'] . '</em>', '
						', !$context['can_reply_approved'] && $context['require_verification'] ? '<br />' : '', '
						<form action="', $scripturl, '?action=post2" method="post" accept-charset="', $context['character_set'], '" name="postmodify" id="postmodify" onsubmit="submitonce(this);" style="margin: 0;">
							<input type="hidden" name="topic" value="', $context['current_topic'], '" />
							<input type="hidden" name="subject" value="', $context['response_prefix'], $context['subject'], '" />
							<input type="hidden" name="icon" value="xx" />
							<input type="hidden" name="from_qr" value="1" />
							<input type="hidden" name="notify" value="', $context['is_marked_notify'] || !empty($options['auto_notify']) ? '1' : '0', '" />
							<input type="hidden" name="not_approved" value="', !$context['can_reply_approved'], '" />
							<input type="hidden" name="goback" value="', empty($options['return_to_post']) ? '0' : '1', '" />
							<input type="hidden" name="last_msg" value="', $context['topic_last_message'], '" />
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
							<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />';

		// Guests just need more.
		if ($context['user']['is_guest'])
			echo '
							<strong>', $txt['name'], ':</strong> <input type="text" name="guestname" value="', $context['name'], '" size="25" class="input_text" tabindex="', $context['tabindex']++, '" />
							<strong>', $txt['email'], ':</strong> <input type="text" name="email" value="', $context['email'], '" size="25" class="input_text" tabindex="', $context['tabindex']++, '" /><br />';

		// Is visual verification enabled?
		if ($context['require_verification'])
			echo '
							<strong>', $txt['verification'], ':</strong>', template_control_verification($context['visual_verification_id'], 'quick_reply'), '<br />';

		if ($options['display_quick_reply'] < 3)
		{
			echo '
							<div class="quickReplyContent">
								<textarea cols="600" rows="7" name="message" tabindex="', $context['tabindex']++, '" class="form-control"></textarea>
							</div>';
		}
		else
		{
			// Show the actual posting area...
			if ($context['show_bbc'])
			{
				echo '
							<div id="bbcBox_message"></div>';
			}

			// What about smileys?
			if (!empty($context['smileys']['postform']) || !empty($context['smileys']['popup']))
				echo '
							<div id="smileyBox_message"></div>';

			echo '
							', template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message'), '
							<script><!-- // --><![CDATA[
								function insertQuoteFast(messageid)
								{
									if (window.XMLHttpRequest)
										getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + \'action=quotefast;quote=\' + messageid + \';xml;pb=', $context['post_box_name'], ';mode=\' + (oEditorHandle_', $context['post_box_name'], '.bRichTextEnabled ? 1 : 0), onDocReceived);
									else
										reqWin(smf_prepareScriptUrl(smf_scripturl) + \'action=quotefast;quote=\' + messageid + \';pb=', $context['post_box_name'], ';mode=\' + (oEditorHandle_', $context['post_box_name'], '.bRichTextEnabled ? 1 : 0), 240, 90);
									return false;
								}
								function onDocReceived(XMLDoc)
								{
									var text = \'\';
									for (var i = 0, n = XMLDoc.getElementsByTagName(\'quote\')[0].childNodes.length; i < n; i++)
										text += XMLDoc.getElementsByTagName(\'quote\')[0].childNodes[i].nodeValue;
									oEditorHandle_', $context['post_box_name'], '.insertText(text, false, true);

									ajax_indicator(false);
								}
							// ]]></script>';

		}
		echo '
							<div class="clearfix"></div>
							<div class="padding pull-right">
								<input type="submit" name="post" value="', $txt['post'], '" onclick="return submitThisOnce(this);" accesskey="s" tabindex="', $context['tabindex']++, '" class="btn btn-primary" />
								<input type="submit" name="preview" value="', $txt['preview'], '" onclick="return submitThisOnce(this);" accesskey="p" tabindex="', $context['tabindex']++, '" class="btn btn-primary" />';

		if ($context['show_spellchecking'])
			echo '
								<input type="button" value="', $txt['spell_check'], '" onclick="spellCheck(\'postmodify\', \'message\');" tabindex="', $context['tabindex']++, '" class="btn btn--primary" />';

		if ($context['drafts_save'] && !empty($options['drafts_show_saved_enabled']))
			echo '
								<input type="submit" name="save_draft" value="', $txt['draft_save'], '" onclick="return confirm(' . JavaScriptEscape($txt['draft_save_note']) . ') && submitThisOnce(this);" accesskey="d" tabindex="', $context['tabindex']++, '" class="btn btn-primary" />
								<input type="hidden" id="id_draft" name="id_draft" value="', empty($context['id_draft']) ? 0 : $context['id_draft'], '" />';

		if (!empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']))
			echo '
								<div class="clear righttext padding"><span id="throbber" style="display:none"><img src="' . $settings['images_url'] . '/loading_sm.gif" alt="" class="centericon" />&nbsp;</span><span id="draft_lastautosave" ></span></div>';

		echo '
							</div>
							<div class="clearfix"></div>
						</form>
				</div>
			</div>';
	}
	else
		echo '
		<br class="clear" />';

	if (!empty($context['drafts_autosave']) && !empty($options['drafts_autosave_enabled']))
		echo '
			<script src="', $settings['default_theme_url'], '/scripts/drafts.js?alp21"></script>
			<script><!-- // --><![CDATA[
				var oDraftAutoSave = new smf_DraftAutoSave({
					sSelf: \'oDraftAutoSave\',
					sLastNote: \'draft_lastautosave\',
					sLastID: \'id_draft\',', !empty($context['post_box_name']) ? '
					sSceditorID: \'' . $context['post_box_name'] . '\',' : '', '
					sType: \'', !empty($options['display_quick_reply']) && $options['display_quick_reply'] > 2 ? 'quick' : 'quick', '\',
					iFreq: ', (empty($modSettings['masterAutoSaveDraftsDelay']) ? 60000 : $modSettings['masterAutoSaveDraftsDelay'] * 1000), '
				});
			// ]]></script>';

	if ($context['show_spellchecking'])
		echo '
			<form action="', $scripturl, '?action=spellcheck" method="post" accept-charset="', $context['character_set'], '" name="spell_form" id="spell_form" target="spellWindow"><input type="hidden" name="spellstring" value="" /></form>
				<script src="', $settings['default_theme_url'], '/scripts/spellcheck.js"></script>';

	echo '
				<script src="', $settings['default_theme_url'], '/scripts/topic.js"></script>
				<script><!-- // --><![CDATA[';

	if (!empty($options['display_quick_reply']))
		echo '
					var oQuickReply = new QuickReply({
						bDefaultCollapsed: ', !empty($options['display_quick_reply']) && $options['display_quick_reply'] > 1 ? 'false' : 'true', ',
						iTopicId: ', $context['current_topic'], ',
						iStart: ', $context['start'], ',
						sScriptUrl: smf_scripturl,
						sImagesUrl: smf_images_url,
						sContainerId: "quickReplyOptions",
						sImageId: "quickReplyExpand",
						sImageCollapsed: "collapse.png",
						sImageExpanded: "expand.png",
						sJumpAnchor: "quickreply",
						bIsFull: ', !empty($options['use_editor_quick_reply']) ? 'true' : 'false', '
					});';

	if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $context['can_remove_post'])
		echo '
					var oInTopicModeration = new InTopicModeration({
						sSelf: \'oInTopicModeration\',
						sCheckboxContainerMask: \'in_topic_mod_check_\',
						aMessageIds: [\'', implode('\', \'', $removableMessageIDs), '\'],
						sSessionId: smf_session_id,
						sSessionVar: smf_session_var,
						sButtonStrip: \'moderationbuttons\',
						sButtonStripDisplay: \'moderationbuttons_strip\',
						bUseImageButton: false,
						bCanRemove: ', $context['can_remove_post'] ? 'true' : 'false', ',
						sRemoveButtonLabel: \'', $txt['quickmod_delete_selected'], '\',
						sRemoveButtonImage: \'delete_selected.png\',
						sRemoveButtonConfirm: \'', $txt['quickmod_confirm'], '\',
						// bCanRestore: ', $context['can_restore_msg'] ? 'true' : 'false', ',
						// sRestoreButtonLabel: \'', $txt['quick_mod_restore'], '\',
						// sRestoreButtonImage: \'restore_selected.png\',
						// sRestoreButtonConfirm: \'', $txt['quickmod_confirm'], '\',
						bCanSplit: ', $context['can_split'] ? 'true' : 'false', ',
						sSplitButtonLabel: \'', $txt['quickmod_split_selected'], '\',
						sSplitButtonImage: \'split_selected.png\',
						sSplitButtonConfirm: \'', $txt['quickmod_confirm'], '\',
						sFormId: \'quickModForm\'
					});';

	echo '
					if (\'XMLHttpRequest\' in window)
					{
						var oQuickModify = new QuickModify({
							sScriptUrl: smf_scripturl,
							sClassName: \'quick_edit\',
							bShowModify: ', $settings['show_modify'] ? 'true' : 'false', ',
							iTopicId: ', $context['current_topic'], ',
							sTemplateBodyEdit: ', JavaScriptEscape('
								<div id="quick_edit_body_container">
									<div id="error_box" style="padding: 4px;" class="error"></div>
									<textarea class="form-control" name="message" rows="12" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 100%; min-width: 100%' : 'width: 100%') . '; margin-bottom: 10px;" tabindex="' . $context['tabindex']++ . '">%body%</textarea><br />
									<input type="hidden" name="\' + smf_session_var + \'" value="\' + smf_session_id + \'" />
									<input type="hidden" name="topic" value="' . $context['current_topic'] . '" />
									<input type="hidden" name="msg" value="%msg_id%" />
									<div class="righttext">
										<input type="submit" name="post" value="' . $txt['save'] . '" tabindex="' . $context['tabindex']++ . '" onclick="return oQuickModify.modifySave(\'' . $context['session_id'] . '\', \'' . $context['session_var'] . '\');" accesskey="s" class="btn" />&nbsp;&nbsp;' . ($context['show_spellchecking'] ? '<input type="button" value="' . $txt['spell_check'] . '" tabindex="' . $context['tabindex']++ . '" onclick="spellCheck(\'quickModForm\', \'message\');" class="btn" />&nbsp;&nbsp;' : '') . '<input type="submit" name="cancel" value="' . $txt['modify_cancel'] . '" tabindex="' . $context['tabindex']++ . '" onclick="return oQuickModify.modifyCancel();" class="btn" />
									</div>
								</div>'), ',
							sTemplateSubjectEdit: ', JavaScriptEscape('<input type="text" name="subject" value="%subject%" maxlength="80" tabindex="' . $context['tabindex']++ . '" class="form-control" />'), ',
							sTemplateBodyNormal: ', JavaScriptEscape('%body%'), ',
							sTemplateSubjectNormal: ', JavaScriptEscape('<a href="#" id="subject_%msg_id%"></a>'), ',
							sTemplateTopSubject: ', JavaScriptEscape($txt['topic'] . ': %subject% &nbsp;(' . $context['num_views_text'] . ')'), ',
							sErrorBorderStyle: ', JavaScriptEscape('1px solid red'), ($context['can_reply'] && !empty($options['display_quick_reply'])) ? ',
							sFormRemoveAccessKeys: \'postmodify\'' : '', '
						});
					}';

	if (!empty($ignoredMsgs))
		echo '
					ignore_toggles([', implode(', ', $ignoredMsgs), '], ', JavaScriptEscape($txt['show_ignore_user_post']), ');';

	echo '
				// ]]></script>';

}

?>