<?php
$edit_link = gp_link_project_edit_get( $project, __( '(edit)' ) );
$table_headings = array(
	'locale'        => __( 'Locale' ),
	'dev'           => __( 'Development' ),
	'dev-readme'    => __( 'Development Readme' ),
	'stable'        => __( 'Stable' ),
	'stable-readme' => __( 'Stable Readme' ),
	'waiting'       => __( 'Waiting' ),
);

gp_title( sprintf( __( '%s &lt; GlotPress' ), esc_html( $project->name ) ) );
gp_breadcrumb_project( $project );

wp_enqueue_script( 'common' );
wp_enqueue_script( 'tablesorter' );

gp_tmpl_header();
?>

<div class="project-header">
	<p class="project-description"><?php echo apply_filters( 'project_description', $project->description, $project ); ?></p>

	<div class="project-box">
		<div class="project-box-header">
			<div class="project-icon">
				<?php echo $project->icon; ?>
			</div>

			<ul class="project-meta">
				<li class="project-name"><?php echo $project->name; ?> <?php echo $edit_link; ?></li>
			</ul>
		</div>
	</div>
</div>

<?php if ( $has_error ) : ?>
<div class="wporg-notice wporg-notice-warning">
	<p>This plugin is not <a href="https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/">properly prepared for localization</a>. If you would like to translate this plugin, <a href="<?php echo esc_url( 'https://wordpress.org/support/plugin/' . $project->slug ); ?>">please contact the author.</a></p>
</div>
<?php endif; ?>

<div class="stats-table">
	<table id="stats-table" class="table">
		<thead>
			<tr>
				<?php foreach ( $table_headings as $heading ) : ?>
				<th class="title"><?php echo $heading; ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $translation_locale_complete as $locale_slug => $total_complete ) :
				$gp_locale = GP_Locales::by_slug( $locale_slug );
				$set_slug  = 'default';

				// Variants (de/formal for example) don't have GP_Locales in this context
				if ( ! $gp_locale && ( list( $base_locale_slug, $set_slug ) = explode( '/', $locale_slug ) ) ) :
					$gp_locale = clone GP_Locales::by_slug( $base_locale_slug );

					// Just append it for now..
					$gp_locale->wp_locale .= '/' . $set_slug;
					$gp_locale->english_name .=  ' (' . ucfirst( $set_slug ) . ')';
				endif;

				if ( ! $gp_locale || ! $gp_locale->wp_locale ) :
					continue;
				endif;
			?>
				<tr>
					<th title="<?php echo esc_attr( $gp_locale->wp_locale ); ?>">
						<a href="<?php echo gp_url( gp_url_join( 'locale', $gp_locale->slug, $set_slug, $project->path ) ); ?>">
							<?php echo esc_html( $gp_locale->english_name ); ?>
						</a>
					</th>
					<?php
						if ( $translation_locale_statuses[ $locale_slug ] ) :
							foreach ( array( 'dev', 'dev-readme', 'stable', 'stable-readme', 'waiting' ) as $subproject_slug ) :
								if ( isset( $translation_locale_statuses[ $locale_slug ][ $subproject_slug ] ) ) :
									$percent = $translation_locale_statuses[ $locale_slug ][ $subproject_slug ];

									if ( 'waiting' === $subproject_slug ) :
										// Color code it on -0~500 waiting strings
										$percent_class = 100 - min( (int) ( $percent / 50 ) * 10, 100 );

										// It's only 100 if it has 0 strings.
										if ( 100 == $percent_class && $percent ) :
											$percent_class = 90;
										endif;

										$link_url  = gp_url( gp_url_join( 'locale', $locale_slug, $project->path ) );
										$link_text = number_format( $percent );
									else :
										$percent_class = (int) ( $percent / 10 ) * 10;
										$link_url  = gp_url_project( $project->path, gp_url_join( $subproject_slug, $locale_slug ) );
										$link_text = "$percent%";

									endif;

									echo '<td data-column-title="' . esc_attr( $table_headings[ $subproject_slug ] ) . '" data-sort-value="' . esc_attr( $percent ) . '" class="percent' . $percent_class .'">'. gp_link_get( $link_url, $link_text ) . '</td>';
								else :
									echo '<td class="none" data-column-title="" data-sort-value="-1">&mdash;</td>';
								endif;
							endforeach;
						else :
							echo '<td class="none" data-sort-value="-1">&mdash;</td>';
							echo '<td class="none" data-sort-value="-1">&mdash;</td>';
							echo '<td class="none" data-sort-value="-1">&mdash;</td>';
							echo '<td class="none" data-sort-value="-1">&mdash;</td>';
							echo '<td class="none" data-sort-value="-1">&mdash;</td>';
						endif;
					?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<script type="text/javascript">
jQuery( function( $ ) {
	$( '#stats-table' ).tablesorter( {
		textExtraction: function( node ) {
			var cellValue = $( node ).text(),
				sortValue = $( node ).data( 'sortValue' );

			return ( undefined !== sortValue ) ? sortValue : cellValue;
		}
	});
});
</script>

<?php gp_tmpl_footer();
