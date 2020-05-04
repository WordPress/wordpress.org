<?php

namespace WordPressdotorg\Forums;

class NSFW_Handler {

	public function __construct() {
		add_action( 'init', array( $this, 'register_taxonomy' ) );

		add_action( 'bbp_new_topic_post_extras', array( $this, 'manually_assign_nsfw_flag' ), 10 );
		add_action( 'bbp_edit_topic_post_extras', array( $this, 'manually_assign_nsfw_flag' ), 10 );

		add_action( 'bbp_new_topic_post_extras', array( $this, 'maybe_flag_nsfw_automatically' ), 11 );
		add_action( 'bbp_edit_topic_post_extras', array( $this, 'maybe_flag_nsfw_automatically' ), 11 );

		add_action( 'nsfw_terms_add_form_fields', array( $this, 'add_term_meta' ) );

		add_action( 'bbp_theme_before_topic_form_subscriptions', array( $this, 'mark_topic_nsfw_form_field' ) );

		add_filter( 'bbp_get_topic_title', array( $this, 'prepend_topic_title' ), 10, 2 );

		add_filter( 'bbp_title', array( $this, 'strip_abbr_from_title' ) );

		add_action( 'admin_head', array( $this, 'taxonomy_styles' ) );
	}

	/**
	 * Provide additional term meta fields.
	 */
	public function add_term_meta() {
		// Output a helping text indicating what to use the description field for.
		echo '<p>' . __( 'Please provide a reason for adding this term in the description field', 'wporg-forums' ) . '</p>';
	}

	/**
	 * Output some CSS to keep the NSFW term interface as simple as possible.
	 */
	public function taxonomy_styles() {
		if ( ! isset( $_GET['taxonomy'] ) || 'nsfw_terms' !== $_GET['taxonomy'] ) {
			return;
		}

		?>

		<style type="text/css">
			body.taxonomy-nsfw_terms .term-slug-wrap {
				display: none;
			}
			body.taxonomy-nsfw_terms .column-posts,
			body.taxonomy-nsfw_terms .column-slug {
				display: none;
			}
		</style>

		<?php
	}

	public function register_taxonomy() {
		register_taxonomy(
			'nsfw_terms',
			'forum',
			array(
				'labels' => array(
					'name' => __( 'NSFW Terms', 'wporg-forums' ),
				),
				'description'   => __( 'Declare terms for mature content that the forums will automatically mark not safe for work (NSFW)', 'wporg-forums' ),
				'public'        => false,
				'show_ui'       => true,
				'show_in_menu'  => true,
				'show_in_rest'  => false,
				'show_tagcloud' => false,
				'show_in_quick_edit' => false,
				'capabilities' => array(
					'keep_gate',
				),
			)
		);
	}

	/**
	 * Helper to check if a topic is in a forum that should get the added features of this file.
	 *
	 * @param $topic_id
	 *
	 * @return bool
	 */
	private function topic_in_affected_forum( $topic_id ) {
		return ( Plugin::REVIEWS_FORUM_ID != bbp_get_topic_forum_id( $topic_id ) && ( ! bbp_is_single_view() || 'reviews' !== bbp_get_view_id() ) );
	}

	/**
	 * Output an additional form field when creating or editing a topic to mark it as containing mature content.
	 */
	public function mark_topic_nsfw_form_field() {
		$topic_id = bbp_get_topic_id();

		if ( $this->topic_in_affected_forum( $topic_id ) ) {
			$checked = false;

			if ( bbp_is_topic_edit() ) {
				$checked = get_post_meta( $topic_id, '_topic_is_nsfw', true );
			}

			?>

			<p>
				<label for="topic-is-nsfw">
					<input type="checkbox" name="topic_is_nsfw" id="topic-is-nsfw" <?php checked( true, $checked ); ?>>
					<?php esc_html_e( 'This topic relates to mature content and may be considered Not Safe For Work (NSFW)', 'wporg-forums' ); ?>
				</label>
			</p>

			<?php
		}
	}

	/**
	 * Capture manually assigned mature content from form submissions.
	 *
	 * @param $topic_id
	 */
	public function manually_assign_nsfw_flag( $topic_id ) {
		if ( ! $this->topic_in_affected_forum( $topic_id ) ) {
			return;
		}

		if ( isset( $_POST['topic_is_nsfw'] ) ) {
			update_post_meta( $topic_id, '_topic_is_nsfw', true );
		} else {
			delete_post_meta( $topic_id, '_topic_is_nsfw' );
		}
	}

	/**
	 * Automatically test if a topic might contain mature content when being published.
	 *
	 * @param $topic_id
	 */
	public function maybe_flag_nsfw_automatically( $topic_id ) {
		// If the topic was manually flagged, save some processing by not seeing if it needs automatic flagging.
		if ( isset( $_POST['topic_is_nsfw'] ) ) {
			return;
		}

		/* Skip checking for moderators, this is so that the flagging happens even on edits
		 * by users, but allows mods to force-remove the tag if needed.
		 */
		if ( current_user_can( 'moderate' ) ) {
			return;
		}

		$topic_title = bbp_get_topic_title( $topic_id );
		$topic_url   = $_POST['site_url'] ?? '';
		$topic_tags  = $_POST['bbp_topic_tags'] ?? '';

		$terms = get_terms(
			array(
				'taxonomy'   => 'nsfw_terms',
				'hide_empty' => false,
			)
		);

		foreach ( $terms as $term ) {
			// If the term is found in the title, tags, or URL field, mark it as NSFW and stop processing.
			if (
				stristr( $topic_title, $term->name ) ||
				stristr( $topic_url, $term->name ) ||
				stristr( $topic_tags, $term->name )
			) {
				update_post_meta( $topic_id, '_topic_is_nsfw', true );
				return;
			}
		}
	}

	/**
	 * Add a NSFW tag to any title marked as such.
	 *
	 * @param $title
	 * @param $topic_id
	 *
	 * @return string
	 */
	public function prepend_topic_title( $title, $topic_id ) {
		$is_nsfw = get_post_meta( $topic_id, '_topic_is_nsfw', true );

		if ( ! $is_nsfw ) {
			return $title;
		}

		$prefix = sprintf(
			'<abbr title="%s">%s</abbr>',
			// translators: Explanation of what the abbreviation NSFW means.
			esc_attr__( 'Not Safe For Work / Mature content', 'wporg-forums' ),
			// translators: Prepended titles for topics with mature content.
			__( '[NSFW]', 'wporg-forums' )
		);

		return "$prefix $title";
	}

	/**
	 * Strip the NSFW abbr tag from the document <title> in a translation-friendly manner.
	 */
	public function strip_abbr_from_title( $title ) {
		return preg_replace( '!<abbr title="[^"]+">([^>]+?)</abbr>!i', '$1', $title );
	}
}

