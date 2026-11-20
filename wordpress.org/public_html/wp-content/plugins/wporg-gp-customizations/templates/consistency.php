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
			<input id="original" type="text" name="search" required value="<?php echo gp_esc_attr_with_entities( $search ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gp_esc_attr_with_entities() escapes the value for an attribute and double-encodes existing entities so they render literally. ?>" class="consistency-form-search" placeholder="Enter original to search for&hellip;">
		</span>

		<span class="consistency-field">
			<label for="set">Locale</label>
			<?php
			$locale_options = [
				'' => 'Select a locale',
			];
			$sets_to_hide   = array(
				'ca/valencia',
				'de/informal',
				'en/formal',
				'en/default',
				'fr/formal',
				'sr/latin',
			);
			$sets           = array_diff_key( $sets, array_flip( $sets_to_hide ) );
			$locale_options = $locale_options + $sets;
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- GlotPress escapes select attributes and option labels.
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
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- GlotPress escapes select attributes and option labels.
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
		echo '<div class="notice"><p>All originals have the same translations.</p></div>';
	} else {
		echo '<div id="translations-overview" class="notice wporg-notice-warning"><p>There are ' . esc_html( $translations_unique_count ) . ' different translations. <a id="toggle-translations-unique" href="#show">View</a></p>';
		echo '<ul class="translations-unique hidden">';
		foreach ( $translations_unique_counts as $translation => $count ) {
			printf(
				'<li>%s <small>(%s)</small> <a class="anchor-jumper with-tooltip" aria-label="Go to translation" href="#%s">&darr;</a></li>',
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_translation() escapes the markup and double-encodes existing entities so the translation renders exactly as written.
				str_replace( ' ', '<span class="space"> </span>', esc_translation( $translation ) ),
				esc_html( 1 === $count ? $count . ' time' : $count . ' times' ),
				esc_attr( 't-' . md5( $translation ) )
			);
		}
		echo '</ul>';
		echo '</div>';
	}

	$results_by_translation = [];
	foreach ( $results as $row ) {
		$results_by_translation[ $row->translation ][] = $row;
	}

	$project_cache = [];
	$parent_cache  = [];
	?>
	<table class="gp-table consistency-table">
		<thead>
			<tr>
				<th>Original</th>
				<th>Translation</th>
			</tr>
		</thead>
		<tbody>
		<?php
		foreach ( $translations_unique as $translation_index => $translation ) {
			$prev_translation = $translations_unique[ $translation_index - 1 ] ?? false;
			$next_translation = $translations_unique[ $translation_index + 1 ] ?? false;
			?>
			<tr id="<?php echo esc_attr( 't-' . md5( $translation ) ); ?>" class="new-translation">
				<th colspan="2" scope="rowgroup">
					<strong><?php echo esc_translation( $translation ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_translation() escapes the markup and double-encodes existing entities so the translation renders exactly as written. ?></strong>
					<?php if ( false !== $next_translation ) : ?>
						<a class="anchor-jumper with-tooltip" aria-label="Go to next translation" href="<?php echo esc_attr( '#t-' . md5( $next_translation ) ); ?>">&darr;</a>
					<?php endif; ?>
					<?php if ( false !== $prev_translation ) : ?>
						<a class="anchor-jumper with-tooltip" aria-label="Go to previous translation" href="<?php echo esc_attr( '#t-' . md5( $prev_translation ) ); ?>">&uarr;</a>
					<?php endif; ?>
				</th>
			</tr>
			<?php
			$matching_results = $results_by_translation[ $translation ] ?? [];

			foreach ( $matching_results as $result ) {
				if ( ! isset( $project_cache[ $result->project_id ] ) ) {
					$p_name      = $result->project_name;
					$p_parent_id = $result->project_parent_id;
					$top_parent  = null;
					$is_active   = true;

					while ( $p_parent_id ) {
						if ( ! array_key_exists( $p_parent_id, $parent_cache ) ) {
							$parent_cache[ $p_parent_id ] = GP::$project->get( $p_parent_id );
						}

						$parent_project = $parent_cache[ $p_parent_id ];
						if ( ! $parent_project ) {
							break;
						}

						$top_parent  = $parent_project;
						$p_parent_id = $parent_project->parent_project_id;
						$p_name      = "{$parent_project->name} - {$p_name}";
						$is_active   = $is_active && $parent_project->active;
					}

					$top_name = $top_parent->name ?? $result->project_name;

					$project_cache[ $result->project_id ] = [
						'name'      => $p_name,
						'is_active' => $is_active,
						'css_class' => sanitize_title( 'project-' . $top_name ),
					];
				}

				$cached_project = $project_cache[ $result->project_id ];

				$project_url = sprintf( '/projects/%s/%s/', $result->project_path, $set );
				$source_url  = sprintf(
					'/projects/%s/%s/?filters[status]=either&filters[original_id]=%d&filters[translation_id]=%d',
					$result->project_path,
					$set,
					(int) $result->original_id,
					(int) $result->translation_id
				);
				?>
				<tr class="<?php echo esc_attr( $cached_project['css_class'] ); ?>">
					<td>
						<?php
						echo '<div class="string">';
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_translation() escapes the markup and double-encodes existing entities so the translation renders exactly as written.
						echo esc_translation( $result->original_singular );
						if ( $result->original_context ) {
							echo ' <span class="context">';
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_translation() escapes the markup and double-encodes existing entities so the translation renders exactly as written.
							echo esc_translation( $result->original_context );
							echo '</span>';
						}
						echo '</div>';
						?>
						<div class="meta">
							Project: <a href="<?php echo esc_url( $project_url ); ?>"><?php echo esc_html( $cached_project['name'] ); ?></a>
							<?php if ( ! $cached_project['is_active'] ) : ?>
								<span class="dashicons dashicons-flag"></span><span class="inactive">(inactive)</span>
							<?php endif; ?>
						</div>
					</td>
					<td>
						<div class="string<?php echo $locale_is_rtl ? ' rtl' : ''; ?>"><?php echo esc_translation( $result->translation ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_translation() escapes the markup and double-encodes existing entities so the translation renders exactly as written. ?></div>
						<div class="meta">
							<a href="<?php echo esc_url( $source_url ); ?>">Source</a> |
							Added: <?php echo esc_html( $result->translation_added ); ?>
						</div>
					</td>
				</tr>
				<?php
			}
		}
		?>
		</tbody>
	</table>
	<?php
}

wp_print_inline_script_tag(
	<<<'JS'
	jQuery( document ).ready( function( $ ) {
		$( '#toggle-translations-unique' ).on( 'click', function( event ) {
			event.preventDefault();
			var $list = $( '.translations-unique' );
			$list.toggleClass( 'hidden' );
			$( this ).text( $list.hasClass( 'hidden' ) ? 'View' : 'Hide' );
		});
	});
	JS
);

gp_tmpl_footer();
