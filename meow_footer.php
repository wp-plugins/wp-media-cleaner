<?php

	if ( !function_exists( 'jordy_meow_flattr' ) ) {
		if ( !defined( 'WP_HIDE_DONATION_BUTTONS' ) )
			add_action( 'admin_head', 'jordy_meow_flattr', 1 );
		function jordy_meow_flattr () {
			?>
				<script type="text/javascript">
					/* <![CDATA[ */
					    (function() {
					        var s = document.createElement('script'), t = document.getElementsByTagName('script')[0];
					        s.type = 'text/javascript';
					        s.async = true;
					        s.src = '//api.flattr.com/js/0.6/load.js?mode=auto&uid=TigrouMeow&popout=0';
					        t.parentNode.insertBefore(s, t);
					    })();
					/* ]]> */
				</script>
			<?php
		}
		function by_jordy_meow() {
			echo '<div><span style="font-size: 13px; position: relative; top: -6px;">' . __( 'Developed by <a style="text-decoration: none;" href="http://www.meow.fr" target="_blank">Jordy Meow</a>', 'wp-media-cleaner' ) . '</span>';
			if ( !defined( 'WP_HIDE_DONATION_BUTTONS' ) )
				echo ' <a class="FlattrButton" style="display:none;" rev="flattr;button:compact;" title="Jordy Meow" href="http://profiles.wordpress.org/TigrouMeow/"></a>';
			echo '</div>';
		}
	}

	if ( !function_exists( 'jordy_meow_donation' ) ) {
		function jordy_meow_donation( $showWPE = true ) {
			if ( defined( 'WP_HIDE_DONATION_BUTTONS' ) && WP_HIDE_DONATION_BUTTONS == true )
				return;
			if ( $showWPE ) {
				echo '<a style="float: right; margin-top: -8px; text-align: right; text-decoration: none; font-size: 11px; color: gray; font-style: italic;" target="_blank" href="http://www.shareasale.com/r.cfm?b=398769&amp;u=767054&amp;m=41388&amp;urllink=&amp;afftrack=">' . __( 'I love and strongly recommend WP Engine. My plugins are all tested with it.', 'wp-media-cleaner' ) . '<br /><img style="height: 55px;" src="http://static.shareasale.com/image/41388/Feature-Fast-468x60.jpeg" border="0" /></a>';
			}
		}
	}

	if ( !function_exists('jordy_meow_footer') ) {
		function jordy_meow_footer() {
			?>
			<div style="color: #32595E; border: 1px solid #DFDFDF; background: white; padding: 0px 10px; margin-top: 15px;">
			<p style="font-size: 11px; font-family: Tahoma;">
			<?php
			_e( '<b>This plugin is actively developed and maintained by <a href="http://www.meow.fr">Jordy Meow</a></b>.<br />More of my tools are available here: <a href="http://apps.meow.fr">Meow Apps</a>. I am also a photographer in Japan: <a href="http://www.totorotimes.com">Totoro Times</a>.', 'wp-media-cleaner' );
			if ( !(defined( 'WP_HIDE_DONATION_BUTTONS' ) && WP_HIDE_DONATION_BUTTONS == true) ) {
				echo '<br />';
				echo ( sprintf( __( 'Donation link: %sPaypal%s. Thanks! ^^', 'wp-media-cleaner' ), '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=H2S7S3G4XMJ6J" target="_blank">', '</a>' ) );
			}
			?>
			</p>
			</div>
			<?php
		}
	}
?>
