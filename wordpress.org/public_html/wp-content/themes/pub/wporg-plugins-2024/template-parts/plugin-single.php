<?php
/**
 * Template part for displaying posts.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;

global $section_slug, $section_title, $section_content, $post;

$content   = Plugin_Directory::instance()->split_post_content_into_pages( get_the_content() );
$is_closed = in_array( get_post_status(), [ 'closed', 'disabled' ], true );

$plugin_title = $is_closed ? $post->post_name : get_the_title();
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'alignwide' ); ?>>
	<?php the_plugin_banner(); ?>

	<header class="plugin-header">
		<?php the_author_notice(); ?>
		<?php the_active_plugin_notice(); ?>
		<?php the_unconfirmed_releases_notice(); ?>

		<div class="entry-thumbnail">
			<?php
			// phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
			echo Template::get_plugin_icon( $post, 'html' );
			?>
		</div>

		<div class="plugin-actions">
			<?php the_plugin_favorite_button(); ?>

			<?php if ( 'publish' === get_post_status() || current_user_can( 'plugin_admin_view', $post ) ) : ?>
				<a class="plugin-download button download-button button-large" href="<?php echo esc_url( Template::download_link() ); ?>"><?php esc_html_e( 'Download', 'wporg-plugins' ); ?></a>
			<?php endif; ?>
			<?php if ( Template::is_preview_available() ) : ?>
				<a class="plugin-preview button download-button button-large" target="_blank" href="<?php echo esc_attr( add_query_arg( array( 'preview' => 1 ), get_the_permalink() ) ); ?>"><?php esc_html_e( 'Live Preview', 'wporg-plugins' ); ?></a>
			<?php elseif ( Template::preview_link() && Template::is_preview_available( $post, 'edit' ) ) : ?>
				<a class="plugin-preview button download-button button-large" target="_blank" href="<?php echo esc_attr( add_query_arg( array( 'preview' => 1 ), get_the_permalink() ) ); ?>"><?php esc_html_e( 'Test Preview', 'wporg-plugins' ); ?></a>
			<?php endif; ?>
		</div>

		<?php if ( get_query_var( 'plugin_advanced' ) ) : ?>
		<h1 class="plugin-title"><a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo wp_kses_post( $plugin_title ); ?></a></h1>
		<?php else : ?>
		<h1 class="plugin-title"><?php echo wp_kses_post( $plugin_title ); ?></h1>
		<?php endif; ?>

		<span class="byline"><?php the_author_byline(); ?></span>
	</header><!-- .entry-header -->

	<span id="description"></span>
	<span id="reviews"></span>
	<span id="installation"></span>
	<span id="developers"></span>
	<span id="advanced" class="<?php if ( get_query_var( 'plugin_advanced' ) ) { echo 'displayed'; } ?>"></span>
	<span id="section-links">
		<ul class="tabs clear">
		<li id="tablink-description"><a href="<?php the_permalink(); ?>#description"><?php esc_html_e( 'Details', 'wporg-plugins' ); ?></a></li>
		<li id="tablink-reviews"><a href="<?php the_permalink(); ?>#reviews"><?php esc_html_e( 'Reviews', 'wporg-plugins' ); ?></a></li>
		<?php if ( isset( $content['installation'] ) && ! $is_closed ) : ?>
			<li id="tablink-installation">
				<a href="<?php the_permalink(); ?>#installation"><?php esc_html_e( 'Installation', 'wporg-plugins' ); ?></a>
			</li>
		<?php endif; ?>
		<li id="tablink-developers"><a href="<?php the_permalink(); ?>#developers"><?php esc_html_e( 'Development', 'wporg-plugins' ); ?></a></li>
		<?php if ( get_query_var( 'plugin_advanced' ) ) : ?>
			<li id="tablink-advanced"><a href="<?php the_permalink(); ?>advanced/"><?php esc_html_e( 'Advanced View', 'wporg-plugins' ); ?></a></li>
		<?php endif; ?>
		</ul>
		<div id="link-support">
			<a href="<?php echo esc_url( Template::get_support_url() ); ?>"><?php esc_html_e( 'Support', 'wporg-plugins' ); ?></a>
		</div>
	</span>
	<script type="text/javascript">if ( '#changelog' == window.location.hash ) { window.setTimeout( function() { window.location.hash = '#developers'; }, 10 ); }</script>

	<div class="entry-content">
		<?php
		if ( get_query_var( 'plugin_advanced' ) ) {
			get_template_part( 'template-parts/section-advanced' );
		} else {
			$plugin_sections_titles = Template::get_plugin_section_titles();

			foreach ( array( 'description', 'screenshots', 'blocks', 'installation', 'faq', 'reviews', 'developers', 'changelog' ) as $section_slug ) {
				$section_content = '';
				$section_title   = $plugin_sections_titles[ $section_slug ] ?? '';

				if ( $is_closed && in_array( $section_slug, [ 'screenshots', 'installation', 'faq', 'changelog' ], true ) ) {
					// Don't show these sections when closed.
					$section_content = '';

				} elseif ( $is_closed && 'description' === $section_slug ) {
					// Don't show the description for closed plugins, show a notice instead.
					$section_content = get_closed_plugin_notice();

				} elseif ( 'blocks' === $section_slug ) {
					$section_content = get_post_meta( get_the_ID(), 'all_blocks', true );

				} elseif ( isset( $content[ $section_slug ] ) ) {
					$section_content = trim( apply_filters( 'the_content', $content[ $section_slug ], $section_slug ) );
				}

				if ( empty( $section_content ) ) {
					continue;
				}

				get_template_part( 'template-parts/section', $section_slug );
			}
		} // plugin_advanced.
		?>
	</div><!-- .entry-content -->

	<div class="entry-meta">
		<?php
			if ( get_query_var( 'plugin_advanced' ) && ( ! $is_closed || current_user_can( 'plugin_admin_view', $post ) ) ) {
				get_template_part( 'template-parts/plugin-sidebar', 'advanced' );
			} elseif ( $is_closed ) {
				get_template_part( 'template-parts/plugin-sidebar', 'closed' );
			} else {
				get_template_part( 'template-parts/plugin-sidebar' );
			}
		?>
	</div><!-- .entry-meta -->
</article><!-- #post-## -->
