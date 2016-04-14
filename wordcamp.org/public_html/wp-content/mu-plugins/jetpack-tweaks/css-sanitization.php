<?php

namespace WordCamp\Jetpack_Tweaks;
defined( 'WPINC' ) or die();

add_action( 'csstidy_optimize_postparse', __NAMESPACE__ . '\sanitize_csstidy_parsed_rules' );
add_action( 'admin_notices',              __NAMESPACE__ . '\notify_import_rules_stripped'  );
add_action( 'csstidy_optimize_subvalue',  __NAMESPACE__ . '\sanitize_csstidy_subvalues'    );

/**
 * Sanitize parsed Custom CSS rules
 *
 * @import rules are stripped because they can introduce security vulnerabilities by embedding external
 * stylesheets that haven't been sanitized, and they also present a maintenance problem because they rely on
 * external resources which could go offline at any point.
 *
 * @charset rules are stripped because manipulating the charset can allow an attacker to introduce XSS
 * vulnerabilities by tricking the browser into interpreting the CSS as HTML.
 *
 * @param \safecss $safecss
 */
function sanitize_csstidy_parsed_rules( $safecss ) {
	if ( ! empty( $safecss->parser->import ) ) {
		update_option( 'custom_css_import_stripped', true );
	}

	$safecss->parser->import  = array();
	$safecss->parser->charset = array();
}

/**
 * Notify the user that @import rules were stripped from their CSS
 */
function notify_import_rules_stripped() {
	global $current_screen;
	$relevant_screens = array( 'appearance_page_editcss', 'appearance_page_remote-css' );

	if ( ! is_a( $current_screen, 'WP_Screen' ) || ! in_array( $current_screen->id, $relevant_screens, true ) ) {
		return;
	}

	if ( ! get_option( 'custom_css_import_stripped' ) ) {
		return;
	}

	delete_option( 'custom_css_import_stripped' );

	?>

	<div class="notice notice-warning">
		<p>
			<?php printf(
				__( 'WARNING: <code>@import</code> rules were stripped for security reasons.
				Please use <a href="%s">the Fonts tool</a> to add web fonts, and merge other stylesheets directly into your custom CSS.',
				'wordcamporg' ),
              admin_url( 'themes.php?page=wc-fonts-options' )
            ); ?>
		</p>
	</div>

	<?php
}

/**
 * Sanitize Custom CSS subvalues
 *
 * @param \safecss $safecss
 */
function sanitize_csstidy_subvalues( $safecss ) {
	$safecss->sub_value = trim( $safecss->sub_value );

	// Send any urls through our filter
	if ( preg_match( '!^\s*(?P<url_expression>url\s*(?P<opening_paren>\(|\\0028)(?P<parenthetical_content>.*)(?P<closing_paren>\)|\\0029))(.*)$!Dis', $safecss->sub_value, $matches ) ) {
		$safecss->sub_value = sanitize_urls_in_css_properties( $matches['parenthetical_content'], $safecss->property );

		// Only replace the url([...]) portion of the sub_value so we don't
		// lose things like trailing commas or !important declarations.
		if ( $safecss->sub_value ) {
			$safecss->sub_value = str_replace( $matches['url_expression'], $safecss->sub_value, $matches[0] );
		}
	}

	// Strip any expressions
	if ( preg_match( '!^\\s*expression!Dis', $safecss->sub_value ) ) {
		$safecss->sub_value = '';
	}
}

/**
 * Sanitize URLs used in CSS properties
 *
 * @param string $url
 * @param string $property
 *
 * @return string
 */
function sanitize_urls_in_css_properties( $url, $property ) {
	$allowed_properties = array( 'background', 'background-image', 'border-image', 'content', 'cursor', 'list-style', 'list-style-image' );
	$allowed_protocols  = array( 'http', 'https' );
	// todo maybe add permenant warning note that `data` shouldn't be allowed, see #1616:comment:4

	// Clean up the string
	$url = trim( $url, "' \" \r \n" );

	// Check against whitelist for properties allowed to have URL values
	if ( ! in_array( trim( $property ), $allowed_properties, true ) ) {
		// trim() is because multiple properties with the same name are stored with
		// additional trailing whitespace so they don't overwrite each other in the hash.
		return '';
	}

	$url = wp_kses_bad_protocol_once( $url, $allowed_protocols );

	if ( empty( $url ) ) {
		return '';
	}

	return "url('" . str_replace( "'", "\\'", $url ) . "')";
}
