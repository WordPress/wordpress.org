<?php
/**
 * Site-level behaviour that must survive a theme swap: analytics, feed
 * discovery, routing and robots rules. Moved from the theme.
 *
 * @package jobswp-blocks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Google Tag Manager container id. Filter it to an empty string to turn GTM
 * off, as on local and staging sites.
 *
 * @return string
 */
function jobswp_blocks_gtm_id() {
	return (string) apply_filters( 'jobswp_blocks_gtm_id', wp_get_environment_type() === 'production' ? 'GTM-P24PF4B' : '' );
}

/**
 * GTM loader in the head.
 */
function jobswp_blocks_gtm_head() {
	$gtm_id = jobswp_blocks_gtm_id();
	if ( '' === $gtm_id ) {
		return;
	}
	?>
	<link rel="dns-prefetch" href="//www.googletagmanager.com"/>
	<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo esc_js( $gtm_id ); ?>');</script>
	<?php
}
add_action( 'wp_head', 'jobswp_blocks_gtm_head', 1 );

/**
 * GTM fallback frame at the start of the body.
 */
function jobswp_blocks_gtm_body() {
	$gtm_id = jobswp_blocks_gtm_id();
	if ( '' === $gtm_id ) {
		return;
	}
	printf(
		'<noscript><iframe src="%s" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>',
		esc_url( 'https://www.googletagmanager.com/ns.html?id=' . rawurlencode( $gtm_id ) )
	);
}
add_action( 'wp_body_open', 'jobswp_blocks_gtm_body' );

/**
 * RSS discovery links for each job category on the front page.
 */
function jobswp_blocks_category_feed_links() {
	if ( ! is_front_page() || ! class_exists( 'Jobs_Dot_WP' ) ) {
		return;
	}
	foreach ( (array) Jobs_Dot_WP::get_job_categories() as $category ) {
		printf(
			'<link rel="alternate" type="application/rss+xml" title="%1$s" href="%2$s" />' . "\n",
			/* translators: 1: site name, 2: job category. */
			esc_attr( sprintf( __( '%1$s &raquo; %2$s Feed', 'jobswp-blocks' ), get_bloginfo( 'name' ), $category->name ) ),
			esc_url( get_term_feed_link( $category->term_id, 'job_category' ) )
		);
	}
}
add_action( 'wp_head', 'jobswp_blocks_category_feed_links' );

/**
 * Author archives answer 404: job posters are not public people.
 */
function jobswp_blocks_author_archives_404() {
	if ( is_author() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
	}
}
add_action( 'wp', 'jobswp_blocks_author_archives_404' );

/**
 * No index for search results and empty category archives.
 *
 * @param array $robots Robots directives.
 * @return array
 */
function jobswp_blocks_noindex( $robots ) {
	global $wp_query;
	if ( is_search() || ( is_tax( 'job_category' ) && 0 === (int) $wp_query->found_posts ) ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'jobswp_blocks_noindex' );
