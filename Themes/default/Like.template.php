<?php

function template_main()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	echo '
	<div id="like_info_', $context['like_info']['id'], '">', $context['like_info']['msg'], '</div>
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