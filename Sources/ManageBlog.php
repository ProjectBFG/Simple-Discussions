<?php

/**
 *
 * @version 1.0 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...'); 
	
function BlogAdminMain()
{
	global $context, $txt;
	isAllowedTo('admin_forum');
	
	loadTemplate('ManageBlog');

	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['blog_admin'],
		'description' => '',
		'tabs' => array(
			'bloglist' => array(),
			'add' => array(),
		),
	);
	
	$subActions = array(
		'bloglist' => array(
			'function' => 'BlogList',
			'template' => 'admin_blog_list',
			'activities' => array(
			),
		),
		'add' => array(
			'function' => 'AddBlog',
			'template' => 'add_blog',
			'activities' => array(
				'new' => 'AddBlog2',
				'edit' => 'EditBlog',
				'edit2' => 'AddBlog2',
			),
		),
	);
	
	// Yep, sub-action time!
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		$subAction = $_REQUEST['sa'];
	else
		$subAction = 'bloglist';

	// Doing something special?
	if (isset($_REQUEST['activity']) && isset($subActions[$subAction]['activities'][$_REQUEST['activity']]))
		$activity = $_REQUEST['activity'];

	// Set a few things.
	$context['page_title'] = $txt['blog_list'];
	$context['sub_action'] = $subAction;
	$context['sub_template'] = !empty($subActions[$subAction]['template']) ? $subActions[$subAction]['template'] : '';

	// Finally fall through to what we are doing.
	$subActions[$subAction]['function']();

	// Any special activity?
	if (isset($activity))
		$subActions[$subAction]['activities'][$activity]();
	
	createToken('admin-blog');
}

function BlogList()
{
	global $context, $smcFunc, $txt, $scripturl, $sourcedir;
	
	$context['page_title'] = $txt['blog_list'];

		$listOptions = array(
		'id' => 'blog_list',
		'title' => $txt['blog_list'],
		'items_per_page' => 10,
		'base_href' => $scripturl . '?action=admin;area=blog;sa=bloglist',
		'default_sort_col' => 'id_blog',
		'get_items' => array(
			'file' => $sourcedir . '/Subs-Blog.php',
			'function' => 'list_getBlog',
			'params' => array(
				isset($where) ? $where : '1=1',
				isset($where_params) ? $where_params : array(),
			),
		),
		'get_count' => array(
			'file' => $sourcedir . '/Subs-Blog.php',
			'function' => 'list_getNumBlog',
			'params' => array(
				isset($where) ? $where : '1=1',
				isset($where_params) ? $where_params : array(),
			),
		),
		'columns' => array(
			'id_blog' => array(
				'header' => array(
					'value' => $txt['blog_id'],
				),
				'data' => array(
					'db' => 'id_blog',
				),
				'sort' => array(
					'default' => 'id_blog',
					'reverse' => 'id_blog DESC',
				),
			),
			'blog' => array(
				'header' => array(
					'value' => $txt['blog'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . strtr($scripturl, array('%' => '%%')) . '?action=blog;b=%1$d">%2$s</a>',
						'params' => array(
							'id_blog' => false,
							'title' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'title',
					'reverse' => 'title DESC',
				),
			),
			'time' => array(
				'header' => array(
					'value' => $txt['blog_time'],
				),
				'data' => array(
					'db' => 'time',
					'timeformat' => true
				),
				'sort' => array(
					'default' => 'time',
					'reverse' => 'time DESC',
				),
			),
			'poster' => array(
				'header' => array(
					'value' => $txt['blog_poster'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $scripturl, $txt;

						return \'<a href="\' . $scripturl . \'?action=profile;u=\' . $rowData[\'id_member\'] . \'" title="\' . $txt[\'profile_of\'] . \' \' . $rowData[\'real_name\'] . \'">\' . $rowData[\'real_name\'] . \'</a>\';
					'),
				),
				'sort' => array(
					'default' => 'id_member',
					'reverse' => 'id_member DESC',
				),
			),
			'ip' => array(
				'header' => array(
					'value' => $txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . strtr($scripturl, array('%' => '%%')) . '?action=trackip;searchip=%1$s">%1$s</a>',
						'params' => array(
							'poster_ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'INET_ATON(poster_ip)',
					'reverse' => 'INET_ATON(poster_ip) DESC',
				),
			),
			'views' => array(
				'header' => array(
					'value' => $txt['blog_views'],
				),
				'data' => array(
					'db'=> 'views',
				),
				'sort' => array(
					'default' => 'views',
					'reverse' => 'views DESC',
				),
			),
			'edit' => array(
				'header' => array(
					'value' => '',
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $scripturl, $txt;

						return \'<a href="\' . $scripturl . \'?action=admin;area=blog;sa=add;activity=edit;b=\' . $rowData[\'id_blog\'] . \'">\' . $txt[\'blog_edit\'] . \'</a>\';
					'),
					'class' => 'centercol',
				),
				'sort' => false,
			),
			// 'check' => array(
				// 'header' => array(
					// 'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
					// 'class' => 'centercol',
				// ),
				// 'data' => array(
					// 'function' => create_function('$rowData', '
						// global $user_info;

						// return \'<input type="checkbox" name="delete[]" value="\' . $rowData[\'id_blog\'] . \'" class="input_check" />\';
					// '),
					// 'class' => 'centercol',
				// ),
			// ),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=blog;sa=bloglist',
			'include_start' => true,
			'include_sort' => true,
		),
		'no_items_label' => $txt['no_blog_added'],
		// 'additional_rows' => array(
			// array(
				// 'position' => 'below_table_data',
				// 'value' => '<input type="submit" name="delete_members" value="' . $txt['admin_delete_members'] . '" onclick="return confirm(\'' . $txt['confirm_delete_members'] . '\');" class="btn" />',
			// ),
		// ),
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['default_list'] = 'member_list';
	$context['sub_template'] = 'admin_blog_list';
	
}

function AddBlog()
{
	global $context, $txt, $scripturl, $sourcedir;
	
	$context['submit_label'] = $txt['post'];
	
	// Needed for the editor only.
	require_once($sourcedir . '/Subs-Editor.php');
	
	$context['content'] = '';
	$context['title'] = '';
	$context['tags'] = '';
	$context['post_url'] = ';activity=new';
	
	// Now create the editor.
	$editorOptions = array(
		'id' => 'content',
		'value' => $context['content'],
		'labels' => array(
			'post_button' => $context['submit_label'],
		),
		// add height and width for the editor
		'height' => '275px',
		'width' => '100%',
		// We do XML preview here.
		'preview_type' => 2,
	);
	create_control_richedit($editorOptions);

	// Store the ID.
	$context['post_box_name'] = $editorOptions['id'];
	
	$context['sub_template'] = 'add_blog';
	$context['page_title'] = $txt['new_blog'];
}

function AddBlog2()
{
	global $smcFunc, $context, $scripturl, $user_info, $sourcedir;
	
	require_once($sourcedir . '/Subs-Post.php');
	require_once($sourcedir . '/Subs-Blog.php');
	
	$_POST['content'] = $smcFunc['htmlspecialchars']($_POST['content'], ENT_QUOTES);
	preparsecode($_POST['content']);
	
	if (isset($_REQUEST['b']))
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}blog_posts
			SET
				title = {string:title}, content = {string:content},
				modified_time = {int:time}, modified_id = {int:member_id}
			WHERE id_blog = {int:blog_id}
			LIMIT 1',
			array(
				'blog_id' => $_REQUEST['b'],
				'title' => $_POST['title'],
				'content' => $_POST['content'],
				'time' => time(),
				'member_id' => $user_info['id'],
			)
		);
		$id_blog = $_REQUEST['b'];
		
		deleteBlogTags($id_blog);
		addBlogTags($id_blog, $_POST['tags']);		
	}
	else
	{
		$smcFunc['db_insert']('normal', '{db_prefix}blog_posts',
			array(
				'time' => 'int', 'id_member' => 'int', 'title' => 'string-255', 'content' => 'string-65534', 'poster_ip' => 'string-255',
			),
			array(
				time(), $user_info['id'], $_POST['title'], $_POST['content'], $user_info['ip'],
			),
			array('id_blog')
		);
		$id_blog = $smcFunc['db_insert_id']('{db_prefix}blog_posts', 'id_blog');
		
		addBlogTags($id_blog, $_POST['tags']);
	}
	
	redirectexit($scripturl . '?action=blog;b=' . $id_blog);
}

function EditBlog()
{
	global $context, $txt, $scripturl, $sourcedir;
	
	$context['submit_label'] = $txt['post'];
	
	// Needed for the editor only.
	require_once($sourcedir . '/Subs-Editor.php');
	require_once($sourcedir . '/Subs-Blog.php');
	
	$query = list_getBlog(1, 0, '', 'bp.id_blog = {int:blog_id}', array('blog_id' => $_REQUEST['b']));
	
	$context['content'] = $query[0]['content'];
	$context['title'] = $query[0]['title'];
	$context['tags'] = implode(', ', getBlogTags($_REQUEST['b']));
	$context['post_url'] = ';activity=edit2;b=' . $_REQUEST['b'];
	
	// Now create the editor.
	$editorOptions = array(
		'id' => 'content',
		'value' => $context['content'],
		'labels' => array(
			'post_button' => $context['submit_label'],
		),
		// add height and width for the editor
		'height' => '275px',
		'width' => '100%',
		// We do XML preview here.
		'preview_type' => 2,
	);
	create_control_richedit($editorOptions);

	// Store the ID.
	$context['post_box_name'] = $editorOptions['id'];
	
	$context['sub_template'] = 'add_blog';
	$context['page_title'] = $txt['new_blog'];
}

?>