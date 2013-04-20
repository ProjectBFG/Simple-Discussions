<?php

if (!defined('SMF'))
	die('No direct access...'); 
	
function SidebarMain()
{
	global $context, $txt;
		
		loadLanguage('Themes');
		loadLanguage('Settings');
	
	$context['page_title'] = $txt['gls_sidebar'];
	
		loadTemplate('Sidebar');
}
	
?>