<?php
global $rosetta;
$latest_release = $rosetta->rosetta->get_latest_release();
if ( false !== $latest_release ) :
	?>
	<p class="download-meta">
		<a class="button download-button button-large button-large" href="<?php echo $latest_release['zip_url']; ?>" role="button">
			<strong><?php
				echo apply_filters( 'no_orphans',
					sprintf(
						__( 'Download WordPress %s', 'rosetta' ),
						$latest_release['version']
					)
				);
			?></strong>
		</a>
		<?php /* translators: %s: File size in megabytes. */ ?>
		<span><?php printf( esc_html__( '.zip &mdash; %s MB', 'rosetta' ), esc_html( $latest_release['zip_size_mb'] ) ); ?></span>
	</p>

	<p class="download-tar">
		<a href="<?php echo $latest_release['targz_url']; ?>"><?php printf(
			/* translators: %s: File size in megabytes. */
			esc_html__( 'Download .tar.gz &mdash; %s MB', 'rosetta' ),
			$latest_release['tar_size_mb'] );
		?></a>
	</p>
	<?php
endif;
?>

<h3><?php esc_html_e( 'Resources', 'rosetta' ); ?></h3>

<p><?php esc_html_e( 'For help with installing or using WordPress, consult our documentation in your language.', 'rosetta' ); ?></p>

<?php
if ( has_nav_menu( 'rosetta_resources' ) ) {
	wp_nav_menu( [
		'theme_location' => 'rosetta_resources',
		'container'      => false,
		'depth'          => 1,
		'fallback_cb'    => false,
	] );
} else {
	?>
	<ul>
		<?php wp_list_bookmarks( 'categorize=0&category_before=&category_after=&title_li=&' ); ?>
	</ul>
	<?php
}
