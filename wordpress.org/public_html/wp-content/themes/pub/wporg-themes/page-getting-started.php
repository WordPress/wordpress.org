<?php
/**
 * Template file for the Getting Started page.
 *
 * @package wporg-themes
 */

get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		?>

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'wrap' ); ?>>
			<header class="entry-header">
				<h2 class="entry-title"><?php _e( 'Getting Started', 'wporg-themes' ); ?></h2>
			</header><!-- .entry-header -->

			<div class="entry-content">
				<p><?php _e( 'The WordPress theme directory is used by millions of WordPress users all over the world. Themes in the directory are available for download from WordPress.org, and WordPress users can also install them directly from their administration screens.', 'wporg-themes' ); ?></p>
				<p><?php _e( 'By hosting your theme on WordPress.org, you&rsquo;ll get:', 'wporg-themes' ); ?></p>
				<ul>
					<li><?php _e( 'Stats on how many times your theme has been downloaded', 'wporg-themes' ); ?></li>
					<li><?php _e( 'User feedback in the forums', 'wporg-themes' ); ?></li>
					<li><?php _e( 'Ratings, to see what users think of your theme', 'wporg-themes' ); ?></li>
				</ul>
				<p>
					<?php _e( 'The goal of the theme directory isn&rsquo;t to host every theme in the world, it&rsquo;s to host the best open source WordPress themes around.', 'wporg-themes' ); ?>
					<?php _e( 'Themes hosted on WordPress.org pass on the same user freedoms as WordPress itself; this means that they are 100% GPL or compatible.', 'wporg-themes' ); ?>
				</p>

				<h2 name="requirements"><?php _e( 'Guidelines &amp; Resources', 'wporg-themes' ); ?></h2>
				<p>
					<?php printf( __( 'To ensure that WordPress users are guaranteed a good experience, every theme in the directory is reviewed by the theme review team. Please <a href="%s">review the guidelines before uploading your theme</a>.', 'wporg-themes' ),
					'//make.wordpress.org/themes/handbook/review/'
					); ?>
				</p>
				<p><?php _e( 'Themes from sites that support non-GPL (or compatible) themes or that don&rsquo;t meet with the theme review guidelines will not be approved.', 'wporg-themes' ); ?></p>
				<p>
					<?php printf( __( 'Your theme will be reviewed using the <a href="%s">Theme Unit Test data</a>. Before uploading your theme please test it with this sample export data.', 'wporg-themes' ),
						'//codex.wordpress.org/Theme_Unit_Test'
					); ?>
				</p>
				<p>
					<?php printf( __( 'Further resources for theme developers can be found in the Codex on the <a href="%s">Theme Development page</a>.', 'wporg-themes' ),
						'//codex.wordpress.org/Theme_Development'
					); ?>
				</p>
				<p>
					<?php printf( __( 'For questions about theme development please use the <a href="%s">Themes and Templates forum</a>.', 'wporg-themes' ),
						'//wordpress.org/support/forum/themes-and-templates'
					); ?>
				</p>

				<p>
					<a class="button button-primary" href="<?php echo home_url('/upload/'); ?>"><?php _e( 'Upload your theme', 'wporg-themes' ); ?></a>
				</p>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	<?php
	endwhile;
endif;

get_footer();
