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

global $section, $section_slug, $section_content, $section_read_more, $post;

$content   = Plugin_Directory::instance()->split_post_content_into_pages( get_the_content() );
$is_closed = in_array( get_post_status(), ['closed', 'disabled'], true );
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php the_plugin_banner(); ?>

	<header class="plugin-header">
		<?php the_active_plugin_notice(); ?>

		<div class="entry-thumbnail">
			<?php echo Template::get_plugin_icon( $post, 'html' ); ?>
		</div>

		<div class="plugin-actions">
			<?php the_plugin_favorite_button(); ?>

			<?php if ( 'publish' === get_post_status() || current_user_can( 'plugin_admin_view', $post ) ) : ?>
				<a class="plugin-download button download-button button-large" href="<?php echo esc_url( Template::download_link() ); ?>"><?php _e( 'Download', 'wporg-plugins' ); ?></a>
			<?php endif; ?>
		</div>

		<?php $plugin_title = $is_closed ? $post->post_name : get_the_title(); ?>
		<h1 class="plugin-title"><a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo $plugin_title; ?></a></h1>

		<span class="byline"><?php the_author_byline(); ?></span>
	</header><!-- .entry-header -->

	<?php if ( ! get_query_var( 'plugin_advanced' ) ) : ?>
		<span id="description"></span>
		<span id="reviews"></span>
		<span id="installation"></span>
		<span id="developers"></span>
		<ul class="tabs clear">
			<li id="tablink-description"><a href="#description"><?php _e( 'Details', 'wporg-plugins' ); ?></a></li>
			<li id="tablink-reviews"><a href="#reviews"><?php _e( 'Reviews', 'wporg-plugins' ); ?></a></li>
			<?php if ( isset( $content['installation'] ) && ! $is_closed ) : ?>
				<li id="tablink-installation">
					<a href="#installation"><?php _e( 'Installation', 'wporg-plugins' ); ?></a>
				</li>
			<?php endif; ?>
			<li id="tablink-support">
				<a href="<?php echo esc_url( Template::get_support_url() ); ?>"><?php _e( 'Support', 'wporg-plugins' ); ?></a>
			</li>
			<li id="tablink-developers"><a href="#developers"><?php _e( 'Development', 'wporg-plugins' ); ?></a></li>
		</ul>
	<?php endif; ?>

	<div class="entry-content">
		<?php
		if ( get_query_var( 'plugin_advanced' ) ) :
			get_template_part( 'template-parts/section-advanced' );
		else :
			$plugin_sections = Template::get_plugin_sections();

			foreach ( array( 'description', 'screenshots', 'installation', 'faq', 'reviews', 'developers', 'changelog' ) as $section_slug ) :
				if ( ! isset( $content[ $section_slug ] ) ) {
					continue;
				}

				$section_content = '';

				if ( 'description' === $section_slug && $is_closed ) {
					// Don't show the description for closed plugins.
					$section_content = get_closed_plugin_notice();

				} else if ( ! in_array( $section_slug, ['screenshots', 'installation', 'faq', 'changelog'], true ) || ! $is_closed ) {
					$section_content = trim( apply_filters( 'the_content', $content[ $section_slug ], $section_slug ) );
				}

				if ( empty( $section_content ) ) {
					continue;
				}

				$section = wp_list_filter( $plugin_sections, array( 'slug' => $section_slug ) );
				$section = array_pop( $section );

				$section_no_read_mores = array( 'description', 'screenshots', 'installation', 'faq', 'reviews' );
				// If the FAQ section is the newer `<dl>` form, no need to do read-more for it.
				if ( false !== stripos( $section_content, '<dl>' ) ) {
					$section_no_read_mores[] = 'faq';
				}

				$section_read_more = ! in_array( $section_slug, $section_no_read_mores );

				get_template_part( 'template-parts/section' );
			endforeach;
		endif; // plugin_advanced
		?>
	</div><!-- .entry-content -->

	<div class="entry-meta">
		<?php get_template_part( 'template-parts/plugin-sidebar', get_query_var( 'plugin_advanced' ) ? 'advanced' : '' ); ?>
	</div><!-- .entry-meta -->
</article><!-- #post-## -->
