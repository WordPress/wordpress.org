<?php
/**
 * Template Name: About -> Testimonials
 *
 * Page template for displaying the Logos and Graphics page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

$GLOBALS['menu_items'] = [
	'about/philosophy'   => __( 'Philosophy', 'wporg' ),
	'about/etiquette'    => __( 'Etiquette', 'wporg' ),
	'about/swag'         => __( 'Swag', 'wporg' ),
	'about/logos'        => __( 'Graphics &amp; Logos', 'wporg' ),
	'about/testimonials' => __( 'Testimonials', 'wporg' ),
];

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

// See inc/page-meta-descriptions.php for the meta description for this page.

/*
 * List of Twitter tweets to display in a random order.
 */
$embed_tweets = array(
	'https://twitter.com/masonjames/status/690264022680231936',
	'https://twitter.com/rosso/status/690299171295748096',
	'https://twitter.com/thatmitchcanter/status/690300103312150529',
	'https://twitter.com/cesjam7/status/690302854213558273',
	'https://twitter.com/TomiToikka/status/690304183598211072',
	'https://twitter.com/takisbig/status/694654894398164992',
	'https://twitter.com/eatwholegrain/status/694510399971037184',
	'https://twitter.com/idylanrobertson/status/691375719532748800',
	'https://twitter.com/bobWP/status/708298319693479937',
);

/*
 * List of WordPress embeds to mix into the content.
 *
 * Please Note: These must be the /embed link and must be https://
 */
$embed_wps = array(
	'https://www.rhyswynne.co.uk/why-ilovewp/embed/',
	'https://ayudawp.com/ilovewp/embed/',
);

// Randomize the tweet order
shuffle( $embed_tweets );

// Strip out everything but the Tweet ID
array_walk( $embed_tweets, function ( &$tweet ) {
	$tweet = preg_replace( '|https?://twitter.com/.*/status/([0-9]+)|', '$1', $tweet );
} );

wp_enqueue_script( 'masonry', 'https://s.w.org/wp-includes/js/masonry.min.js', array(), null, true );
wp_enqueue_script( 'twitter-widgets', 'https://platform.twitter.com/widgets.js', array( 'masonry' /* The appended JS requires it */ ), null, true );

$custom_js = <<<EOJS
twttr.ready( function( twttr ) {
	var embed_holder = document.getElementById( 'embeds' ),
		embed_holder_jq = jQuery( '#embeds' ),
		embed_holder_masonry;

	embed_holder_masonry = embed_holder_jq.masonry({
		itemSelector: 'iframe, twitterwidget',
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
				margin: 0, width: 372
			}
		).then( function() {
			embed_holder_masonry.masonry('reloadItems').masonry();
		});
	});

	embeds.wpembeds.forEach( function( wpembed ) {
		var iframe = jQuery( '<iframe security="restricted" src="' + wpembed + '" width="372" height="500" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" class="wp-embedded-content"></iframe>' );

		iframe.on( 'load', function() {
			embed_holder_masonry.masonry('reloadItems').masonry();
		});

		embed_holder_jq.append( iframe );
	});

});
EOJS;

wp_add_inline_script( 'twitter-widgets', $custom_js );
wp_localize_script( 'twitter-widgets', 'embeds', array(
	'tweets' => $embed_tweets,
	'wpembeds' => $embed_wps,
) );

get_header( 'child-page' );
the_post();
?>
	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title col-8"><?php esc_html_e( 'Testimonials', 'wporg' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">

				<section class="col-8">
					<h3><?php _e( 'Share your WordPress story', 'wporg' ); ?></h3>

					<p><?php _e( 'Want to have your story featured on this page?', 'wporg' ); ?></p>

					<p><?php
						/* translators: Link to the twitter #ilovewp feed */
						printf( __( "Make a blog post with your story and tweet a link to it using the <a href='%s'>#ilovewp</a> hashtag. We'll select the best ones and feature them here!", 'wporg' ), 'https://twitter.com/search?q=%23ilovewp' );
					?></p>

					<p>
						<a href="https://twitter.com/intent/tweet?button_hashtag=ilovewp" class="twitter-hashtag-button" data-size="large" data-related="WordPress" data-dnt="true">
						<?php
							/* translators: The #ilovewp Hashtag */
							printf( __( 'Tweet %s', 'wporg' ), '#ilovewp' );
						?>
						</a>
					</p>

				</section>

				<section class="col-10" id="embeds">
				</section>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
