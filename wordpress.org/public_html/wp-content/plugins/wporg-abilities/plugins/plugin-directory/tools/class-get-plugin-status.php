<?php
/**
 * Get Plugin Status tool.
 *
 * @package WordPressdotorg\Abilities\Plugins\Plugin_Directory\Tools
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Plugins\Plugin_Directory\Tools;

use WordPressdotorg\Abilities\Plugins\Plugin_Directory\Ability_Base;
use WordPressdotorg\Plugin_Directory\Clients\HelpScout as HelpScout_Client;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools\Helpscout;

defined( 'ABSPATH' ) || exit;

/**
 * Get_Plugin_Status class.
 */
class Get_Plugin_Status extends Ability_Base {

	/**
	 * Register this tool as an ability.
	 */
	public static function register(): void {
		wp_register_ability(
			'wporg/plugins/plugin-directory/get-plugin-status',
			array(
				'label'               => 'Get Plugin Status',
				'description'         => 'Retrieves the current status and any reviewer feedback for a plugin owned by the authenticated user. Returns the plugin status (new, pending, approved, rejected, published, closed, or disabled), key dates, and review correspondence when in review. Only returns results for plugins associated with the authenticated account.',
				'category'            => 'wporg-plugins-plugin-directory',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'plugin_slug'    => array(
							'type'        => 'string',
							'description' => 'The plugin slug as assigned during submission.',
						),
						'full_history' => array(
							'type'        => 'boolean',
							'description' => 'Return the full review conversation history. Defaults to false, which returns only the most recent reviewer message.',
							'default'     => false,
						),
					),
					'required'   => array( 'plugin_slug' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array(
							'type'        => 'object',
							'description' => 'Plugin status and metadata.',
							'properties'  => array(
								'slug'             => array(
									'type'        => 'string',
									'description' => 'The plugin slug.',
								),
								'name'             => array(
									'type'        => 'string',
									'description' => 'The plugin display name.',
								),
								'status'           => array(
									'type'        => 'string',
									'description' => 'Human-readable status with explanation.',
								),
								'submitted_date'   => array(
									'type'        => 'string',
									'description' => 'ISO 8601 submission date.',
								),
								'rejection_reason' => array(
									'type'        => 'string',
									'description' => 'Reason for rejection, when applicable.',
								),
								'close_reason'     => array(
									'type'        => 'string',
									'description' => 'Reason for closure, when applicable.',
								),
							),
						),
						'feedback' => array(
							'type'        => 'array',
							'description' => 'Review correspondence. Only populated for plugins with status new or pending.',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'from' => array(
										'type'        => 'string',
										'description' => 'Who sent the message.',
										'enum'        => array( 'author', 'reviewer' ),
									),
									'date' => array(
										'type'        => 'string',
										'description' => 'ISO 8601 date of the message.',
									),
									'body' => array(
										'type'        => 'string',
										'description' => 'Message content in markdown.',
									),
								),
							),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'execute' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'meta'                => array(
					'mcp'         => array( 'type' => 'tool' ),
					'annotations' => array(
						'readonly'     => true,
						'idempotent'   => true,
						'destructive'  => false,
					),
				),
			)
		);
	}

	/**
	 * Require an authenticated user.
	 *
	 * @return true|\WP_Error
	 */
	public static function check_permission() {
		if ( get_current_user_id() > 0 ) {
			return true;
		}

		return new \WP_Error(
			'authentication_required',
			'You must be authenticated to retrieve plugin status.'
		);
	}

	/**
	 * Retrieve plugin status and review feedback.
	 *
	 * @param array $input The tool input containing 'plugin_slug'.
	 * @return array MCP tool result.
	 */
	public static function execute( array $input ): array {
		self::maybe_load_plugin_directory();

		$slug = sanitize_title( $input['plugin_slug'] );
		$post = self::get_plugin_post( $slug );

		if ( ! $post ) {
			return array(
				'error' => sprintf( 'No plugin with slug "%s" was found for your account.', $slug ),
			);
		}

		$full_history = ! empty( $input['full_history'] );

		return array(
			'status'   => self::get_status_data( $post ),
			'feedback' => self::get_feedback( $post, $full_history ),
		);
	}

	/**
	 * Look up a plugin post owned by the current user.
	 *
	 * @param string $slug The plugin slug.
	 * @return \WP_Post|null
	 */
	private static function get_plugin_post( string $slug ): ?\WP_Post {
		$posts = get_posts( array(
			'post_type'   => 'plugin',
			'name'        => $slug,
			'post_status' => 'any',
			'author'      => get_current_user_id(),
			'numberposts' => 1,
		) );

		return $posts[0] ?? null;
	}

	/**
	 * Build status data for the plugin.
	 *
	 * @param \WP_Post $post The plugin post.
	 * @return array
	 */
	private static function get_status_data( \WP_Post $post ): array {
		$status_labels = array(
			'new'      => 'Awaiting Review — This plugin has not yet been reviewed.',
			'pending'  => 'Being Reviewed — Waiting on action from you. Review the feedback returned by this tool.',
			'approved' => sprintf( 'Approved — Upload your plugin to its SVN repository at https://plugins.svn.wordpress.org/%s/. See wporg://plugins/plugin-directory/plugin-faq for details.', $post->post_name ),
			'rejected' => 'Rejected',
			'publish'  => 'Published',
			'disabled' => 'Disabled',
			'closed'   => 'Closed',
		);

		$data = array(
			'slug'           => $post->post_name,
			'name'           => $post->post_title,
			'status'         => $status_labels[ $post->post_status ] ?? $post->post_status,
			'submitted_date' => self::format_meta_timestamp( $post, '_submitted_date' ) ?? $post->post_date_gmt,
		);

		// Include status change timestamps when available.
		foreach ( array( '_new', '_pending', '_approved', '_rejected' ) as $meta_key ) {
			$timestamp = self::format_meta_timestamp( $post, $meta_key );
			if ( $timestamp ) {
				$data[ ltrim( $meta_key, '_' ) . '_date' ] = $timestamp;
			}
		}

		if ( 'rejected' === $post->post_status ) {
			$reason = get_post_meta( $post->ID, '_rejection_reason', true );
			if ( $reason ) {
				$reasons      = class_exists( Template::class ) ? Template::get_rejection_reasons() : array();
				$data['rejection_reason'] = $reasons[ $reason ] ?? $reason;
			}
		}

		if ( in_array( $post->post_status, array( 'closed', 'disabled' ), true ) ) {
			$close_data = class_exists( Template::class ) ? Template::get_close_data( $post ) : null;
			if ( $close_data ) {
				$data['close_reason'] = $close_data['label'] ?? '';
				$data['close_date']   = $close_data['date'] ?? '';
			}
		}

		return $data;
	}

	/**
	 * Format a post meta timestamp as an ISO 8601 date string.
	 *
	 * @param \WP_Post $post     The post.
	 * @param string   $meta_key The meta key storing a Unix timestamp.
	 * @return string|null ISO 8601 date string or null.
	 */
	private static function format_meta_timestamp( \WP_Post $post, string $meta_key ): ?string {
		$timestamp = get_post_meta( $post->ID, $meta_key, true );

		if ( ! $timestamp ) {
			return null;
		}

		return gmdate( 'c', (int) $timestamp );
	}

	/**
	 * Get review feedback from HelpScout conversation threads.
	 *
	 * @param \WP_Post $post         The plugin post.
	 * @param bool     $full_history Whether to return the full conversation history.
	 * @return array
	 */
	private static function get_feedback( \WP_Post $post, bool $full_history = false ): array {
		if ( ! class_exists( Helpscout::class ) || ! class_exists( HelpScout_Client::class ) ) {
			return array();
		}

		// Only fetch review feedback for plugins still in the review pipeline.
		if ( ! in_array( $post->post_status, array( 'new', 'pending' ), true ) ) {
			return array();
		}

		$emails = Helpscout::get_emails( $post, array( 'subject' => 'Review in Progress:' ) );

		if ( ! $emails ) {
			return array();
		}

		$feedback = array();

		foreach ( $emails as $email ) {
			$threads = self::get_conversation_threads( (int) $email->id );

			if ( ! $threads ) {
				continue;
			}

			foreach ( $threads as $thread ) {
				// Skip internal notes and system events — only include customer and agent messages.
				if ( ! in_array( $thread->type ?? '', array( 'customer', 'reply', 'message' ), true ) ) {
					continue;
				}

				$body = $thread->body ?? '';
				if ( $body ) {
					$body = self::html_to_text( $body );
					$body = self::strip_boilerplate( $body );
				}

				$feedback[] = array(
					'from' => 'customer' === $thread->type ? 'author' : 'reviewer',
					'date' => $thread->createdAt ?? '',
					'body' => $body,
				);
			}
		}

		// Return in chronological order (oldest first) so it reads as a conversation.
		$feedback = array_reverse( $feedback );

		// By default, return only the most recent reviewer message.
		if ( ! $full_history ) {
			for ( $i = count( $feedback ) - 1; $i >= 0; $i-- ) {
				if ( 'reviewer' === $feedback[ $i ]['from'] ) {
					return array( $feedback[ $i ] );
				}
			}
		}

		return $feedback;
	}

	/**
	 * Strip boilerplate from review feedback, keeping only the issues sections.
	 *
	 * Review emails contain plugin-specific feedback surrounded by process
	 * explanations and generic instructions. This extracts just the relevant
	 * content using known marker pairs:
	 *
	 * - Manual review: "## List of issues found" → "## 👉 Continue with the review process"
	 * - Automated review: "### Understanding the Review Queue" → "## 👉 Your next steps"
	 *
	 * @param string $text The markdown-converted feedback body.
	 * @return string The stripped feedback, or the original text if no markers are found.
	 */
	private static function strip_boilerplate( string $text ): string {
		$marker_pairs = array(
			array(
				'start' => '## List of issues found',
				'end'   => '## 👉 Continue with the review process',
			),
			array(
				'start' => '### Understanding the Review Queue',
				'end'   => '## 👉 Your next steps',
			),
		);

		foreach ( $marker_pairs as $pair ) {
			$start_pos = strpos( $text, $pair['start'] );

			if ( false === $start_pos ) {
				continue;
			}

			// Keep everything from the start marker onward.
			$text = substr( $text, $start_pos );

			// Strip everything from the end marker onward.
			$end_pos = strpos( $text, $pair['end'] );

			if ( false !== $end_pos ) {
				$text = substr( $text, 0, $end_pos );
			}
		}

		return trim( $text );
	}

	/**
	 * Fetch conversation threads from HelpScout, with short-lived caching.
	 *
	 * @param int $conversation_id The HelpScout conversation ID.
	 * @return array|null Array of thread objects or null on failure.
	 */
	private static function get_conversation_threads( int $conversation_id ): ?array {
		$cache_key = 'helpscout_threads_' . $conversation_id;
		$cached    = get_site_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = HelpScout_Client::api( '/v2/conversations/' . $conversation_id . '/threads' );

		if ( ! $response || empty( $response->_embedded->threads ) ) {
			return null;
		}

		$threads = $response->_embedded->threads;

		set_site_transient( $cache_key, $threads, 5 * MINUTE_IN_SECONDS );

		return $threads;
	}
}
