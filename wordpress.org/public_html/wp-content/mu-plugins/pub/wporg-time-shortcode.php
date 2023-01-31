<?php
/**
 * Plugin Name: Time shortcode for P2 and o2 blogs
 * Description: Attempts to parse a time string like <code>[time]any-valid-time-string-here[/time]</code> and creates a format that shows it in the viewers local time zone.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 *
 * @package WordPressdotorg\TimeShortcode
 */

namespace WordPressdotorg\TimeShortcode;

/**
 * Registers time shortcode.
 */
function init() {
	if ( ! class_exists( '\P2' ) && ! class_exists( '\o2' ) ) {
		return;
	}

	add_shortcode( 'time', __NAMESPACE__ . '\time_shortcode' );
	add_filter( 'comment_text', __NAMESPACE__ . '\time_shortcode_in_comments', 1 );
}
add_action( 'init', __NAMESPACE__ . '\init' );

/**
 * Builds the time shortcode output.
 *
 * This implements the functionality of the Gallery Shortcode for displaying
 * WordPress images on a post.
 *
 * @since 2.5.0
 *
 * @staticvar int $instance
 *
 * @param array  $attr    Attributes of the gallery shortcode.
 * @param string $content Shortcode content.
 * @return string HTML content to display time.
 */
function time_shortcode( $attr, $content = '' ) {
	// Replace non-breaking spaces with a regular white space.
	$gmtcontent = preg_replace( '/\xC2\xA0|&nbsp;/', ' ', $content );

	// PHP understands "GMT" better than "UTC" for timezones.
	$gmtcontent = str_replace( 'UTC', 'GMT', $gmtcontent );

	// Remove the word "at" from the string, if present. Allows strings like "Monday, April 6 at 19:00 UTC" to work.
	$gmtcontent = str_replace( ' at ', ' ', $gmtcontent );

	// Try to parse the time, relative to the post time. Or current time, if an attr is set.
	$timestamp = ! isset( $attr[0] ) ? get_the_date( 'U' ) : time();
	$time      = strtotime( $gmtcontent, $timestamp );

	// If that didn't work, give up.
	if ( false === $time || -1 === $time ) {
		return $content;
	}

	// Build the link and abbr microformat.
	$out = '<a href="https://www.timeanddate.com/worldclock/fixedtime.html?iso=' . gmdate( 'Ymd\THi', $time ) . '"><abbr class="date" title="' . gmdate( 'c', $time ) . '">' . $content . '</abbr></a>';

	// Add the time converter JS code.
	if ( ! has_action( 'wp_footer', __NAMESPACE__ . '\time_converter_script' ) ) {
		add_action( 'wp_footer', __NAMESPACE__ . '\time_converter_script', 999 );
	}

	// Return the new link.
	return $out;
}

/**
 * Prints script to convert time in the viewers local time zone.
 */
function time_converter_script() {
	?>
	<script type="text/javascript">
		( function( $ ) {
			function convertTime() {
				var parseDate, formatTime, formatDate, toLocaleTimeStringSupportsLocales;

				parseDate = function( text ) {
					var m = /^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})\+00:00$/.exec( text );

					return new Date(
						// Date.UTC(year, monthIndex (0..11), day, hour, minute, second)
						Date.UTC( + m[1], + m[2] - 1, + m[3], + m[4], + m[5], + m[6] )
					);
				};

				formatTime = function( d ) {
					return d.toLocaleTimeString( navigator.language, {
						weekday     : 'long',
						month       : 'long',
						day         : 'numeric',
						year        : 'numeric',
						hour        : '2-digit',
						minute      : '2-digit',
						timeZoneName: 'short'
					} );
				};

				formatDate = function( d ) {
					return d.toLocaleDateString( navigator.language, {
						weekday: 'long',
						month  : 'long',
						day    : 'numeric',
						year   : 'numeric'
					} );
				};

				// Not all browsers, particularly Safari, support arguments to .toLocaleTimeString().
				toLocaleTimeStringSupportsLocales = (
					function() {
						try {
							new Date().toLocaleTimeString( 'i' );
						} catch ( e ) {
							return e.name === 'RangeError';
						}
						return false;
					}
				)();

				$( 'abbr.date' ).each( function() {
					var $el = $( this ), d, newText = '';

					d = parseDate( $el.attr( 'title' ) );
					if ( d ) {
						if ( ! toLocaleTimeStringSupportsLocales ) {
							newText += formatDate( d );
							newText += ' ';
						}
						newText += formatTime( d );
						$el.text( newText );
					}
				} );
			}
			convertTime();
			$( document.body ).on( 'post-load ready.o2', convertTime );
		})( jQuery );
	</script>
<?php
}

/**
 * Adds support for the time shortcode in comments.
 *
 * @param string $comment Text of the current comment.
 * @return string Text of the comment.
 */
function time_shortcode_in_comments( $comment ) {
	global $shortcode_tags;

	// Save the shortcodes.
	$saved_tags = $shortcode_tags;

	// Only process the time shortcode.
	$shortcode_tags = array(
		'time' =>__NAMESPACE__ . '\time_shortcode'
	);

	// Do the time shortcode on the comment.
	$comment = do_shortcode( $comment );

	// Restore the normal shortcodes.
	$shortcode_tags = $saved_tags;

	return $comment;
}
