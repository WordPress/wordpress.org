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
	public static function get_openai_review( $original_singular, $translation, $locale, $glossary_query, $is_retry ): array {
		$openai_query = '';
		$openai_key   = apply_filters( 'gp_get_openai_key', self::$gp_openai_key );

		if ( empty( trim( $openai_key ) ) ) {
			return array();
		}
		$openai_temperature = 0;

		$gp_locale     = GP_Locales::by_field( 'slug', $locale );
		$openai_query .= 'For the english text  "' . $original_singular . '", is "' . $translation . '" a correct translation in ' . $gp_locale->english_name . '?';
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
						'temperature' => $openai_temperature,
					)
				),
			)
		);
		$end_time        = microtime( true );
		$time_taken      = $end_time - $start_time;

		$response_status = wp_remote_retrieve_response_code( $openai_response );
		$output          = json_decode( wp_remote_retrieve_body( $openai_response ), true );

		if ( 200 !== $response_status || is_wp_error( $openai_response ) ) {
			$response['openai']['status'] = $response_status;
			$response['openai']['error']  = wp_remote_retrieve_response_message( $openai_response );
			return $response;
		}

		$message                          = $output['choices'][0]['message'];
		$response['openai']['status']     = $response_status;
		$response['openai']['review']     = trim( trim( $message['content'] ), '"' );
		$response['openai']['time_taken'] = $time_taken;

		return $response;
	}
}
