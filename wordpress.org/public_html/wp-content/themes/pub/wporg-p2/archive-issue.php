<?php

add_action('pre_option_p2_hide_sidebar', '__return_true');
remove_action( 'wp_head', 'p2_hidden_sidebar_css' );

get_header();
?>
<style>
.sleeve_main { margin-left: 0; margin-right: 0; }
#wrapper { background: transparent; }
</style>

<script type='text/javascript'>
jQuery(document).ready(function($){
	$('.issue-toggle').click(function() {
		issuetext = $(this).closest('tr').next('.issue-text');
		if ( issuetext.css( 'display' ) == 'none' ) {
			$(this).removeClass( "dashicons-arrow-down" ).addClass( "dashicons-arrow-up" );
			issuetext.show();
		} else {
			$(this).removeClass( "dashicons-arrow-up" ).addClass( "dashicons-arrow-down" );
			issuetext.hide();
		}
		return false;
	});
});
</script>

<div class="sleeve_main">
	<div id="main">
		<?php issue_pagenav(); ?>
		<table id="issuelist">
			<tr class="issuehead">
			<th class="issue-report">Report by</th>
			<th class="issue-date">Date</th>
			<th class="issue-type">Issue</th>
			<th class="issue-status">Status</th>
			<th class="issue-link">Link</th>
			<th class="issue-who">Assignee</th>
			<th class="issue-action">Actions</th>
			</tr>

		<?php if ( have_posts() ) : ?>

			<?php
			while ( have_posts() ) : the_post();
				issue_table_row();
			endwhile;
			?>
		<?php endif; ?>
		</table>
		<?php issue_pagenav(); ?>
		<div style="clear:both;"></div>
	</div> <!-- main -->

</div> <!-- sleeve -->

<?php get_footer(); ?>


