<?php
/**
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
	global $context, $settings, $options, $txt, $scripturl;

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
		// We can make a little sidebar here... A happy sidebar R.I.P. Bob Ross :/
		echo '
			<div class="sidebar visible_desktop">
				<div class="well">
					',$txt['dummy_sidebar'],'
				</div>
			</div>';
			
		// Main things happen here (topic/stats)	
		echo '
			<div class="mainstuff">
				<div id="index_topics">
					<div class="entry-title" style="display: none;">', $context['forum_name_html_safe'], ' - ', $txt['recent_posts'], '</div>
					<div class="entry-content" style="display: none;">
						<a rel="feedurl" href="', $scripturl, '?action=.xml;type=webslice">', $txt['subscribe_webslice'], '</a>
					</div>';
	
		// Show lots of posts.
		if (!empty($context['latest_topics']))
		{
			echo '
					<table class="table table-bordered">
						<tbody>';
			foreach ($context['latest_topics'] as $topic)
				echo '
						<tr>
							<td class="replies center">', $topic['replies'], '<br />'.$txt['replies'].'</td>
							<td class="views center">', $topic['views'], '<br />'.$txt['views'].'</td>
							<td class="topic">
								<h4>', $topic['link'], '</h4>
								<div class="pull-right">', $topic['time'], ' ', $txt['by'], ' ', $topic['poster']['link'], '</div>
							</td>
						</tr>';
			echo '
						</tbody>
					</table>
				</div>';
		}
		echo '
				', template_button_strip($context['new_topic'], 'right'), '
				<br /><br />';

	template_info_center();
	
		echo '
			</div>
				<div class="clearfix"></div>';
}

function template_info_center()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	// Here's where the "Info Center" starts...
	echo '
	<div class="well" id="info_center">
		<h3 class="catbg">
			<span class="icon-arrow-down" data-toggle="collapse" data-target="#upshrinkHeaderIC" id="collapseIC"></span>
			<a href="#">', sprintf($txt['info_center_title'], $context['forum_name_html_safe']), '</a>
		</h3>
		<div id="upshrinkHeaderIC" class="collapse in">
			<ul class="nav nav-tabs" id="ic_tabs">
				<li class="active"><a href="#ic_stats" data-toggle="tab">Stats</a></li>
				<li><a href="#ic_membersonline" data-toggle="tab">Members Online</a></li>
			</ul>
			<div id="ic_tabsContent" class="tab-content">';

	// Show statistical style information...
	if ($settings['show_stats_index'])
	{
		echo '
				<div class="tab-pane fade in active" id="ic_stats">
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
			if(!$(this).is(e.target))return;
			$(\'#collapseIC\').toggleClass(\'icon-arrow-up icon-arrow-down\', 200);
		});
	</script>';
}
?>