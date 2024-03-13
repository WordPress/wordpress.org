<?php
/**
 * The header for our theme.
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WPBBP
 */

namespace WordPressdotorg\Forums;

\WordPressdotorg\skip_to( '#content' );

echo do_blocks( '<!-- wp:wporg/global-header {"style":{"border":{"bottom":{"color":"var:preset|color|white-opacity-15","style":"solid","width":"1px"}}}} /-->' );

$is_forums_home = function_exists( 'bbp_is_forum_archive' ) && bbp_is_forum_archive();
$is_user_profile = function_exists( 'bbp_is_single_user' ) && bbp_is_single_user();
$is_homepage = is_page_template( 'page-homepage.php' );

echo do_blocks( $is_forums_home || is_front_page() || $is_homepage
	? '<!-- wp:pattern {"slug":"wporg-support/local-nav-home"} /-->'
	: '<!-- wp:pattern {"slug":"wporg-support/local-nav"} /-->'
);

?>

<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'wporg-forums' ); ?></a>

	<div id="content" class="site-content">
		<?php if ( is_front_page() || $is_homepage ) : ?>
			<?php echo do_blocks(
				sprintf(
					'<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"right":"var:preset|spacing|edge-space","left":"var:preset|spacing|edge-space"}}},"backgroundColor":"charcoal-2","className":"has-white-color has-charcoal-2-background-color has-text-color has-background has-link-color","layout":{"type":"constrained"}} -->
					<div class="wp-block-group alignfull has-white-color has-charcoal-2-background-color has-text-color has-background has-link-color" style="padding-right:var(--wp--preset--spacing--edge-space);padding-left:var(--wp--preset--spacing--edge-space)">

						<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"bottom"}} -->
						<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">

							<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"50px","fontStyle":"normal","fontWeight":"400"}},"fontFamily":"eb-garamond"} -->
							<h1 class="wp-block-heading has-eb-garamond-font-family" style="font-size:50px;font-style:normal;font-weight:400">%s</h1>
							<!-- /wp:heading -->

							<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2.3"}},"textColor":"white"} -->
							<p class="has-white-color has-text-color" style="line-height:2.3">%s</p>
							<!-- /wp:paragraph -->

						</div>
						<!-- /wp:group -->

					</div>
					<!-- /wp:group -->

					<!-- wp:wporg/language-suggest {"align":"full"} -->
					<div class="wp-block-wporg-language-suggest alignfull"></div>
					<!-- /wp:wporg/language-suggest -->

					<!-- wp:group {"layout":{"type":"constrained","justifyContent":"center"},"style":{"border":{"bottom":{"color":"var:preset|color|light-grey-1","style":"solid","width":"1px"}},"spacing":{"padding":{"left":"var:preset|spacing|edge-space","right":"var:preset|spacing|edge-space"}}}} -->
					<div class="wp-block-group alignfull" style="padding-left:var(--wp--preset--spacing--edge-space);padding-right:var(--wp--preset--spacing--edge-space);border-bottom:1px solid var(--wp--preset--color--light-grey-1)">

						<!-- wp:pattern {"slug":"wporg-support/search-field"} /-->

					</div>
					<!-- /wp:group -->',
					__( 'Support', 'wporg-forums' ),
					/* Translators: subhead */
					__( 'We&#8217;ve got a variety of resources to help you get the most out of WordPress.', 'wporg-forums' )
				)
			); ?>
		<?php elseif ( $is_forums_home ) : ?>
			<?php echo do_blocks(
				sprintf(
					'<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"right":"var:preset|spacing|edge-space","left":"var:preset|spacing|edge-space"}}},"backgroundColor":"charcoal-2","className":"has-white-color has-charcoal-2-background-color has-text-color has-background has-link-color","layout":{"type":"constrained"}} -->
					<div class="wp-block-group alignfull has-white-color has-charcoal-2-background-color has-text-color has-background has-link-color" style="padding-right:var(--wp--preset--spacing--edge-space);padding-left:var(--wp--preset--spacing--edge-space)">

						<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"bottom"}} -->
						<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">

							<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"50px","fontStyle":"normal","fontWeight":"400"}},"fontFamily":"eb-garamond"} -->
							<h1 class="wp-block-heading has-eb-garamond-font-family" style="font-size:50px;font-style:normal;font-weight:400">%s</h1>
							<!-- /wp:heading -->

							<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2.3"}},"textColor":"white"} -->
							<p class="has-white-color has-text-color" style="line-height:2.3">%s</p>
							<!-- /wp:paragraph -->

						</div>
						<!-- /wp:group -->

					</div>
					<!-- /wp:group -->

					<!-- wp:wporg/language-suggest {"align":"full"} -->
					<div class="wp-block-wporg-language-suggest alignfull"></div>
					<!-- /wp:wporg/language-suggest -->

					<!-- wp:group {"layout":{"type":"constrained","justifyContent":"center"},"style":{"border":{"bottom":{"color":"var:preset|color|light-grey-1","style":"solid","width":"1px"}},"spacing":{"padding":{"left":"var:preset|spacing|edge-space","right":"var:preset|spacing|edge-space"}}}} -->
					<div class="wp-block-group alignfull" style="padding-left:var(--wp--preset--spacing--edge-space);padding-right:var(--wp--preset--spacing--edge-space);border-bottom:1px solid var(--wp--preset--color--light-grey-1)">

						<!-- wp:pattern {"slug":"wporg-support/search-field"} /-->

					</div>
					<!-- /wp:group -->',
					esc_html__( 'Forums', 'wporg-forums' ),
					esc_html__( 'Learn how to help, or get help you need.', 'wporg-forums' )
				)
			); ?>
		<?php elseif ( ! $is_user_profile ) : ?>
			<?php echo do_blocks(
				'<!-- wp:group {"style":{"spacing":{"border":{"bottom":{"color":"var:preset|color|light-grey-1","style":"solid","width":"1px"}},"padding":{"left":"var:preset|spacing|edge-space","right":"var:preset|spacing|edge-space"}}}} -->
				<div class="wp-block-group alignfull" style="padding-left:var(--wp--preset--spacing--edge-space);padding-right:var(--wp--preset--spacing--edge-space);border-bottom:1px solid var(--wp--preset--color--light-grey-1)">

					<!-- wp:pattern {"slug":"wporg-support/search-field"} /-->

				</div>
				<!-- /wp:group -->'
			); ?>
		<?php endif; ?>
