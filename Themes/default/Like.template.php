<?php

/**
 * ProjectGLS
 *
 * @copyright 2013 ProjectGLS
 * @license http://next.mmobrowser.com/projectgls/license.txt
 * @version 1.0 Alpha 1
 */

function template_main()
{
	global $context, $txt;

	echo '
	<div id="like_info_', $context['like_info']['id'], '"><div class="alert alert-', $context['like_info']['alert'], ' text-left"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><strong>', $context['like_info']['msg'], '</strong></div></div>
	<div id="like_count_', $context['like_info']['id'], '">', sprintf($txt['like_info_display'], $context['like_info']['like_count']), '</div>
	<div id="liked_', $context['like_info']['id'], '">
		<ul>';
foreach ($context['like_info']['liked'] as $member)
{
echo '
			<li>', $member['link'], '</li>';
}
	echo '
		</ul>
	</div>';
	
	// print_r($context['like_info']);
}

?>