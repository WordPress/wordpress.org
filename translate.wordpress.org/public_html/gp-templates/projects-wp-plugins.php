<?php
$edit_link = gp_link_project_edit_get( $project, __( '(edit)' ) );

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

<div class="stats-table">
	<table id="stats-table" class="table">
		<thead>
			<tr>
				<th class="title"><?php _e( 'Locale' ); ?></th>
				<th class="title"><?php _e( 'Development' ); ?></th>
				<th class="title"><?php _e( 'Development Readme' ); ?></th>
				<th class="title"><?php _e( 'Stable' ); ?></th>
				<th class="title"><?php _e( 'Stable Readme' ); ?></th>
				<th class="title"><?php _e( 'Waiting' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $translation_locale_statuses as $locale_slug => $set ) :
				$gp_locale = GP_Locales::by_slug( $locale_slug );
				$set_slug  = 'default';

				// Variants (de/formal for example) don't have GP_Locales in this context
				if ( ! $gp_locale && ( list( $base_locale_slug, $set_slug ) = explode( '/', $locale_slug ) ) ) :
					$gp_locale = clone GP_Locales::by_slug( $base_locale_slug );

					// Just append it for now..
					$gp_locale->wp_locale .= '/' . $set_slug;
				endif;

				if ( ! $gp_locale || ! $gp_locale->wp_locale ) :
					continue;
				endif;
			?>
				<tr>
					<th title="<?php echo esc_attr( $gp_locale->wp_locale ); ?>">
						<a href="<?php echo gp_url( gp_url_join( 'locale', $gp_locale->slug, $set_slug ) ); ?>">
							<?php echo esc_html( $gp_locale->english_name ); ?>
						</a>
					</th>
					<?php
						if ( $set ) :
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

										$link_url  = gp_url( gp_url_join( 'locale', $locale_slug, $set_slug, $project->path ) );
										$link_text = number_format( $percent );
									else :
										$percent_class = (int) ( $percent / 10 ) * 10;
										$link_url  = gp_url_join( $project->slug, $subproject_slug, $locale_slug, $set_slug );
										$link_text = "$percent%";

									endif;

									echo '<td data-sort-value="' . esc_attr( $percent ) . '" class="percent' . $percent_class .'">'. gp_link_get( $link_url, $link_text ) . '</td>';
								else :
									echo '<td class="none" data-sort-value="-1">&mdash;</td>';
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
