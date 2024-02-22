<?php
/**
 * The template for the Add Your Plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

get_header(); ?>

	<main id="main" class="site-main" role="main">

		<?php
		while ( have_posts() ) :
			the_post();
		?>

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php esc_html_e( 'Add Your Plugin', 'wporg-plugins' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content">
				<section>
					<div class="container">
						<p>
							<?php
							printf(
								/* translators: 1: URL to plugin guidelines, 2: URL to plugin developer FAQ. */
								wp_kses_post( __( 'Before you submit your plugin, we ask you to review our <a href="%1$s">Guidelines</a> and read the <a href="%2$s">Frequently Asked Questions</a>. A brief selections of common questions are listed below the form.', 'wporg-plugins' ) ),
								esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/' ),
								esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/' )
							);
							?>
						</p>

						<?php echo do_shortcode( '[wporg-plugin-upload]' ); ?>

						<p>
							<?php
							printf(
								/* translators: URL to plugin guidelines. */
								wp_kses_post( __( 'Once submitted, your plugin will be manually reviewed for any common errors as well as ensuring it complies with <a href="%s">all the guidelines</a>.', 'wporg-plugins' ) ),
								esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/' )
							);
							?>
						</p>
					</div>
				</section>

				<section>
					<div class="container">
						<h2 id="faq"><?php esc_html_e( 'Frequently Asked Questions', 'wporg-plugins' ); ?></h2>

						<h3><?php esc_html_e( 'How long will the review process take?', 'wporg-plugins' ); ?></h3>
						<p>
							<?php
							printf(
								/* translators: URL to plugin developer FAQ. */
								wp_kses_post( __( 'This is in the <a href="%s">Developer FAQ</a>. It takes anywhere between 1 and 10 days. We attempt to review all plugins within 5 business days of submission, but the process takes as long as it takes, depending on the complexity of your plugin.', 'wporg-plugins' ) ),
								esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/' )
							);
							?>
						</p>

						<h3><?php esc_html_e( 'What will my plugin URL be?', 'wporg-plugins' ); ?></h3>
						<p><?php echo wp_kses_post( __( 'Your plugin&#8217;s URL will be populated based on the value of <code>Plugin Name</code> in your main plugin file (the one with the plugin headers). If you set yours as <code>Plugin Name: Boaty McBoatface</code> then your URL will be <code>https://wordpress.org/plugins/boaty-mcboatface</code> and your slug will be <code>boaty-mcboatface</code> for example. If there is an existing plugin with your name, then you will be <code>boaty-mcboatface-2</code> and so on. It behaves exactly like WordPress post names.', 'wporg-plugins' ) ); ?></p>
						<p><?php echo wp_kses_post( __( 'Once your plugin is approved, it <em>cannot</em> be renamed.', 'wporg-plugins' ) ); ?></p>

						<h3><?php esc_html_e( 'I made a mistake in my plugin name. Should I resubmit?', 'wporg-plugins' ); ?></h3>
						<p><?php echo wp_kses_post( __( 'Please don&#8217;t! Instead email <code>plugins@wordpress.org</code> and we can rename your plugin as long as it&#8217;s not approved. Since we check emails first, the odds are we&#8217;ll catch it. If we don&#8217;t, just email us and explain the mistake. We&#8217;ll explain what to do.', 'wporg-plugins' ) ); ?></p>

						<h3><?php esc_html_e( 'Why can\'t I submit a plugin with certain display names?', 'wporg-plugins' ); ?></h3>
						<p><?php echo wp_kses_post( __( 'Certain plugin names are prohibited due to trademark abuse. Similarly, we prevent their use in plugin slugs entirely for your protection.', 'wporg-plugins' ) ); ?></p>
					</div>
				</section>
			</div><!-- .entry-content -->

			<footer class="entry-footer">
				<?php
				edit_post_link(
					sprintf(
						/* translators: %s: Name of current post */
						esc_html__( 'Edit %s', 'wporg-plugins' ),
						the_title( '<span class="screen-reader-text">"', '"</span>', false )
					),
					'<span class="edit-link">',
					'</span>'
				);
				?>
			</footer><!-- .entry-footer -->
		</article><!-- #post-## -->

		<?php endwhile; ?>

	</main><!-- #main -->

<?php
get_footer();
