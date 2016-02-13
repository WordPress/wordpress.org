<?php

if ( is_front_page() && ( ! get_query_var( 'browse' ) || 'featured' == get_query_var( 'browse' ) ) ) {
		printf(
			/* translators: 1: Plugins count 2: Download count */
			'<p class="intro">' . __( 'Plugins extend and expand the functionality of WordPress. %1$s plugins with %2$s total downloads are at your fingertips.', 'wporg-plugins' ) . '</p>',
			'<strong>' . number_format_i18n( wp_count_posts( 'plugin' )->publish ) . '</strong>',
			'<strong>' . number_format_i18n( Plugin_Directory_Template_Helpers::get_total_downloads() ) . '</strong>'
	);
}

if ( 'beta' == get_query_var( 'browse' ) ) {
	echo '<p class="intro">' . __( 'The plugins listed here are proposed for a future version of WordPress. They are under active development.<br />You can try them out, provide feedback, or join one of the development teams.', 'wporg-plugins' ) . '</p>';
} elseif ( 'favorites' == get_query_var( 'browse' ) ) {
	echo '<p class="intro">' . sprintf(
		__( 'Your favorite plugins are listed here. They also appear on <a href="%s">your profile</a>.', 'wporg-plugins' ),
		'https://profiles.wordpress.org/' . wp_get_current_user()->user_nicename
	) . '</p>';
}