<?php

namespace WPOrg_Cli;

use WP_Error;

class Markdown_Import {

	private static $input_name = 'wporg-cli-markdown-source';
	private static $meta_key = 'wporg_cli_markdown_source';
	private static $nonce_name = 'wporg-cli-markdown-source-nonce';
	private static $submit_name = 'wporg-cli-markdown-import';
	private static $supported_post_types = array( 'handbook' );

	/**
	 * Handle a request to import from the markdown source
	 */
	public static function action_load_post_php() {
		if ( ! isset( $_GET[ self::$submit_name ] )
			|| ! isset( $_GET[ self::$nonce_name ] )
			|| ! isset( $_GET['post'] ) ) {
			return;
		}
		$post_id = (int) $_GET['post'];
		if ( ! current_user_can( 'edit_post', $post_id )
			|| ! wp_verify_nonce( $_GET[ self::$nonce_name ], self::$input_name )
			|| ! in_array( get_post_type( $post_id ), self::$supported_post_types, true ) ) {
			return;
		}

		$response = self::update_post_from_markdown_source( $post_id );
		if ( is_wp_error( $response ) ) {
			wp_die( $response->get_error_message() );
		}

		wp_safe_redirect( get_edit_post_link( $post_id, 'raw' ) );
		exit;
	}

	/**
	 * Add an input field for specifying Markdown source
 	 */
	public static function action_edit_form_after_title( $post ) {
		if ( ! in_array( $post->post_type, self::$supported_post_types, true ) ) {
			return;
		}
		$markdown_source = get_post_meta( $post->ID, self::$meta_key, true );
		?>
		<label>Markdown source: <input
			type="text"
			name="<?php echo esc_attr( self::$input_name ); ?>"
			value="<?php echo esc_attr( $markdown_source ); ?>"
			placeholder="Enter a URL representing a markdown file to import"
			size="50" />
		</label> <?php
			if ( $markdown_source ) :
				$update_link = add_query_arg( array(
					self::$submit_name => 'import',
					self::$nonce_name  => wp_create_nonce( self::$input_name ),
				), get_edit_post_link( $post->ID, 'raw' ) );
				?>
				<a class="button button-small button-primary" href="<?php echo esc_url( $update_link ); ?>">Import</a>
			<?php endif; ?>
		<?php wp_nonce_field( self::$input_name, self::$nonce_name ); ?>
		<?php
	}

	/**
	 * Save the Markdown source input field
	 */
	public static function action_save_post( $post_id ) {

		if ( ! isset( $_POST[ self::$input_name ] )
			|| ! isset( $_POST[ self::$nonce_name ] )
			|| ! in_array( get_post_type( $post_id ), self::$supported_post_types, true ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST[ self::$nonce_name ], self::$input_name ) ) {
			return;
		}

		$markdown_source = '';
		if ( ! empty( $_POST[ self::$input_name ] ) ) {
			$markdown_source = esc_url_raw( $_POST[ self::$input_name ] );
		}
		update_post_meta( $post_id, self::$meta_key, $markdown_source );
	}

	/**
	 * Update a post from its Markdown source
	 */
	private static function update_post_from_markdown_source( $post_id ) {
		$markdown_source = self::get_markdown_source( $post_id );
		if ( is_wp_error( $markdown_source ) ) {
			return $markdown_source;
		}
		if ( ! function_exists( 'jetpack_require_lib' ) ) {
			return new WP_Error( 'missing-jetpack-require-lib', 'jetpack_require_lib() is missing on system.' );
		}

		// Transform GitHub repo HTML pages into their raw equivalents
		$markdown_source = preg_replace( '#https?://github\.com/([^/]+/[^/]+)/blob/(.+)#', 'https://raw.githubusercontent.com/$1/$2', $markdown_source );
		$markdown_source = add_query_arg( 'v', time(), $markdown_source );
		$response = wp_remote_get( $markdown_source );
		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'invalid-http-code', 'Markdown source returned non-200 http code.' );
		}

		$markdown = wp_remote_retrieve_body( $response );
		// Strip YAML doc from the header
		$markdown = preg_replace( '#^---(.+)---#Us', '', $markdown );

		// Transform to HTML and save the post
		jetpack_require_lib( 'markdown' );
		$parser = new \WPCom_GHF_Markdown_Parser;
		$html = $parser->transform( $markdown );
		$post_data = array(
			'ID'           => $post_id,
			'post_content' => wp_filter_post_kses( wp_slash( $html ) ),
		);
		wp_update_post( $post_data );
		return true;
	}

	/**
	 * Retrieve the markdown source URL for a given post.
	 */
	public static function get_markdown_source( $post_id ) {
		$markdown_source = get_post_meta( $post_id, self::$meta_key, true );
		if ( ! $markdown_source ) {
			return new WP_Error( 'missing-markdown-source', 'Markdown source is missing for post.' );
		}

		return $markdown_source;
	}
}
