<?php
namespace WordPressdotorg\Plugin_Directory\Theme;

get_header();
?>

<?php the_post(); ?>

<?php get_template_part( 'filter-bar' ); ?>

<div class="wrapper">
	<div class="col-12" itemscope itemtype="http://schema.org/SoftwareApplication">
		<h2><?php the_title(); ?></h2>
		<?php the_content(); ?>
	</div>
</div>

<br class="clear" />
<?php
get_footer();
