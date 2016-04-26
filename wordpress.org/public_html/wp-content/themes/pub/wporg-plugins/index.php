<?php
namespace WordPressdotorg\Plugin_Directory\Theme;

get_header();
?>

<?php get_template_part( 'filter-bar' ); ?>

<div class="wrapper">
	<div class="col-12" itemscope itemtype="http://schema.org/SoftwareApplication">
		<?php get_template_part( 'view-intro' ); ?>

		<div class="plugin-group">
		
		<?php
			if ( have_posts() ) {
				while ( have_posts() ) {
					the_post();
					get_template_part( 'plugin-card' );
				}
			} else {
				echo '<p class="no-plugin-results">No plugins match your request.</p>';
			}
		?>

		</div>

	</div>
</div>

<br class="clear" />
<?php
get_footer();
