<?php /*

**************************************************************************

Plugin Name:  WordPress.tv oEmbed Provider
Plugin URI:   http://wordpress.tv/oembed/
Description:  Creates an oEmbed provider for WordPress.tv.
Author:       Viper007Bond

**************************************************************************/

class WordCampTV_oEmbed {

	/**
	 * Class construct, duh.
	 *
	 * @global WP $wp
	 */
	function __construct() {
		global $wp;

		add_action( 'template_redirect', array( $this, 'oembed_provider' ), 2 );
		add_action( 'wp_head', array( $this, 'maybe_add_discovery_tags' ) );

		// Set up WordPress to accept /oembed/
		add_rewrite_rule( 'oembed/?(.*)', 'index.php?$matches[1]&oembed=1', 'top' );
		// @see rewrite.php in plugins to flush rules

		// Tell WordPress to not ignore the "oembed" query variable
		$wp->add_query_var( 'oembed' );
	}


	// Invalid (404)
	function fourohfour() {
		status_header( 404 );
		exit( 'Missing or invalid URL parameter.' );
	}


	// oEmbed output
	function oembed_provider() {
		if ( ! get_query_var( 'oembed' ) ) {
			return false;
		}

		if ( empty( $_GET['url'] ) ) {
			$this->fourohfour();
		}

		if ( is_ssl() ) {
			$_lookup_url = str_replace( 'http:', 'https:', $_GET['url'] );
		} else {
			$_lookup_url = str_replace( 'https:', 'http:', $_GET['url'] );
		}

		// Do a quick check to see if the URL starts with the blog's URL
		if ( get_bloginfo( 'url' ) != substr( $_lookup_url, 0, strlen( get_bloginfo( 'url' ) ) ) ) {
			$this->fourohfour();
		}

		// Attempt to turn the URL into a post object
		$post_ID = url_to_postid( $_lookup_url );
		$post    = get_post( $post_ID );
		if ( empty( $post_ID ) || ! $post ) {
			$this->fourohfour();
		}

		$defaults = array(
			'maxwidth'  => '400',
			'maxheight' => '300',
		);

		if ( ! empty( $_GET['maxwidth'] ) ) {
			$maxwidth = (int) $_GET['maxwidth'];
		}
		if ( empty( $maxwidth ) ) {
			$maxwidth = $defaults['maxwidth'];
		}

		if ( ! empty( $_GET['maxheight'] ) ) {
			$maxheight = (int) $_GET['maxheight'];
		}
		if ( empty( $maxheight ) ) {
			$maxheight = $defaults['maxheight'];
		}

		$data = array(
			'type'    => 'video',
			'version' => '1.0',
		);

		// Alright, let's see what kind of video is used
		// VideoPress
		if ( false !== stristr( $post->post_content, '[wpvideo' ) ) {
			preg_match( '#\[wpvideo ([a-zA-Z0-9]+)#i', $post->post_content, $guid );
			$guid = $guid[1];
			$info = function_exists( 'video_get_info_by_guid' ) ? video_get_info_by_guid( $guid ) : new StdClass;
			if ( empty( $guid ) || ! $info ) {
				status_header( 500 );
				exit( 'An error has occurred on our end. Please contact WordPress.com support.' );
			}

			list( $width, $height ) = wp_expand_dimensions( $info->width, $info->height, $maxwidth, $maxheight );

			$data['title']  = $info->title;
			$data['width']  = $width;
			$data['height'] = $height;
			$data['html']   = videopress_2015_player_get_html( array(
				'guid'   => $info->guid,
				'width'  => $width,
				'height' => $height,
			) );
		} // Other video types aren't supported quite yet
		else {
			status_header( 501 );
			exit( 'Support has not been added for this non-VideoPress type video yet.' );
		}

		// Figure out the format
		$format = 'json';
		if ( ! empty( $_GET['format'] ) ) {
			$format = $_GET['format'];
		}

		// Output the response
		switch ( $format ) {
			case 'json':
				header( 'Content-Type: application/json' );
				echo json_encode( $data );
				exit();
			case 'xml':
				header( 'Content-Type: text/xml' );
				echo '<' . '?xml version="1.0" encoding="utf-8" standalone="yes"?>' . "\n";
				echo "<oembed>\n";
				foreach ( $data as $tag => $value ) {
					echo "	<{$tag}>" . htmlspecialchars( $value ) . "</{$tag}>\n";
				}
				echo '</oembed>';
				exit();
			default;
				header( 'HTTP/1.0 501 Not Implemented' );
				exit();
		}
	}


	// If it's a single post that uses VideoPress, add discovery tags
	function maybe_add_discovery_tags() {
		if ( is_single() && stristr( get_post()->post_content, '[wpvideo' ) ) {
			printf( '<link rel="alternate" type="application/json+oembed" href="%1$s" title="%2$s" />' . "\n",
				esc_url( add_query_arg( array( 'url' => urlencode( get_permalink() ), 'format' => 'json' ), home_url( '/oembed/' ) ) ),
				the_title_attribute( array( 'echo' => false ) )
			);
			printf( '<link rel="alternate" type="text/xml+oembed" href="%1$s" title="%2$s" />' . "\n",
				esc_url( add_query_arg( array( 'url' => urlencode( get_permalink() ), 'format' => 'xml' ), home_url( '/oembed/' ) ) ),
				the_title_attribute( array( 'echo' => false ) )
			);
		}
	}
}

add_action( 'init', 'WordCampTV_oEmbed', 5 );
function WordCampTV_oEmbed() {
	global $WordCampTV_oEmbed;
	$WordCampTV_oEmbed = new WordCampTV_oEmbed();
}
