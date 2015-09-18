<?php
gp_title( __( 'Translation status overview &lt; GlotPress' ) );

$breadcrumb   = array();
$breadcrumb[] = gp_link_get( '/', __( 'Locales' ) );
$breadcrumb[] = __( 'Translation status overview' );
gp_breadcrumb( $breadcrumb );
gp_tmpl_header();


?>
<div class="stats-table">
	<table class="table">
		<thead>
			<tr>
				<th class="title"><?php _e( 'Locale' ); ?></th>
				<?php foreach ( $projects as $slug => $project ) : ?>
					<th><?php
						$namereplace = array( 'WordPress.org ', 'WordPress for ', 'WordPress ', 'ectory' );
						echo esc_html( str_replace( $namereplace, '', $project->name ) );
					?></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $translation_locale_complete as $locale_slug => $total_complete ) :
				$gp_locale = GP_Locales::by_slug( $locale_slug );
				$set_slug = 'default';
				// Variants (de/formal for example) don't have GP_Locales in this context
				if ( ! $gp_locale && ( list( $base_locale_slug, $set_slug ) = explode( '/', $locale_slug ) ) ) {
					$gp_locale = clone GP_Locales::by_slug( $base_locale_slug );
					// Just append it for now..
					$gp_locale->wp_locale .= '/' . $set_slug;
				}
				if ( ! $gp_locale || ! $gp_locale->wp_locale ) {
					continue;
				}
			?>
				<tr>
					<th title="<?php echo esc_attr( $gp_locale->english_name ); ?>">
						<a href="<?php echo gp_url_join( 'locale', $gp_locale->slug, $set_slug ); ?>">
							<?php echo esc_html( $gp_locale->wp_locale ); ?> 
						</a>
					</th>
					<?php
					foreach ( $projects as $slug => $project ) {
						$projecturl = gp_url_join( 'locale', $gp_locale->slug, $set_slug, $project->path );

						if ( isset( $translation_locale_statuses[ $locale_slug ][ $project->path ] ) ) {
							$percent = $translation_locale_statuses[ $locale_slug ][ $project->path ];

							if ( 'waiting' === $project->path ) {
								// Color code it on -0~500 waiting strings
								$percent_class = 100-min( (int) ( $percent / 50 ) * 10, 100 );
								// It's only 100 if it has 0 strings.
								if ( 100 == $percent_class && $percent ) {
									$percent_class = 90;
								}
								$percent_class = 'percent' . $percent_class;
								echo '<td class="' . $percent_class .'"><a href="' . $projecturl . '">' . number_format( $percent ) . '</a></td>';
							} else {
								$percent_class = 'percent' . (int) ( $percent / 10 ) * 10;
								echo '<td class="' . $percent_class .'"><a href="' . $projecturl . '">' . $percent . '%</a></td>';
							}

						} else {
							echo '<td class="none">&mdash;</td>';
						}
						
						
					}
					?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

</div>

<?php
gp_tmpl_footer();
