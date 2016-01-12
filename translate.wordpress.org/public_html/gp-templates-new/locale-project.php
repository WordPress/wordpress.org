<?php
gp_title( sprintf( __( 'Projects translated to %s &lt; GlotPress' ),  esc_html( $locale->english_name ) ) );

$breadcrumb   = array();
$breadcrumb[] = gp_link_get( '/', __( 'Locales' ) );
$breadcrumb[] = gp_link_get( gp_url_join( '/locale', $locale_slug, $set_slug ), esc_html( $locale->english_name ) );
$breadcrumb[] = gp_link_get( gp_url_join( '/locale', $locale_slug, $set_slug, $project->path ), esc_html( $project->name ) );
$breadcrumb[] = $sub_project->name;
gp_breadcrumb( $breadcrumb );
gp_tmpl_header();
?>

<div class="project-header">
	<p class="project-description"><?php echo $sub_project->description; ?></p>

	<div class="project-box percent-<?php echo $project_status->percent_complete; ?>">
		<div class="project-box-header">
			<div class="project-icon">
				<?php echo $project_icon; ?>
			</div>

			<ul class="project-meta">
				<li class="project-name"><?php echo $sub_project->name; ?></li>
				<li class="locale-english"><?php echo $locale->english_name; ?></li>
				<li class="locale-native"><?php echo $locale->native_name; ?></li>
				<li class="locale-code">
					<?php
					echo $locale->wp_locale;

					if ( count( $variants ) > 1 ) {
						?>
						<select id="variant-selector" name="variant">
							<?php
							foreach ( $variants as $variant ) {
								$selected =
								printf(
									'<option name="%s" data-project-url="%s"%s>%s</option>',
									$variant,
									esc_url( gp_url_join( '/locale', $locale_slug, $variant, $sub_project->path ) ),
									( $set_slug == $variant ) ? ' selected="selected"' : '',
									ucfirst( $variant )
								);
							}
							?>
						</select>
						<?php
					}
					?>
				</li>
			</ul>

			<div class="project-status">
				<?php echo $project_status->percent_complete . '%'; ?>
			</div>
		</div>

		<div class="project-status-progress percent">
			<div class="percent-complete" style="width:<?php echo $project_status->percent_complete; ?>%;"></div>
		</div>

		<div class="project-box-footer">
			<ul class="projects-dropdown">
				<li><span>All Sub-Projects</span>
					<ul>
						<?php
						// Show the current project if it has strings.
						if ( $sub_project_status->all_count ) {
							printf(
								'<li><a href="%s">%s <span>%s</span></a>',
								gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ) ),
								$sub_project->name,
								$sub_project_status->percent_complete . '%'
							);
						}

						foreach ( $sub_projects as $_sub_project ) {
							$status = $sub_project_statuses[ $_sub_project->id ];

							printf(
								'<li><a href="%s">%s <span>%s</span></a>',
								gp_url_project( $_sub_project->path, gp_url_join( $locale->slug, $set_slug ) ),
								$_sub_project->name,
								$status->percent_complete . '%'
							);
						}
						?>
					</ul>
				</li>
			</ul>
		</div>
	</div>
</div>

<?php
if ( 'wp-plugins' === $project->path && ! in_array( 'dev', $sub_project_slugs ) && ! in_array( 'stable', $sub_project_slugs ) ) {
	?>
	<div class="wporg-notice wporg-notice-warning">
		<p>This plugin is not <a href="https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/">properly prepared for localization</a>. If you would like to translate this plugin, <a href="<?php echo esc_url( 'https://wordpress.org/support/plugin/' . $project->slug ); ?>">please contact the author.</a></p>
	</div>
	<?php
}
?>

<div class="locale-project">
	<table class="locale-sub-projects">
		<thead>
			<tr>
				<th class="header"><?php _e( 'Set / Sub Project' ); ?></th>
				<th><?php _e( 'Translated' ); ?></th>
				<th><?php _e( 'Fuzzy' ); ?></th>
				<th><?php _e( 'Untranslated' ); ?></th>
				<th><?php _e( 'Waiting' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			// Show the current project if it has strings.
			if ( $sub_project_status->all_count ) {
				?>
				<tr>
					<td class="set-name">
						<strong><?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ) ), $sub_project->name ); ?></strong>
						<?php if ( $sub_project_status->percent_complete > 90 ) : ?>
							<span class="sub-project-status percent-90"><?php echo $sub_project_status->percent_complete; ?>%</span>
						<?php else : ?>
							<span class="sub-project-status"><?php echo $sub_project_status->percent_complete; ?>%</span>
						<?php endif; ?>
					</td>
					<td class="stats translated">
						<?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ), array( 'filters[translated]' => 'yes', 'filters[status]' => 'current') ), absint( $sub_project_status->current_count ) ); ?>
					</td>
					<td class="stats fuzzy">
						<?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ), array( 'filters[status]' => 'fuzzy' ) ), absint( $sub_project_status->fuzzy_count ) ); ?>
					</td>
					<td class="stats untranslated">
						<?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ), array( 'filters[status]' => 'untranslated' ) ), absint( $sub_project_status->all_count ) -  absint( $sub_project_status->current_count ) ); ?>
					</td>
					<td class="stats waiting">
						<?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ), array( 'filters[translated]' => 'yes', 'filters[status]' => 'waiting' ) ), absint( $sub_project_status->waiting_count ) ); ?>
					</td>
					</tr>
				</tr>
				<?php
			}
			?>

			<?php
			foreach ( $sub_projects as $sub_project ) {
				$status = $sub_project_statuses[ $sub_project->id ];
				?>
				<tr>
					<td class="set-name">
						<strong><?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ) ), $sub_project->name ); ?></strong>
						<?php if ( $status->percent_complete > 90 ) : ?>
							<span class="sub-project-status percent-90"><?php echo $status->percent_complete; ?>%</span>
						<?php else : ?>
							<span class="sub-project-status"><?php echo $status->percent_complete; ?>%</span>
						<?php endif; ?>
					</td>
					<td class="stats translated">
						<?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ), array( 'filters[translated]' => 'yes', 'filters[status]' => 'current') ), absint( $status->current_count ) ); ?>
					</td>
					<td class="stats fuzzy">
						<?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ), array( 'filters[status]' => 'fuzzy' ) ), absint( $status->fuzzy_count ) ); ?>
					</td>
					<td class="stats untranslated">
						<?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ), array( 'filters[status]' => 'untranslated' ) ), absint( $status->all_count ) -  absint( $status->current_count ) ); ?>
					</td>
					<td class="stats waiting">
						<?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ), array( 'filters[translated]' => 'yes', 'filters[status]' => 'waiting' ) ), absint( $status->waiting_count ) ); ?>
					</td>
					</tr>
				</tr>
				<?php
			}
			?>
		</tbody>
	</table>
</div>

<script>
	jQuery( document ).ready( function( $ ) {
		$( '#variant-selector' ).on( 'change', function( event ) {
			event.preventDefault();

			var $optionSelected = $( 'option:selected', this ),
				projectUrl = $optionSelected.data( 'projectUrl' );

			if ( projectUrl.length ) {
				window.location = projectUrl;
			}
		});

		$( '.projects-dropdown > li' ).on( 'click', function() {
			$( this ).parent( '.projects-dropdown' ).toggleClass( 'open' );
		});
	});
</script>

<?php gp_tmpl_footer();
