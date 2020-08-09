<?php

namespace WordPressdotorg\Markdown;

use WP_Post;

class Editor {
	public function __construct( Importer $importer ) {
		$this->importer = $importer;
	}

	public function init() {
		add_filter( 'the_title', array( $this, 'filter_the_title_edit_link' ), 10, 2 );
		add_filter( 'get_edit_post_link', array( $this, 'redirect_edit_link_to_github' ), 10, 3 );
		add_filter( 'o2_filter_post_actions', array( $this, 'redirect_o2_edit_link_to_github' ), 11, 2 );
		add_action( 'edit_form_top', array( $this, 'render_editor_warning' ) );

		if ( ! has_action( 'wp_head', array( __CLASS__, 'render_edit_button_style' ) ) ) {
			add_action( 'wp_head', array( __CLASS__, 'render_edit_button_style' ) );
		}
	}

	public static function render_edit_button_style() {
		?>
		<style>
			a.github-edit {
				margin-left: .5em;
				font-size: .5em;
				vertical-align: top;
				display: inline-block;
				border: 1px solid #eeeeee;
				border-radius: 2px;
				background: #eeeeee;
				padding: .5em .6em .4em;
				color: black;
				margin-top: 0.1em;
			}
			a.github-edit > * {
				opacity: 0.6;
			}
			a.github-edit:hover > * {
				opacity: 1;
				color: black;
			}
			a.github-edit img {
				height: .8em;
			}
		</style>
		<?php
	}

	/**
	 * Render a warning for editors accessing the edit page via the admin.
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public function render_editor_warning( WP_Post $post ) {
		if ( $post->post_type !== $this->importer->get_post_type() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p><p><a href="%s">%s</a></p></div>',
			'This page is maintained on GitHub. Content, title, and slug edits here will be discarded on next sync.',
			$this->get_markdown_edit_link( $post->ID ),
			'Edit on GitHub'
		);
	}

	/**
	 * Append a "Edit on GitHub" link to Handbook document titles
	 */
	public function filter_the_title_edit_link( $title, $id = null ) {
		// Only apply to the main title for the document
		if ( ! is_singular( $this->importer->get_post_type() )
			|| ! is_main_query()
			|| ! in_the_loop()
			|| is_embed()
			|| $id !== get_queried_object_id() ) {
			return $title;
		}

		$markdown_source = $this->get_markdown_edit_link( get_the_ID() );
		if ( ! $markdown_source ) {
			return $title;
		}

		$src = plugins_url( 'assets/images/github-mark.svg', dirname( dirname( __DIR__ ) ) . '/wporg-cli/wporg-cli.php' );

		return $title . ' <a class="github-edit" href="' . esc_url( $markdown_source ) . '"><img src="' . esc_url( $src ) . '"> <span>Edit</span></a>';
	}

	/**
	 * WP-CLI Handbook pages are maintained in the GitHub repo, so the edit
	 * link should ridirect to there.
	 */
	public function redirect_edit_link_to_github( $link, $post_id, $context ) {
		if ( is_admin() ) {
			return $link;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $link;
		}

		if ( $this->importer->get_post_type() !== $post->post_type ) {
			return $link;
		}

		$markdown_source = $this->get_markdown_edit_link( $post_id );
		if ( ! $markdown_source ) {
			return $link;
		}

		if ( 'display' === $context ) {
			$markdown_source = esc_url( $markdown_source );
		}

		return $markdown_source;
	}

	/**
	 * o2 does inline editing, so we also need to remove the class name that it looks for.
	 *
	 * o2 obeys the edit_post capability for displaying the edit link, so we also need to manually
	 * add the edit link if it isn't there - it always redirects to GitHub, so it doesn't need to
	 * obey the edit_post capability in this instance.
	 */
	public function redirect_o2_edit_link_to_github( $actions, $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $actions;
		}

		if ( $this->importer->get_post_type() !== $post->post_type ) {
			return $actions;
		}

		$markdown_source = $this->get_markdown_edit_link( $post_id );
		if ( ! $markdown_source ) {
			return $actions;
		}

		/*
		 * Define our own edit post action for o2.
		 *
		 * Notable differences from the original are:
		 * - the 'href' parameter always goes to the GitHub source.
		 * - the 'o2-edit' class is missing, so inline editing is disabled.
		 */
		$edit_action = array(
			'action' => 'edit',
			'href' => $markdown_source,
			'classes' => array( 'edit-post-link' ),
			'rel' => $post_id,
			'initialState' => 'default'
		);

		// Find and replace the existing edit action.
		$replaced = false;
		foreach( $actions as &$action ) {
			if ( 'edit' === $action['action'] ) {
				$action = $edit_action;
				$replaced = true;
				break;
			}
		}
		unset( $action );

		// If there was no edit action replaced, add it in manually.
		if ( ! $replaced ) {
			$actions[30] = $edit_action;
		}

		return $actions;
	}

	protected function get_markdown_edit_link( $post_id ) {
		$markdown_source = $this->importer->get_markdown_source( $post_id );
		if ( is_wp_error( $markdown_source ) ) {
			return '';
		}
		if ( 'github.com' !== parse_url( $markdown_source, PHP_URL_HOST )
			|| false !== stripos( $markdown_source, '/edit/master/' ) ) {
			return $markdown_source;
		}
		$markdown_source = str_replace( '/blob/master/', '/edit/master/', $markdown_source );

		return apply_filters( 'wporg_markdown_edit_link', $markdown_source, $post_id );
	}
}
