<?php
gp_title( __( 'Translation status overview &lt; GlotPress' ) );
gp_enqueue_script( 'tablesorter' );

$breadcrumb   = array();
$breadcrumb[] = gp_link_get( '/', __( 'Locales' ) );
$breadcrumb[] = __( 'Translation status overview' );
gp_breadcrumb( $breadcrumb );
gp_tmpl_header();

?>
<div class="stats-table">
	<style>
		.gp-content {
			max-width: 95%;
		}
	</style>
	<table id="stats-table" class="table">
		<thead>
			<tr>
				<th class="col-locale-code"><?php _e( 'Locale' ); ?></th>
				<?php foreach ( $projects as $slug => $project ) :
					$name = str_replace( array( 'WordPress.org ', 'WordPress for ', 'WordPress ', 'ectory', ' - Development' ), '', $project->name );
					if ( $slug == 'wp-plugins' || $slug == 'wp-themes' ) {
						$name = "Waiting $name";
					}
					?>
					<th class="col-<?php echo esc_attr( sanitize_title( $name ) ); ?>"><?php echo esc_html( $name ); ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $translation_locale_complete as $locale_slug => $total_complete ) :
				$gp_locale = GP_Locales::by_slug( $locale_slug );

				if ( ! $gp_locale || ! $gp_locale->wp_locale ) {
					continue;
				}

				list( $locale, $set_slug ) = array_merge( explode( '/', $locale_slug ), [ 'default' ] );
				?>
				<tr>
					<th title="<?php echo esc_attr( $gp_locale->english_name ); ?>">
						<a href="<?php echo esc_url( gp_url( gp_url_join( 'locale', $locale, $set_slug ) ) ); ?>">
							<?php echo esc_html( $gp_locale->wp_locale ); ?>
						</a>
					</th>
					<?php
					foreach ( $projects as $slug => $project ) {
						$projecturl = gp_url( gp_url_join( 'locale', $locale, $set_slug, $project->path ) );
						$project_name = str_replace( array( 'WordPress.org ', 'WordPress for ', 'WordPress ', 'ectory' ), '', $project->name );

						if ( isset( $translation_locale_statuses[ $locale_slug ][ $project->path ] ) ) {
							$percent = $translation_locale_statuses[ $locale_slug ][ $project->path ];

							if ( 'waiting' === $project->path || 'wp-plugins' === $project->path || 'wp-themes' === $project->path ) {
								$project_link_title = '';
								if ( 'wp-plugins' === $project->path || 'wp-themes' === $project->path ) {
									$project_link_title = "Last Updated {$project->cache_last_updated}";

									// Filter Plugins/Themes to Waiting (Most first) - Relying upon these being the last items.
									$projecturl = add_query_arg( 'filter', 'strings-waiting-and-fuzzy', $projecturl );
								}

								// Color code it on -0~500 waiting strings
								$percent_class = 100-min( (int) ( $percent / 50 ) * 10, 100 );
								// It's only 100 if it has 0 strings.
								if ( 100 == $percent_class && $percent ) {
									$percent_class = 90;
								}
								$percent_class = 'percent' . $percent_class;
								echo '<td data-column-title="' . esc_attr( $project_name ) . '" data-sort-value="'. esc_attr( $percent ) . '" class="' . $percent_class .'"><a href="' . esc_url( $projecturl ) . '" title="' . esc_attr( $project_link_title ) . '">' . number_format( $percent ) . '</a></td>';
							} else {
								$percent_class = 'percent' . (int) ( $percent / 10 ) * 10;
								echo '<td data-column-title="' . esc_attr( $project_name ) . '" data-sort-value="' . esc_attr( $percent ) . '" class="' . $percent_class .'"><a href="' . esc_url( $projecturl ) . '">' . $percent . '%</a></td>';
							}

						} else {
							echo '<td class="none" data-column-title="" data-sort-value="-1">&mdash;</td>';
						}
					}
					?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<script type="text/javascript">
jQuery( document ).ready( function( $ ) {
	$( '#stats-table' ).tablesorter( {
		theme: 'wporg-translate',
		textExtraction: function( node ) {
			var cellValue = $( node ).text(),
				sortValue = $( node ).data( 'sortValue' );

			return ( undefined !== sortValue ) ? sortValue : cellValue;
		}
	});
});
</script>
<?php
gp_tmpl_footer();
