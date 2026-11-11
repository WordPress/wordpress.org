<?php
/**
 * Site-level hooks: analytics, feed discovery, author archives and robots rules.
 *
 * @package wporg-jobs-blocks
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Google Tag Manager container id, empty outside production.
 *
 * @return string
 */
function wporg_jobs_blocks_gtm_id(): string {
	/**
	 * Filters the Google Tag Manager container id.
	 *
	 * @param string $gtm_id Container id. An empty string turns GTM off.
	 */
	return (string) apply_filters( 'wporg_jobs_blocks_gtm_id', wp_get_environment_type() === 'production' ? 'GTM-P24PF4B' : '' );
}

/**
 * GTM loader in the head.
 */
function wporg_jobs_blocks_gtm_head(): void {
	$gtm_id = wporg_jobs_blocks_gtm_id();
	if ( '' === $gtm_id ) {
		return;
	}
	?>
	<link rel="dns-prefetch" href="//www.googletagmanager.com"/>
	<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo esc_js( $gtm_id ); ?>');</script>
	<?php
}
add_action( 'wp_head', 'wporg_jobs_blocks_gtm_head', 1 );

/**
 * GTM fallback frame at the start of the body.
 */
function wporg_jobs_blocks_gtm_body(): void {
	$gtm_id = wporg_jobs_blocks_gtm_id();
	if ( '' === $gtm_id ) {
		return;
	}
	printf(
		'<noscript><iframe src="%s" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>',
		esc_url( 'https://www.googletagmanager.com/ns.html?id=' . rawurlencode( $gtm_id ) )
	);
}
add_action( 'wp_body_open', 'wporg_jobs_blocks_gtm_body' );

/**
 * RSS discovery links for each job category on the front page.
 */
function wporg_jobs_blocks_category_feed_links(): void {
	if ( ! is_front_page() || ! class_exists( 'Jobs_Dot_WP' ) ) {
		return;
	}
	foreach ( (array) Jobs_Dot_WP::get_job_categories() as $category ) {
		printf(
			'<link rel="alternate" type="application/rss+xml" title="%1$s" href="%2$s" />' . "\n",
			/* translators: 1: site name, 2: job category. */
			esc_attr( sprintf( __( '%1$s &raquo; %2$s Feed', 'wporg-jobs-blocks' ), get_bloginfo( 'name' ), $category->name ) ),
			esc_url( get_term_feed_link( $category->term_id, 'job_category' ) )
		);
	}
}
add_action( 'wp_head', 'wporg_jobs_blocks_category_feed_links' );

// Jobs have no comments.
add_filter( 'feed_links_show_comments_feed', '__return_false' );

/**
 * Author archives answer 404: job posters are not public people.
 */
function wporg_jobs_blocks_author_archives_404(): void {
	if ( is_author() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
	}
}
add_action( 'wp', 'wporg_jobs_blocks_author_archives_404' );

/**
 * No index for search results and empty category archives.
 *
 * @param array $robots Robots directives.
 * @return array
 */
function wporg_jobs_blocks_noindex( array $robots ): array {
	global $wp_query;
	if ( is_search() || ( is_tax( 'job_category' ) && 0 === (int) $wp_query->found_posts ) ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'wporg_jobs_blocks_noindex' );
