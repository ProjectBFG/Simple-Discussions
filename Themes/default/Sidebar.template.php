<?php

function template_main()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;
	
	echo '
	<div id="admincenter">
		<h3 class="catbg">
			', $txt['gls_sidebar'], '
		</h3>
		<div class="alert alert-info">
			', $txt['gls_sidebar_info'], '
		</div>
		<div class="well">
			<div class="content">
				<dl class="settings">
					<dt>
						',$txt['gls_sidebar_stats'],'
					</dt>
					<dd>
						<input type="checkbox" name="options[sidebar_stats]" id="options-sidebar-stats" value="1"', !empty($modSettings['sidebar_stats']) ? ' checked="checked"' : '', ' class="input_check" />
					</dd>
					<dt>
						',$txt['gls_sidebar_online'],'
					</dt>
					<dd>
						<input type="checkbox" name="options[sidebar_online]" id="options-sidebar-online" value="1"', !empty($modSettings['sidebar_online']) ? ' checked="checked"' : '', ' class="input_check" />
					</dd>
				</dl>
				
				<input type="submit" name="save" value="' . $txt['save'] . '" class="btn" />
				<input type="hidden" value="0" name="options[sidebar_stats]" />
				
			</div>
		</div>	
	</div>';
}

?>