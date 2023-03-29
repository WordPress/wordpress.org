<?php

wp_register_style(
	'wporg-translate',
	plugins_url( 'style.css', __FILE__ ),
	[ 'gp-base', 'wporg-style' ],
	filemtime( __DIR__ . '/style.css' )
);
gp_enqueue_style( 'wporg-translate' );

gp_enqueue_script( 'jquery' );

wp_register_script(
	'details-element-polyfill',
	plugins_url( 'js/details-element-polyfill.min.js', __FILE__ ),
	[],
	'2.3.1'
);

gp_enqueue_script( 'details-element-polyfill' );

wp_register_script(
	'autosize',
	plugins_url( 'js/autosize.min.js', __FILE__ ),
	[],
	'4.0.2'
);

wp_register_script(
	'wporg-translate-editor',
	plugins_url( 'js/editor.js', __FILE__ ),
	[ 'gp-editor', 'autosize' ],
	filemtime( __DIR__ . '/js/editor.js' )
);

wp_localize_script(
	'wporg-translate-editor',
	'wporgEditorSettings',
	array(
		'nonce' => wp_create_nonce( 'wporg-editor-settings' ),
	)
);

wp_register_style(
	'chartist',
	plugins_url( 'css/chartist.min.css', __FILE__ ),
	[],
	'0.9.5'
);
wp_register_script(
	'chartist',
	plugins_url( 'js/chartist.min.js', __FILE__ ),
	[],
	'0.9.5'
);

if ( isset( $template ) && 'translations' === $template ) {
	gp_enqueue_script( 'wporg-translate-editor' );
}

// Remove Emoji fallback support
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

// Remove output of hreflang tags.
add_action( 'wp_head', function () {
	remove_action( 'wp_head', 'WordPressdotorg\Theme\hreflang_link_attributes' );
}, 1 );

/**
 * Set the document title to that of GlotPress.
 *
 * @see https://github.com/GlotPress/GlotPress-WP/issues/8
 */
add_filter( 'document_title_parts', static function() {
	return [
		'title' => gp_title(),
	];
}, 1 );

/**
 * Prints markup for translations help modal in footer.
 */
add_action( 'gp_footer', static function() use ( $template, $args ) {
	if ( 'translations' === $template && ! empty( $args['locale_slug'] ) ) {
		$locale = GP_Locales::by_slug( $args['locale_slug'] );
		wporg_translation_help_modal( $locale );
	}
} );

/**
 * Prints markup for the translation help dialog.
 *
 * @param \GP_Locale $locale Locale of the current translation set.
 */
function wporg_translation_help_modal( $locale ) {
	$locale_resources = wporg_get_locale_resources( $locale )
	?>
	<div id="wporg-translation-help-modal" class="wporg-translate-modal">
		<div class="wporg-translate-modal__overlay">
			<div class="wporg-translate-modal__frame" role="dialog" aria-labelledby="wporg-translation-help-modal-headline">
				<div class="wporg-translate-modal__header">
					<h1 id="wporg-translation-help-modal-headline" class="wporg-translate-modal__headline">Help</h1>
					<button type="button" aria-label="Close modal" class="wporg-translate-modal__close"><span class="screen-reader-text">Close</span><span aria-hidden="true" class="dashicons dashicons-no-alt"></span></button>
				</div>

				<div class="wporg-translate-modal__content">
					<div class="wporg-translate-modal__content_primary">
						<h2>Validating Translations</h2>
						<p>After a contributor suggests a string, the string gets a status of “suggested”. In order to transform them into “approved” strings (which will be used in WordPress), a Translation Editor needs to accept (or reject) those suggestions.<br>
							Please give editors a reasonable time to validate suggestions — We are all volunteers.</p>

						<h2>Translation Status</h2>
						<ul class="no-list">
							<li><span style="color: #c1e1b9">◼︎</span> <strong>Current:</strong> Indicates an approved string.</li>
							<li><span style="color: #ffe399">◼︎</span> <strong>Waiting:</strong> Indicates a string that was suggested, but not yet approved by a Translation Editor.</li>
							<li><span style="color: #fbc5a9">◼︎</span> <strong>Fuzzy:</strong> Indicates a “fuzzy” string. Those translations need to be reviewed for accuracy and edited or approved.</li>
							<li><span style="color: #cdc5e1">◼︎</span> <strong>Old:</strong> Indicates a string that was obsoleted by a newer, approved translation.</li>
							<li><span style="color: #dc3232">◼︎</span> <strong>Warning:</strong> Indicates validation warnings. These translations either need to be corrected or their warnings explicitly discarded by a Translation Editor.</li>
						</ul>
					</div>
					<div class="wporg-translate-modal__content_secondary">
						<?php if ( $locale_resources ) : ?>
							<h2><span aria-hidden="true" class="dashicons dashicons-info"></span> Locale Resources for <?php echo esc_html( $locale->english_name ); ?></h2>
							<?php echo wp_kses_post( $locale_resources ); ?>
						<?php endif; ?>

						<h2><span aria-hidden="true" class="dashicons dashicons-admin-page"></span> Global Handbook Resources</h2>
						<?php
						add_filter( 'walker_nav_menu_start_el', 'wporg_add_nav_description', 10, 4 );
						wp_nav_menu( [
							'theme_location' => 'wporg_translate_global_resources',
							'fallback_cb'    => false,
							'container'      => false,
							'menu_id'        => 'global-resources',
							'depth'          => 2,
						] );
						remove_filter( 'walker_nav_menu_start_el', 'wporg_add_nav_description' );
						?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Adds descriptions to navigation items.
 *
 * @param string  $item_output The menu item output.
 * @param WP_Post $item        Menu item object.
 * @param int     $depth       Depth of the menu.
 * @param array   $args        wp_nav_menu() arguments.
 * @return string Menu item with possible description.
 */
function wporg_add_nav_description( $item_output, $item, $depth, $args ) {
	if ( $item->description ) {
		$item_output = str_replace(
			$args->link_after . '</a>',
			$args->link_after . '</a>: <span class="menu-item-description">' . $item->description . '</span>',
			$item_output
		);
	}

	return $item_output;
}

/**
 * Retrieves the navigation menu from the assigned Rosetta site.
 *
 * Output is cached for an hour.
 *
 * @param \GP_Locale $locale
 * @return string The HTML markup of the navigation menu.
 */
function wporg_get_locale_resources( $locale ) {
	if ( empty( $locale->wp_locale ) ) {
		return '';
	}

	$transient_key = 'locale-resources-' . $locale->wp_locale;
	$cache         = get_transient( $transient_key );

	if ( false !== $cache ) {
		return $cache;
	}

	$result = get_sites( [
		'locale'     => $locale->wp_locale,
		'network_id' => WPORG_GLOBAL_NETWORK_ID,
		'path'       => '/',
		'fields'     => 'ids',
		'number'     => '1',
	] );
	$site_id = array_shift( $result );
	if ( ! $site_id ) {
		set_transient( $transient_key, '', HOUR_IN_SECONDS );
		return '';
	}

	switch_to_blog( $site_id );

	add_filter( 'walker_nav_menu_start_el', 'wporg_add_nav_description', 10, 4 );

	$menu = wp_nav_menu( [
		'theme_location' => 'rosetta_translation_contributor_resources',
		'fallback_cb'    => false,
		'container'      => false,
		'menu_id'        => 'locale-resources',
		'depth'          => 2,
		'echo'           => false,
	] );

	remove_filter( 'walker_nav_menu_start_el', 'wporg_add_nav_description' );

	restore_current_blog();

	set_transient( $transient_key, $menu, HOUR_IN_SECONDS );

	return $menu;
}

/**
 * Adds custom classes to the array of body classes.
 *
 * @param array $classes An array of body classes.
 * @return array Filtered body classes.
 */
function wporg_gp_template_body_classes( $classes ) {
	$classes[] = 'no-js';
	return $classes;
}
add_filter( 'body_class', 'wporg_gp_template_body_classes' );

add_action( 'gp_tmpl_load_locations', function( $locations, $template, $args, $template_path ) {
	$core_templates = GP_PATH . 'gp-templates/';
	require_once $core_templates . 'helper-functions.php';
	$locations[] = $core_templates;
	return $locations;
}, 50, 4 );

function wporg_gp_is_index() {
	return GP::$current_route instanceof \WordPressdotorg\GlotPress\Routes\Routes\Index;
}

/**
 * Prints JavaScript helper for menu toggle.
 */
add_action( 'gp_footer', function() {
	?>
	<script>
		( function( $ ) {
			$( function() {
				var $menu = $( '#site-navigation' );

				if ( $menu.length ) {
					$menu.find( 'button' ).on( 'click', function() {
						$menu.toggleClass( 'toggled' );
					} );
				}
			} );
		} )( jQuery );
	</script>
	<?php
} );

/**
 * Prints HTML markup for translation textareas.
 *
 * @param object $entry Current translation entry.
 * @param array $permissions User's permissions.
 * @param int $index Plural index.
 */
function wporg_gp_translate_textarea( $entry, $permissions, $index = 0 ) {
	list( $can_edit, $can_approve ) = $permissions;
	$disabled = $can_edit ? '' : 'disabled="disabled"';
	?>
	<div class="textareas<?php echo ( 0 === $index ) ? ' active' : ''; ?>" data-plural-index="<?php echo $index; ?>">
		<?php
		if ( isset( $entry->warnings[ $index ] ) ) :
			$warnings = $entry->warnings[ $index ];
			foreach ( $warnings as $key => $value ) :
				?>
				<div class="warning secondary">
					<strong><?php _e( 'Warning:', 'glotpress' ); ?></strong> <?php echo esc_html( $value ); ?>

					<?php if ( $can_approve ) : ?>
						<a href="#" class="discard-warning" data-nonce="<?php echo esc_attr( wp_create_nonce( 'discard-warning_' . $index . $key ) ); ?>" data-key="<?php echo esc_attr( $key ); ?>" data-index="<?php echo esc_attr( $index ); ?>"><?php _e( 'Discard', 'glotpress' ); ?></a>
					<?php endif; ?>
				</div>
				<?php
			endforeach;
		endif;
		?>
		<textarea placeholder="Enter translation here" class="foreign-text" name="translation[<?php echo esc_attr( $entry->original_id ); ?>][]" id="translation_<?php echo esc_attr( $entry->original_id ); ?>_<?php echo esc_attr( $index ); ?>" <?php echo $disabled; // WPCS: XSS ok. ?>><?php echo esc_translation( gp_array_get( $entry->translations, $index ) ); // WPCS: XSS ok. ?></textarea>
	</div>
	<?php
}

/**
 * Counts the number of warnings.
 *
 * @param object $entry Current translation entry.
 * @return int Number of warnings.
 */
function wporg_gp_count_warnings( $entry ) {
	$count = 0;

	if ( empty( $entry->warnings ) ) {
		return $count;
	}

	foreach ( $entry->warnings as $warnings ) {
		$count += count( $warnings );
	}

	return $count;
}

/**
 * Lists file references for a translation.
 *
 * @param \GP_Project $project Current project.
 * @param object      $entry Current translation entry.
 */
function wporg_references( $project, $entry ) {
	?>
	<ul>
		<?php
		foreach ( $entry->references as $reference ) :
			list( $file, $line ) = array_pad( explode( ':', $reference ), 2, 0 );
			if ( $source_url = $project->source_url( $file, $line ) ) :
				?>
				<li><a target="_blank" href="<?php echo $source_url; ?>"><?php echo $file.':'.$line ?></a></li>
			<?php
			elseif ( wp_http_validate_url( $reference ) ) :
				?>
				<li><a target="_blank" href="<?php echo esc_url( $reference ); ?>"><?php echo esc_html( $reference ); ?></a></li>
			<?php
			else :
				echo "<li>$file:$line</li>";
			endif;
		endforeach;
		?>
	</ul>
	<?php
}

/**
 * Update the URL reference for wordpress-org wporg-mu-plugin file locations.
 *
 * @param string $source_url
 * @param \GP_Project $project
 * @param string $file
 * @param string $line
 *
 * @return Source URL.
 */
function wporg_references_wordpress_org_github( $source_url, $project, $file, $line ) {
	if ( 'meta/wordpress-org' === $project->path ) {
		// wporg-mu-plugins is mu-plugins/ based, but NOT those in mu-plugins/pub
		if ( str_starts_with( $file, 'mu-plugins/' ) && ! str_starts_with( $file, 'mu-plugins/pub/' ) ) {
			$source_url = "https://github.com/WordPress/wporg-mu-plugins/blob/trunk/{$file}#L{$line}";

		// wporg-gutenberg theme is pretty unique path..
		} elseif ( str_contains( $file, '/themes/wporg-gutenberg/' ) ) {
			$source_url = "https://github.com/WordPress/wporg-gutenberg/blob/trunk/{$file}#L{$line}";
		}
	}

	return $source_url;
}
add_filter( 'gp_reference_source_url', 'wporg_references_wordpress_org_github', 10, 4 );

/**
 * Whether to show the context or not.
 *
 * Prevents displaying the context if it doesn't provide any new information
 * to the translator.
 * Especially for mobile projects the context is mostly a duplicate of the singular string.
 *
 * @param \Translation_Entry $translation Current translation entry.
 * @return bool Whether to show the context or not.
 */
function wporg_gp_should_display_original_context( $translation ) {
	// No context available.
	if ( ! $translation->context ) {
		return false;
	}

	// Context is the same as the singular.
	if ( $translation->singular === $translation->context ) {
		return false;
	}

	// Context was cut-off due to VARCHAR(255) in the database schema.
	if ( 255 === mb_strlen( $translation->context ) && 0 === strpos( $translation->singular, $translation->context ) ) {
		return false;
	}

	return true;
}
