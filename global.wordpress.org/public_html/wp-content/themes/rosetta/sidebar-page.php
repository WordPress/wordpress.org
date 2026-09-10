<?php
global $rosetta;
$latest_release = $rosetta->rosetta->get_latest_release();
if ( false !== $latest_release ) :
	?>
	<p class="download-meta">
		<a class="button download-button button-large button-large" href="<?php echo esc_url( $latest_release['zip_url'] ); ?>" role="button">
			<strong><?php
				echo wp_kses_post(
					apply_filters(
						'no_orphans',
						sprintf(
							/* translators: %s: WordPress version. */
							esc_html__( 'Download WordPress %s', 'rosetta' ),
							esc_html( $latest_release['version'] )
						)
					)
				);
			?></strong>
		</a>
		<?php /* translators: %s: File size in megabytes. */ ?>
		<span><?php printf( esc_html__( '.zip &mdash; %s MB', 'rosetta' ), esc_html( $latest_release['zip_size_mb'] ) ); ?></span>
	</p>

	<p class="download-tar">
		<?php /* translators: %s: File size in megabytes. */ ?>
		<a href="<?php echo esc_url( $latest_release['targz_url'] ); ?>"><?php printf( esc_html__( 'Download .tar.gz &mdash; %s MB', 'rosetta' ), esc_html( $latest_release['tar_size_mb'] ) ); ?></a>
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
