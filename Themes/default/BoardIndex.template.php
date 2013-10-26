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
			<script src="', $settings['default_theme_url'], '/scripts/fader.js"></script>
			<script><!-- // --><![CDATA[
		
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
			// ]]></script>';
	}

			
	if ($context['sidebar_settings']['sidebar_enabled'])
	{
		// We can make a little sidebar here... A happy little sidebar R.I.P. Bob Ross :/
		echo '
			<div class="sidebar visible-desktop">
				<div class="well">
					',$txt['dummy_sidebar'],'
				</div>';

		// Stats and Online list (Sidebar)
			echo '
				<div class="well">';
				
		// Stats coming here
		if (!empty($context['sidebar_settings']['sidebar_stats']))
			echo '<strong>', $context['common_stats']['boardindex_total_posts'], '</strong><br /><br />';
				
		// Here my online list
		if (!empty($context['sidebar_settings']['sidebar_online']))
		{
		// "Users online" - in order of activity.
			echo '
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

	echo $context['show_who'] ? '</a><br />' : '', '

					', $txt['most_online_today'], ': <strong>', comma_format($modSettings['mostOnlineToday']), '</strong><br />
					', $txt['most_online_ever'], ': ', comma_format($modSettings['mostOnline']), ' <br /> (', timeformat($modSettings['mostDate']), ')<br />';

	// Assuming there ARE users online... each user in users_online has an id, username, name, group, href, and link.
	if (!empty($context['users_online']))
	{
		echo '
					', sprintf($txt['users_active'], $modSettings['lastActive']), ': <br /> ', implode(', ', $context['list_users_online']);

		// Showing membergroups?
		if (!empty($settings['show_group_key']) && !empty($context['membergroups']))
			echo '<br /><br />
					<span class="membergroups">[' . implode(',&nbsp;', $context['membergroups']). ']</span>';
	}

	echo '
				</div>';
				
		echo '
			</div>
			<div class="mainstuff">';
		}			
	}
		else
		echo '
			<div class="mainstuff2">';
		
		
		// Main things happen here (topic/stats)	
		echo '
				<div id="index_topics">
					<ul class="pagination">', $context['page_index'], '</ul>
					<div class="entry-title" style="display: none;">', $context['forum_name_html_safe'], ' - ', $txt['recent_posts'], '</div>
					<div class="entry-content" style="display: none;">
						<a rel="feedurl" href="', $scripturl, '?action=.xml;type=webslice">', $txt['subscribe_webslice'], '</a>
					</div>';
	
		// Show lots of posts.
		if (!empty($context['latest_topics']))
		{
			// How it looks at desktop
			echo '
				<div class="visible-desktop">
					<table class="table table-bordered">
						<tbody>';
			
			foreach ($context['latest_topics'] as $topic)
				echo '
						<tr>
							<td class="replies center">', $topic['replies'], '<br />', $txt['replies'], '</td>
							<td class="views center">', $topic['views'], '<br />', $txt['views'], '</td>
							<td class="views center">', $topic['likes'], '<br />', $txt['likes'], '</td>
							<td class="topic">
								<h4>', $topic['link'], '</h4>
								<div class="pull-right">', $topic['time'], ' ', $txt['by'], ' ', $topic['poster']['link'], '</div>
							</td>
						</tr>';
			
			echo '
						</tbody>
					</table>
				</div>';
						
			// How it looks at mobile
			echo '
				<div class="hidden-desktop">
					<table class="table table-bordered">
						<tbody>';
			
			foreach ($context['latest_topics'] as $topic)
				echo '
						<tr>
							<td class="topic">
								<h4>', $topic['link'], '</h4>
								<div class="pull-left">', $topic['likes'], ' ', $txt['likes'], ' | ', $topic['views'], ' ', $txt['views'], ' </div>
								<div class="pull-right">', $topic['time'], ' ', $txt['by'], ' ', $topic['poster']['link'], '</div>
							</td>
						</tr>';
						
			echo '
						</tbody>
					</table>
				</div>';				
		}
		echo '
				</div>
				', template_button_strip($context['new_topic'], 'right'), '
			</div>
	<div class="clearfix"></div>';
}
?>