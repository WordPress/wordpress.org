<table id="translations" class="translations clear">
		<thead>

		</thead>
		​
		<tbody>
			<tr class="editor untranslated priority-normal no-warnings no-translations" id="editor-12561869" row="12561869" style="display: table-row;">
				<td colspan="5">
					<div class="editor-panel">
						<div class="editor-panel__left">
							<div class="panel-header">
								<h3>Original</h3>
							</div>
							<div class="panel-content">
								<div class="source-string strings">
									<div class="source-string__singular">
										<span class="original"><?php echo esc_html( $original->singular ); ?></span>
										<span aria-hidden="true" class="original-raw"><?php echo esc_html( $original->singular ); ?></span>
									</div>
								</div>
								​
								<div class="source-details">
									<details class="source-details__references" close="">
										<summary>Comments all
										<?php foreach ( $locales_with_comments as $locale_with_comments ) : ?>
											<a class="<?php echo esc_attr( $locale_with_comments == $locale_slug ? 'active-locale-link' : '' ); ?>" href="<?php echo esc_attr( $args['original_permalink'] . $locale_with_comments . '/default1' ); ?>">
												| <?php echo esc_html( $locale_with_comments ); ?>
											</a>
										<?php endforeach; ?>
										</summary>

										<?php gp_tmpl_load( 'comment-section', get_defined_vars() ); ?>

									</details>
								</div>
								<div class="suggestions-wrapper">
									<details class="suggestions__other-languages initialized" data-nonce="b1ee0a8267" open="">
										<summary>All Languages</summary>
										<?php if ( $translations_by_locale ) : ?>
											<ul class="suggestions-list">
											<?php
											foreach ( $translations_by_locale as $_locale => $translation ) :
												?>

												<li>
													<div class="translation-suggestion with-tooltip" tabindex="0" role="button" aria-pressed="false" aria-label="Copy translation">
														<span class="translation-suggestion__translation">
															<?php echo esc_html( strtoupper( $_locale ) ) . ' - ' . esc_html( $translation ); ?>
														</span>
													</div>
												</li>
											<?php endforeach; ?>	
											</ul>
										<?php else : ?>
											<p class="no-suggestions">No suggestions.</p>
										<?php endif; ?>
									</details>
								</div>
								​ ​

							</div>
						</div>
						​
						<div class="editor-panel__right">
							<div class="panel-header">
								<h3>Meta</h3>
							</div>
							<div class="panel-content">
								<div class="meta">
									<dl>
										<dt>Status:</dt>
										<dd><?php echo esc_html( $no_of_translations ) . ' of ' . esc_html( count( $all_translation_sets ) ) . ' languages translated'; ?></dd>
									</dl>
									<dl>
										<dt>Priority of the original:</dt>
										<dd><?php echo esc_html( $priority ); ?></dd>
									</dl>
									<div class="source-details">
										<details class="source-details__references">
											<summary>References</summary>
											<ul>
												<li><a target="_blank" href="https://plugins.trac.wordpress.org/browser/friends/trunk/templates/frontend/messages/message-form.php#L36">templates/frontend/messages/message-form.php:36</a></li>
											</ul>
										</details>
									</div>
								</div>	​
							</div>
						</div>
					</div>
				</td>
			</tr> ​ 
		</tbody>
	</table>
