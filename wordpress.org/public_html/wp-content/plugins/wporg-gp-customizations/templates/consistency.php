<?php
gp_title( 'Translation Consistency &lt; GlotPress' );
$breadcrumb   = array();
$breadcrumb[] = gp_link_get( '/', 'Locales' );
$breadcrumb[] = 'Translation Consistency';
gp_breadcrumb( $breadcrumb );
gp_tmpl_header();
?>

<p>Analyze translation consistency across projects. The result is limited to 500 translations.</p>


<form action="/consistency" method="get" class="consistency-form">
	<p class="consistency-fields">
		<span class="consistency-field">
			<label for="original">Original</label>
			<input id="original" type="text" name="search" required value="<?php echo esc_html( $search ); ?>" class="consistency-form-search" placeholder="Enter original to search for&hellip;">
		</span>

		<span class="consistency-field">
			<label for="set">Locale</label>
			<?php
			$locale_options = [
				'' => 'Select a locale',
			];
			$sets_to_hide = array(
				'ca/valencia',
				'nl/formal/default',
				'en/formal',
				'en/default',
				'fr/formal',
				'de/formal/default',
				'de-ch/info/default',
				'pt/ao90/default',
				'sr/latin',
				'sr/latin/latin',
			);
			$sets = array_diff_key( $sets, array_flip( $sets_to_hide ) );
			$locale_options = array_merge( $locale_options, $sets );
			echo gp_select(
				'set',
				$locale_options,
				$set,
				[
					'class'    => 'consistency-form-locale',
					'required' => 'required',
				]
			);
			?>
		</span>

		<span class="consistency-field">
			<label for="project">Project</label>
			<?php
			$project_options = [
				'' => 'All Projects',
			];
			$project_options = $project_options + $projects;
			echo gp_select(
				'project',
				$project_options,
				$project,
				[
					'class' => 'consistency-form-project',
				]
			);
			?>
		</span>
	</p>

	<p>
		<label>
			<input type="checkbox" name="search_case_sensitive" value="1"<?php checked( $search_case_sensitive ); ?>>
			Case Sensitive
		</label>
	</p>

	<p>
		<button type="submit" class="button is-primary consistency-form-submit">Analyze</button>
	</p>
</form>

<?php
if ( $performed_search && ! $results ) {
	echo '<div class="notice"><p>No results were found.</p></div>';

} elseif ( $performed_search && $results ) {
	$translations_unique_count  = count( $translations_unique );
	$has_different_translations = $translations_unique_count > 1;
	if ( ! $has_different_translations ) {
		echo '<div class="notice">';
		echo '<p>All originals have the same translations.</p>';
		if ( $notice_message ) {
			// esc_html() is not needed here because $notice_message is already escaped.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<p>' . $notice_message . '</p>';
		}
		echo '</div>'; // .notice
	} else {
		if ( $is_current_user_gte_for_locale ) {
			echo '<form action="/consistency" method="post" class="bulk-update-form">';
			echo '<input type="hidden" name="nonce" value="' . wp_create_nonce( 'bulk-update-consistency' ) . '">';
			echo '<input type="hidden" name="set" value="' . esc_translation( $set ) . '">';
			echo '<input type="hidden" name="search" value="' . esc_html( $search ) . '">';
			echo '<input type="hidden" name="search_case_sensitive" value="' . esc_translation( $search_case_sensitive ) . '">';
			echo '<input type="hidden" name="project" value="' . esc_translation( $project ) . '">';
		}
		echo '<div id="translations-overview" class="notice wporg-notice-warning"><p>There are ' . $translations_unique_count . ' different translations. <a id="toggle-translations-unique" href="#show">Hide</a></p>';
		echo '<div class="translations-unique">';
		if ( ! empty( $error_message ) ) {
			if ( 1 == count( $error_message ) ) {
				echo '<div class="error"><p>There is an error: ' . esc_html( $error_message[0] ) . '</p></div>';
			} else {
				echo '<div class="error">There are some errors:<ul>';
				foreach ( $error_message as $error ) {
					echo '<li>' . esc_html( $error ) . '</li>';
				}
				echo '</ul></div>';
			}
		}
		if ( $notice_message ) {
			// esc_html() is not needed here because $notice_message is already escaped.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<div class="notice"><p>' . $notice_message . '</p></div>';
		}
		if ( $is_current_user_gte_for_locale ) {
			echo '<p>To make bulk updates:</p>';
			echo '<ol class="bulk-update-notice">';
			echo '<li>Check the translations you want to update.</li>';
			echo '<li>Select the translation you want to use as replacement.</li>';
			echo '<li>Click the "Update translations" button.</li>';
			echo '</ol>';
		}
		echo '<ul class="translations-unique">';
		foreach ( $translations_unique_counts as $translation => $count ) {
			printf(
				'<li>%s <small>(%s)</small> <a class="anchor-jumper with-tooltip" aria-label="Go to translation" href="#%s">&darr;</a>',
				str_replace( ' ', '<span class="space"> </span>', esc_translation( $translation ) ),
				1 === $count ? $count . ' time' : $count . ' times',
				esc_attr( 't-' . md5( $translation ) )
			);
			if ( $is_current_user_gte_for_locale ) {
				echo '<small>Update with: </small>';
				echo '<select name="translation[' . esc_html( $translation ) . ']" id="replace-' . esc_html( $translation ) . '">';
				echo '<option value="wporg-bulk-update-do-not-update">Don\'t update this translation</option>';
				foreach ( $translations_unique as $unique_translation ) {
					if ( $unique_translation === $translation ) {
						continue;
					}
					echo '<option value="' . esc_attr( $unique_translation ) . '">' . esc_html( $unique_translation ) . '</option>';
				}
				echo '</select>';
			}
			echo '</li>';
		}
		echo '</ul>'; // .translations-unique
		if ( $is_current_user_gte_for_locale ) {
			echo '<button type="submit" class="button is-primary consistency-update-form-submit">Update translations</button>';
		}
		echo '</form>'; // .bulk-update-form
		echo '</div>'; // .translations-unique
		echo '</div>'; // #translations-overview
	}

	?>
	<table class="gp-table consistency-table">
		<thead>
			<th>Original</th>
			<th>Translation</th>
		</thead>
		<tbody>
		<?php
		$translations = array_keys( $translations_unique_counts );
		foreach ( $translations as $translation_index => $translation ) {
			$prev_arrow = '';
			$next_arrow = '';

			$prev_translation = $translations[ $translation_index - 1 ] ?? false;
			$next_translation = $translations[ $translation_index + 1 ] ?? false;

			if ( ! $prev_translation ) {
				$next_arrow = '<a class="anchor-jumper with-tooltip" aria-label="Go to next translation" href="' . esc_attr( '#t-' . md5( $next_translation ) ) . '">&darr;</a>';
			} elseif ( ! $next_translation ) {
				$prev_arrow = '<a class="anchor-jumper with-tooltip" aria-label="Go to previous translation" href="' . esc_attr( '#t-' . md5( $prev_translation ) ) . '">&uarr;</a>';
			} else {
				$prev_arrow = '<a class="anchor-jumper with-tooltip" aria-label="Go to previous translation" href="' . esc_attr( '#t-' . md5( $prev_translation ) ) . '">&uarr;</a>';
				$next_arrow = '<a class="anchor-jumper with-tooltip" aria-label="Go to next translation" href="' . esc_attr( '#t-' . md5( $next_translation ) ) . '">&darr;</a>';
			}

			printf(
				'<tr id="%s" class="new-translation"><th colspan="2"><strong>%s</strong> %s %s</th></tr>',
				esc_attr( 't-' . md5( $translation ) ),
				esc_translation( $translation ),
				$next_arrow,
				$prev_arrow
			);

			foreach ( $results as $result ) {
				if ( $result->translation != $translation ) {
					continue;
				}

				$project_name      = $result->project_name;
				$parent_project_id = $result->project_parent_id;
				$is_active = $result->active;
				while ( $parent_project_id ) {
					$parent_project    = GP::$project->get( $parent_project_id );
					$parent_project_id = $parent_project->parent_project_id;
					$project_name = "{$parent_project->name} - {$project_name}";
					$is_active = $is_active && $parent_project->active;
				}

				$original_context = '';
				if ( $result->original_context ) {
					$original_context = sprintf(
						' <span class="context">%s</span>',
						esc_translation( $result->original_context )
					);
				}

				if( $is_active ) {
					$active_text = '';
				} else {
					$active_text = sprintf(
						' <span class="dashicons dashicons-flag"></span><span class="inactive">%s</span>',
						esc_translation( '(inactive)' )
					);
				}

				printf(
					'<tr class="%s"><td>%s</td><td>%s</td></tr>',
					isset( $parent_project->name ) ? sanitize_title( 'project-' . $parent_project->name ) : '',
					sprintf(
						'<div class="string">%s%s</div>
						<div class="meta">Project: <a href="/projects/%s/%s/">%s</a>%s</div>',
						esc_translation( $result->original_singular ),
						$original_context,
						$result->project_path,
						$set,
						$project_name,
						$active_text
				),
					sprintf(
						'<div class="string%s">%s</div>
						<div class="meta">
							<a href="/projects/%s/%s/?filters[status]=either&filters[original_id]=%d&filters[translation_id]=%d">Source</a> |
							Added: %s
						</div>',
						$locale_is_rtl ? ' rtl' : '',
						esc_translation( $result->translation ),
						$result->project_path,
						$set,
						$result->original_id,
						$result->translation_id,
						$result->translation_added
					)
				);
			}
		}
		?>
		</tbody>
	</table>
	<?php
}
?>

	<script>
		jQuery(document).ready(function ($) {
			$('#toggle-translations-unique').on('click', function (event) {
				event.preventDefault();
				$('div.translations-unique').toggleClass('hidden');
				if ( 'View' === $('#toggle-translations-unique').text() ) {
					$('#toggle-translations-unique').text('Hide');
				} else {
					$('#toggle-translations-unique').text('View');
				}
			});
		});
	</script>

<?php gp_tmpl_footer();
