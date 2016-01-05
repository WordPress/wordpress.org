<?php
/*
Template Name: Releases page
*/

get_header();
$releases = $rosetta->rosetta->get_releases_breakdown();

the_post();
?>
	<div id="headline">
		<div class="wrapper">
			<h2><?php the_title(); ?></h2>
		</div>
	</div>

	<div id="pagebody">
		<div class="wrapper">
			<div class="col-9" role="main">
<?php
	if ( ! empty( $releases ) ):
		if ( isset( $releases['latest'] ) ):
			rosetta_release_row( null, null, null, true );
?>
			<h3 id="latest"><?php _e( 'Latest release', 'rosetta' ); ?></h3>
			<table class="releases latest">
				<?php echo rosetta_release_row( $releases['latest'], 'alt' ); ?>
			</table>
<?php
		endif;
		if ( ! empty( $releases['branches'] ) ):
?>
			<a name="older" id="older"></a>
<?php
		foreach ( $releases['branches'] as $branch => $branch_rels ):
			rosetta_release_row( null, null, null, true );
?>
			<h3><?php printf( __( '%s Branch', 'rosetta' ), $branch );?></h3>
			<table class="releases">
<?php
	foreach ( $branch_rels as $release ):
?>
				<?php rosetta_release_row( $release, 'alt' );?>
<?php
	endforeach;
?>
			</table>
<?php
		endforeach;
		endif; # any branches
		if ( ! empty( $releases['betas'] ) ):
?>
			<h3 id="betas"><?php _e( 'Beta &amp; RC releases', 'rosetta' ); ?></h3>
			<table id="beta" class="releases">
<?php
	rosetta_release_row( null, null, null, true );
	foreach ( $releases['betas'] as $release ):
?>
				<?php rosetta_release_row( $release, 'alt', 'beta-first' ); ?>
<?php
	endforeach;
?>
			</table>

<?php
		endif; # any betas
	else: # no releases
?>
	<p><?php _e( 'There are no releases, yet.', 'rosetta' ); ?></p>
<?php endif; # if releases?>
			</div>
		</div>
	</div>
<?php
get_footer();
