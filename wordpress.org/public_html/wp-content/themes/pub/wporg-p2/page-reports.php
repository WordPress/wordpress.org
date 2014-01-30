<?php
/**
 * For the /reports/ page
 */
?>
<?php get_header(); ?>

<style>
#main ul#postlist li {
	border: 0;
}
#main h4 {
	display: none;
}
#main .postcontent h4 {
	display: block;
}
.report-group h3 {
	font-weight: bold;
}
#main .report-group h4 {
	font-size: 1.2em;
}
#main .report-group h4 a {
	font-weight: normal;
}
.report {
	float: left;
	display: inline-block;
	width: 220px;
	margin-right: 15px;
}
.wide .report, .report.wide {
	width: 335px;
}
.narrow .report, .report.narrow {
	width: 130px;
	margin-right: 10px;
}
.report h4 {
	margin-bottom: 8px;
}
.report p {
	margin-top: 0;
}
.report p a {
	border: 0;
}
.report-group {
	clear: both;
	overflow: auto;
	margin-bottom: 30px;
}
.reports h3 {
	margin: 0 0 10px;
}
</style>
<script src="https://core.trac.wordpress.org/chrome/common/js/jquery.js"></script>
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
