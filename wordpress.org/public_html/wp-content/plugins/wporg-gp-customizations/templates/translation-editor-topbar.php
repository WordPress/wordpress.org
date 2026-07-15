<?php
/**
 * Singleton top bar that mirrors the open editor row's status-action buttons.
 *
 * Rendered once per translations page in the gp_footer hook, gated by the
 * current user's `approve` capability on the translation set.
 *
 * @package WordPressdotorg\GlotPress\Customizations
 */

?>
<div id="translation-editor-topbar" class="translation-editor-topbar" style="display: none;" aria-live="polite">
	<div class="translation-editor-topbar__inner">
		<div class="translation-editor-topbar__buttons" id="translation-editor-topbar__buttons"></div>
		<button
			type="button"
			class="translation-editor-topbar__close"
			aria-label="<?php esc_attr_e( 'Hide quick-action bar until next page reload', 'glotpress' ); ?>"
		>
			<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
		</button>
	</div>
</div>
