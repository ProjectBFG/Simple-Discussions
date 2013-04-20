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
	global $context, $user_info;
	
	echo '
	<div class="pagesection">
		<div class="pagelinks pull-left">', $context['page_index'], '</div>
		', template_button_strip($context['normal_buttons'], 'right'), '
	</div>
	Hey ', $user_info['name'], ', this is our main blog page. There is nothing right now, but won\'t last that long no worries.';
}

?>