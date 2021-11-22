<?php
/**
 * Template Name: About -> Testimonials
 *
 * Page template for displaying the Logos and Graphics page.
 *
 * @package WordPressdotorg\MainTheme
 */

// phpcs:disable WordPress.VIP.RestrictedFunctions

namespace WordPressdotorg\MainTheme;

$GLOBALS['menu_items'] = [
	'about/philosophy'   => _x( 'Philosophy', 'Page title', 'wporg' ),
	'about/etiquette'    => _x( 'Etiquette', 'Page title', 'wporg' ),
	'about/swag'         => _x( 'Swag', 'Page title', 'wporg' ),
	'about/logos'        => _x( 'Graphics &amp; Logos', 'Page title', 'wporg' ),
	'about/testimonials' => _x( 'Testimonials', 'Page title', 'wporg' ),
];

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

/* See inc/page-meta-descriptions.php for the meta description for this page. */

// Pull list of testimonial URLs from the news page holding them.
switch_to_blog( 8 );
$testimonials_post = get_page_by_path( 'testimonials' );
restore_current_blog();

if ( $testimonials_post instanceof \WP_Post ) {
	// We only need the URLs in the post_content.
	preg_match_all( '|https://\S+|', $testimonials_post->post_content, $testimonials );
	$testimonials = $testimonials[0];
} else {
	$testimonials = [];
}

// Separate out the twitter from the WPs.
$embed_tweets = array_values( array_filter( $testimonials, function( $t ) {
	return strpos( $t, 'https://twitter.com' ) === 0;
} ) );
$embed_wps = array_values( array_filter( $testimonials, function( $t ) {
	return strpos( $t, 'https://twitter.com' ) !== 0;
} ) );

// Randomize the tweet order.
shuffle( $embed_tweets );

// Strip out everything but the Tweet ID.
array_walk( $embed_tweets, function ( &$tweet ) {
	$tweet = preg_replace( '|https?://twitter.com/.*/status/([0-9]+)|', '$1', $tweet );
} );

wp_enqueue_script( 'twitter-widgets', 'https://platform.twitter.com/widgets.js', array( 'jquery', 'masonry' /* The appended JS requires it */ ), null, true );

$custom_js = <<<EOJS
twttr.ready( function( twttr ) {
	var embed_holder = document.getElementById( 'embeds' ),
		embed_holder_jq = jQuery( '#embeds' ),
		embed_holder_masonry;

	embed_holder_masonry = embed_holder_jq.masonry({
		itemSelector: '.wp-embed, .twitter-tweet-rendered',
		columnWidth: 372,
		gutter: 10,
		initLayout: false
	});

	embeds.tweets.forEach( function( id ) {
		twttr.widgets.createTweet(
			id,
			embed_holder,
			{
				align: 'left',
				conversation: 'none',
				cards: 'hidden',
				dnt: true,
				margin: 0,
				width: 372
			}
		).then( function() {
			embed_holder_masonry.masonry('reloadItems').masonry();
		});
	});

	embeds.wpembeds.forEach( function( wpembed ) {
		var embed = jQuery( '<div class="wp-embed"><iframe security="restricted" src="' + wpembed + '" width="372" height="500" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" class="wp-embedded-content"></iframe></div>' );

		// Extra margin top/bottom to match Twitter embeds (that have set a 0 margin)
		embed.css( 'margin', '10px 0' );

		embed.on( 'load', function() {
			embed_holder_masonry.masonry('reloadItems').masonry();
		});

		embed_holder_jq.append( embed );
	});

});
EOJS;

wp_add_inline_script( 'twitter-widgets', $custom_js );
wp_localize_script( 'twitter-widgets', 'embeds', array(
	'tweets'   => $embed_tweets,
	'wpembeds' => $embed_wps,
) );

get_header( 'child-page' );
the_post();
?>
	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header row">
				<h1 class="entry-title col-8"><?php the_title(); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<h2><?php esc_html_e( 'Share your WordPress story', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'Want to have your story featured on this page?', 'wporg' ); ?></p>
					<p>
						<?php
						/* translators: 1: Link to the twitter #ILoveWP feed 2: The #ILoveWP Hashtag */
						printf( wp_kses_post( __( 'Make a blog post with your story and tweet a link to it using the <a href="%1$s">%2$s</a> hashtag. We&#8217;ll select the best ones and feature them here!', 'wporg' ) ), 'https://twitter.com/hashtag/ILoveWP', '#ILoveWP' );
						?>
					</p>

					<p>
						<a href="https://twitter.com/intent/tweet?button_hashtag=ILoveWP" class="twitter-hashtag-button" data-size="large" data-related="WordPress" data-dnt="true">
						<?php
							/* translators: The #ILoveWP Hashtag */
							printf( esc_html__( 'Tweet %s', 'wporg' ), '#ILoveWP' );
						?>
						</a>
					</p>
				</section>

				<section class="col-12">
					<div class="row">
						<div class="offset-2 col-8">
							<h2><?php esc_html_e( 'WordPress on Twitter', 'wporg' ); ?></h2>
						</div>
					</div>
					<div class="row">
						<div class="offset-1 col-10">	
							<div  id="embeds"></div>
						</div>
					</div>
				</section>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
