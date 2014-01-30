<?php
/**
 * For the /reports/ page
 */

if ( isset( $_GET['from-trac'] ) ) {
	send_origin_headers();
	while ( have_posts() ) {
		the_post();
		p2_load_entry();
	}
	return;
}
?>

<?php get_header(); ?>

<style>
/* changes to P2 */
#main ul#postlist li {
	border: 0;
}
#main h4 {
	display: none;
}
#main .postcontent h4 {
	display: block;
}
#main .postcontent .trac-only {
	display: none;
}
.ticket-reports .report-group h3 {
	font-weight: bold;
}
#main .report-group h4,
.ticket-reports .report-group h4 {
	font-size: 1.2em;
}
#main .report-group h4 a,
.ticket-reports .report-group h4 a {
	font-weight: normal;
}
.ticket-reports .report {
	float: left;
	display: inline-block;
	width: 220px;
	margin-right: 15px;
}
.ticket-reports .wide .report,
.ticket-reports .report.wide {
	width: 335px;
}
.ticket-reports .narrow .report,
.ticket-reports .report.narrow {
	width: 130px;
	margin-right: 10px;
}
.ticket-reports .report h4 {
	margin-bottom: 8px;
}
.ticket-reports .report p {
	margin-top: 0;
}
.ticket-reports .report p a {
	border: 0;
}
.ticket-reports .report-group {
	clear: both;
	overflow: auto;
	margin-bottom: 30px;
}
.ticket-reports .reports h3 {
	margin: 0 0 10px;
}
</style>
<script>
$(document).on( 'ready', function() {
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

<div class="sleeve_main">

	<div id="main">
		<h2><?php the_title(); ?></h2>

		<ul id="postlist">
		<?php if ( have_posts() ) : ?>

			<?php while ( have_posts() ) : the_post(); ?>
				<?php p2_load_entry(); ?>
			<?php endwhile; ?>

		<?php endif; ?>
		</ul>

	</div> <!-- main -->

</div> <!-- sleeve -->

<?php get_footer(); ?>
