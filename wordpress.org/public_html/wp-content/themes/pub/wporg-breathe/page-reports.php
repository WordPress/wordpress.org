<?php
/**
 * For the /reports/ page
 */

if ( isset( $_GET['from-trac'] ) ) {
	send_origin_headers();
	while ( have_posts() ) {
		the_post();
		get_template_part( 'content', get_post_format() );
	}
	return;
}
?>

<?php get_header(); ?>

<style>
/* changes to P2 */
#primary ul#postlist li {
	border: 0;
}
#primary header {
	display: none;
}
#primary article h4 {
	display: block;
}
#primary article .trac-only {
	display: none;
}
#primary .ticket-reports .report {
	margin-right: 15px;
}
.ticket-reports .very-narrow {
	margin-right: -10px;
}
#primary .ticket-reports .narrow .report {
	margin-right: 40px;
}
</style>
<script>
jQuery(document).ready( function($) {
	$( '.tickets-by-topic' ).on( 'change', function() {
		var topic = $(this).val();
		if ( ! topic ) {
			return;
		}
		window.location.href = $(this).data( 'location' ) + topic;
		return false;
	});
});
</script>

<div id="primary" class="content-area">
	<div class="site-content">
	<div role="main">
		<h2><?php the_title(); ?></h2>

		<ul id="postlist">
		<?php if ( have_posts() ) : ?>

			<?php while ( have_posts() ) : the_post(); ?>
				<?php get_template_part( 'content', get_post_format() ); ?>
			<?php endwhile; ?>

		<?php endif; ?>
		</ul>

		</div>
		</div><!-- #content -->

	</div><!-- #primary -->
	<div id="primary-modal"></div>

	<!-- A fake o2 content area -->
	<div style="display: none;"><div id="content"></div></div>

<?php get_sidebar(); ?>
<?php get_footer(); ?>
