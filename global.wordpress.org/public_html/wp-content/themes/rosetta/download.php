<?php
/*
Template Name: Releases page
*/

get_header();
$releases = $rosetta->rosetta->get_releases_breakdown();
?>
	<div class="wrapper">
	<div class="section releases">
<?php
	if ( ! empty( $releases ) ):
		if ( isset( $releases['latest'] ) ):
			release_row(null, null, null, true);
?>
			<h3 id="latest"><?php _e('Latest release', 'rosetta'); ?></h3>
			<table class="releases latest">
				<?php echo release_row( $releases['latest'], 'alt' ); ?>
			</table>
<?php
		endif;
		if ( ! empty( $releases['branches'] ) ):
?>
			<a name="older" id="older"></a>
<?php
		foreach ( $releases['branches'] as $branch => $branch_rels ):
			release_row( null, null, null, true );
?>
			<h3><?php printf( __('%s Branch', 'rosetta'), $branch );?></h3>
			<table class="releases">
<?php
	foreach ( $branch_rels as $release ):
?>
				<?php release_row( $release, 'alt' );?>
<?php
	endforeach;
?>
			</table>
<?php
		endforeach;
		endif; # any branches
		if ( ! empty( $releases['betas'] ) ):
?>
			<h3 id="betas"><?php _e('Beta &amp; RC releases', 'rosetta'); ?></h3>
			<table id="beta" class="releases">
<?php
	release_row( null, null, null, true );
	foreach ( $releases['betas'] as $release ):
?>
				<?php release_row( $release, 'alt', 'beta-first' ); ?>
<?php
	endforeach;
?>
			</table>

<?php
		endif; # any betas
	else: # no releases
?>
	<p><?php _e('There are no releases'); ?></p>
<?php endif; # if releases?>
	</div>
	</div>
<?php get_footer(); ?>
