<?php
gp_title( __( 'Translation status overview &lt; GlotPress' ) );
gp_enqueue_script( 'tablesorter' );

$breadcrumb   = array();
$breadcrumb[] = gp_link_get( '/', __( 'Locales' ) );
$breadcrumb[] = gp_link_get( gp_url_join( '/locale', $locale_path ), esc_html( $gp_locale->english_name ) );
$breadcrumb[] = trim( ucwords( $view ), 's' ) . ' translation status overview';
gp_breadcrumb( $breadcrumb );
gp_tmpl_header();

$columns = array(
	'all'          => __( 'Translated Percent' ),
	'current'      => __( 'Translated' ),
	'untranslated' => __( 'Untranslated' ),
	'fuzzy'        => __( 'Fuzzy' ),
	'waiting'      => __( 'Waiting' ),
);
$main_column_title = trim( ucwords( $view ), 's' );

?>
<div class="stats-table">
	<table id="stats-table" class="table">
		<thead>
			<tr>
				<th><span class="with-tooltip" aria-label="Sorted by active installations"><?php echo $main_column_title; ?></span></th>
				<?php
					foreach ( $columns as $title ) {
						printf( "<th>%s</th>", $title );
					}

				?>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $items as $slug => $details ) {
				$project_link = gp_url( gp_url_join( 'locale', $locale_path, $details->project->path ) );
				// Themes get a shortcut..
				if ( 'themes' == $view ) {
					$project_link = gp_url( gp_url_join( 'projects', $details->project->path, $locale_path ) );
				}

				$project_overview_link = gp_url( gp_url_join( 'projects', $details->project->path ) );

				echo '<tr>';
				printf(
					'<th title="%s" data-column-title="%s" data-sort-value="%s"><a href="%s">%s</a></th>',
					esc_attr( sprintf( __( "%s+ Active Installations" ), number_format_i18n( $details->installs ) ) ),
					esc_attr( $main_column_title ),
					$details->installs,
					$project_overview_link,
					$details->project->name
				);

				foreach ( $columns as $field => $title ) {
					$sort_value = $stat_value = ( $details->stats->{$field} ?? 0 );
					$percent = $stat_value / $details->stats->all * 100;
					$link = $project_link;
					$cell_text = number_format_i18n( $stat_value );

					if ( in_array( $field, [ 'fuzzy', 'untranslated', 'waiting' ] ) ) {
						$percent = 100 - $percent;
						$link    = add_query_arg( 'filters[status]', $field, $project_link );
					} elseif ( 'all' == $field ) {
						$percent    = ($details->stats->current ?? 0) / $details->stats->all * 100;
						$sort_value = $percent;
						$cell_text  = ( $percent > 50 ? floor( $percent ) : ceil( $percent ) ) . '%';
					}

					$percent_class = 'percent' . (int) ( $percent / 10 ) * 10;

					printf( '<td class="%s" data-column-title="%s" data-sort-value="%s"><a href="%s">%s</a></td>',
						$percent_class,
						$title,
						$sort_value,
						$link,
						$cell_text
					);
				}
				echo '</tr>';
			}
			?>
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
