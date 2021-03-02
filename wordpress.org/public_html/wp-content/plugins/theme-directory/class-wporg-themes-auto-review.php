<?php

namespace WPORG_Theme_Directory_Trac;

use WP_Error;
use WP_REST_Controller, WP_REST_Server, WP_REST_Response;

defined( 'WPINC' ) || die();

/**
 *
 * @see WP_REST_Controller
 */
class WPorg_Themes_Rest_API_Auto_Review_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 */
	public function __construct( ) {
		$this->namespace         = 'themes/v1';
		$this->rest_base         = 'github';

		$this->register_routes();
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<theme_slug>[^/]+)/(?P<ticket_id>[\d]+)/',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * A Permission Check callback which validates the request against a WP_ORG token.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool|\WP_Error True if the token exists, WP_Error upon failure.
	 */
	function update_item_permissions_check( $request ) {
		return $this->permission_check_api_bearer( $request, 'AUTO_REVIEW_TRAC_API_GITHUB_BEARER_TOKEN' );
	}

	/**
	 * A Permission Check callback which validates the a request against a given token.
	 *
	 * @param \WP_REST_Request $request  The Rest API Request.
	 * @param string           $constant The constant that contains the expected bearer.
	 * @return bool|\WP_Error True if the token exists, WP_Error upon failure.
	 */
	function permission_check_api_bearer( $request, $constant = false ) {
		$authorization_header = $request->get_header( 'authorization' );
		$authorization_header = trim( str_ireplace( 'bearer', '', $authorization_header ) );

		if (
			! $authorization_header ||
			! $constant ||
			! defined( $constant ) ||
			! hash_equals( constant( $constant ), $authorization_header )
		) {
			return new \WP_Error(
				'not_authorized',
				__( 'Sorry! You cannot do that.', 'wporg-plugins' ),
				array( 'status' => \WP_Http::UNAUTHORIZED )
			);
		}

		return true;
	}

	/**
	 * Returns whether the ticket number is for the correct theme.
	 * 
	 * @param string $keywords A string that should include the theme slug.
	 * @param string $theme_slug The theme slug.
	 * @return bool
	 */
	public function ticket_is_for_theme( $keywords, $theme_slug ) {
		return ! empty( $keywords ) && strpos( $keywords , $theme_slug );
	}

	/**
	 * Returns a cleaned up version of the content.
	 * 
	 * @param string $content GitHub specific language to format.
	 * @return string
	 */
	public function get_formatted_content( $content ) {
		$padding = PHP_EOL . PHP_EOL;

		// Add some space around the titles
		$content = str_replace( "]:", "]:" . $padding, $content );
		$content = str_replace( "[", $padding . "[", $content );

		// Remove empty spaces
		$content = trim( $content );

		$disclaimer = sprintf( __( "''The following has been generated by https://github.com/WordPress/theme-review-action, this is for informational purposes only.''" . PHP_EOL, 'wporg-themes' ) );

		$content = "
{$disclaimer}
{$content}
";
		return $content;
	}

	/**
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function update_item( $request ) {
		if ( ! class_exists( 'Trac' ) ) {
			require_once ABSPATH . WPINC . '/class-IXR.php';
			require_once ABSPATH . WPINC . '/class-wp-http-ixr-client.php';
			require_once __DIR__ . '/lib/class-trac.php';
		}

		$trac_instance = new \Trac( 'themetracbot', THEME_TRACBOT_PASSWORD, 'https://themes.trac.wordpress.org/login/xmlrpc' );;
		$theme_slug = $request[ 'theme_slug' ];
		$ticket_id = $request[ 'ticket_id' ];

		// Check for ticket
		$ticket = $trac_instance->ticket_get( $ticket_id );

		if( ! $ticket ){
			return new \WP_Error(
				'rest_invalid_ticket',
				__( 'Unable to locate ticket.', 'wporg-themes' ),
				array( 'status' => \WP_Http::NOT_FOUND )
			);
		}

		// Check to make sure ticket matches theme
		if( ! $this->ticket_is_for_theme( $ticket['keywords'], $theme_slug ) ) {
			return new \WP_Error(
				'rest_invalid_ticket_for_theme',
				__( 'Ticket does not match theme.', 'wporg-themes' ),
				array( 'status' => \WP_Http::BAD_REQUEST )
			);
		}

		$body_params =  $request->get_body_params();
		$content = $this->get_formatted_content( $body_params[ 'content' ] );

		$updated_ticket = $trac_instance->ticket_update( $ticket_id, $content, [], false );

		if( ! $updated_ticket ) {
			return new \WP_Error(
				'rest_error_updating_ticket',
				__( 'We ran into an error updating the ticket.', 'wporg-themes' ),
				array( 'status' => \WP_Http::INTERNAL_SERVER_ERROR )
			);
		}

		return new WP_REST_Response( __( 'Success.', 'wporg-themes' ), \WP_Http::OK );
	}
}

new WPorg_Themes_Rest_API_Auto_Review_Controller();
