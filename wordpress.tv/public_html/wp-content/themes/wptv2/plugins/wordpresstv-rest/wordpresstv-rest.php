<?php

/**
 * WordPress.tv REST APi
 *
 */
class WordPressTV_REST_API {

	/**
	 * Constructor fired during init.
	 *
	 * @global WP $wp
	 */
	function __construct() {
		global $wp;

		add_action( 'template_redirect', array( $this, 'template_redirect' ), 2 );
		add_rewrite_rule( 'api/(.*)', 'index.php?wptvapi=$matches[1]', 'top' );
		// @see rewrite.php in plugins to flush rules

		// ?unisubs and ?guid query variables.
		$wp->add_query_var( 'wptvapi' );
	}

	/**
	 * Returns a 404 status header and exits.
	 */
	function fourohfour() {
		status_header( 404 );
		exit( 'Invalid request.' );
	}

	/**
	 * Handle API requests.
	 *
	 * Supported Endpoints:
	 * - /api/videos.json
	 *   Accepts all WP_Query parameters, such as 'event', 'per_page', and 'paged'.
	 * - /api/events.json
	 * - /api/speakers.json
	 * - /api/languages.json
	 * - /api/tags.json
	 * - /api/categories.json
	 *   All above endpoints accept 'paged'.
	 */
	function template_redirect() {
		global $wp_query, $post, $wptv;

		if ( ! get_query_var( 'wptvapi' ) ) {
			return;
		}

		$matches = array();
		if ( ! preg_match( '/^(.+)\.(json|array)$/i', get_query_var( 'wptvapi' ), $matches ) ) {
			$this->error( 'Invalid request.' );
		}

		$method   = $matches[1];
		$format   = $matches[2];
		$response = array();

		switch ( $method ) {
			case 'videos':
				if ( isset( $_REQUEST['posts_per_page'] ) ) {
					query_posts( array_merge( $wp_query->query, array( 'posts_per_page' => intval( $_REQUEST['posts_per_page'] ) ) ) );
				}

				$response['query'] = array(
					'results' => (int) $wp_query->found_posts,
					'pages'   => (int) $wp_query->max_num_pages,
				);

				$response['videos'] = array();
				while ( have_posts() ) {
					the_post();

					// Super lame hack to get a thumbnail from VideoPress :)
					ob_start();
					$wptv->the_video_image( 50, null, false, false );
					$thumbnail = esc_url_raw( trim( ob_get_contents() ) );
					ob_end_clean();

					$video = array(
						'title'       => $post->post_title,
						'permalink'   => get_permalink( get_the_ID() ),
						'thumbnail'   => $thumbnail,
						'date'        => $post->post_date_gmt,
						'description' => get_the_excerpt(),
						'slides'      => get_post_meta( $post->ID, '_wptv_slides_url', true ),
						'speakers'    => array(),
						'event'       => array(),
						'language'    => array(),
						'tags'        => array(),
						'category'    => array(),
						'year'        => array(),
						'location'    => array(),
						'producer'    => array(),
						'video'       => array(
							'mp4'      => array(),
							'ogg'      => array(),
							'original' => false,
						),
						'subtitles'   => array(),
					);

					foreach ( [ 'speakers', 'event', 'language', 'tags', 'category' ] as $tax ) {
						$terms = get_the_terms( get_the_ID(), $tax );
						if ( ! $terms || is_wp_error( $terms ) ) {
							continue;
						}
						foreach ( $terms as $t ) {
							// Special Cases
							if ( 'category' == $tax && $t->parent == 91093 /* Year */ ) {
								$video['year'] = array(
									'slug' => $t->slug,
									'name' => $t->name,
									'link' => get_term_link( $t )
								);
								continue;
							} elseif ( 'category' == $tax && $t->parent == 6418 /* Location */ ) {
								$video['location'] = array(
									'slug' => $t->slug,
									'name' => $t->name,
									'link' => get_term_link( $t )
								);
								continue;
							}

							$video[ $tax ][] = array(
								'slug' => $t->slug,
								'name' => $t->name,
								'link' => get_term_link( $t )
							);
						}
					}

					if ( $producer = get_the_terms( get_the_ID(), 'producer' ) ) {
						$video['producer']['name'] = $producer[0]->name;
					}
					if ( $producer_username = get_the_terms( get_the_ID(), 'producer-username' ) ) {
						$video['producer']['username'] = $producer_username[0]->name;
						$video['producer']['link'] = 'https://profiles.wordpress.org/' . urlencode( $producer_username[0]->name ) . '/';
					}

					$attachment_url = $wptv->get_video_attachment_url( $post );
					if ( $attachment_url ) {
						$video['video']['original'] = $attachment_url;
					}

					if ( function_exists( 'find_all_videopress_shortcodes' ) ) {
						$post_videos = array_keys( find_all_videopress_shortcodes( $post->post_content ) );
						if ( $post_videos ) {
							$post_video = video_get_info_by_guid( $post_videos[0] );
							$api_data   = video_get_single_response( $post_video );

							// Original uploaded file, may vary in format.
							if ( empty( $video['video']['original'] ) ) {
								$video['video']['original'] = $api_data['original'];
							}

							// Ogg - No longer generated as of May 2021
							if ( $link = video_highest_resolution_ogg( $post_video ) ) {
								$video['video']['ogg']['low'] = $link;
							}

							// MP4 - Audio no longer available in all formats
							$mp4_formats = array( 'low' => 'fmt_std', 'med' => 'fmt_dvd', 'high' => 'fmt_hd' );
							foreach ( $mp4_formats as $mp4_field => $mp4_format ) {
								// Check if HLS transcoded, no audio, no need to link to it.
								if ( ! empty( $api_data['files'][ str_replace( 'fmt_', '', $mp4_format ) ]['hls'] ) ) {
									continue;
								}

								$video['video']['mp4'][ $mp4_field ] = video_url_by_format( $post_video, $mp4_format );
							}
						}

						// Expose the subtitles
						$video['subtitles'] = (array) $api_data['subtitles'];
					}

					$response['videos'][] = $video;
				}

				break;
			case 'events':
			case 'speakers':
			case 'languages':
			case 'tags':
			case 'categories':
				$taxonomies = array(
					'events'     => 'event',
					'speakers'   => 'speakers',
					'languages'  => 'language',
					'tags'       => 'post_tag',
					'categories' => 'category',
				);

				$taxonomy = $taxonomies[ $method ];
				$taxonomy_obj = get_taxonomy( $taxonomy );
				$total_count = wp_count_terms( $taxonomy, array( 'hide_empty' => false ) );

				$page = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
				$per_page = 200;

				$terms = get_terms( array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => $per_page,
					'offset'     => $per_page * ($page - 1),
					'orderby'    => 'id',
					'order'      => 'DESC',
				) );

				$response['query'] = array(
					'results' => (int) $total_count,
					'page'    => $page,
					'pages'   => ceil( $total_count / $per_page ),
				);

				$response[ $method ] = array();
				foreach ( $terms as $t ) {
					$item = array(
						'id'   => $t->term_id,
						'name' => $t->name,
						'link' => get_term_link( $t ),
						'api'  => add_query_arg( $taxonomy_obj->query_var, $t->slug, home_url( '/api/videos.json') ),
						'videos' => $t->count,
					);

					if ( 'event' == $t->taxonomy ) {
						$item['youtube_playlist_id'] = get_option( "term_meta_{$t->term_id}_youtube_playlist_id", '' );
						$item['hashtag'] = get_option( "term_meta_{$t->term_id}_hashtag", '' );
					}

					$response[ $method ][] = $item;
				}

				break;
			default:
				$this->error( 'Unknown method.' );
				break;
		}

		if ( ! empty( $response ) ) {
			switch ( $format ) {
				case 'json':
					header( 'Content-type: application/json' );
					echo wp_json_encode( $response );
					break;
				case 'array':
					if ( defined( 'WPCOM_SANDBOXED' ) && WPCOM_SANDBOXED ) {
						header( 'Content-Type: text/plain' );
						print_r( $response );
					}
					break;
			}
			die();
		}

		$this->error( 'Empty response.' );
		die();
	}

	function error( $message, $http_code = 404 ) {
		status_header( $http_code );
		exit( $message );
	}
}

// Initialize the object.
add_action( 'init', 'wptv_rest_api_init', 5 );
function wptv_rest_api_init() {
	$wptv_rest_api = new WordPressTV_REST_API();
}
