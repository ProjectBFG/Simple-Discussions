<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

function template_main()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Show some statistics if stat info is off.
	if (!$settings['show_stats_index'])
		echo '
	<div id="index_common_stats">
		', $txt['members'], ': ', $context['common_stats']['total_members'], ' &nbsp;&#8226;&nbsp; ', $txt['posts_made'], ': ', $context['common_stats']['total_posts'], ' &nbsp;&#8226;&nbsp; ', $txt['topics_made'], ': ', $context['common_stats']['total_topics'], '<br />
		', $settings['show_latest_member'] ? ' ' . sprintf($txt['welcome_newest_member'], ' <strong>' . $context['common_stats']['latest_member']['link'] . '</strong>') : '' , '
	</div>';

	// Show the news fader?  (assuming there are things to show...)
	if (!empty($settings['show_newsfader']) && !empty($context['news_lines']))
	{
		echo '
			<div id="newsfader">
					<h3 class="catbg">
						<img id="newsupshrink" src="', $settings['images_url'], '/collapse.png" alt="*" title="', $txt['hide'], '" align="bottom" style="display: none;" />
						', $txt['news'], '
					</h3>
				<div class="roundframe rfix" id="smfFadeScrollerCont">
					<ul class="reset" id="smfFadeScroller"', empty($options['collapse_news_fader']) ? '' : ' style="display: none;"', '>
						<li>
							', implode('</li><li>', $context['news_lines']), '
						</li>
					</ul>
				</div>
			</div>
			<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/fader.js"></script>
			<script type="text/javascript"><!-- // --><![CDATA[
		
				// Create a news fader object.
				var oNewsFader = new smc_NewsFader({
					sFaderControlId: \'smfFadeScroller\',
					sItemTemplate: ', JavaScriptEscape('%1$s'), ',
					iFadeDelay: ', empty($settings['newsfader_time']) ? 5000 : $settings['newsfader_time'], '
				});
		
				// Create the news fader toggle.
				var smfNewsFadeToggle = new smc_Toggle({
					bToggleEnabled: true,
					bCurrentlyCollapsed: ', empty($options['collapse_news_fader']) ? 'false' : 'true', ',
					aSwappableContainers: [
						\'smfFadeScrollerCont\'
					],
					aSwapImages: [
						{
							sId: \'newsupshrink\',
							srcExpanded: smf_images_url + \'/collapse.png\',
							altExpanded: ', JavaScriptEscape($txt['hide']), ',
							srcCollapsed: smf_images_url + \'/expand.png\',
							altCollapsed: ', JavaScriptEscape($txt['show']), '
						}
					],
					oThemeOptions: {
						bUseThemeSettings: ', $context['user']['is_guest'] ? 'false' : 'true', ',
						sOptionName: \'collapse_news_fader\',
						sSessionVar: smf_session_var,
						sSessionId: smf_session_id
					},
					oCookieOptions: {
						bUseCookie: ', $context['user']['is_guest'] ? 'true' : 'false', ',
						sCookieName: \'newsupshrink\'
					}
				});
			// ]]></script>
		';
	}

	echo '
	<div id="boardindex_table" class="boardindex_table">
		<table class="table table-bordered">';

	/* Each category in categories is made up of:
	id, href, link, name, is_collapsed (is it collapsed?), can_collapse (is it okay if it is?),
	new (is it new?), collapse_href (href to collapse/expand), collapse_image (up/down image),
	and boards. (see below.) */
	foreach ($context['categories'] as $category)
	{
		// If theres no parent boards we can see, avoid showing an empty category (unless its collapsed)
		if (empty($category['boards']) && !$category['is_collapsed'])
			continue;

		echo '
			<thead id="category_', $category['id'], '">
				<tr>
					<td colspan="4">
						<h3 class="catbg">';

		// If this category even can collapse, show a link to collapse it.
		if ($category['can_collapse'])
			echo '
							<a href="', $category['collapse_href'], '" title="', $category['is_collapsed'] ? $txt['show'] : $txt['hide'], '">
								<span class="', !$category['is_collapsed'] ? 'icon-arrow-down' : 'icon-arrow-up', '"></span>
							</a>';

		echo '
							', $category['link'], '
						</h3>
					</td>
				</tr>
			</thead>';

		// Assuming the category hasn't been collapsed...
		if (!$category['is_collapsed'])
		{

		echo '
			<tbody id="category_', $category['id'], '_boards">';
			/* Each board in each category's boards has:
			new (is it new?), id, name, description, moderators (see below), link_moderators (just a list.),
			children (see below.), link_children (easier to use.), children_new (are they new?),
			topics (# of), posts (# of), link, href, and last_post. (see below.) */
			foreach ($category['boards'] as $board)
			{
				echo '
				<tr id="board_', $board['id'], '">
					<td class="windowbg center"', !empty($board['children']) ? ' rowspan="2"' : '', '>
						<a href="', ($board['is_redirect'] || $context['user']['is_guest'] ? $board['href'] : $scripturl . '?action=unread;board=' . $board['id'] . '.0;children'), '">';

				// If the board or children is new, show an indicator.
				if ($board['new'] || $board['children_new'])
					echo '
							<img src="', $settings['images_url'], '/', $context['theme_variant_url'], 'on', $board['new'] ? '' : '2', '.png" alt="', $txt['new_posts'], '" title="', $txt['new_posts'], '" />';
				// Is it a redirection board?
				elseif ($board['is_redirect'])
					echo '
							<img src="', $settings['images_url'], '/', $context['theme_variant_url'], 'redirect.png" alt="*" title="*" />';
				// No new posts at all! The agony!!
				else
					echo '
							<img src="', $settings['images_url'], '/', $context['theme_variant_url'], 'off.png" alt="', $txt['old_posts'], '" title="', $txt['old_posts'], '" />';

				echo '
						</a>
					</td>
					<td class="info">
						<a class="subject" href="', $board['href'], '" name="b', $board['id'], '">', $board['name'], '</a>';

				// Has it outstanding posts for approval?
				if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics']))
					echo '
						<a href="', $scripturl, '?action=moderate;area=postmod;sa=', ($board['unapproved_topics'] > 0 ? 'topics' : 'posts'), ';brd=', $board['id'], ';', $context['session_var'], '=', $context['session_id'], '" title="', sprintf($txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link">(!)</a>';

				echo '

						<p>', $board['description'] , '</p>';

				// Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.)
				if (!empty($board['moderators']))
					echo '
						<p class="moderators">', count($board['moderators']) == 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $board['link_moderators']), '</p>';

				// Show some basic information about the number of posts, etc.
					echo '
					</td>
					<td class="windowbg center">
						<p>', comma_format($board['posts']), ' ', $board['is_redirect'] ? $txt['redirects'] : $txt['posts'], '
						', $board['is_redirect'] ? '' : '<br /> '.comma_format($board['topics']) . ' ' . $txt['board_topics'], '
						</p>
					</td>
					<td class="lastpost">';

				if (!empty($board['last_post']['id']))
					echo '
						<p>', $board['last_post']['last_post_message'], '</p>';
				echo '
					</td>
				</tr>';
				// Show the "Child Boards: ". (there's a link_children but we're going to bold the new ones...)
				if (!empty($board['children']))
				{
					// Sort the links into an array with new boards bold so it can be imploded.
					$children = array();
					/* Each child in each board's children has:
							id, name, description, new (is it new?), topics (#), posts (#), href, link, and last_post. */
					foreach ($board['children'] as $child)
					{
						if (!$child['is_redirect'])
							$child['link'] = '<a href="' . $child['href'] . '" ' . ($child['new'] ? 'class="board_new_posts" ' : '') . 'title="' . ($child['new'] ? $txt['new_posts'] : $txt['old_posts']) . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')">' . $child['name'] . ($child['new'] ? '</a> <a href="' . $scripturl . '?action=unread;board=' . $child['id'] . '" title="' . $txt['new_posts'] . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')"><span class="new_posts">' . $txt['new'] . '</span>' : '') . '</a>';
						else
							$child['link'] = '<a href="' . $child['href'] . '" title="' . comma_format($child['posts']) . ' ' . $txt['redirects'] . ' - ' . $child['short_description'] . '">' . $child['name'] . '</a>';

						// Has it posts awaiting approval?
						if ($child['can_approve_posts'] && ($child['unapproved_posts'] || $child['unapproved_topics']))
							$child['link'] .= ' <a href="' . $scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link">(!)</a>';

						$children[] = $child['new'] ? '<strong>' . $child['link'] . '</strong>' : $child['link'];
					}

				echo '
					<tr id="board_', $board['id'], '_children" class="windowbg2">
						<td colspan="3" class="windowbg children">
							<p><strong>', $txt['parent_boards'], '</strong>: ', implode(', ', $children), '</p>
						</td>
					</tr>';
				}
			}
		echo '
			</tbody>';
		}

	}
	echo '
		</table>
	</div>
		<ul id="posting_icons">';

	if ($context['user']['is_logged'])
	echo '
			<li class="floatleft"><img src="', $settings['images_url'], '/', $context['theme_variant_url'], 'new_some.png" alt="" /> ', $txt['new_posts'], '</li>';

	echo '
			<li class="floatleft"><img src="', $settings['images_url'], '/', $context['theme_variant_url'], 'new_none.png" alt="" /> ', $txt['old_posts'], '</li>
			<li class="floatleft"><img src="', $settings['images_url'], '/', $context['theme_variant_url'], 'new_redirect.png" alt="" /> ', $txt['redirect_board'], '</li>
		</ul>';

	// Show the mark all as read button?
	if ($settings['show_mark_read'] && !empty($context['categories']))
	echo '
		<div class="mark_read">', template_button_strip($context['mark_read_button'], 'right'), '</div>';

	template_info_center();
}

function template_info_center()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Here's where the "Info Center" starts...
	echo '
	<div class="well" id="info_center">
		<h3 class="catbg">
			<span class="icon-arrow-down" data-toggle="collapse" data-target="#upshrinkHeaderIC" id="collapseIC"></span>
			<a href="#">', sprintf($txt['info_center_title'], $context['forum_name_html_safe']), '</a>
		</h3>
		<div id="upshrinkHeaderIC" class="collapse in">
			<ul class="nav nav-tabs" id="ic_tabs">
				<li class="active"><a href="#ic_recent_posts_content" data-toggle="tab">Recent</a></li>
				<li><a href="#ic_stats" data-toggle="tab">Stats</a></li>
				<li><a href="#ic_membersonline" data-toggle="tab">Members Online</a></li>
			</ul>
			<div id="ic_tabsContent" class="tab-content">';

	// This is the "Recent Posts" bar.
	if (!empty($settings['number_recent_posts']) && (!empty($context['latest_posts']) || !empty($context['latest_post'])))
	{
		echo '
				<div class="tab-pane fade in active" id="ic_recent_posts_content">
					<div class="entry-title" style="display: none;">', $context['forum_name_html_safe'], ' - ', $txt['recent_posts'], '</div>
					<div class="entry-content" style="display: none;">
						<a rel="feedurl" href="', $scripturl, '?action=.xml;type=webslice">', $txt['subscribe_webslice'], '</a>
					</div>';

		// Only show one post.
		if ($settings['number_recent_posts'] == 1)
		{
			// latest_post has link, href, time, subject, short_subject (shortened with...), and topic. (its id.)
			echo '
					<p id="infocenter_onepost" class="inline">
						<a href="', $scripturl, '?action=recent">', $txt['recent_view'], '</a>&nbsp;&quot;', sprintf($txt['is_recent_updated'], '&quot;' . $context['latest_post']['link'], '&quot;'), ' (', $context['latest_post']['time'], ')<br />
					</p>';
		}
		// Show lots of posts.
		elseif (!empty($context['latest_posts']))
		{
			echo '
					<table class="table table-stripped">
						<thead>
							<tr>
								<th class="recentpost first_th">', $txt['message'], '</th>
								<th class="recentposter">', $txt['author'], '</th>
								<th class="recentboard">', $txt['board'], '</th>
								<th class="recenttime last_th">', $txt['date'], '</th>
							</tr>
						</thead>
						<tbody>';

			/* Each post in latest_posts has:
					board (with an id, name, and link.), topic (the topic's id.), poster (with id, name, and link.),
					subject, short_subject (shortened with...), time, link, and href. */
			foreach ($context['latest_posts'] as $post)
				echo '
						<tr>
							<td class="recentpost"><strong>', $post['link'], '</strong></td>
							<td class="recentposter">', $post['poster']['link'], '</td>
							<td class="recentboard">', $post['board']['link'], '</td>
							<td class="recenttime">', $post['time'], '</td>
						</tr>';
			echo '
						</tbody>
					</table>';
		}
		echo '
				</div>';
	}

	// Show statistical style information...
	if ($settings['show_stats_index'])
	{
		echo '
				<div class="tab-pane fade" id="ic_stats">
					', $context['common_stats']['boardindex_total_posts'], '', !empty($settings['show_latest_member']) ? ' - '. $txt['latest_member'] . ': <strong> ' . $context['common_stats']['latest_member']['link'] . '</strong>' : '', '<br />
					', (!empty($context['latest_post']) ? $txt['latest_post'] . ': <strong>&quot;' . $context['latest_post']['link'] . '&quot;</strong>  ( ' . $context['latest_post']['time'] . ' )<br />' : ''), '
					<a href="', $scripturl, '?action=recent">', $txt['recent_view'], '</a>
				</div>';
	}

	// "Users online" - in order of activity.
	echo '
				<div class="tab-pane fade" id="ic_membersonline">
					', $context['show_who'] ? '<a href="' . $scripturl . '?action=who">' : '', '<strong>', $txt['online'], ': </strong>', comma_format($context['num_guests']), ' ', $context['num_guests'] == 1 ? $txt['guest'] : $txt['guests'], ', ', comma_format($context['num_users_online']), ' ', $context['num_users_online'] == 1 ? $txt['user'] : $txt['users'];

	// Handle hidden users and buddies.
	$bracketList = array();
	if ($context['show_buddies'])
		$bracketList[] = comma_format($context['num_buddies']) . ' ' . ($context['num_buddies'] == 1 ? $txt['buddy'] : $txt['buddies']);
	if (!empty($context['num_spiders']))
		$bracketList[] = comma_format($context['num_spiders']) . ' ' . ($context['num_spiders'] == 1 ? $txt['spider'] : $txt['spiders']);
	if (!empty($context['num_users_hidden']))
		$bracketList[] = comma_format($context['num_users_hidden']) . ' ' . ($context['num_spiders'] == 1 ? $txt['hidden'] : $txt['hidden_s']);

	if (!empty($bracketList))
		echo ' (' . implode(', ', $bracketList) . ')';

	echo $context['show_who'] ? '</a>' : '', '

					&nbsp;-&nbsp;', $txt['most_online_today'], ': <strong>', comma_format($modSettings['mostOnlineToday']), '</strong>&nbsp;-&nbsp;
					', $txt['most_online_ever'], ': ', comma_format($modSettings['mostOnline']), ' (', timeformat($modSettings['mostDate']), ')<br />';

	// Assuming there ARE users online... each user in users_online has an id, username, name, group, href, and link.
	if (!empty($context['users_online']))
	{
		echo '
					', sprintf($txt['users_active'], $modSettings['lastActive']), ': ', implode(', ', $context['list_users_online']);

		// Showing membergroups?
		if (!empty($settings['show_group_key']) && !empty($context['membergroups']))
			echo '
					<span class="membergroups">[' . implode(',&nbsp;', $context['membergroups']). ']</span>';
	}

	echo '
				</div>';

	// If they are logged in, but statistical information is off... show a personal message bar.
	if ($context['user']['is_logged'] && !$settings['show_stats_index'])
	{
		echo '
				<div class="pminfo">
					', empty($context['user']['messages']) ? $txt['you_have_no_msg'] : ($context['user']['messages'] == 1 ? sprintf($txt['you_have_one_msg'], $scripturl . '?action=pm') : sprintf($txt['you_have_many_msgs'], $scripturl . '?action=pm', $context['user']['messages'])), '
				</div>';
	}

	echo '
			</div>
		</div>
	</div>';
	
	// Info center collapse object.
	echo '
	<script>
		$(\'#upshrinkHeaderIC\').on(\'show hide\', function(e){
			$(\'#collapseIC\').toggleClass(\'icon-arrow-up icon-arrow-down\', 200);
		});
	</script>';
}
?>