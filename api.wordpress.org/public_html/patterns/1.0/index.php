<?php

namespace WordPressdotorg\API\Patterns;

/*
 * @todo
 *
 * - get review of endpoint
 * - add translation
 * - add unit tests
 */

main( $_GET );


/**
 * Main entry point.
 *
 * @todo
 * - add object caching here, or in lower methods
 * - add docs to codex
 *
 * @param array $unsafe_request
 */
function main( $unsafe_request ) {
	setup_environment();

	$safe_request = validate_request( $unsafe_request );

	switch ( $safe_request['action'] ) {
		case 'query_patterns':
			$result = search_patterns( $safe_request['query'] );
			break;

		case 'pattern_information':
			$result = get_pattern( $safe_request['slug'] );
			break;

		default:
			$result = array( 'error' => 'Invalid action. See https://codex.wordpress.org/WordPress.org_API for documentation.' );
	}

	serve_response( $result );
}

/**
 * Bootstrap the necessary environmental conditional.
 */
function setup_environment() {
	$base_dir = dirname( dirname( __DIR__ ) );

	require $base_dir . '/init.php';
	require $base_dir . '/includes/wp-json-encode.php';
}

/**
 * Validate the request.
 *
 * @param array $unsafe_request
 *
 * @return array
 */
function validate_request( $unsafe_request ) {
	// Only accepts GET requests.
	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
		header( $_SERVER['SERVER_PROTOCOL'] . ' 405 Method not allowed' );
		header( 'Allow: GET' );
		header( 'Content-Type: text/plain' );

		die( 'This API only serves GET requests.' );
	}

	// Remove unrecognized parameters.
	$safe_request = array_intersect_key(
		$unsafe_request,
		array(
			'action' => '',
			'search' => '',
			'slug'   => '',
		)
	);

	array_walk( $safe_request, function( &$value ) {
		// @todo probably wanna do more here, maybe port `sanitize_text_field()`.
		$value = preg_replace( '/[^a-zA-Z_-]+/', '', $value );
	} );

	return $safe_request;
}

/**
 * Search for pattern that match the given query.
 *
 * @todo search directory database instead of just hardcoded patterns. use jetpack search with custom weightings.
 *
 * @param string $query
 *
 * @return array[]
 */
function search_patterns( $query ) {
	return get_core_patterns();
}

/**
 * Get all information about a single pattern.
 *
 * @todo search directory database instead of just hardcoded patterns.
 *
 * @param $pattern_slug
 *
 * @return array|string[]
 */
function get_pattern( $pattern_slug ) {
	$core_patterns = get_core_patterns();

	return $core_patterns[ $pattern_slug ] ?? array( 'error' => 'Not found' );
}

/**
 * Get Core's built-in patterns.
 *
 * @todo add these to database instead, or will always want them hardcoded?
 *
 * @return array[]
 */
function get_core_patterns() {
	return array(
		'heading-paragraph' => array(
			'title'         => __( 'Heading and paragraph', 'gutenberg' ),
			'content'       => "<!-- wp:group -->\n<div class=\"wp-block-group\"><div class=\"wp-block-group__inner-container\"><!-- wp:heading {\"fontSize\":\"large\"} -->\n<h2 class=\"has-large-font-size\"><span style=\"color:#ba0c49\" class=\"has-inline-color\"><strong>2</strong>.</span><br>" . __( 'Which treats of the first sally the ingenious Don Quixote made from home', 'gutenberg' ) . "</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>" . __( 'These preliminaries settled, he did not care to put off any longer the execution of his design, urged on to it by the thought of all the world was losing by his delay, seeing what wrongs he intended to right, grievances to redress, injustices to repair, abuses to remove, and duties to discharge.', 'gutenberg' ) . "</p>\n<!-- /wp:paragraph --></div></div>\n<!-- /wp:group -->",
			'viewportWidth' => 1000,
			'categories'    => array( 'text' ),
			'description'   => _x( 'A heading preceded by a chapter number, and followed by a paragraph.', 'Block pattern description', 'gutenberg' ),
		),

		'large-header' => array(
			'title'         => __( 'Large header with a heading', 'gutenberg' ),
			'content'       => "<!-- wp:cover {\"url\":\"https://s.w.org/images/core/5.5/don-quixote-06.jpg\",\"id\":165,\"dimRatio\":15,\"focalPoint\":{\"x\":\"0.40\",\"y\":\"0.26\"},\"minHeight\":375,\"minHeightUnit\":\"px\",\"contentPosition\":\"center center\",\"align\":\"wide\"} -->\n<div class=\"wp-block-cover alignwide has-background-dim-20 has-background-dim is-position-center-center\" style=\"background-image:url(https://s.w.org/images/core/5.5/don-quixote-06.jpg);min-height:375px;background-position:40% 26%\"><div class=\"wp-block-cover__inner-container\"><!-- wp:paragraph {\"align\":\"center\",\"placeholder\":\"" . __( 'Write title…', 'gutenberg' ) . "\",\"style\":{\"typography\":{\"fontSize\":74,\"lineHeight\":\"1.1\"},\"color\":{\"text\":\"#fffffa\"}}} -->\n<p class=\"has-text-align-center has-text-color\" style=\"line-height:1.1;font-size:74px;color:#fffffa\"><strong>" . __( 'Don Quixote', 'gutenberg' ) . "</strong></p>\n<!-- /wp:paragraph --></div></div>\n<!-- /wp:cover -->",
			'viewportWidth' => 1000,
			'categories'    => array( 'header' ),
			'description'   => _x( 'A large hero section with an example background image and a heading in the center.', 'Block pattern description', 'gutenberg' ),
		),

		'large-header-button'        => array(
			'title'         => __( 'Large header with a heading and a button ', 'gutenberg' ),
			'content'       => "<!-- wp:cover {\"minHeight\":575,\"minHeightUnit\":\"px\",\"customGradient\":\"linear-gradient(135deg,rgb(249,72,72) 1%,rgb(179,22,22) 100%)\",\"contentPosition\":\"center center\",\"align\":\"wide\"} -->\n<div class=\"wp-block-cover alignwide has-background-dim has-background-gradient is-position-center-center\" style=\"background:linear-gradient(135deg,rgb(249,72,72) 1%,rgb(179,22,22) 100%);min-height:575px\"><div class=\"wp-block-cover__inner-container\"><!-- wp:columns {\"align\":\"wide\"} -->\n<div class=\"wp-block-columns alignwide\"><!-- wp:column {\"width\":12} -->\n<div class=\"wp-block-column\" style=\"flex-basis:12%\"><!-- wp:spacer -->\n<div style=\"height:100px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer --></div>\n<!-- /wp:column -->\n\n<!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:spacer -->\n<div style=\"height:100px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->\n\n<!-- wp:paragraph {\"align\":\"left\",\"placeholder\":\"" . __( 'Write title…', 'gutenberg' ) . "\",\"style\":{\"typography\":{\"fontSize\":68,\"lineHeight\":\"1.2\"},\"color\":{\"text\":\"#fffffa\"}}} -->\n<p class=\"has-text-align-left has-text-color\" style=\"line-height:1.2;font-size:68px;color:#fffffa\"><strong>" . __( 'Thou hast seen', 'gutenberg' ) . '</strong><br><strong>' . __( 'nothing yet', 'gutenberg' ) . "</strong></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button {\"borderRadius\":3,\"style\":{\"color\":{\"background\":\"#fffffa\",\"text\":\"#00000a\"}}} -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link has-text-color has-background\" style=\"border-radius:3px;background-color:#fffffa;color:#00000a\">" . __( 'Read now', 'gutenberg' ) . "</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons -->\n\n<!-- wp:spacer -->\n<div style=\"height:100px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer --></div>\n<!-- /wp:column -->\n\n<!-- wp:column {\"width\":12} -->\n<div class=\"wp-block-column\" style=\"flex-basis:12%\"><!-- wp:spacer -->\n<div style=\"height:100px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer --></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns --></div></div>\n<!-- /wp:cover -->",
			'viewportWidth' => 1000,
			'categories'    => array( 'header' ),
			'description'   => _x( 'A large hero section with a bright gradient background, a big heading and a filled button.', 'Block pattern description', 'gutenberg' ),
		),

		'quote'                      => array(
			'title'         => __( 'Quote', 'gutenberg' ),
			'content'       => "<!-- wp:group -->\n<div class=\"wp-block-group\"><div class=\"wp-block-group__inner-container\"><!-- wp:image {\"align\":\"center\",\"width\":164,\"height\":164,\"sizeSlug\":\"large\",\"className\":\"is-style-rounded\"} -->\n<div class=\"wp-block-image is-style-rounded\"><figure class=\"aligncenter size-large is-resized\"><img src=\"https://s.w.org/images/core/5.5/don-quixote-03.jpg\" alt=\"" . __( 'Pencil drawing of Don Quixote', 'gutenberg' ) . "\" width=\"164\" height=\"164\"/></figure></div>\n<!-- /wp:image -->\n\n<!-- wp:quote {\"align\":\"center\",\"className\":\"is-style-large\"} -->\n<blockquote class=\"wp-block-quote has-text-align-center is-style-large\"><p>" . __( '"Do you see over yonder, friend Sancho, thirty or forty hulking giants? I intend to do battle with them and slay them."', 'gutenberg' ) . '</p><cite>' . __( '— Don Quixote', 'gutenberg' ) . "</cite></blockquote>\n<!-- /wp:quote -->\n\n<!-- wp:separator {\"className\":\"is-style-dots\"} -->\n<hr class=\"wp-block-separator is-style-dots\"/>\n<!-- /wp:separator --></div></div>\n<!-- /wp:group -->",
			'viewportWidth' => 800,
			'categories'    => array( 'text' ),
			'description'   => _x( 'A quote and citation with an image above, and a separator at the bottom.', 'Block pattern description', 'gutenberg' ),
		),

		'text-three-columns-buttons' => array(
			'title'         => __( 'Three columns of text with buttons', 'gutenberg' ),
			'categories'    => array( 'columns' ),
			'content'       => "<!-- wp:group {\"align\":\"wide\"} -->\n<div class=\"wp-block-group alignwide\"><div class=\"wp-block-group__inner-container\"><!-- wp:columns {\"align\":\"wide\"} -->\n<div class=\"wp-block-columns alignwide\"><!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:paragraph -->\n<p>" . __( 'Which treats of the character and pursuits of the famous Don Quixote of La Mancha.', 'gutenberg' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button {\"borderRadius\":50,\"style\":{\"color\":{\"text\":\"#ba0c49\"}},\"className\":\"is-style-outline\"} -->\n<div class=\"wp-block-button is-style-outline\"><a class=\"wp-block-button__link has-text-color\" style=\"border-radius:50px;color:#ba0c49\">" . __( 'Chapter One', 'gutenberg' ) . "</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons --></div>\n<!-- /wp:column -->\n\n<!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:paragraph -->\n<p>" . __( 'Which treats of the first sally the ingenious Don Quixote made from home.', 'gutenberg' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button {\"borderRadius\":50,\"style\":{\"color\":{\"text\":\"#ba0c49\"}},\"className\":\"is-style-outline\"} -->\n<div class=\"wp-block-button is-style-outline\"><a class=\"wp-block-button__link has-text-color\" style=\"border-radius:50px;color:#ba0c49\">" . __( 'Chapter Two', 'gutenberg' ) . "</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons --></div>\n<!-- /wp:column -->\n\n<!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:paragraph -->\n<p>" . __( 'Wherein is related the droll way in which Don Quixote had himself dubbed a knight.', 'gutenberg' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button {\"borderRadius\":50,\"style\":{\"color\":{\"text\":\"#ba0c49\"}},\"className\":\"is-style-outline\"} -->\n<div class=\"wp-block-button is-style-outline\"><a class=\"wp-block-button__link has-text-color\" style=\"border-radius:50px;color:#ba0c49\">" . __( 'Chapter Three', 'gutenberg' ) . "</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons --></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns --></div></div>\n<!-- /wp:group -->",
			'viewportWidth' => 1000,
			'description'   => _x( 'Three small columns of text, each with an outlined button with rounded corners at the bottom.', 'Block pattern description', 'gutenberg' ),
		),

		'text-two-columns' => array(
			'title'       => __( 'Two columns of text', 'gutenberg' ),
			'categories'  => array( 'columns' ),
			'content'     => "<!-- wp:group -->\n<div class=\"wp-block-group\"><div class=\"wp-block-group__inner-container\"><!-- wp:heading {\"style\":{\"typography\":{\"fontSize\":38},\"color\":{\"text\":\"#ba0c49\"}}} -->\n<h2 class=\"has-text-color\" style=\"font-size:38px;color:#ba0c49\">" . __( 'Which treats of the character and pursuits of the famous gentleman Don Quixote of La Mancha', 'gutenberg' ) . "</h2>\n<!-- /wp:heading -->\n\n<!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:paragraph {\"style\":{\"typography\":{\"fontSize\":18}}} -->\n<p style=\"font-size:18px\">" . __( 'In a village of La Mancha, the name of which I have no desire to call to mind, there lived not long since one of those gentlemen that keep a lance in the lance-rack, an old buckler, a lean hack, and a greyhound for coursing. An olla of rather more beef than mutton, a salad on most nights, scraps on Saturdays, lentils on Fridays, and a pigeon or so extra on Sundays, made away with three-quarters of his income.', 'gutenberg' ) . "</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:column -->\n\n<!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:paragraph {\"style\":{\"typography\":{\"fontSize\":18}}} -->\n<p style=\"font-size:18px\">" . __( 'The rest of it went in a doublet of fine cloth and velvet breeches and shoes to match for holidays, while on week-days he made a brave figure in his best homespun. He had in his house a housekeeper past forty, a niece under twenty, and a lad for the field and market-place, who used to saddle the hack as well as handle the bill-hook. The age of this gentleman of ours was bordering on fifty; he was of a hardy habit, spare, gaunt-featured, a very early riser and a great sportsman.', 'gutenberg' ) . "</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns --></div></div>\n<!-- /wp:group -->",
			'description' => _x( 'Two columns of text preceded by a long heading.', 'Block pattern description', 'gutenberg' ),
		),

		'text-two-columns-with-images' => array(
			'title'       => __( 'Two columns of text with images', 'gutenberg' ),
			'categories'  => array( 'columns' ),
			'content'     => "<!-- wp:group -->\n<div class=\"wp-block-group\"><div class=\"wp-block-group__inner-container\"><!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:image {\"sizeSlug\":\"large\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"https://s.w.org/images/core/5.5/don-quixote-02.jpg\" alt=\"\"/></figure>\n<!-- /wp:image -->\n\n<!-- wp:paragraph {\"style\":{\"typography\":{\"fontSize\":18}}} -->\n<p style=\"font-size:18px\">" . __( 'They must know, then, that the above-named gentleman whenever he was at leisure (which was mostly all the year round) gave himself up to reading books of chivalry with such ardour and avidity that he almost entirely neglected the pursuit of his field-sports, and even the management of his property; and to such a pitch did his eagerness and infatuation go that he sold many an acre of tillageland to buy books of chivalry to read, and brought home as many of them as he could get.', 'gutenberg' ) . "</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:column -->\n\n<!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:image {\"sizeSlug\":\"large\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"https://s.w.org/images/core/5.5/don-quixote-04.jpg\" alt=\"\"/></figure>\n<!-- /wp:image -->\n\n<!-- wp:paragraph {\"style\":{\"typography\":{\"fontSize\":18}}} -->\n<p style=\"font-size:18px\">" . __( 'But of all there were none he liked so well as those of the famous Feliciano de Silva\'s composition, for their lucidity of style and complicated conceits were as pearls in his sight, particularly when in his reading he came upon courtships and cartels, where he often found passages like "the reason of the unreason with which my reason is afflicted so weakens my reason that with reason I murmur at your beauty;" or again, "the high heavens render you deserving of the desert your greatness deserves."', 'gutenberg' ) . "</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:column --></div>\n<!-- /wp:columns --></div></div>\n<!-- /wp:group -->",
			'description' => _x( 'Two columns of text, each with an image on top.', 'Block pattern description', 'gutenberg' ),
		),

		'three-buttons' => array(
			'title'         => __( 'Three buttons', 'gutenberg' ),
			'content'       => "<!-- wp:buttons {\"align\":\"center\"} -->\n<div class=\"wp-block-buttons aligncenter\"><!-- wp:button {\"borderRadius\":50,\"style\":{\"color\":{\"gradient\":\"linear-gradient(135deg,rgb(135,9,53) 0%,rgb(179,22,22) 100%)\",\"text\":\"#fffffa\"}}} -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link has-text-color has-background\" style=\"border-radius:50px;background:linear-gradient(135deg,rgb(135,9,53) 0%,rgb(179,22,22) 100%);color:#fffffa\">" . __( 'About Cervantes', 'gutenberg' ) . "</a></div>\n<!-- /wp:button -->\n\n<!-- wp:button {\"borderRadius\":50,\"style\":{\"color\":{\"gradient\":\"linear-gradient(317deg,rgb(135,9,53) 0%,rgb(179,22,22) 100%)\",\"text\":\"#fffffa\"}}} -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link has-text-color has-background\" style=\"border-radius:50px;background:linear-gradient(317deg,rgb(135,9,53) 0%,rgb(179,22,22) 100%);color:#fffffa\">" . __( 'Contact us', 'gutenberg' ) . "</a></div>\n<!-- /wp:button -->\n\n<!-- wp:button {\"borderRadius\":50,\"style\":{\"color\":{\"gradient\":\"linear-gradient(42deg,rgb(135,9,53) 0%,rgb(179,22,22) 100%)\",\"text\":\"#fffffa\"}}} -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link has-text-color has-background\" style=\"border-radius:50px;background:linear-gradient(42deg,rgb(135,9,53) 0%,rgb(179,22,22) 100%);color:#fffffa\">" . __( 'Books', 'gutenberg' ) . "</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons -->",
			'viewportWidth' => 600,
			'categories'    => array( 'buttons' ),
			'description'   => _x( 'Three filled buttons with rounded corners, side by side.', 'Block pattern description', 'gutenberg' ),
		),

		'two-buttons'   => array(
			'title'         => __( 'Two buttons', 'gutenberg' ),
			'content'       => "<!-- wp:buttons {\"align\":\"center\"} -->\n<div class=\"wp-block-buttons aligncenter\"><!-- wp:button {\"borderRadius\":2,\"style\":{\"color\":{\"background\":\"#ba0c49\",\"text\":\"#fffffa\"}}} -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link has-text-color has-background\" style=\"border-radius:2px;background-color:#ba0c49;color:#fffffa\">" . __( 'Download now', 'gutenberg' ) . "</a></div>\n<!-- /wp:button -->\n\n<!-- wp:button {\"borderRadius\":2,\"style\":{\"color\":{\"text\":\"#ba0c49\"}},\"className\":\"is-style-outline\"} -->\n<div class=\"wp-block-button is-style-outline\"><a class=\"wp-block-button__link has-text-color\" style=\"border-radius:2px;color:#ba0c49\">" . __( 'About Cervantes', 'gutenberg' ) . "</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons -->",
			'viewportWidth' => 500,
			'categories'    => array( 'buttons' ),
			'description'   => _x( 'Two buttons, one filled and one outlined, side by side.', 'Block pattern description', 'gutenberg' ),
		),

		'two-images'    => array(
			'title'       => __( 'Two images side by side', 'gutenberg' ),
			'categories'  => array( 'gallery' ),
			'description' => _x( 'An image gallery with two example images.', 'Block pattern description', 'gutenberg' ),
			'content'     => "<!-- wp:gallery {\"ids\":[null,null],\"align\":\"wide\"} -->\n<figure class=\"wp-block-gallery alignwide columns-2 is-cropped\"><ul class=\"blocks-gallery-grid\"><li class=\"blocks-gallery-item\"><figure><img src=\"https://s.w.org/images/core/5.5/don-quixote-05.jpg\" alt=\"" . __( 'An old pencil drawing of Don Quixote and Sancho Panza sitting on their horses, by Wilhelm Marstrand.', 'gutenberg' ) . '"/></figure></li><li class="blocks-gallery-item"><figure><img src="https://s.w.org/images/core/5.5/don-quixote-01.jpg" alt="' . __( 'An old pencil drawing of Don Quixote and Sancho Panza sitting on their horses, by Wilhelm Marstrand.', 'gutenberg' ) . "\"/></figure></li></ul></figure>\n<!-- /wp:gallery -->",
		),
	);
}

/**
 * Serve the response.
 *
 * @param array $data
 */
function serve_response( $data ) {
	header( 'Access-Control-Allow-Origin: *' );
	header( 'Content-Type: application/json; charset=UTF-8' );

	echo wp_json_encode( $data );
}


// @todo stub, replace with real translation.
function __( $text, $domain = 'default' ) {
	return $text;
}

// @todo stub, replace with real translation.
function _x( $text, $context, $domain = 'default' ) {
	return $text;
}