<?php
gp_title( __( 'Translation status overview &lt; GlotPress' ) );

$breadcrumb   = array();
$breadcrumb[] = gp_link_get( '/', __( 'Locales' ) );
$breadcrumb[] = __( 'Translation status overview' );
gp_breadcrumb( $breadcrumb );
gp_tmpl_header();

function get_rgb_for_percent( $percent ) {
	// This function does 100% = Green, < 100% = Red~Yellow
	if ( $percent == 100 ) {
		return 'rgb(0,153,0)';
	}
	$min = 0;
	$max = 153;
	$num = floor( $min + ( ($max - $min) * ( $percent/100 ) ) );
	return "rgb(153,$num,0)";
}

?>
<div class="table">
	<style>
		<?php /* Temporary styling */ ?>
		table.table {
			text-align: center;
		}
		table.table thead th {
			text-align: center;
			font-weight: bold;
		}
		table.table .none {
			color: #ccc;
		}
		table.table tbody th {
			text-align: left;
		}
	</style>
	<table class="table">
		<thead>
			<tr>
				<th><?php _e( 'Language' ); ?></th>
				<?php foreach ( $projects as $slug => $project ) : ?>
					<th><?php echo esc_html( str_replace( 'WordPress.org ', '', $project->name ) ); ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $translation_locale_complete as $locale_slug => $total_complete ) :
				$gp_locale = GP_Locales::by_slug( $locale_slug );
				// Variants (de/formal for example) don't have GP_Locales in this context
			?>
				<tr>
					<th title="<?php echo esc_attr( $gp_locale->english_name ?: $locale_slug ); ?>"><?php echo esc_html( $gp_locale->wp_locale ?: $locale_slug ); ?></th>
					<?php foreach ( $projects as $slug => $project ) :
						if ( isset( $translation_locale_statuses[ $locale_slug ][ $project->path ] ) ) {
							$percent = $translation_locale_statuses[ $locale_slug ][ $project->path ];
							$percent = '<span style="color:' . get_rgb_for_percent( $percent ) . '">' . $percent . '%</span>';

						} else {
							$percent = '<span class="none">&mdash;</span>';
						}
					?>
						<td><?php echo $percent; ?></td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

</div>

<?php
gp_tmpl_footer();
