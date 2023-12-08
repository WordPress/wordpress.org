<?php

namespace Dotorg\Matrix;

const MATRIX_INTEGRATIONS_ENABLED = true;

class Poster {
	const HOMESERVER_NAME = 'community.wordpress.org';
	const HOMESERVER_URL  = 'https://wporg.automattrix.com';

	/**
	 * Function to send a message to a matrix room, respecting the MATRIX_INTEGRATIONS_ENABLED constant
	 *
	 * Wrapper for _send()
	 *
	 * @param string      $where Room ID or Room alias reference (full or local part).
	 * @param string      $message Text/Markdown message to post.
	 * @param string|null $who Bot account that should post this message.
	 * @return void|bool  Returns null on bad input, true on success, false on failure
	 */
	public static function send( string $where, string $message, string $who = null ) {
		if ( ! MATRIX_INTEGRATIONS_ENABLED ) {
			return;
		}

		return self::_send( $where, $message, $who );
	}

	/**
	 * Function to send a message to a matrix room, regardless of MATRIX_INTEGRATIONS_ENABLED constant
	 *
	 * Blind wrapper for _send()
	 *
	 * @param string      $where Room ID or Room alias reference (full or local part).
	 * @param string      $message Text/Markdown message to post.
	 * @param string|null $who Bot account that should post this message.
	 * @return void|bool  Returns null on bad input, true on success, false on failure
	 */
	public static function force_send( string $where, string $message, string $who = null ) {
		return self::_send( $where, $message, $who );
	}

	/**
	 * Function that figures out the room id & endpoint that would be used to post the message
	 *
	 * @param string      $where Room ID or Room alias reference (full or local part).
	 * @param string      $message Text/Markdown message to post.
	 * @param string|null $who Bot account that should post this message (eg: polyglotsbot).
	 * @return void|bool  Returns null on bad input, true on success, false on failure
	 */
	private static function _send( string $where, string $message, string $who = null ) {
		if ( empty( $where ) || empty( trim( $message ) ) ) {
			return;
		}

		// which HTTP endpoint to use?
		// constants defined in "secrets.php".
		$constant_name = 'MATRIX_INTEGRATIONS_' . strtoupper( $who ) . '_POSTING_ENDPOINT';
		if ( defined( $constant_name ) ) {
			$http_endpoint = constant( $constant_name ); // get constant's value
		} else {
			$http_endpoint = MATRIX_INTEGRATIONS_MATRIXBOT_POSTING_ENDPOINT;
		}

		return self::post_message( $where, $message, $http_endpoint );
	}

	/**
	 * Posts the message by sending a POST request to maubot's HTTP endpoint.
	 *
	 * @param string $room_id_or_alias Room ID or Room alias reference (full or local part) where the message needs to be posted.
	 * @param string $message Text/Markdown message to post.
	 * @param string $http_endpoint What HTTP endpoint should the request be sent to.
	 * @return bool  Returns true on success, false on failure
	 */
	private static function post_message( string $room_id_or_alias, string $message, string $http_endpoint ): bool {
		if ( WPORG_SANDBOXED ) {
			$room_id_or_alias = "#matrix-testing:community.wordpress.org";
		}

		if ( empty( $room_id_or_alias ) || empty( $message ) || empty( $http_endpoint ) ) {
			return false;
		}

		$context = stream_context_create( [
			'http' => [
				'header' => "Content-type: application/json\r\n",
				'method' => 'POST',
				'content' => json_encode( [
					'room' => $room_id_or_alias,
					'message' => $message,
				] ),
			],
		] );

		if ( false === file_get_contents( $http_endpoint, false, $context ) ) {
			error_log( __NAMESPACE__ . '\\' . __FUNCTION__ . "() error posting message to matrix homeserver - " . error_get_last()['message'] );
			return false;
		}

		return true;
	}
}
