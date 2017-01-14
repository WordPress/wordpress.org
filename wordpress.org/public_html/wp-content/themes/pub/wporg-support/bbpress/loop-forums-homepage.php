<?php do_action( 'bbp_template_before_forums_loop' ); ?>

<div id="forums-list-<?php bbp_forum_id(); ?>" class="bbp-forums three-up">


		<?php while ( bbp_forums() ) : bbp_the_forum(); ?>

			<?php bbp_get_template_part( 'loop', 'single-forum-homepage' ); ?>

		<?php endwhile; ?>


</div><!-- .forums-directory -->

<div class="themes-plugins">
	
	<h3>Themes and Plugins</h3>
	<p>Looking for help with a specific <a href="https://wordpress.org/themes/"><span class="dashicons dashicons-admin-appearance"></span> theme</a> or <a href="https://wordpress.org/plugins/"><span class="dashicons dashicons-admin-plugins"></span> plugin</a>? Head to the theme or plugin's page and find the "View support forum" link to visit the theme or plugin's individual forum.</p>

</div>

<?php do_action( 'bbp_template_after_forums_loop' ); ?>
