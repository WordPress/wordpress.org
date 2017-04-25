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

		$output = '<div class="plugin-contributors">';

		$output .= '<p>' . sprintf(
			/* translators: %s: plugin name */
			__( '&#8220;%s&#8221; is open source software. The following people have contributed to this plugin.', 'wporg-plugins' ),
			$title
		) . '</p>';

		ob_start();
		the_widget( 'WordPressdotorg\Plugin_Directory\Widgets\Contributors', array(), array(
			'before_title'  => '<span class="screen-reader-text">',
			'after_title'   => '</span>',
			'before_widget' => '',
			'after_widget'  => '',
		) );

		$output .= ob_get_clean();
		$output .= '</div>';

		$output .= '<div class="plugin-development">';

		$locales = Plugin_I18n::instance()->get_locales();
		if ( ! empty( $locales ) ) {
			$output .= '<p>';

			$locale_names = wp_list_pluck( $locales, 'name', 'wp_locale' );
			$wp_locales = wp_list_pluck( $locales,'wp_locale' );

			$sites = get_sites( [
				'network_id' => WPORG_GLOBAL_NETWORK_ID,
				'public'     => 1,
				'path'       => '/',
				'locale__in' => $wp_locales,
				'number'     => '',
			] );

			if ( $sites ) {
				$locales_list = implode( ', ', array_map( function( $site ) use ( $slug, $locale_names ) {
					return sprintf( '<a href="%1$s">%2$s</a>', esc_url( "{$site->home}/plugins/{$slug}/" ), $locale_names[ $site->locale ] );
				}, $sites ) );

				$locales_count = count( $sites );

				if ( 1 === $locales_count ) {
					$output .= sprintf(
						/* translators: 1: plugin name, 2: locale name */
						__( '&#8220;%1$s&#8221; has been translated into %2$s.' ),
						$title,
						$locales_list
					) . ' ';
				} else {
					$output .= sprintf(
						/* translators: 1: plugin name, 2: number of locales, 3: list of locales */
						_n(
							'&#8220;%1$s&#8221; has been translated into these %2$d locales: %3$s.',
							'&#8220;%1$s&#8221; has been translated into these %2$d locales: %3$s.',
							$locales_count
						),
						$title,
						$locales_count,
						$locales_list
					) . ' ';
				}

				$output .= sprintf(
					/* translators: URL to translator view */
					__( 'Thank you to <a href="%s">the translators</a> for their contributions.', 'wporg-plugins' ),
					esc_url( "https://translate.wordpress.org/projects/wp-plugins/{$slug}/contributors" )
				);
				$output .= '</p>';
			}
		}

		$output .= '<p>' . sprintf( '<a href="%1$s">%2$s</a>',
			esc_url( 'https://translate.wordpress.org/projects/wp-plugins/' . $slug ),
			sprintf(
				/* translators: %s: plugin name */
				__( 'Translate &#8220;%s&#8221; into your language.', 'wporg-plugins' ),
				$title
			)
		) . '</p>';


		$output .= '<h3>' . __( 'Interested in development?', 'wporg-plugins' ) . '</h3>';

		if ( is_user_logged_in() ) {
			$subscribed = Tools::subscribed_to_plugin_commits( $post, get_current_user_id() );
			$email_url  = esc_url( add_query_arg( array(
				'_wpnonce' => wp_create_nonce( 'wp_rest' ),
				( $subscribed ? 'unsubscribe' : 'subscribe' ) => '1',
			), home_url( "wp-json/plugins/v1/plugin/{$slug}/commit-subscription" ) ) );

			$output .= '<p>' . sprintf(
				/* translators: 1: Trac URL, 2: SVN repository URL, 3: development log URL, 4: RSS URL, 5: email subscription URL */
				__( '<a href="%1$s">Browse the code</a>, check out the <a href="%2$s">SVN repository</a>, or subscribe to the <a href="%3$s">development log</a> by <a href="%4$s">email</a> or <a href="%5$s">RSS</a>.', 'wporg-plugins' ),
				esc_url( "https://plugins.trac.wordpress.org/browser/{$slug}/" ),
				esc_url( "https://plugins.svn.wordpress.org/{$slug}/" ),
				esc_url( "https://plugins.trac.wordpress.org/log/{$slug}/" ),
				esc_url( "https://plugins.trac.wordpress.org/log/{$slug}/?limit=100&mode=stop_on_copy&format=rss" ),
				$email_url
			) . '</p>';
		} else {
			$output .= '<p>' . sprintf(
				/* translators: 1: Trac URL, 2: development log URL, 3: RSS URL */
				__( '<a href="%1$s">Browse the code</a> or subscribe to the <a href="%2$s">development log</a> by <a href="%3$s">RSS</a>.', 'wporg-plugins' ),
				esc_url( "https://plugins.trac.wordpress.org/browser/{$slug}/" ),
				esc_url( "https://plugins.trac.wordpress.org/log/{$slug}/" ),
				esc_url( "https://plugins.trac.wordpress.org/log/{$slug}/?limit=100&mode=stop_on_copy&format=rss" )
			) . '</p>';
		}
		$output .= '</div>';
		return $output;
	}
}
