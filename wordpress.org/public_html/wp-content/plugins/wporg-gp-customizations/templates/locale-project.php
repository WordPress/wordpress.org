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
	<p class="project-description"><?php
		$description = apply_filters( 'project_description', $sub_project->description, $sub_project );

		// Localize the links to the currently viewed locale.
		$description = WordPressdotorg\GlotPress\Customizations\Plugin::get_instance()->localize_links( $description, $locale->wp_locale );

		echo $description;
	?></p>

	<div class="project-box percent-<?php echo $project_status->percent_complete; ?>">
		<div class="project-box-header">
			<div class="project-icon">
				<?php echo $project_icon; ?>
			</div>

			<ul class="project-meta">
				<li class="project-name"><?php echo $sub_project->name; ?></li>
				<li class="locale-english"><?php echo $locale->english_name; ?></li>
				<?php if ( $locale->english_name !== $locale->native_name ) : ?>
					<li class="locale-native"><?php echo $locale->native_name; ?></li>
				<?php endif; ?>
				<li class="locale-code">
					<?php
					echo $locale->wp_locale;

					if ( count( $variants ) > 1 ) {
						?>
						<select id="variant-selector" name="variant">
							<?php
							foreach ( $variants as $variant ) {
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
				<?php if ( $locale_glossary ) : ?>
					<li class="locale-glossary">
						<a href="<?php echo esc_url( gp_url_join( gp_url( '/locale' ), $locale_slug, $set_slug, 'glossary' ) ); ?>" class="glossary-link"><?php _e( 'Locale Glossary', 'glotpress' ); ?></a>
					</li>
				<?php elseif ( $can_create_locale_glossary ) : ?>
					<li class="locale-glossary">
						<a href="<?php echo esc_url( gp_url_join( gp_url( '/locale' ), $locale_slug, $set_slug, 'glossary' ) ); ?>" class="glossary-link"><?php _e( 'Create Locale Glossary', 'glotpress' ); ?></a>
					</li>
				<?php endif; ?>
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
								esc_url( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ) ) ),
								$sub_project->name,
								$sub_project_status->percent_complete . '%'
							);
						}

						foreach ( $sub_projects as $_sub_project ) {
							$status = $sub_project_statuses[ $_sub_project->slug ];

							printf(
								'<li><a href="%s">%s <span>%s</span></a>',
								esc_url( gp_url_project( $_sub_project->path, gp_url_join( $locale->slug, $set_slug ) ) ),
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

<?php if ( ! $sub_project->active ) : ?>
	<div class="wporg-notice wporg-notice-warning">
		<p>This project is no longer actively used. Translations remain for archiving purposes.</p>
	</div>
<?php endif; ?>

<?php
if ( 'wp-plugins' === $project->path ) {
	if ( ! in_array( 'dev', $sub_project_slugs ) && ! in_array( 'stable', $sub_project_slugs ) ) {
		?>
		<div class="wporg-notice wporg-notice-error">
			<p>This plugin is not <a href="https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/">properly prepared for localization</a> (<a href="https://wordpress.slack.com/archives/C0E7F4RND">View detailed logs on Slack</a>). If you would like to translate this plugin, <a href="<?php echo esc_url( 'https://wordpress.org/support/plugin/' . $sub_project->slug ); ?>">please contact the author</a>.</p>
		</div>
		<?php
	} else {
		$stable_project_slug = in_array( 'stable', $sub_project_slugs, true ) ? 'stable' : 'dev';
		$stable_project_name = 'stable' === $stable_project_slug ? 'Stable (latest release)' : 'Development (trunk)';
		$status              = $sub_project_statuses[ $stable_project_slug ];
		?>
		<div class="wporg-notice wporg-notice-info">
			<p>Translations for the readme are published almost immediately.
				The initial language pack for the plugin will be generated when 90% of the <a href="<?php echo esc_url( gp_url_project( $sub_project->path, gp_url_join( $stable_project_slug, $locale->slug, $set_slug ) ) ); ?>"><?php echo $stable_project_name; ?></a> sub-project strings have been translated (currently <?php echo $status->percent_complete . '%'; ?>).</p>
		</div>
		<?php
	}
} elseif ( 'wp-themes' === $project->path ) {
	?>
	<div class="wporg-notice wporg-notice-info">
		<p>The initial language pack for the theme will be generated when 90% of the project strings have been translated (currently <?php echo $sub_project_status->percent_complete . '%'; ?>).</p>
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
						<?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ), array( 'filters[translated]' => 'yes', 'filters[status]' => 'current') ), number_format_i18n( $sub_project_status->current_count ) ); ?>
					</td>
					<td class="stats fuzzy">
						<?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ), array( 'filters[translated]' => 'yes', 'filters[status]' => 'fuzzy' ) ), number_format_i18n( $sub_project_status->fuzzy_count ) ); ?>
					</td>
					<td class="stats untranslated">
						<?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ), array( 'filters[status]' => 'untranslated' ) ), number_format_i18n( $sub_project_status->untranslated_count ) ); ?>
					</td>
					<td class="stats waiting">
						<?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ), array( 'filters[translated]' => 'yes', 'filters[status]' => 'waiting' ) ), number_format_i18n( $sub_project_status->waiting_count ) ); ?>
					</td>
					</tr>
				</tr>
				<?php
			}
			?>

			<?php
			if ( 'wp-plugins' === $project->path ) {
				// Ensure consistent order of development and stable projects.
				usort( $sub_projects, function( $a, $b ) {
					$a_is_dev = ( substr( $a->slug, 0, 3 ) == 'dev' );
					$b_is_dev = ( substr( $b->slug, 0, 3 ) == 'dev' );

					// Sort same-type projects alphabetically
					if ( $a_is_dev === $b_is_dev ) {
						return strnatcasecmp( $a->name, $b->name );
					}

					// Sort Stable before Dev.
					return $a_is_dev <=> $b_is_dev;
				} );
			}

			foreach ( $sub_projects as $sub_project ) {
				$status = $sub_project_statuses[ $sub_project->slug ];
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
						<?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ), array( 'filters[translated]' => 'yes', 'filters[status]' => 'current') ), number_format_i18n( $status->current_count ) ); ?>
					</td>
					<td class="stats fuzzy">
						<?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ), array( 'filters[translated]' => 'yes', 'filters[status]' => 'fuzzy' ) ), number_format_i18n( $status->fuzzy_count ) ); ?>
					</td>
					<td class="stats untranslated">
						<?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ), array( 'filters[status]' => 'untranslated' ) ), number_format_i18n( $status->untranslated_count ) ); ?>
					</td>
					<td class="stats waiting">
						<?php gp_link( gp_url_project( $sub_project->path, gp_url_join( $locale->slug, $set_slug ), array( 'filters[translated]' => 'yes', 'filters[status]' => 'waiting' ) ), number_format_i18n( $status->waiting_count ) ); ?>
					</td>
					</tr>
				</tr>
				<?php
			}
			?>
		</tbody>
	</table>
</div>

<div class="locale-project-contributors">
	<div class="locale-project-contributors-group locale-project-contributors-contributors">
		<h3>Translation Contributors</h3>
		<?php if ( $locale_contributors['contributors'] ) : ?>
		<table class="locale-project-contributors-table">
			<thead>
				<tr>
					<th class="contributor-name">Contributor</th>
					<th class="contributor-stats">Translations</th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $locale_contributors['contributors'] as $contributor ) {
				$detailed = '';

				if ( 'wp-plugins' === $project->path ) {
					// Ensure consistent order of development and stable projects.
					usort( $contributor->detailed, function( $a, $b ) {
						return strnatcasecmp( $a->project->name, $b->project->name );
					} );
				}

				foreach ( $contributor->detailed as $detail_project_id => $detail_data ) {
					$detailed .= '<strong class="detailed__project-name">' . $detail_data->project->name . ':</strong>';

					if ( $detail_data->total_count > 0 ) {
						$total_count = gp_link_get(
							gp_url_project(
								$detail_data->project->path,
								gp_url_join( $locale->slug, $set_slug ),
								[
									'filters[translated]' => 'yes',
									'filters[status]'     => 'current_or_waiting_or_fuzzy_or_untranslated',
									'filters[user_login]' => $contributor->login,
								]
							),
							number_format_i18n( $detail_data->total_count )
						);
					} else {
						$total_count = number_format_i18n( $detail_data->total_count );
					}

					if ( $detail_data->current_count > 0 ) {
						$current_count = gp_link_get(
							gp_url_project(
								$detail_data->project->path,
								gp_url_join( $locale->slug, $set_slug ),
								[
									'filters[translated]' => 'yes',
									'filters[status]'     => 'current',
									'filters[user_login]' => $contributor->login,
								]
							),
							number_format_i18n( $detail_data->current_count )
						);
					} else {
						$current_count = number_format_i18n( $detail_data->current_count );
					}

					if ( $detail_data->waiting_count > 0 ) {
						$waiting_count = gp_link_get(
							gp_url_project(
								$detail_data->project->path,
								gp_url_join( $locale->slug, $set_slug ),
								[
									'filters[translated]' => 'yes',
									'filters[status]'     => 'waiting',
									'filters[user_login]' => $contributor->login,
								]
							),
							number_format_i18n( $detail_data->waiting_count )
						);
					} else {
						$waiting_count = number_format_i18n( $detail_data->waiting_count );
					}

					if ( $detail_data->fuzzy_count > 0 ) {
						$fuzzy_count = gp_link_get(
							gp_url_project(
								$detail_data->project->path,
								gp_url_join( $locale->slug, $set_slug ),
								[
									'filters[translated]' => 'yes',
									'filters[status]'     => 'fuzzy',
									'filters[user_login]' => $contributor->login,
								]
							),
							number_format_i18n( $detail_data->fuzzy_count )
						);
					} else {
						$fuzzy_count = number_format_i18n( $detail_data->fuzzy_count );
					}

					$detailed .= sprintf(
						'
							<div class="total">
								<p>%s</p>
							</div>
							<div class="current">
								<p>%s</p>
							</div>
							<div class="waiting">
								<p>%s</p>
							</div>
							<div class="fuzzy">
								<p>%s</p>
							</div>
						',
						$total_count,
						$current_count,
						$waiting_count,
						$fuzzy_count
					);
				}

				printf(
					'<tr id="contributor-%s">
						<td class="contributor-name">
							%s
							<a href="https://profiles.wordpress.org/%s/">%s %s</a>
							<span>Last translation submitted: %s ago</span>
						</td>
						<td class="contributor-stats">
							<div class="total">
								<span>Total</span>
								<p>%s</p>
							</div>
							<div class="current">
								<span>Translated</span>
								<p>%s</p>
							</div>
							<div class="waiting">
								<span>Suggested</span>
								<p>%s</p>
							</div>
							<div class="fuzzy">
								<span>Fuzzy</span>
								<p>%s</p>
							</div>
							<div class="detailed">
								<details>
									<summary>Per project</summary>
									%s
								</details>
							</div>
						</td>
					</tr>',
					$contributor->nicename,
					$contributor->is_editor ? '<span class="translation-editor">Editor</span>' : '',
					$contributor->nicename,
					get_avatar( $contributor->email, 40 ),
					$contributor->display_name ?: $contributor->nicename,
					human_time_diff( strtotime( $contributor->last_update ) ),
					number_format_i18n( $contributor->total_count ),
					number_format_i18n( $contributor->current_count ),
					number_format_i18n( $contributor->waiting_count ),
					number_format_i18n( $contributor->fuzzy_count ),
					$detailed
				);
			}
			?>
			</tbody>
		</table>
		<?php else : ?>
			<p>None, be the first?</p>
		<?php endif; ?>
	</div>

	<div class="locale-project-contributors-group locale-project-contributors-editors">
		<h3>Translation Editors</h3>
		<?php
		if ( $locale_contributors['editors']['project'] ) :
			?>
			<p>These users can validate and approve your translations for this specific project.</p>
			<ul>
				<?php
				foreach ( $locale_contributors['editors']['project'] as $editor ) {
					printf(
						'<li><a href="https://profiles.wordpress.org/%s/">%s %s</a></li>',
						$editor->nicename,
						get_avatar( $editor->email, 40 ),
						$editor->display_name ?: $editor->nicename
					);
				}
				?>
			</ul>
			<?php
		else :
			?>
			<p>There are no editors for this specific project, yet. <a href="https://make.wordpress.org/polyglots/handbook/plugin-theme-authors-guide/pte-request/">Become an editor.</a></p>
			<?php
		endif;

		if ( $locale_contributors['editors']['inherited'] ) :
			?>
			<hr>
			<p>The following users can edit translations for either a parent project or all projects.</p>
			<ul class="compressed">
				<?php
				foreach ( $locale_contributors['editors']['inherited'] as $editor ) {
					printf(
						'<li><a href="https://profiles.wordpress.org/%s/">%s %s</a></li>',
						$editor->nicename,
						get_avatar( $editor->email, 15 ),
						$editor->display_name ? $editor->display_name : $editor->nicename
					);
				}
				?>
			</ul>
			<?php
		endif;
		?>
	</div>
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
