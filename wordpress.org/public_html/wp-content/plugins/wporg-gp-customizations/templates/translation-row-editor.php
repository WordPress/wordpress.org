<?php
/**
 * Template for a single translation row editor in a translation set display.
 */


$more_links = array();
if ( $translation->translation_status ) {
	$translation_permalink               = gp_url_project_locale(
		$project,
		$locale->slug,
		$translation_set->slug,
		array(
			'filters[status]'         => 'either',
			'filters[original_id]'    => $translation->original_id,
			'filters[translation_id]' => $translation->id,
		)
	);
	$more_links['translation-permalink'] = '<a href="' . esc_url( $translation_permalink ) . '">Permalink to translation</a>';
} else {
	$original_permalink               = gp_url_project_locale( $project, $locale->slug, $translation_set->slug, array( 'filters[original_id]' => $translation->original_id ) );
	$more_links['original-permalink'] = '<a href="' . esc_url( $original_permalink ) . '">Permalink to original</a>';
}

$original_history      = gp_url_project_locale(
	$project,
	$locale->slug,
	$translation_set->slug,
	array(
		'filters[status]'      => 'either',
		'filters[original_id]' => $translation->original_id,
		'sort[by]'             => 'translation_date_added',
		'sort[how]'            => 'asc',
	)
);
$more_links['history'] = '<a href="' . esc_url( $original_history ) . '">Translation History</a>';

/**
 * Allows to modify the more links in the translation editor.
 *
 * @since 2.3.0
 *
 * @param array $more_links The links to be output.
 * @param GP_Project $project Project object.
 * @param GP_Locale $locale Locale object.
 * @param GP_Translation_Set $translation_set Translation Set object.
 * @param GP_Translation $translation Translation object.
 */
$more_links = apply_filters( 'gp_translation_row_template_more_links', $more_links, $project, $locale, $translation_set, $translation );
?>
<tr class="editor <?php echo gp_translation_row_classes( $translation ); ?>" id="editor-<?php echo esc_attr( $translation->row_id ); ?>" row="<?php echo esc_attr( $translation->row_id ); ?>">
	<td colspan="<?php echo $can_approve ? 5 : 4; ?>">
		<div class="editor-panel">
			<div class="editor-panel__left">
				<div class="panel-header">
					<?php
					$status = sprintf(
						'<span class="panel-header__bubble%s">%s</span>',
						$translation->translation_status ? ' panel-header__bubble--' . $translation->translation_status : '',
						display_status( $translation->translation_status )
					);

					$warnings_count = wporg_gp_count_warnings( $translation );
					$warnings_info  = '';
					if ( $warnings_count ) {
						$warnings_info = ' <span class="panel-header__bubble panel-header__bubble--warning">' . sprintf(
							_n( '%s warning', '%s warnings', $warnings_count ),
							number_format_i18n( $warnings_count )
						) . '</span>';
					}
					?>
					<h3>Original <?php echo $status . $warnings_info; ?></h3>
					<div class="panel-header-actions">
						<button type="button" class="panel-header-actions__cancel with-tooltip" aria-label="Close current editor">
							<span class="screen-reader-text">Close</span><span aria-hidden="true" class="dashicons dashicons-no-alt"></span>
						</button>
						<button type="button" class="panel-header-actions__previous with-tooltip" aria-label="Open previous editor">
							<span class="screen-reader-text">Previous</span><span aria-hidden="true" class="dashicons dashicons-arrow-up-alt2"></span>
						</button>
						<button type="button" class="panel-header-actions__next with-tooltip" aria-label="Open next editor">
							<span class="screen-reader-text">Next</span><span aria-hidden="true" class="dashicons dashicons-arrow-down-alt2"></span>
						</button>
						<div class="button-menu">
							<button type="button" class="button-menu__toggle with-tooltip" aria-label="Show contextual links">
								<span class="screen-reader-text">Links</span><span aria-hidden="true" class="dashicons dashicons-menu-alt"></span>
							</button>
							<ul class="button-menu__dropdown">
								<?php foreach ( $more_links as $link ) : ?>
									<li><?php echo $link; ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				</div>
				<div class="panel-content">
					<div class="source-string strings">
						<?php
						$singular = $translation->singular_glossary_markup ?? esc_translation( $translation->singular );
						$plural   = $translation->plural_glossary_markup ?? esc_translation( $translation->plural );

						if ( ! $translation->plural ) :
							?>
							<div class="source-string__singular">
								<span class="original"><?php echo prepare_original( $singular ); ?></span>
								<span aria-hidden="true" class="original-raw"><?php echo esc_translation( $translation->singular ); ?></span>
							</div>
						<?php else : ?>
							<div class="source-string__singular">
								<small>Singular:</small>
								<span class="original"><?php echo prepare_original( $singular ); ?></span>
								<span aria-hidden="true" class="original-raw"><?php echo esc_translation( $translation->singular ); ?></span>
							</div>
							<div class="source-string__plural">
								<small>Plural:</small>
								<span class="original"><?php echo prepare_original( $plural ); ?></span>
								<span aria-hidden="true" class="original-raw"><?php echo esc_translation( $translation->plural ); ?></span>
							</div>
						<?php endif; ?>
					</div>

					<div class="source-details">
						<?php if ( wporg_gp_should_display_original_context( $translation ) ) : ?>
							<details open class="source-details__context">
								<summary>Context</summary>
								<span class="context bubble"><?php echo esc_translation( $translation->context ); ?></span>
							</details>
						<?php endif; ?>
						<?php
						if ( $translation->extracted_comments ) :
							?>
							<details open class="source-details__comment">
								<summary><?php _e( 'Comment', 'glotpress' ); ?></summary>
								<p>
									<?php
									/**
									 * Filters the extracted comments of an original.
									 *
									 * @param string         $extracted_comments Extracted comments of an original.
									 * @param GP_Translation $translation        Translation object.
									 */
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo apply_filters( 'gp_original_extracted_comments', $translation->extracted_comments, $translation );
									?>
								</p>
							</details>
						<?php endif; ?>
						<?php if ( $translation->references ) : ?>
							<details class="source-details__references">
								<summary>References</summary>
								<?php wporg_references( $project, $translation ); ?>
							</details>
						<?php endif; ?>
					</div>

					<div class="translation-wrapper">
						<?php if ( $translation->plural && $locale->nplurals > 1 ) : ?>
							<div class="translation-form-wrapper">
								<span>Form:</span>
								<ul class="translation-form-list">
									<?php if ( 2 === (int) $locale->nplurals && 'n != 1' === $locale->plural_expression ) : ?>
										<li>
											<button class="translation-form-list__tab translation-form-list__tab--active with-tooltip"
													type="button"
													aria-label="Translation for singular form"
													data-plural-index="0">
												Singular
											</button>
										</li>
										<li>
											<button class="translation-form-list__tab with-tooltip"
													type="button"
													aria-label="Translation for plural form"
													data-plural-index="1">
												Plural
											</button>
										</li>
									<?php else : ?>
										<?php
										foreach ( range( 0, $locale->nplurals - 1 ) as $plural_index ) :
											$plural_string = implode( ', ', $locale->numbers_for_index( $plural_index ) );
											?>
											<li>
												<button
														class="translation-form-list__tab with-tooltip<?php echo ( 0 === $plural_index ) ? ' translation-form-list__tab--active' : ''; ?>"
														data-plural-index="<?php echo $plural_index; ?>"
														aria-label="<?php printf( 'This plural form is used for numbers like: %s', $plural_string ); ?>"
														type="button">
													<?php echo $plural_string; ?>
												</button>
											</li>
										<?php endforeach; ?>
									<?php endif; ?>
								</ul>
							</div>
						<?php endif; ?>

						<?php if ( ! $translation->plural ) : ?>
							<?php wporg_gp_translate_textarea( $translation, array( $can_edit, $can_approve_translation ) ); ?>
						<?php else : ?>
							<?php foreach ( range( 0, $locale->nplurals - 1 ) as $plural_index ) : ?>
								<?php wporg_gp_translate_textarea( $translation, array( $can_edit, $can_approve ), $plural_index ); ?>
							<?php endforeach; ?>
						<?php endif; ?>

						<div class="translation-actions">
							<?php if ( $can_edit ) : ?>
								<div class="translation-actions__primary">
									<button class="button is-primary translation-actions__save with-tooltip"
											type="button"
											aria-label="<?php echo $can_approve_translation ? 'Save and approve translation' : 'Suggest new translation'; ?>"
											data-nonce="<?php echo esc_attr( wp_create_nonce( 'add-translation_' . $translation->original_id ) ); ?>">
										<?php echo $can_approve_translation ? 'Save' : 'Suggest'; ?>
									</button>
								</div>
								<div class="translation-actions__secondary">
									<button type="button" class="button is-small translation-actions__copy with-tooltip" aria-label="Copy original">
										<span class="screen-reader-text">Copy</span><span aria-hidden="true" class="dashicons dashicons-admin-page"></span>
									</button>
									<button type="button" class="button is-small translation-actions__insert-tab with-tooltip" aria-label="Insert tab">
										<span class="screen-reader-text">Insert tab</span><span aria-hidden="true" class="dashicons dashicons-editor-indent"></span>
									</button>
									<button type="button" class="button is-small translation-actions__insert-newline with-tooltip" aria-label="Insert newline">
										<span class="screen-reader-text">Insert newline</span><span aria-hidden="true" class="dashicons dashicons-editor-break"></span>
									</button>
									<button type="button" class="button is-small translation-actions__ltr with-tooltip" aria-label="Switch to LTR">
										<span class="screen-reader-text">LTR</span><span aria-hidden="true" class="dashicons dashicons-editor-ltr"></span>
									</button>
									<button type="button" class="button is-small translation-actions__rtl with-tooltip" aria-label="Switch to RTL">
										<span class="screen-reader-text">RTL</span><span aria-hidden="true" class="dashicons dashicons-editor-rtl"></span>
									</button>
									<button type="button" class="button is-small translation-actions__help with-tooltip" aria-label="Show help">
										<span class="screen-reader-text">Help</span><span aria-hidden="true" class="dashicons dashicons-editor-help"></span>
									</button>
								</div>
							<?php elseif ( is_user_logged_in() ) : ?>
								<?php echo apply_filters( 'wporg_translate_cannot_edit_translation_hint', '<p class="info">Sorry, you are not allowed to edit this translation.</p>', $translation ); ?>
							<?php else : ?>
								<p class="info">
									<?php
									printf(
										'You <a href="%s">have to log in</a> to edit this translation.',
										esc_url( wp_login_url( gp_url_current() ) )
									);
									?>
								</p>
							<?php endif; ?>
						</div>
					</div>

					<?php
					if ( $can_edit && has_action( 'wporg_translate_suggestions' ) ) {
						?>
						<div class="suggestions-wrapper">
							<?php do_action( 'wporg_translate_suggestions', $translation ); ?>
						</div>
						<?php
					}
					?>
				</div>
			</div>

			<div class="editor-panel__right">
<!--				<div class="panel-header">
					<h3><?php /*_e( 'Meta', 'glotpress' ); */ ?></h3>
				</div>-->
				<div class="panel-content">
					<?php ob_start(); ?>
					<div class="meta" id="sidebar-div-meta-<?php echo $translation->row_id; ?>">
						<?php gp_tmpl_load( 'translation-row-editor-meta-feedback', get_defined_vars() ); ?>
						<?php if ( $translation->translation_status && ( $can_approve_translation || $can_reject_self ) ) : ?>
							<div class="status-actions">
								<?php if ( $can_approve_translation ) : ?>
									<?php if ( 'current' !== $translation->translation_status ) : ?>
										<button class="button  is-primary approve" tabindex="-1" data-nonce="<?php echo esc_attr( wp_create_nonce( 'update-translation-status-current_' . $translation->id ) ); ?>"><strong>+</strong> <?php _e( 'Approve', 'glotpress' ); ?></button>
									<?php endif; ?>
									<?php if ( 'rejected' !== $translation->translation_status ) : ?>
										<button class="button reject" tabindex="-1" data-nonce="<?php echo esc_attr( wp_create_nonce( 'update-translation-status-rejected_' . $translation->id ) ); ?>"><strong>&minus;</strong> <?php _e( 'Reject', 'glotpress' ); ?></button>
										<?php if ( apply_filters( 'gp_enable_changesrequested_status', false ) ) : // todo: delete when we merge the gp-translation-helpers in GlotPress ?>
											<button class="button changesrequested" style="display: none;" data-nonce="<?php echo esc_attr( wp_create_nonce( 'update-translation-status-changesrequested_' . $translation->id ) ); ?>" title="<?php esc_attr_e( 'Request changes for this translation. The existing translation will be kept as part of the translation history.', 'glotpress' ); ?>"><strong>&minus;</strong> <?php _ex( 'Request changes', 'Action', 'glotpress' ); ?></button>
										<?php endif; ?>
									<?php endif; ?>
									<?php if ( 'fuzzy' !== $translation->translation_status ) : ?>
										<button class="button fuzzy" tabindex="-1" data-nonce="<?php echo esc_attr( wp_create_nonce( 'update-translation-status-fuzzy_' . $translation->id ) ); ?>"><strong>~</strong> <?php _e( 'Fuzzy', 'glotpress' ); ?></button>
									<?php endif; ?>
								<?php elseif ( $can_reject_self ) : ?>
									<button class="button reject" tabindex="-1" data-nonce="<?php echo esc_attr( wp_create_nonce( 'update-translation-status-rejected_' . $translation->id ) ); ?>"><strong>&minus;</strong> <?php _e( 'Reject Suggestion', 'glotpress' ); ?></button>
									<button class="button fuzzy" tabindex="-1" data-nonce="<?php echo esc_attr( wp_create_nonce( 'update-translation-status-fuzzy_' . $translation->id ) ); ?>"><strong>~</strong> <?php _e( 'Fuzzy', 'glotpress' ); ?></button>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<dl>
							<dt><?php _e( 'Status:', 'glotpress' ); ?></dt>
							<dd>
								<?php echo display_status( $translation->translation_status ); ?>
							</dd>
						</dl>

						<?php if ( $translation->translation_added && $translation->translation_added !== '0000-00-00 00:00:00' ) : ?>
							<dl>
								<dt><?php _e( 'Added:', 'glotpress' ); ?></dt>
								<dd><?php echo $translation->translation_added; ?> UTC</dd>
							</dl>
						<?php endif; ?>
						<?php if ( $translation->date_modified && $translation->date_modified !== '0000-00-00 00:00:00' && $translation->date_modified !== $translation->translation_added ) : ?>
							<dl>
								<dt><?php _e( 'Last modified:', 'glotpress' ); ?></dt>
								<dd><?php echo $translation->date_modified; ?> UTC</dd>
							</dl>
						<?php endif; ?>
						<?php if ( $translation->user ) : ?>
							<dl>
								<dt><?php _e( 'Translated by:', 'glotpress' ); ?></dt>
								<dd><?php gp_link_user( $translation->user ); ?></dd>
							</dl>
						<?php endif; ?>
						<?php if ( $translation->user_last_modified && ( ! $translation->user || $translation->user->ID !== $translation->user_last_modified->ID ) ) : ?>
							<dl>
								<dt>
								<?php
								if ( 'current' === $translation->translation_status ) {
									_e( 'Approved by:', 'glotpress' );
								} elseif ( 'rejected' === $translation->translation_status ) {
									_e( 'Rejected by:', 'glotpress' );
								} else {
									_e( 'Last updated by:', 'glotpress' );
								}
								?>
								</dt>
								<dd><?php gp_link_user( $translation->user_last_modified ); ?></dd>
							</dl>
						<?php endif; ?>

						<dl>
							<dt><?php _e( 'Priority of the original:', 'glotpress' ); ?></dt>
							<?php if ( $can_write ) : ?>
								<dd>
								<?php
									echo gp_select(
										'priority-' . $translation->original_id,
										GP::$original->get_static( 'priorities' ),
										$translation->priority,
										array(
											'class'      => 'priority',
											'tabindex'   => '-1',
											'data-nonce' => wp_create_nonce( 'set-priority_' . $translation->original_id ),
										)
									);
								?>
									</dd>
							<?php else : ?>
								<dd><?php echo gp_array_get( GP::$original->get_static( 'priorities' ), $translation->priority, 'unknown' ); ?></dd>
							<?php endif; ?>
						</dl>
					</div>
					<?php
					$meta_sidebar = ob_get_clean();

					$sidebar_tabs  = '<nav class="nav-sidebar">';
					$sidebar_tabs .= '<ul class="sidebar-tabs">';
					$sidebar_tabs .= '	<li class="current tab-meta" data-tab="sidebar-tab-meta-' . $translation->row_id . '" data-row-id="' . $translation->row_id . '">Meta</li>';
					$sidebar_tabs .= '	<li class="tab-discussion" data-tab="sidebar-tab-discussion-' . $translation->row_id . '" data-row-id="' . $translation->row_id . '">Discussion&nbsp;<span class="count"></span></li>';
					$sidebar_tabs .= '	<li class="tab-others" data-tab="sidebar-tab-others-' . $translation->row_id . '" data-row-id="' . $translation->row_id . '">Others&nbsp;<span class="count"></span></li>';
					$sidebar_tabs .= '</ul>';
					$sidebar_tabs .= $meta_sidebar;
					$sidebar_tabs .= '<div class="meta discussion" id="sidebar-div-discussion-' . $translation->row_id . '"  data-row-id="' . $translation->row_id . '" style="display: none;"></div>';
					$sidebar_tabs .= '<div class="meta others" id="sidebar-div-others-' . $translation->row_id . '"  data-row-id="' . $translation->row_id . '" style="display: none;">';
					$sidebar_tabs .= '	<details class="details-translation-memory" open="">';
					$sidebar_tabs .= '		<summary class="summary-translation-memory" id="summary-translation-memory-' . $translation->row_id . '">Translation Memory';
					$sidebar_tabs .= '			<span aria-hidden="true" class="suggestions__loading-indicator__icon"><span></span><span></span><span></span></span>';
					$sidebar_tabs .= '		</summary>';
					$sidebar_tabs .= '		<div class="sidebar-div-others-translation-memory-content" id="sidebar-div-others-translation-memory-content-' . $translation->row_id . '"></div>';
					$sidebar_tabs .= '	</details>';
					$sidebar_tabs .= '	<details class="details-other-locales" open="">';
					$sidebar_tabs .= '		<summary class="summary-other-locales" id="summary-other-locales-' . $translation->row_id . '">Other locales';
					$sidebar_tabs .= '			<span aria-hidden="true" class="suggestions__loading-indicator__icon"><span></span><span></span><span></span></span>';
					$sidebar_tabs .= '		</summary>';
					$sidebar_tabs .= '		<div class="sidebar-div-others-other-locales-content" id="sidebar-div-others-other-locales-content-' . $translation->row_id . '"></div>';
					$sidebar_tabs .= '	</details>';
					$sidebar_tabs .= '	<details class="details-history" open="">';
					$sidebar_tabs .= '		<summary class="summary-history" id="summary-history-' . $translation->row_id . '">History';
					$sidebar_tabs .= '			<span aria-hidden="true" class="suggestions__loading-indicator__icon"><span></span><span></span><span></span></span>';
					$sidebar_tabs .= '		</summary>';
					$sidebar_tabs .= '		<div class="sidebar-div-others-history-content" id="sidebar-div-others-history-content-' . $translation->row_id . '"></div>';
					$sidebar_tabs .= '	</details>';
					$sidebar_tabs .= '</div>'; /* meta others */
					$sidebar_tabs .= '</nav>';

					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $sidebar_tabs;
					?>

					<?php do_action( 'wporg_translate_meta', $translation ); ?>
				</div>
			</div>
		</div>
	</td>
</tr>
