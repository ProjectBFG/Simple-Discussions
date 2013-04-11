<?php

/**
 *
 * @version 1.0 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...'); 

function list_getBlogs($start, $items_per_page, $sort, $where, $where_params = array())
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT
			bp.id_blog, bp.title, bp.id_member, bp.time, bp.content, bp.poster_ip, bp.modified_time, bp.modified_id, bp.num_views,
			mem.real_name, bt.tag
		FROM {db_prefix}blog_posts AS bp
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bp.id_member)
			LEFT JOIN {db_prefix}blog_tags AS bt ON (bt.id_blog = bp.id_blog)
		WHERE ' . ($where == '1' ? '1=1' : $where) . (!empty($sort) ? '
		ORDER BY {raw:sort}' : '') . '
		LIMIT {int:start}' . (!empty($items_per_page) ? ', {int:per_page}' : ''),
		array_merge($where_params, array(
			'sort' => $sort,
			'start' => $start,
			'per_page' => $items_per_page,
		))
	);
	$blogs = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$blogs[] = $row;
	$smcFunc['db_free_result']($request);

	return $blogs;
}

function list_getNumBlogs($where, $where_params = array())
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(id_blog)
		FROM {db_prefix}blog_posts
		WHERE ' . $where,
		array_merge($where_params, array(
		))
	);
	list ($num_blogs) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $num_blogs;
}

function deleteBlogTags($blog)
{
	global $smcFunc;
	
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}blog_tags
		WHERE id_blog = {int:blog_id}',
		array(
			'blog_id' => $blog,
		)
	);
	
	return;
}

function addBlogTags($blog, $tags)
{
	global $smcFunc;
	
	$tags = explode(',', str_replace(array(' ', '&nbsp;'), '', $tags));
		
	foreach ($tags as $tag)
	{
		$smcFunc['db_insert']('normal', '{db_prefix}blog_tags',
			array(
				'tag' => 'string-255', 'id_blog' => 'int', 'status' => 'int',
			),
			array(
				$tag, $blog, 1,
			),
			array()
		);
	}
	
	return;
}

function getBlogTags($blog)
{
	global $smcFunc;
	
	$request = $smcFunc['db_query']('', '
		SELECT tag
		FROM {db_prefix}blog_tags
		WHERE id_blog = {int:blog_id}',
		array(
			'blog_id' => $blog,
		)
	);
	$tags = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$tags[] = $row['tag'];
	$smcFunc['db_free_result']($request);

	return $tags;
}

?>