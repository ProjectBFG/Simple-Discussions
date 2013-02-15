<?php

if (!defined('SMF'))
	die('Hacking attempt...');

function BlogMain()
{
	global $context, $txt, $scripturl;
	
	loadTemplate('Blog');
	
	// Build the linktree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=blog',
		'name' => $txt['blog']
	);
	
	$subActions = array(
	);

	if (!isset($_REQUEST['sa']) || !isset($subActions[$_REQUEST['sa']]))
		BlogMainPage();
	else
		$subActions[$_REQUEST['sa']]();
}

function BlogMainPage()
{
	global $context, $txt, $scripturl;
	
	$context['normal_buttons'] = array(
		'new_blog' => array('text' => 'new_blog', 'lang' => true, 'url' => $scripturl . '?action=admin;area=blog;sa=add', 'active' => true),
	);
	$context['page_index'] = '';
	
	$context['page_title'] = $txt['blog'];
}

?>