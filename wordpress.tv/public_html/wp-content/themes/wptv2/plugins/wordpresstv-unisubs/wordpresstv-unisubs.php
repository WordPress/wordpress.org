<?php

/**
 * Universal Subtitles for WordPress.tv
 *
 * This class is included from the WordPress.tv VIP theme's function.php file.
 * Provides a /unisubs/ end-point for Universal Subtitles to access stuff via HTTP.
 *
 * @global WP $wp
 */
class WordCampTV_Unisubs {

	/**
	 * Constructor fired during init.
	 */
	function __construct() {
		global $wp;

		add_action( 'template_redirect', array( &$this, 'template_redirect' ), 2 );
		add_rewrite_rule( 'unisubs/?(.*)', 'index.php?$matches[1]&unisubs=1', 'top' );
		// @see rewrite.php in plugins to flush rules

		// ?unisubs and ?guid query variables.
		$wp->add_query_var( 'unisubs' );
		$wp->add_query_var( 'guid' );

		// Let's change the videopress.js file. @todo: Uncomment this before going live with Unisubs. OR dequeue it.
		// add_filter( 'script_loader_src', array( &$this, 'script_loader_src' ), 10, 2 );
	}

	/**
	 * Returns a 404 status header and exits.
	 */
	function fourohfour() {
		status_header( 404 );
		exit( 'Invalid request.' );
	}

	/**
	 * Returns a $post object by searching for the wpvideo guid or false.
	 *
	 * @param int $guid ID.
	 *
	 * @return bool
	 */
	function get_post_by_guid( $guid ) {
		$posts = get_posts( array( 's' => "[wpvideo $guid]", 'post_status' => 'publish' ) );
		if ( $posts ) {
			return $posts[0];
		}

		return false;
	}

	/**
	 * Unisubs API output and ?guid= redirect hook, fired during
	 * template_redirect, output in ?format=(json|xml)
	 */
	function template_redirect() {

		// If a ?guid has been specified, but not a /unisubs/ endpoint, redirect
		// to the post permalink. Allows requests like http://wordpress.tv/?guid=nWoZmCPz
		if ( get_query_var( 'guid' ) && ! get_query_var( 'unisubs' ) ) {
			$post = $this->get_post_by_guid( get_query_var( 'guid' ) );
			if ( $post ) {
				wp_redirect( get_permalink( $post ) );
				exit();
			}
		}

		// Is this a /unisubs/ endpoint?
		if ( ! get_query_var( 'unisubs' ) ) {
			return false;
		}

		// Either ?guid or ?url must be present for /unisubs/
		if ( ! isset( $_GET['guid'] ) && ! isset( $_GET['url'] ) ) {
			$this->fourohfour();
		}

		$post    = false;
		$post_id = 0;

		if ( isset( $_GET['guid'] ) ) {
			$post = $this->get_post_by_guid( $_GET['guid'] );
			if ( ! $post ) {
				$this->fourohfour();
			}
		}

		if ( isset( $_GET['url'] ) ) {
			// Do a quick check to see if the URL starts with the blog's URL
			if ( home_url() != substr( $_GET['url'], 0, strlen( home_url() ) ) ) {
				$this->fourohfour();
			}

			$post_id = url_to_postid( $_GET['url'] );
			if ( empty( $post_id ) || ! $post = get_post( $post_id ) ) {
				$this->fourohfour();
			}
		}

		// Works with VideoPress videos only.
		if ( false == stristr( $post->post_content, '[wpvideo' ) ) {
			status_header( 501 );
			exit( 'Support has not been added for this non-VideoPress type video yet.' );
		}

		$data = array(
			'type'      => 'video',
			'version'   => '1.0',
			'permalink' => esc_url_raw( get_permalink( $post_id ) ),
		);

		// Search for VideoPress.
		preg_match( '#\[wpvideo ([a-zA-Z0-9]+)#i', $post->post_content, $guid );
		$guid = $guid[1];
		$info = function_exists( 'video_get_info_by_guid' ) ? video_get_info_by_guid( $guid ) : new StdClass;
		if ( empty( $guid ) || ! $info ) {
			status_header( 500 );
			exit( 'An error has occurred on our end. Please contact WordPress.com support.' );
		}

		list( $width, $height ) = array( $info->width, $info->height );

		// Let's try and get the best thumbnail available.
		$thumb = false;
		foreach ( array( 'hd_files', 'dvd_files', 'std_files' ) as $key ) {
			if ( isset( $info->{$key} ) && ! $thumb ) {
				$files = maybe_unserialize( $info->{$key} );
				$thumb = isset( $files['original_img'] ) ? $files['original_img'] : false;
			}
		}

		$data['guid']        = $info->guid;
		$data['post_id']     = $post->ID;
		$data['title']       = ! empty( $post->post_title ) ? $post->post_title : 'Untitled';
		$data['description'] = ! empty( $info->description ) ? $info->description : 'No description given.';
		$data['thumbnail']   = esc_url_raw( sprintf( 'http://videos.videopress.com/%s/%s', $info->guid, $thumb ) );

		$data['width']    = absint( $width );
		$data['height']   = absint( $height );
		$data['duration'] = absint( $info->duration );
		$data['swf']      = esc_url_raw( sprintf( 'http://v.wordpress.com/wp-content/plugins/video/assets/player.wptv.swf?guid=%s', $info->guid ) );

		// Redirect to the SWF file if we need to.
		if ( isset( $_GET['redirect_to_swf'] ) ) {
			wp_redirect( $data['swf'] );
			exit();
		}

		$format = 'json';
		if ( ! empty( $_GET['format'] ) ) {
			$format = strtolower( $_GET['format'] );
		}

		// Output the response based on $format.
		switch ( $format ) {
			case 'json':
				header( 'Content-Type: application/json' );
				echo json_encode( $data );
				exit();
			case 'xml':
				header( 'Content-Type: text/xml' );
				echo '<' . '?xml version="1.0" encoding="utf-8" standalone="yes"?>' . "\n";
				echo "<item>\n";
				foreach ( $data as $tag => $value ) {
					echo "	<{$tag}>" . htmlspecialchars( $value ) . "</{$tag}>\n";
				}
				echo '</item>';
				exit();
			case 'array': // useful for debugging
				if ( defined( 'WPCOM_SANDBOXED' ) && WPCOM_SANDBOXED ) {
					print_r( $data );
					exit();
				}
				break;
			default:
				header( 'HTTP/1.0 501 Not Implemented' );
				exit();
		}
	}

	/**
	 * Replaces the src= for VideoPress into a special videopress.wptv.js,
	 * filters script_loader_src. Note: not used yet.
	 *
	 * @param string $src
	 * @param string $handle
	 *
	 * @return string
	 */
	function script_loader_src( $src, $handle ) {
		if ( $handle == 'videopress' ) {
			$src = plugins_url( '/video/assets/js/videopress.wptv.js' );
		}

		return $src;
	}
}

// Initialize the object.
add_action( 'init', 'wptv_unisubs_init', 5 );
function wptv_unisubs_init() {
	global $wptv_unisubs;
	$wptv_unisubs = new WordCampTV_Unisubs();
}
