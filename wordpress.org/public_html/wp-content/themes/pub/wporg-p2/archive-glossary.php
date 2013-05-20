<?php
/**
 * Glossary content
 */
?>
<?php get_header(); ?>

<div class="sleeve_main">

	<div id="main">
		<h2><?php post_type_archive_title(); ?></h2>

		<?php do_action( 'wporg_handbook_glossary' ); ?>

	</div> <!-- main -->

</div> <!-- sleeve -->

<?php get_footer(); ?>
