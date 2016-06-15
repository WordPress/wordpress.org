<?php
/**
 * The template for the Add Your Plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

get_header(); ?>

	<main id="main" class="site-main" role="main">

		<?php while ( have_posts() ) : the_post(); ?>

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php _e( 'Add Your Plugin', 'wporg-plugins' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content">
				<p><?php printf( __( 'Once submitted, your plugin will be manually reviewed for any common errors as well as ensuring it complies with <a href="%s">all the guidelines</a>.', 'wporg-plugins' ), esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/' ) ); ?></p>

				<?php do_shortcode( '[wporg-plugin-upload]' ); ?>

				<p><?php _e( 'Even if you’ve submitted a dozen plugins, take the time to refresh your memory with the following information:', 'wporg-plugins' ); ?>
				<ul>
				 	<li><a href="https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/"><?php _e( 'How to use SVN', 'wporg-plugins' ); ?></a></li>
				 	<li><a href="https://developer.wordpress.org/plugins/wordpress-org/deploying-your-plugin/"><?php _e( 'Deploying your plugin', 'wporg-plugins' ); ?></a></li>
				 	<li><a href="https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/"><?php _e( 'Plugin Assets (and how to use them)', 'wporg-plugins' ); ?></a></li>
				 	<li><a href="https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/"><?php _e( 'Developer FAQ', 'wporg-plugins' ); ?></a></li>
				</ul>

				<hr />

				<h3 id="faq"><?php _e( 'FAQ', 'wporg-plugins' ); ?></h3>

				<h4><?php _e( 'How long will the review process take?', 'wporg-plugins' ); ?></h4>
				<p><?php printf( __( 'This is in the <a href="%s">Developer FAQ</a>. It takes anywhere between 1 and 10 days. We attempt to review all plugins within 5 business days of submission, but the process takes as long as it takes, depending on the complexity of your plugin.', 'wporg-plugins' ), esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/' ) ); ?></p>

				<h4><?php _e( 'What will my plugin URL be?', 'wporg-plugins' ); ?></h4>
				<p><?php _e( 'Your plugin’s URL will be populated based on the value of <code>Plugin Name</code> in your main plugin file (the one with the plugin headers). If you set yours as <code>Plugin Name: Boaty McBoatface</code> then your URL will be <code>https://wordpress.org/plugins/boaty-mcboatface</code> and your slug will be <code>boaty-mcboatface</code> for example. If there is an existing plugin with your name, then you will be <code>boaty-mcboatface-2</code> and so on. It behaves exactly like WordPress post names.', 'wporg-plugins' ); ?></p>
				<p><?php _e( 'Once your plugin is approved, it <em>cannot</em> be renamed.', 'wporg-plugins' ); ?></p>

				<h4><?php _e( 'I made a mistake in my plugin name. Should I resubmit?', 'wporg-plugins' ); ?></h4>
				<p><?php _e( 'Please don’t! Instead email <code>plugins@wordpress.org</code> and we can rename your plugin as long as it’s not approved. Since we check emails first, the odds are we’ll catch it. If we don’t, just email us and explain the mistake. We’ll explain what do to.', 'wporg-plugins' ); ?></p>

				<h4><?php _e( 'Why was I told my plugin name was unacceptable?', 'wporg-plugins' ); ?></h4>
				<p><?php printf( __( 'This is explained in detail in our <a href="%s">detailed plugin guidelines</a>, but currently we give you the chance to rename it during the review process if the plugin name violates the guideline. Some terms (like “plugin” and “wordpress”) will be removed for you, as those should not be used at all. We get it; you’re a WordPress Plugin.', 'wporg-plugins' ), esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/' ) ); ?></p>
				<p><?php _e( 'Regarding the names and trademarks of other companies and plugins, don’t use them at the start of your plugin name. If you’re not Facebook, you shouldn’t submit a plugin that uses <code>facebook</code> as the first term in your slug. “Facebook Like Sharer” (which would be <code>facebook-like-sharer</code>) is not acceptable, but “Like Sharer for Facebook” (which would be <code>like-sharer-for-facebook</code>) would be alright.', 'wporg-plugins' ); ?></p>
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

		<?php endwhile;	?>

	</main><!-- #main -->

<?php
get_footer();
