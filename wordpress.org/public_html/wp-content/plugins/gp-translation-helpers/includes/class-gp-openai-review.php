<?php

class GP_OpenAI_Review {
	/**
	 * The OpenAI key.
	 *
	 * @var string
	 */
	private static $gp_openai_key = '';

	/**
	 * Get suggestions from OpenAI (ChatGPT).
	 *
	 * @param string  $original_singular The singular from the original string.
	 * @param string  $translation       The translation.
	 * @param string  $locale            The locale.
	 * @param string  $glossary_query   The prompt generated to include glossary for the locale.
	 * @param boolean $is_retry   Flag to check if the request is a retry.
	 *
	 * @return array
	 */
	public static function get_openai_review( $original, $translation, $language, $glossary_query, $is_retry ): array {
		$openai_query = '';
		$openai_key   = apply_filters( 'gp_get_openai_key', self::$gp_openai_key );

		if ( empty( trim( $openai_key ) ) ) {
			return array(
				'status' => 404,
				'error' => 'no-openai-key',
			);
		}

		$openai_query .= 'For the english text  "' . $original . '", is "' . $translation . '" a correct translation in ' . $language . '?';
		if ( $glossary_query ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $glossary_query,
			);
		}
		$messages[]      = array(
			'role'    => 'user',
			'content' => $openai_query,
		);
		$start_time      = microtime( true );
		$openai_response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $openai_key,
				),
				'body'    => wp_json_encode(
					array(
						'model'       => 'gpt-3.5-turbo',
						'max_tokens'  => 1000,
						'n'           => 1,
						'messages'    => $messages,
					)
				),
			)
		);
		$end_time        = microtime( true );
		$time_taken      = $end_time - $start_time;

		$response_status = wp_remote_retrieve_response_code( $openai_response );
		$output          = json_decode( wp_remote_retrieve_body( $openai_response ), true );
		$response = array();

		if ( 200 !== $response_status || is_wp_error( $openai_response ) ) {
			$response['status'] = $response_status;
			$response['error']  = wp_remote_retrieve_response_message( $openai_response );
			return $response;
		}

		$message                          = $output['choices'][0]['message'];
		$response['status']     = $response_status;
		$response['review']     = trim( trim( $message['content'] ), '"' );
		$response['time_taken'] = $time_taken;

		return $response;
	}
}
