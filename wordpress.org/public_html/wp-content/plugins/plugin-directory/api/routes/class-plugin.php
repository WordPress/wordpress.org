<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\API\Base;
use WP_REST_Server;

/**
 * An API Endpoint to expose a single Plugin data via api.wordpress.org/plugins/info/1.x
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin extends Base {

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
		$result['version'] = get_post_meta( $post_id, 'version', true );

		$author = get_user_by( 'id', $post->post_author );
		$result['author'] = $author->display_name;
		if ( $author->user_url ) {
			$result['author'] = sprintf( '<a href="%s">%s</a>', $author->user_url, $result['author'] );
		}

		$result['author_profile'] = $this->get_user_profile_link( $post->post_author );
		$result['contributors'] = array();

		$contributors = get_post_meta( $post_id, 'contributors', true ) ?: array( $post->user_login );
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

		$result['requires'] = get_post_meta( $post_id, 'requires', true );
		$result['tested'] = get_post_meta( $post_id, 'tested', true );
		$result['compatibility'] = array();
		$result['rating'] = get_post_meta( $post_id, 'rating', true ) * 20; // Stored as 0.0 ~ 5.0, API outputs as 0..100
		$result['ratings'] = array_map( 'intval', (array) get_post_meta( $post_id, 'ratings', true ) );
		$result['num_ratings'] = array_sum( $result['ratings'] );
		$result['active_installs'] = (int)get_post_meta( $post_id, 'active_installs', true );
		$result['downloaded'] = get_post_meta( $post_id, 'downloads', true );
		$result['last_updated'] = gmdate( 'Y-m-d', strtotime( $post->post_modified_gmt ) );
		$result['added'] = gmdate( 'Y-m-d', strtotime( $post->post_date_gmt ) );
		$result['homepage'] = get_post_meta( $post_id, 'header_plugin_uri', true );
		$result['sections'] = array();

		$_pages = preg_split( "#<!--section=(.+?)-->#", $post->post_content, - 1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		for ( $i = 0; $i < count( $_pages ); $i += 2 ) {
			$result['sections'][ $_pages[ $i ] ] = apply_filters( 'the_content', $_pages[ $i + 1 ] );
		}
		$result['sections']['reviews'] = $this->get_plugin_reviews_markup( $post->post_name );
		$result['description'] = $result['sections']['description'];

		$result['short_description'] = $post->post_excerpt;
		$result['download_link'] = Template::download_link( $post );

		$result['tags'] = array();
		if ( $terms = get_the_terms( $post->ID, 'plugin_category' ) ) {
			foreach ( $terms as $term ) {
				$result['tags'][ $term->slug ] = $term->name;
			}
		}

		$result['stable_tag'] = get_post_meta( $post_id, 'stable_tag', true );

		$result['versions'] = array();
		if ( $versions = get_post_meta( $post_id, 'tagged_versions', true ) ) {
			if ( 'trunk' != $result['stable_tag'] ) {
				array_push( $versions, 'trunk' );
			}
			foreach ( $versions as $version ) {	
				$result['versions'][ $version ] = Template::download_link( $post, $version );
			}
		}

		$result['donate_link'] = get_post_meta( $post_id, 'donate_link', true );

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
	 * @param string $plugin_slug The plugin slug.
	 * @return string HTML blob of data.
	 */
	protected function get_plugin_reviews_markup( $plugin_slug ) {
		$output = '';
		foreach ( $this->get_plugin_reviews_data( $plugin_slug ) as $review ) {
			$output .= $this->get_plugin_reviews_markup_singular( $review );
		}
		return $output;
	}

	/**
	 * Generates a HTML blob for a single review.
	 *
	 * @param object $review Single row of data from `self::get_plugin_reviews_data()`
	 * @return string Blob of HTML representing the review.
	 */
	protected function get_plugin_reviews_markup_singular( $review ) {
		$reviewer = get_user_by( 'id', $review->topic_poster );
		ob_start();

		// Copied from bb-theme/wporg/_reviews.php, with quite a few things stripped out.
?>
<div class="review">
	<div class="review-head">
		<div class="reviewer-info">
			<div class="review-title-section">
				<h4 class="review-title"><?php echo $review->topic_title; ?></h4>
				<div class="star-rating"><?php
					/* Core has .star-rating .star colour styling, which is why we use a custom wrapper and template */
					echo Template::dashicons_stars( array(
						'rating' => wporg_get_rating( $review->topic_id ),
						'template' => '<span class="star %1$s"></span>',
					) );
				?></div>
			</div>
			<p class="reviewer">
				By <a href="https://profiles.wordpress.org/<?php echo $reviewer->user_nicename; ?>"><?php echo get_avatar( $review->topic_poster, 16, 'monsterid' ); ?></a>
				<a href="https://profiles.wordpress.org/<?php echo $reviewer->user_nicename; ?>" class="reviewer-name"><?php
					echo $reviewer->display_name;
	
					if ( $reviewer->display_name != $reviewer->user_login ) {
						echo " <small>({$reviewer->user_login})</small>";
					}
				?></a><?php
					/* // Display author badge next to the person's name if they're reviewing their own thing
					if ( class_exists( '\WPORG_Extend_Author_Badge' ) ) {
						echo \WPORG_Extend_Author_Badge::get_instance()->show_author_badge( '', $post->post_id );
					} */
				?>,
				<span class="review-date"><?php echo gmdate( 'F j, Y', strtotime( $review->topic_start_time ) ); ?></span>
				<?php if ( $review->wp_version ) : ?>
					<span class="review-wp-version">for WordPress <?php echo $review->wp_version; ?></span>
				<?php endif; ?>
			</p>
		</div>
	</div>
	<div class="review-body"><?php echo $review->post_text; ?></div>
</div>
<?php
		return ob_get_clean();

	}

	/**
	 * Fetch the latest 10 reviews for a given plugin from the database.
	 *
	 * This uses raw SQL to query the bbPress tables to fetch reviews.
	 *
	 * @param string $plugin_slug The slug of the plugin.
	 * @return array An array of review details.
	 */
	protected function get_plugin_reviews_data( $plugin_slug ) {
		global $wpdb;
		if ( ! defined( 'WPORGPATH' ) || ! defined( 'CUSTOM_USER_TABLE' ) ) {
			// Reviews are stored in the main supoport forum, which isn't open source yet.
			return array();
		}

		if ( $reviews = wp_cache_get( $plugin_slug, 'reviews' ) ) {
			return $reviews;
		}

		// The forums are the source for users, and also where reviews live.
		$table_prefix = str_replace( 'users', '', CUSTOM_USER_TABLE );
		$forum_id = 18; // The Review Forums ID

		$reviews = $wpdb->get_results( $wpdb->prepare( "
			SELECT
				t.topic_id, t.topic_title, t.topic_poster, t.topic_start_time,
				p.post_text,
				tm_wp.meta_value as wp_version
			FROM {$table_prefix}topics AS t
			JOIN {$table_prefix}meta AS tm ON ( tm.object_type = 'bb_topic' AND t.topic_id = tm.object_id AND tm.meta_key = 'is_plugin' )
			JOIN {$table_prefix}posts as p ON ( t.topic_id = p.topic_id AND post_status = 0 AND post_position = 1 )
			LEFT JOIN {$table_prefix}meta AS tm_wp ON ( tm_wp.object_type = 'bb_topic' AND t.topic_id = tm_wp.object_id AND tm_wp.meta_key = 'wp_version' )
			WHERE t.forum_id = %d AND t.topic_status = 0 AND t.topic_sticky = 0 AND tm.meta_value = %s
			ORDER BY t.topic_start_time DESC
			LIMIT 10",
			$forum_id,
			$plugin_slug
		) );

		wp_cache_set( $plugin_slug, $reviews, 'reviews' );
		return $reviews;
	}
}

