<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\API\Base;
use WP_REST_Server;

/**
 * An API Endpoint to expose a single Plugin data via api.wordpress.org/plugins/info/1.x
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin extends Base {

	/**
	 * Plugin constructor.
	 */
	function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/?', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'plugin_info' ),
			'args' => array(
				'plugin_slug' => array(
					'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
				)
			)
		) );
	}

	/**
	 * Endpoint to retrieve a full plugin representation.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return array A formatted array of all the data for the plugin.
	 */
	function plugin_info( $request ) {
		$plugin_slug = $request['plugin_slug'];

		global $post;
		$post = Plugin_Directory::get_plugin_post( $plugin_slug );

		if ( 'publish' != $post->post_status ) {
			// Copy what the REST API does if the param is incorrect
			return new \WP_Error(
				'rest_invalid_param',
				'Invalid parameter(s): plugin_slug',
				array(
					'status' => \WP_HTTP::BAD_REQUEST,
					'params' => array(
						'plugin_slug' => 'Invalid parameter.'
					)
				)
			);
		}

		$post_id = $post->ID;

		$result = array();
		$result['name'] = $post->post_title;
		$result['slug'] = $post->post_name;
		$result['version'] = get_post_meta( $post_id, 'version', true ) ?: '0.0';

		$author = get_user_by( 'id', $post->post_author );
		$result['author'] = $author->display_name;
		if ( $author->user_url ) {
			$result['author'] = sprintf( '<a href="%s">%s</a>', $author->user_url, $result['author'] );
		}

		$result['author_profile'] = $this->get_user_profile_link( $post->post_author );
		$result['contributors'] = array();

		if ( $contributors = get_the_terms( $post->ID, 'plugin_contributors' ) ) {
			$contributors = wp_list_pluck( $contributors, 'slug' );
		} else {
			$contributors = array();
			if ( $author = get_user_by( 'id', $post->post_author ) ) {
				$contributors[] = $author->user_nicename;
			}
		}
		foreach ( $contributors as $contributor ) {
			$user = get_user_by( 'slug', $contributor );
			if ( ! $user ) {
				continue;
			}

			$result['contributors'][ $user->user_nicename ] = array(
				'profile' => $this->get_user_profile_link( $user ),
				'avatar' => get_avatar_url( $user, array( 'default' => 'monsterid', 'rating' => 'g' ) ),
				'display_name' => $user->display_name
			);
		}

		$result['requires'] = get_post_meta( $post_id, 'requires', true ) ?: false;
		$result['tested'] = get_post_meta( $post_id, 'tested', true ) ?: false;
		$result['compatibility'] = array();
		$result['rating'] = ( get_post_meta( $post_id, 'rating', true ) ?: 0 ) * 20; // Stored as 0.0 ~ 5.0, API outputs as 0..100
		$result['ratings'] = array_map( 'intval', (array) get_post_meta( $post_id, 'ratings', true ) );
		$result['num_ratings'] = array_sum( $result['ratings'] );
		$result['support_threads'] = intval( get_post_meta( $post_id, 'support_threads', true ) );
		$result['support_threads_resolved'] = intval( get_post_meta( $post_id, 'support_threads_resolved', true ) );
		$result['active_installs'] = intval( get_post_meta( $post_id, 'active_installs', true ) );
		$result['downloaded'] = intval( get_post_meta( $post_id, 'downloads', true ) );
		$result['last_updated'] = gmdate( 'Y-m-d g:ia \G\M\T', strtotime( $post->post_modified_gmt ) );
		$result['added'] = gmdate( 'Y-m-d', strtotime( $post->post_date_gmt ) );
		$result['homepage'] = get_post_meta( $post_id, 'header_plugin_uri', true );
		$result['sections'] = array();

		$_pages = preg_split( "#<!--section=(.+?)-->#", $post->post_content, - 1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		for ( $i = 0; $i < count( $_pages ); $i += 2 ) {
			$result['sections'][ $_pages[ $i ] ] = apply_filters( 'the_content', $_pages[ $i + 1 ], $_pages[ $i ] );
		}
		$result['sections']['screenshots'] = ''; // placeholder to put screenshots prior to reviews at the end.
		$result['sections']['reviews'] = $this->get_plugin_reviews_markup( $post->post_name );

		if ( !empty( $result['sections']['faq'] ) ) {
			$result['sections']['faq'] = $this->get_simplified_faq_markup( $result['sections']['faq'] );
		} 
		
		$result['description'] = $result['sections']['description'];

		$result['short_description'] = $post->post_excerpt;
		$result['download_link'] = Template::download_link( $post );

		$result['screenshots'] = array();
		$descriptions = get_post_meta( $post->ID, 'screenshots', true ) ?: array();
		$screen_shots = get_post_meta( $post->ID, 'assets_screenshots', true ) ?: array();

		/*
		 * Find the image that corresponds with the text.
		 * The image numbers are stored within the 'resolution' key.
		 */
		foreach ( $screen_shots as $image ) {
			$result['screenshots'][ $image['resolution'] ] = array(
				'src'     => Template::get_asset_url( $post, $image ),
				'caption' => array_key_exists( $image['resolution'], $descriptions ) ? $descriptions[ $image['resolution'] ] : ''
			);
		}

		if ( $result['screenshots'] ) {
			$result['sections']['screenshots'] = $this->get_screenshot_markup( $result['screenshots'] );
		} else {
			unset( $result['sections']['screenshots'] );
		}

		$result['tags'] = array();
		if ( $terms = get_the_terms( $post->ID, 'plugin_category' ) ) {
			foreach ( $terms as $term ) {
				$result['tags'][ $term->slug ] = $term->name;
			}
		}

		$result['stable_tag'] = get_post_meta( $post_id, 'stable_tag', true ) ?: 'trunk';

		$result['versions'] = array();
		if ( $versions = get_post_meta( $post_id, 'tagged_versions', true ) ) {
			if ( 'trunk' != $result['stable_tag'] ) {
				array_push( $versions, 'trunk' );
			}
			foreach ( $versions as $version ) {
				$result['versions'][ $version ] = Template::download_link( $post, $version );
			}
		}

		$result['donate_link'] = get_post_meta( $post_id, 'donate_link', true ) ?: false;

		$result['banners'] = array();
		if ( $banners = Template::get_plugin_banner( $post ) ) {
			if ( isset( $banners['banner'] ) ) {
				$result['banners']['low'] = $banners['banner'];
			}
			if ( isset( $banners['banner_2x'] ) ) {
				$result['banners']['high'] = $banners['banner_2x'];
			}
		}

		$result['icons'] = array();
		if ( $icons = Template::get_plugin_icon( $post ) ) {
			if ( !empty( $icons['icon'] ) && empty( $icons['generated'] ) ) {
				$result['icons']['1x'] = $icons['icon'];
			} elseif ( !empty( $icons['icon'] ) && ! empty( $icons['generated'] ) ) {
				$result['icons']['default'] = $icons['icon'];
			}
			if ( !empty( $icons['icon_2x'] ) ) {
				$result['icons']['2x'] = $icons['icon_2x'];
			}
			if ( !empty( $icons['svg'] ) ) {
				$result['icons']['svg'] = $icons['svg'];
			}
		}

		// That's all folks!

		return $result;
	}

	/**
	 * Generate a link for a user to profiles.wordpress.org.
	 *
	 * @param int|\WP_User|string A WP_User instance, or ID/slug of a user.
	 * @return string The profiles.wordpress.org link.
	 */
	protected function get_user_profile_link( $user ) {
		$u = false;
		if ( $user instanceOf \WP_User ) {
			$u = $user;
		} else {
			if ( is_numeric( $user ) ) {
				$u = get_user_by( 'id', $user );
			}
			if ( ! $u ) {
				$u = get_user_by( 'slug', $user );
			}
		}

		return 'https://profiles.wordpress.org/' . $u->user_nicename;
	}

	/**
	 * Returns a HTML formatted representation of the latest 10 reviews for a plugin.
	 *
	 * This intentionally uses different markup than what the theme uses, as it's for display within the WordPress Administration area.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @return string HTML blob of data.
	 */
	protected function get_plugin_reviews_markup( $plugin_slug ) {
		$output = '';
		foreach ( Tools::get_plugin_reviews( $plugin_slug, 10 ) as $review ) {
			$output .= $this->get_plugin_reviews_markup_singular( $review );
		}
		return $output;
	}

	/**
	 * Generates a HTML blob for a single review.
	 *
	 * @param object $review The review data.
	 * @return string Blob of HTML representing the review.
	 */
	protected function get_plugin_reviews_markup_singular( $review ) {
		$reviewer = get_user_by( 'id', $review->post_author );
		ob_start();
?>
<div class="review">
	<div class="review-head">
		<div class="reviewer-info">
			<div class="review-title-section">
				<h4 class="review-title"><?php echo esc_html( $review->post_title ); ?></h4>
				<div class="star-rating"><?php
					/* Core has .star-rating .star colour styling, which is why we use a custom wrapper and template */
					echo Template::dashicons_stars( array(
						'rating' => $review->post_rating,
						'template' => '<span class="star %1$s"></span>',
					) );
				?></div>
			</div>
			<p class="reviewer">
				<?php
					$review_author_markup_profile = esc_url( 'https://profiles.wordpress.org/' . $reviewer->user_nicename );
					$review_author_markup  = '<a href="' . $review_author_markup_profile . '">';
					$review_author_markup .= get_avatar( $reviewer->ID, 16, 'monsterid' ) . '</a>';
					$review_author_markup .= '<a href="' . $review_author_markup_profile . '" class="reviewer-name">';
					$review_author_markup .= $reviewer->display_name;
					if ( $reviewer->display_name != $reviewer->user_login ) {
						$review_author_markup .= " <small>({$reviewer->user_login})</small>";
					}
					$review_author_markup .= '</a>';

					printf( __( 'By %1$s on %2$s', 'wporg-plugins' ),
						$review_author_markup,
						'<span class="review-date">' . gmdate( 'F j, Y', strtotime( $review->post_modified ) ) . '</span>'
					);
				?>
			</p>
		</div>
	</div>
	<div class="review-body"><?php echo $review->post_content; ?></div>
</div>
<?php
		return ob_get_clean();

	}

	/**
	 * Return a 'simplified' markup for the FAQ screen.
	 * WordPress only supports a whitelisted selection of tags, `<dl>` is not one of them.
	 *
	 * @see https://core.trac.wordpress.org/browser/tags/4.7/src/wp-admin/includes/plugin-install.php#L478
	 * @param string $markup The existing Markup.
	 * @return string Them markup with `<dt>` replaced with `<h4>` and `<dd>` with `<p>`.
	 */
	protected function get_simplified_faq_markup( $markup ) {
		$markup = str_replace(
			array( '<dl>', '</dl>', '<dt>', '</dt>', '<dd>', '</dd>' ),
			array( '',      '',     '<h4>', '</h4>', '<p>',  '</p>'  ),
			$markup
		);

		return $markup;
	}

	protected function get_screenshot_markup( $screenshots ) {
		$markup = '<ol>';

		foreach ( $screenshots as $shot ) {
			if ( $shot['caption'] ) {
				$markup .= sprintf(
					'<li><a href="%1$s"><img src="%1$s" alt="%2$s"></a><p>%3$s</p></li>',
					esc_attr( $shot['src'] ),
					esc_attr( $shot['caption'] ),
					$shot['caption']
				);
			} else {
				$markup .= sprintf(
					'<li><a href="%1$s"><img src="%1$s" alt=""></a></li>',
					esc_attr( $shot['src'] )
				);
			}
		}

		$markup .= '</ol>';
		return $markup;
	}

}

