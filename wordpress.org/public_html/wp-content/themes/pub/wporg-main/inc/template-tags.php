<?php
/**
 * Custom template tags
 *
 * @package WordPressdotorg\MainTheme
 */

// phpcs:disable WordPress.VIP.RestrictedFunctions.get_page_by_path_get_page_by_path

namespace WordPressdotorg\MainTheme;

/**
 * Displays table col tags.
 */
function release_cols() {
	?>
	<col width="15%" />
	<col width="25%" />
	<col width="15%" />
	<col width="15%" />
	<?php if ( ! defined( 'IS_ROSETTA_NETWORK' ) || ! IS_ROSETTA_NETWORK ) : ?>
		<col width="15%" />
	<?php endif; ?>
	<?php
}

/**
 * Displays a table row with release information.
 *
 * @param array $release Release to be processed.
 */
function release_row( $release ) {
	?>
	<tr>
		<td><?php echo esc_html( $release['version'] ); ?></td>
		<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), $release['builton'] ) ); ?></td>
		<td><a href="<?php echo esc_url( $release['zip_url'] ); ?>">zip</a><br>
			<small>(<a href="<?php echo esc_url( $release['zip_url'] . '.md5' ); ?>">md5</a> | <a href="<?php echo esc_url( $release['zip_url'] . '.sha1' ); ?>">sha1</a>)</small>
		</td>
		<td>
			<?php if ( $release['targz_url'] ) : ?>
				<a href="<?php echo esc_url( $release['targz_url'] ); ?>">tar.gz</a><br>
				<small>(<a href="<?php echo esc_url( $release['targz_url'] . '.md5' ); ?>">md5</a> | <a href="<?php echo esc_url( $release['targz_url'] . '.sha1' ); ?>">sha1</a>)</small>
			<?php endif; ?>
		</td>
		<?php if ( ! defined( 'IS_ROSETTA_NETWORK' ) || ! IS_ROSETTA_NETWORK ) : ?>
			<td>
				<?php if ( ! empty( $release['iis_url'] ) ) : ?>
					<a href="<?php echo esc_url( $release['iis_url'] ); ?>">IIS zip</a><br>
					<small>(<a href="<?php echo esc_url( $release['iis_url'] . '.md5' ); ?>">md5</a> | <a href="<?php echo esc_url( $release['iis_url'] . '.sha1' ); ?>">sha1</a>)</small>
				<?php endif; ?>
			</td>
		<?php endif; ?>
	</tr>
	<?php
}

/**
 * Retrieve the localised downloads link.
 *
 * Uses the 'txt-download' page if exists, falling back to the 'releases' page for older sites,
 * and finally, the english downloads page.
 */
function get_downloads_url() {
	static $downloads_url = null;

	if ( is_null( $downloads_url ) ) {
		$downloads_url  = 'https://wordpress.org/downloads/';
		$downloads_page = get_page_by_path( 'txt-download' );

		if ( ! $downloads_page ) {
			$downloads_page = get_page_by_path( 'download' );
		}

		if ( ! $downloads_page ) {
			$downloads_page = get_page_by_path( 'releases' );
		}

		if ( $downloads_page ) {
			$downloads_url = get_permalink( $downloads_page );
		}
	}

	return $downloads_url;
}
