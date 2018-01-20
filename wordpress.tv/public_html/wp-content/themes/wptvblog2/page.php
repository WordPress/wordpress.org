<?php
/**
 * Page Template.
 *
 * @package WordPressTV_Blog
 */

get_header();
the_post();
?>
<div class="wptv-hero">
	<h2 class="page-title">
		<?php the_title(); ?>
	</h2>
</div>

<div class="container">
	<div class="primary-content">

		<div <?php post_class(); ?>>

			<div class="entry">
				<div class="sleeve">
					<?php the_content(); ?><br />

					<div id="comments">
						<?php
						wp_link_pages();
						comments_template();
						?>
					</div>
				</div>
			</div><!-- .entry -->

		</div><!-- post_class() -->

	</div><!-- .primary-content -->
</div><!-- .container -->

<?php
get_footer();
