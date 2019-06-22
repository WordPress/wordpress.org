<?php

namespace WordPressdotorg\GlotPress\Discussion;

use GP;
use GP_Locales;

class Plugin {

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;


	/**
	 * Returns always the same instance of this plugin.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof Plugin ) ) {
			self::$instance = new Plugin();
		}
		return self::$instance;
	}

	/**
	 * Instantiates a new Plugin object.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
	}

	/**
	 * Initializes the plugin.
	 */
	public function plugins_loaded() {
		// Temporarily hide for public while in development.
		if ( ! is_caped() ) {
			return;
		}

		add_action( 'wporg_translate_meta', [ $this, 'show_discussion_meta' ] );
		add_action( 'gp_pre_tmpl_load', [ $this, 'pre_tmpl_load' ], 10, 2 );
	}

	/**
	 * Enqueue custom styles and scripts.
	 */
	public function pre_tmpl_load( $template, $args ) {
		if ( 'translations' !== $template || ! is_user_logged_in() ) {
			return;
		}

		$blog_id = $this->get_blog_id( $args['locale_slug'] );
		if ( ! $blog_id ) {
			return;
		}

		wp_register_style(
			'gp-translation-discussions',
			plugins_url( 'css/translation-discussions.css', PLUGIN_FILE ),
			[],
			'20190506'
		);
		gp_enqueue_style( 'gp-translation-discussions' );

		wp_register_script(
			'gp-translation-discussions',
			plugins_url( '/js/translation-discussions.js', PLUGIN_FILE ),
			[ 'gp-editor' ],
			'20190510'
		);

		wp_localize_script( 'gp-translation-discussions', 'WPORG_TRANSLATION_DISCUSSION', [
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'endpoint' => get_rest_url( $blog_id ),
		] );

		gp_enqueue_script( 'gp-translation-discussions' );
	}

	/**
	 * Adds discussion items.
	 *
	 * @param object $entry Current translation row entry.
	 */
	public function show_discussion_meta( $entry ) {
		$set     = GP::$translation_set->get( $entry->translation_set_id );
		$blog_id = $this->get_blog_id( $set->locale  );
		if ( ! $blog_id ) {
			return;
		}


		$reject_reasons = [
			'style-guide' => 'Style Guide',
			'glossary'    => 'Glossary',
			'grammar'     => 'Grammar',
			'punctuation' => 'Punctuation',
			'branding'    => 'Branding',
			'typos'       => 'Typos',
		];

		?>
		<div class="feedback-modal feedback-modal__reject-with-feedback wporg-translate-modal">
			<div class="wporg-translate-modal__overlay">
				<div class="wporg-translate-modal__frame" role="dialog" aria-labelledby="wporg-translation-help-modal-headline">
					<div class="wporg-translate-modal__header">
						<h1 id="wporg-translation-help-modal-headline" class="wporg-translate-modal__headline">Reject with Feedback</h1>
						<button type="button" aria-label="Close modal" class="wporg-translate-modal__close"><span class="screen-reader-text">Close</span><span aria-hidden="true" class="dashicons dashicons-no-alt"></span></button>
					</div>

					<div class="wporg-translate-modal__content">
						<form action="POST">
							<fieldset>
								<legend>Reason</legend>

								<ul class="feedback__reasons">
									<?php foreach ( $reject_reasons as $reason_code => $reason_text ) : ?>
										<li class="feedback__reason">
											<label >
												<input type="checkbox" name="reject_reason[]" value="<?php echo esc_attr( $reason_code ); ?>" />
												<?php echo esc_html( $reason_text ); ?>
											</label>
										</li>
									<?php endforeach; ?>
								</ul>
							</fieldset>

							<label class="feedback">
								<?php _e( 'Comment:', 'glotpress' ); ?>
								<textarea placeholder="Let the contributor know what they did wrong…" name="comment" rows="4"></textarea>
							</label>

							<p class="feedback__actions">
								<button class="button feedback__action-submit" type="submit">Submit</button>
							</p>
						</form>
					</div>
				</div>
			</div>
		</div>

		<div class="meta">
			<button type="button" class="button__start-discussion">Start Discussion</button>
		</div>

		<div class="feedback-modal feedback-modal__start-discussion wporg-translate-modal">
			<div class="wporg-translate-modal__overlay">
				<div class="wporg-translate-modal__frame" role="dialog" aria-labelledby="wporg-translation-help-modal-headline">
					<div class="wporg-translate-modal__header">
						<h1 id="wporg-translation-help-modal-headline" class="wporg-translate-modal__headline">Start Discussion</h1>
						<button type="button" aria-label="Close modal" class="wporg-translate-modal__close"><span class="screen-reader-text">Close</span><span aria-hidden="true" class="dashicons dashicons-no-alt"></span></button>
					</div>

					<div class="wporg-translate-modal__content">
						<form action="POST">
							<label class="feedback">
								<?php _e( 'Comment:', 'glotpress' ); ?>
								<textarea required placeholder="Type your question or feedback to this string…" name="comment" rows="4"></textarea>
							</label>

							<p class="feedback__actions">
								<button class="button feedback__action-submit" type="submit">Submit</button>
							</p>
						</form>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Returns the blog ID of a locale.
	 *
	 * @param string $locale_slug Slug of GlotPress locale.
	 * @return int Blog ID on success, 0 on failure.
	 */
	public function get_blog_id( $locale_slug ) {
		static $mapping = [];

		if ( isset( $mapping[ $locale_slug ] ) ) {
			return $mapping[ $locale_slug ];
		}

		$gp_locale = GP_Locales::by_slug( $locale_slug );
		if ( ! $gp_locale || ! isset( $gp_locale->wp_locale ) ) {
			return false;
		}

		$wp_locale = $gp_locale->wp_locale;

		$result = get_sites( [
			'network_id' => get_current_network_id(),
			'path'       => '/support/',
			'number'     => 1,
			'locale'    => $wp_locale,
		] );
		$site = array_shift( $result );


		$mapping[ $locale_slug ] = $site ? (int) $site->blog_id : 0;

		return $mapping[ $locale_slug ];
	}
}

