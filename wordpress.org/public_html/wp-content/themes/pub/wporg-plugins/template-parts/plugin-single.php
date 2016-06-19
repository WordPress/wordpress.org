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

$content = Plugin_Directory::instance()->split_post_content_into_pages( get_the_content() );

$widget_args = array(
	'before_title' => '<h4 class="widget-title">',
	'after_title'  => '</h4>',
);

?><article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php if ( time() - get_post_modified_time() > 2 * YEAR_IN_SECONDS ) : ?>
		<div class="plugin-notice notice notice-warning notice-alt">
			<p><?php _e( 'This plugin <strong>hasn&#8217;t been updated in over 2 years</strong>. It may no longer be maintained or supported and may have compatibility issues when used with more recent versions of WordPress.', 'wporg-plugins' ); ?></p>
		</div><!-- .plugin-notice -->
	<?php endif; ?>

	<?php echo Template::get_plugin_banner( get_post(), 'html' ); ?>

	<header class="plugin-header">
		<div class="plugin-thumbnail">
			<?php echo Template::get_plugin_icon( get_post(), 'html' ); ?>
		</div>

		<a class="plugin-download button download-button button-large" href="<?php echo esc_url( Template::download_link() ); ?>" itemprop="downloadUrl"><?php _e( 'Download', 'wporg-plugins' ); ?></a>
		<meta itemprop="softwareVersion" content="<?php echo esc_attr( get_post_meta( get_the_ID(), 'version', true ) ); ?>">
		<meta itemprop="fileFormat" content="application/zip">

		<?php the_title( '<h1 class="plugin-title">', '</h1>' ); ?>

		<span class="byline"><?php printf( esc_html_x( 'By %s', 'post author', 'wporg-plugins' ), '<span class="author vcard"><a class="url fn n" href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( Template::encode( get_the_author() ) ) . '</a></span>' ); ?></span>
	</header><!-- .entry-header -->

	<div class="entry-content">
		<?php
			$plugin_sections = Template::get_plugin_sections();

			foreach ( array( 'description', 'screenshots', 'faq', 'reviews', 'changelog', 'developers' ) as $section_slug ) :
				if ( ! array_key_exists( $section_slug, $content ) || in_array( $section_slug, array( 'installation', 'other_notes' ) ) ) :
					continue;
				endif;

				$section_content = trim( apply_filters( 'the_content', $content[ $section_slug ], $section_slug ) );
				if ( empty( $section_content ) ) :
					continue;
				endif;

				$section = wp_list_filter( $plugin_sections, array( 'slug' => $section_slug ) );
				$section = array_pop( $section );
		?>

			<div id="<?php echo esc_attr( $section_slug ); ?>" class="read-more" aria-expanded="false">
				<h2><?php echo $section['title']; ?></h2>
				<?php echo $section_content; ?>
			</div>
				<?php if ( 'reviews' === $section_slug ) : ?>
				<a class="reviews-link" href="<?php echo esc_url( 'https://wordpress.org/support/view/plugin-reviews/' . get_post()->post_name . '/' ); ?>">
					<?php printf( __( 'Read all %s reviews', 'wporg-plugins' ), array_sum( get_post_meta( get_the_ID(), 'ratings', true ) ) ); ?>
				</a>
				<?php else : ?>
			<button type="button" class="button-link section-toggle" aria-controls="<?php echo esc_attr( $section_slug ); ?>"><?php _e( 'Read more', 'wporg-plugins' ); ?></button>
				<?php endif; ?>
		<?php endforeach; ?>
	</div><!-- .entry-content -->

	<div class="entry-meta">
		<?php
		the_widget( 'WordPressdotorg\Plugin_Directory\Widgets\Meta',    array(), $widget_args );
		the_widget( 'WordPressdotorg\Plugin_Directory\Widgets\Ratings', array(), $widget_args );
		the_widget( 'WordPressdotorg\Plugin_Directory\Widgets\Support', array(), $widget_args );
		the_widget( 'WordPressdotorg\Plugin_Directory\Widgets\Donate',  array(), $widget_args );
		?>
	</div><!-- .entry-meta -->
</article><!-- #post-## -->
