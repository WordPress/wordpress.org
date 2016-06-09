<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

/**
 * The Plugin Categories metabox
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Plugin_Categories {

	/**
	 * Displays the categories metabox for plugins.
	 * The HTML here matches what Core uses.
	 *
	 * @param \WP_Post $post
	 */
	static function display( $post ) {
		?>
		<div id="taxonomy-plugin_category" class="categorydiv">
			<div class="notice notice-info inline">
				<p><?php _e( 'You can assign up to 3 categories.', 'wporg-plugins' ); ?></p>
			</div>

			<div id="plugin_category-all" class="tabs-panel">
				<?php // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks. ?>
				<input type='hidden' name='tax_input[plugin_category][]' value='0' />
				<ul id="plugin_tagchecklist" data-wp-lists="list:plugin_category" class="categorychecklist form-no-clear">
					<?php wp_terms_checklist( $post->ID, array( 'taxonomy' => 'plugin_category' ) ); ?>
				</ul>
			</div>
		</div>
		<?php
	}
}
