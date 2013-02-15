<?php

function template_admin_blog_list()
{
	global $context, $user_info;
	
	template_show_list('blog_list');
}

function template_add_blog()
{
	global $context, $scripturl, $txt;
	echo '
	<form action="', $scripturl, '?action=admin;area=blogs;sa=add', $context['post_url'], '" method="post" accept-charset="', $context['character_set'], '" name="postmodify" id="postmodify" class="flow_hidden" onsubmit="submitonce(this);smc_saveEntities(\'postmodify\', [\'title\', \'', $context['post_box_name'], '\', \'tags\');" enctype="multipart/form-data">
		<div id="preview_section"', isset($context['preview_message']) ? '' : ' style="display: none;"', '>
				<h3 class="catbg">
					<span id="preview_subject">', empty($context['preview_subject']) ? '' : $context['preview_subject'], '</span>
				</h3>
			<div class="windowbg">
				<div class="content">
					<div class="post" id="preview_body">
						', empty($context['preview_message']) ? '<br />' : $context['preview_message'], '
					</div>
				</div>
			</div>
		</div><br />
		
			<h3 class="catbg">', $context['page_title'], '</h3>
		
		<div>
			<div class="roundframe">
				<dl id="post_header">
					<dt class="clear">
						<span', isset($context['post_error']['no_title']) ? ' class="error"' : '', ' id="caption_title">', $txt['subject'], ':</span>
					</dt>
					<dd>
						<input type="text" name="title"', $context['title'] == '' ? '' : ' value="' . $context['title'] . '"', ' tabindex="', $context['tabindex']++, '" size="80" maxlength="80"', isset($context['post_error']['no_title']) ? ' class="error"' : ' class="input_text"', ' />
					</dd>
					<dt class="clear">
						<span id="caption_tags">', $txt['blog_tags'], ':<div class="smalltext">', $txt['blog_tags_desc'], '</div></span>
					</dt>
					<dd>
						<input type="text" name="tags"', $context['tags'] == '' ? '' : ' value="' . $context['tags'] . '"', ' tabindex="', $context['tabindex']++, '" size="80" class="input_text" />
					</dd>
				</dl>
				', template_control_richedit($context['post_box_name'], 'smileyBox_message', 'bbcBox_message'), '
				<br class="clear_right" />
				<span id="post_confirm_buttons">
					', template_control_richedit_buttons($context['post_box_name']), '
				</span>
			</div>
		</div>
	</form>';

}

?>