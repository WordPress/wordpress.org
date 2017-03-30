<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;
use WordPressdotorg\Plugin_Directory\Plugin_I18n;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * The [wporg-plugins-developers] shortcode handler to display developer information.
 *
 * @package WordPressdotorg\Plugin_Directory\Shortcodes
 */
class Developers {

	/**
	 * @return string
	 */
	static function display() {
		$post   = get_post();
		$slug   = $post->post_name;
		$title  = get_the_title( $post );
		$output = '<p>' . sprintf( __( '%s is open source software. The following people have contributed to this plugin.', 'wporg-plugins' ), $title ) . '</p>';

		ob_start();
		the_widget( 'WordPressdotorg\Plugin_Directory\Widgets\Contributors', array(), array(
			'before_title'  => '<span class="screen-reader-text">',
			'after_title'   => '</span>',
			'before_widget' => '',
			'after_widget'  => '',
		) );

		$output .= ob_get_clean();

		$locales = Plugin_I18n::instance()->get_locales();
		$output .= '<p>';

		if ( ! empty( $locales ) ) {
			$locales_list = implode( ', ', array_map( function( $locale ) use ( $slug ) {
				return sprintf( '<a href="%1$s">%2$s</a>', esc_url( "{$locale->locale}.wordpress.org/plugins/{$slug}/" ), $locale->name );
			}, $locales ) );
			/* Translators: 1: Plugin name; 2: Number of locales; 3: List of locales; */
			$output .= sprintf( '%1$s has been translated into these %2$d locales: %3$s.', $title, count( $locales ), $locales_list ) . ' ';
			$output .= sprintf(
				/* Translators: URL to translator view; */
				__( 'Thank you to <a href="%s">the translators</a> for their contributions.', 'wporg-plugins' ),
				esc_url( "https://translate.wordpress.org/projects/wp-plugins/{$slug}/contributors" )
			);
			$output .= '<br />';
		}

		/* Translators: 1: GlotPress URL; 2: Plugin name; */
		$output .= sprintf( __( '<a href="%1$s">Translate %2$s into your language.</a>', 'wporg-plugins' ), esc_url( 'https://translate.wordpress.org/projects/wp-plugins/' . $slug ), $title );
		$output .= '</p>';

		$output   .= '<h3>' . __( 'Interested in development?', 'wporg-plugins' ) . '</h3>';
		/* Translators: 1: Trac URL; 2: Development log URL; 3: RSS URL; */
		$format    = __( '<a href="%1$s">Browse the code</a> or subscribe to the <a href="%2$s">development log</a> by <a href="%3$s">RSS</a>.' );
		$email_url = '';

		if ( is_user_logged_in() ) {
			/* Translators: 1: Trac URL; 2: Development log URL; 3: RSS URL; 4: Email subscription URL; */
			$format     = __( '<a href="%1$s">Browse the code</a> or subscribe to the <a href="%2$s">development log</a> by <a href="%4$s">email</a> or <a href="%3$s">RSS</a>.' );
			$subscribed = Tools::subscribed_to_plugin_commits( $post, get_current_user_id() );
			$email_url  = esc_url( add_query_arg( array(
				'_wpnonce' => wp_create_nonce( 'wp_rest' ),
				( $subscribed ? 'unsubscribe' : 'subscribe' ) => '1',
			), home_url( "wp-json/plugins/v1/plugin/{$slug}/commit-subscription" ) ) );
		}

		$output .= '<p>' . sprintf(
			$format,
			esc_url( "https://plugins.trac.wordpress.org/browser/{$slug}/" ),
			esc_url( "https://plugins.trac.wordpress.org/log/{$slug}/" ),
			esc_url( "https://plugins.trac.wordpress.org/log/{$slug}/?limit=100&mode=stop_on_copy&format=rss" ),
			$email_url
		) . '</p>';

		return $output;
	}
}
