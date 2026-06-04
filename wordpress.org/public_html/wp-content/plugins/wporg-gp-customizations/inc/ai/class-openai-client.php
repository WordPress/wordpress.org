<?php
/**
 * Centralized OpenAI API Client for WordPress.org GlotPress
 *
 * This class provides a unified interface for all OpenAI API interactions
 * across WordPress.org GlotPress plugins, handling authentication, requests,
 * error handling, and response parsing.
 *
 * @package    WordPressdotorg\GlotPress\Customizations\AI
 * @author     WordPress.org
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License
 * @link       https://wordpress.org/
 */

namespace WordPressdotorg\GlotPress\Customizations\AI;

defined( 'ABSPATH' ) || exit;

use WordPressdotorg\GlotPress\Customizations\AI\OpenAI_Models;

/**
 * OpenAI API Client class.
 */
class OpenAI_Client {

	/**
	 * OpenAI API base URL.
	 *
	 * @var string
	 */
	const API_BASE_URL = 'https://api.openai.com/v1';

	/**
	 * API key for authentication.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * OpenAI model to use.
	 *
	 * @var string
	 */
	private string $model;



	/**
	 * Temperature setting for generation (0-2).
	 *
	 * @var float
	 */
	private float $temperature;

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	private int $timeout;

	/**
	 * Last error encountered.
	 *
	 * @var string|null
	 */
	private ?string $last_error = null;

	/**
	 * Constructor.
	 *
	 * Automatically retrieves OpenAI configuration from current user's settings
	 * via get_user_option('gp_default_sort').
	 *
	 * Side effect: the model id is routed through OpenAI_Models::resolve_for_current_user(),
	 * which may write a migrated value back to the current user's gp_default_sort option
	 * when the stored value (or the $config['model'] override) is not in OpenAI_Models::KEPT.
	 *
	 * @param array $config Optional configuration overrides.
	 *                      - openai_api_key: string.
	 *                      - model: string.
	 *                      - temperature: float.
	 *                      - timeout: int (default: 20).
	 */
	public function __construct( array $config = array() ) {
		$user_options = get_user_option( 'gp_default_sort' );
		if ( ! is_array( $user_options ) ) {
			$user_options = array();
		}

		$this->api_key     = $config['openai_api_key'] ?? gp_array_get( $user_options, 'openai_api_key', '' );
		$stored_model      = $config['model'] ?? gp_array_get( $user_options, 'openai_model', OpenAI_Models::FALLBACK );
		$this->model       = OpenAI_Models::resolve_for_current_user( $stored_model );
		$this->temperature = $config['temperature'] ?? (float) gp_array_get( $user_options, 'openai_temperature', 0 );
		$this->timeout     = $config['timeout'] ?? 20;

		if ( $this->temperature < 0 || $this->temperature > 2 ) {
			$this->temperature = 0;
		}
		// Temperature is not supported in some models (o1, o3, o4, gpt-5 series).
		if ( preg_match( '/^(o1|o3|o4|gpt-5)/i', $this->model ) ) {
			$this->temperature = 1;
		}
	}

	/**
	 * Create a chat completion.
	 *
	 * @param array $messages Array of message objects with 'role' and 'content' keys.
	 *                        Example: [
	 *                          ['role' => 'system', 'content' => 'You are a translator'],
	 *                          ['role' => 'user', 'content' => 'Translate this text'],
	 *                        ].
	 *
	 * @return array|false Response array on success, false on failure.
	 *                     Success response structure:
	 *                     [
	 *                       'content' => string,          // The generated text
	 *                       'usage' => [
	 *                         'prompt_tokens' => int,
	 *                         'completion_tokens' => int,
	 *                         'total_tokens' => int
	 *                       ],
	 *                       'model' => string,            // Model used
	 *                       'raw_response' => array       // Full API response
	 *                     ]
	 */
	public function chat_completion( array $messages ): array|false {
		$this->reset_error();

		if ( empty( $messages ) ) {
			$this->set_error( 'No messages provided for chat completion.' );
			return false;
		}

		foreach ( $messages as $message ) {
			if ( ! isset( $message['role'] ) || ! isset( $message['content'] ) ) {
				$this->set_error( 'Invalid message structure. Each message must have "role" and "content" keys.' );
				return false;
			}
		}

		$body = array(
			'model'       => $this->model,
			'messages'    => $messages,
			'temperature' => $this->temperature,
		);

		// Additional parameters for gpt-5 series. The gpt-5.4 / gpt-5.5 family
		// rejects reasoning_effort 'minimal'; the supported values are
		// 'none' / 'low' / 'medium' / 'high' / 'xhigh'. We default to 'none'
		// (cheapest, matches the previous gpt-5.1 override).
		if ( preg_match( '/^gpt-5/i', $this->model ) ) {
			$body['frequency_penalty'] = 0;
			$body['presence_penalty']  = 0;
			$body['reasoning_effort']  = 'none';
			$body['verbosity']         = 'low';
		}
		
		$response = $this->request( '/chat/completions', $body );
		if ( false === $response ) {
			return false;
		}

		return $this->parse_chat_completion_response( $response );
	}

	/**
	 * Make a request to the OpenAI API.
	 *
	 * @param string $endpoint API endpoint (e.g., '/chat/completions').
	 * @param array  $body     Request body.
	 *
	 * @return array|false Response array on success, false on failure.
	 */
	private function request( string $endpoint, array $body ): array|false {
		$url = self::API_BASE_URL . $endpoint;

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => $this->timeout,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->set_error( 'API request failed: ' . $response->get_error_message() );
			$this->log_error( $endpoint, $response->get_error_message(), $response );
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			$error_message = wp_remote_retrieve_response_message( $response );
			$this->set_error( "API request failed with status {$status_code}: {$error_message}" );
			$this->log_error( $endpoint, "Status {$status_code}: {$error_message}", $response );
			return false;
		}

		$body_content = wp_remote_retrieve_body( $response );
		$decoded      = json_decode( $body_content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->set_error( 'Failed to decode API response: ' . json_last_error_msg() );
			$this->log_error( $endpoint, 'JSON decode error: ' . json_last_error_msg(), $response );
			return false;
		}

		return $decoded;
	}

	/**
	 * Parse chat completion response.
	 *
	 * @param array $response Raw API response.
	 *
	 * @return array|false Parsed response or false on error.
	 */
	private function parse_chat_completion_response( array $response ): array|false {
		if ( ! isset( $response['choices'][0]['message']['content'] ) ) {
			$this->set_error( 'Invalid response structure from OpenAI API.' );
			return false;
		}

		$message = $response['choices'][0]['message'];
		$content = trim( trim( $message['content'] ), '"' );

		return array(
			'content'      => $content,
			'usage'        => $response['usage'] ?? array(),
			'model'        => $response['model'] ?? $this->model,
			'raw_response' => $response,
		);
	}

	/**
	 * Get the last error message.
	 *
	 * @return string|null Error message or null if no error.
	 */
	public function get_last_error(): ?string {
		return $this->last_error;
	}

	/**
	 * Check if the API is ready to be used.
	 *
	 * @return bool True if API is ready, false otherwise.
	 */
	public function is_ready(): bool {
		return ! empty( trim( $this->api_key ) );
	}

	/**
	 * Set an error message.
	 *
	 * @param string $error Error message.
	 */
	private function set_error( string $error ): void {
		$this->last_error = $error;
	}

	/**
	 * Reset error state.
	 */
	private function reset_error(): void {
		$this->last_error = null;
	}

	/**
	 * Log an error.
	 *
	 * Ignores 401/429 errors in production to reduce log noise.
	 *
	 * @param string $endpoint Endpoint that failed.
	 * @param string $message  Error message.
	 * @param mixed  $response Optional. Response object (WP_Error or HTTP response array).
	 */
	private function log_error( string $endpoint, string $message, $response = null ): void {
		if ( defined( 'WPORG_SANDBOXED' ) && WPORG_SANDBOXED ) {
			wp_send_json_error( array( 'OpenAI API Error [' . $endpoint . ']', $message ) );
		} else {
			if ( $response && ! is_wp_error( $response ) ) {
				$status_code = wp_remote_retrieve_response_code( $response );
				if ( 429 === $status_code || 401 === $status_code ) {
					return;
				}
			}
			trigger_error( 'OpenAI API Error [' . $endpoint . ']: ' . $message, E_USER_WARNING ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
