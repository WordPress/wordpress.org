<?php
/**
 * Template part for displaying a message that posts cannot be found.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

?>

<section class="no-results not-found">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Nothing Found', 'wporg-plugins' ); ?></h1>
	</header><!-- .page-header -->

	<div class="page-content">
		<?php
		if ( is_home() && current_user_can( 'publish_posts' ) ) : ?>

			<p><?php printf( wp_kses( __( 'Ready to publish your first post? <a href="%1$s">Get started here</a>.', 'wporg-plugins' ), array( 'a' => array( 'href' => array() ) ) ), esc_url( admin_url( 'post-new.php' ) ) ); ?></p>

		<?php elseif ( is_search() ) : ?>

			<p><?php esc_html_e( 'Sorry, but nothing matched your search terms.', 'wporg-plugins' ); ?></p>
			<p><?php esc_html_e( 'Please try again with some different keywords.', 'wporg-plugins' ); ?></p>
			<?php
				get_search_form();

		elseif ( is_tax( 'plugin_section', 'favorites' ) ) :
		  if ( is_user_logged_in() ) :
		    $current_user = wp_get_current_user();
		?>
		
			<p><?php esc_html_e( 'No favorites have been added, yet.', 'wporg-plugins' ); ?></p>
			
			<?php if ( get_query_var( 'favorites_user' ) === $current_user->user_nicename ) : ?>
			<p><?php esc_html_e( 'Find a plugin and mark it as a favorite to see it here.', 'wporg-plugins' ); ?></p>
			<p><?php printf( __( 'Your favorite plugins are also shared on <a href="%s">your profile</a>.', 'wporg-plugins' ), esc_url( 'https://profile.wordpress.org/' . $current_user->user_nicename ) ); ?></p>
			<?php endif; ?>

		<?php else : ?>
		
			<p><?php printf( __( '<a href="%s">Login to WordPress.org</a> to mark plugins as favorites.', 'wporg-plugins' ), esc_url( 'https://login.wordpress.org/?redirect_to=https%3A%2F%2Fwordpress.org%2Fplugins%2Fbrowse%2Ffavorites%2F' ) ); ?></p>

		<?php
  		  endif; // is_user_logged_in()
  		else :
    ?>

			<p><?php esc_html_e( 'It seems we can&#8217;t find what you&#8217;re looking for. Perhaps searching can help.', 'wporg-plugins' ); ?></p>
			<?php
				get_search_form();

		endif; ?>
	</div><!-- .page-content -->
</section><!-- .no-results -->
