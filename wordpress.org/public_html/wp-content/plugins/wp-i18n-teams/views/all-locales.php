<div class="translators-info show-all">
	<p class="locale-filters">
	<?php
		_e( 'Releases:', 'worg' );

		$release_statuses = array(
			'all' => _n_noop( '%s locale.', '%s locales.', 'wporg' ),
			'latest' => _n_noop( '%s locale up to date.', '%s locales up to date.', 'wporg' ),
			'minor-behind' => _n_noop( '%s locale behind by minor versions.', '%s locales behind by minor versions.', 'wprog' ),
			'major-behind-one' => _n_noop( '%s locale behind one major version.', '%s locales behind by one major version.', 'wporg' ),
			'major-behind-many' => _n_noop( '%s locale behind more than one major version.', '%s locales behind more than one major version.', 'wporg' ),
			'no-releases' => _n_noop( '%s locale has a site, no release.', '%s locales have a site but never released.', 'wporg' ),
			'no-site' => _n_noop( '%s locale doesn&#8217;t have a site.', '%s locales don&#8127;t have a site.', 'wporg' ),
		);

		foreach ( $release_statuses as $status => $nooped_plural ) {
			$string = translate_nooped_plural( $nooped_plural, $locale_data['status_counts'][ $status ] );
			$string = sprintf( $string, sprintf( '<strong class="i18n-label %s">%s</strong>', $status, $locale_data['status_counts'][ $status ] ) );
			printf( ' <a href="#" class="i18n-filter" data-filter="%s">%s</a>', $status, $string );
		}
	?>
	</p>

	<p class="locale-filters">
	<?php
		_e( 'Translations:', 'worg' );

		$translation_statuses = array(
			'all' => _n_noop( '%s locale.', '%s locales.', 'wporg' ),
			'translated-100' => _n_noop( '%s locale at 100%%.', '%s locales at 100%%.', 'wporg' ),
			'translated-95' => _n_noop( '%s locale has more than 95%%.', '%s locales have more than 95%%.', 'wporg' ),
			'translated-90' => _n_noop( '%s locale has more than 90%%.', '%s locales have more than 90%%.', 'wporg' ),
			'translated-50' => _n_noop( '%s locale has more than 50%%.', '%s locales have more than 50%%.', 'wporg' ),
			'translated-50-less' => _n_noop( '%s locale has less than 50%%.', '%s locales have less than 50%%.', 'wporg' ),
		);

		foreach ( $translation_statuses as $status => $nooped_plural ) {
			$string = translate_nooped_plural( $nooped_plural, $locale_data['status_counts'][ $status ] );
			$string = sprintf( $string, sprintf( '<strong class="i18n-label %s">%s</strong>', $status, $locale_data['status_counts'][ $status ] ) );
			printf( ' <a href="#" class="i18n-filter" data-filter="%s">%s</a>', $status, $string );
		}
	?>
	</p>

	<table>
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'Locale', 'wporg' ); ?></th>
				<th><?php _e( 'WP Locale', 'wporg' ); ?></th>
				<th><?php _e( 'Version', 'wporg' ); ?></th>
				<th colspan="2">GlotPress</th>
				<th><!-- intentionally blank --></th>
			</tr>
		</thead>

		<tbody>
			<?php foreach ( $locales as $locale ) : ?>
				<?php
				$classes  = 'locale-version ';
				$classes .= $locale_data[ $locale->wp_locale ]['release_status'] . ' ' . $locale_data[ $locale->wp_locale ]['translation_status'];
				?>
				<tr class="<?php echo trim( $classes ); ?>">
					<td class="no-right-border">
						<?php if ( $locale_data[ $locale->wp_locale ]['rosetta_site_url'] ) : ?>
							<a href="<?php echo esc_url( $locale_data[ $locale->wp_locale ]['rosetta_site_url'] ); ?>">
								<?php echo esc_html( $locale->english_name ); ?>
							</a>
						<?php else : ?>
							<?php echo esc_html( $locale->english_name ); ?>
						<?php endif; ?>
					</td>
					<td class="no-left-border"><?php echo esc_html( $locale->native_name ); ?></td>

					<td><?php echo esc_html( $locale->wp_locale ); ?></td>

					<td>
						<?php
							if ( $locale_data[ $locale->wp_locale ]['rosetta_site_url'] ) {
								if ( $locale_data[ $locale->wp_locale ]['latest_release'] ) {
									echo esc_html( $locale_data[ $locale->wp_locale ]['latest_release'] );
								} else {
									_e( 'None', 'wporg' );
								}
							} else {
								_e( 'No site', 'wporg' );
							}
						?>
					</td>
					<td class="right no-right-border">
						<a href="https://translate.wordpress.org/languages/<?php echo $locale->slug; ?>">
							<?php echo ( isset( $percentages[ $locale->wp_locale ] ) ) ? $percentages[ $locale->wp_locale ] . '%' : '&mdash;'; ?>
						</a>
					</td>
					<td class="no-left-border nowrap">
						<a href="https://translate.wordpress.org/languages/<?php echo $locale->slug; ?>">
							<?php echo $locale->slug; ?>
						</a>
					<td>
						<a href="<?php echo esc_url( add_query_arg( 'locale', $locale->wp_locale ) ); ?>">
							<?php _e( 'Details', 'wporg' ); ?>
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div> <!-- /.translators-info -->
