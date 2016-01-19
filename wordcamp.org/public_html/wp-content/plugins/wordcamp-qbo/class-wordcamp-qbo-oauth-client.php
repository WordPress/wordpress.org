<?php
/**
 * WordCamp QBO Oauth Client
 *
 * Note: This is NOT a general-purpose OAuth client, it is only suitable
 * for the WordCamp QBO plugin.
 */
class WordCamp_QBO_OAuth_Client {
    private $consumer_key;
    private $consumer_secret;
    private $oauth_token;
    private $oauth_token_secret;

    /**
     * @param string $consumer_key The OAuth consumer key
     * @param string $consumer_secret The secret
     */
    public function __construct( $consumer_key, $consumer_secret ) {
        $this->consumer_key = $consumer_key;
        $this->consumer_secret = $consumer_secret;
    }

    /**
     * Set current OAuth token
     *
     * @param string $oauth_token An OAuth token.
     * @param string $oauth_token_secret The OAuth token secret.
     */
    public function set_token( $oauth_token, $oauth_token_secret ) {
        $this->oauth_token = $oauth_token;
        $this->oauth_token_secret = $oauth_token_secret;
    }

    /**
     * Get a request token.
     *
     * @param string $callback_url The URL to which a successful authentication will return.
     *
     * @return array An array with the tokens.
     */
    public function get_request_token( $request_url, $callback_url ) {
        $args = array_merge( $this->_get_default_args(), array(
            'oauth_callback' => $callback_url,
        ) );

        $args['oauth_signature'] = $this->_get_signature( 'POST', $request_url, $args );
        $args = array_map( 'rawurlencode', $args );

        $response = wp_remote_post( add_query_arg( $args, $request_url ) );
        if ( is_wp_error( $response ) )
            return $response;

        if ( wp_remote_retrieve_response_code( $response ) != 200 )
            return new WP_Error( 'error', 'Could not get OAuth request token.' );

        $result = wp_parse_args( wp_remote_retrieve_body( $response ), array(
            'oauth_token' => '',
            'oauth_token_secret' => '',
            'oauth_callback_confirmed' => '',
        ) );

        return $result;
    }

    /**
     * Get an OAuth access token.
     *
     * @param string $verifier A verifier token from the authentication flow.
     *
     * @return array The access token.
     */
    public function get_access_token( $request_url, $verifier ) {
        $args = array_merge( $this->_get_default_args(), array(
            'oauth_verifier' => $verifier,
            'oauth_token' => $this->oauth_token,
        ) );

        $args['oauth_signature'] = $this->_get_signature( 'POST', $request_url, $args );
        $args = array_map( 'rawurlencode', $args );

        $response = wp_remote_post( add_query_arg( $args, $request_url ) );

        if ( is_wp_error( $response ) )
            return $response;

        if ( wp_remote_retrieve_response_code( $response ) != 200 )
            return new WP_Error( 'error', 'Could not get OAuth access token.' );

        $result = wp_parse_args( wp_remote_retrieve_body( $response ), array(
            'oauth_token' => '',
            'oauth_token_secret' => '',
        ) );

        return $result;
    }

    /**
     * Get a string suitable for the Authorization header.
     *
     * @see http://oauth.net/core/1.0a/#auth_header
     *
     * @param string $method The request method.
     * @param string $request_url The request URL (without query)
     * @param array|string $request_args Any additional query/body args.
     *
     * @return string An OAuth string ready for the Authorization header.
     */
    public function get_oauth_header( $method, $request_url, $request_args = array() ) {
        $oauth_args = array_merge( $this->_get_default_args(), array(
            'oauth_token' => $this->oauth_token,
        ) );

        $all_args = $oauth_args;
        if ( is_array( $request_args ) && ! empty( $request_args ) )
            $all_args = array_merge( $oauth_args, $request_args );

        $oauth_args['oauth_signature'] = $this->_get_signature( $method, $request_url, $all_args );

        $header_parts = array();
        foreach ( $oauth_args as $key => $value )
            $header_parts[] = sprintf( '%s="%s"', rawurlencode( $key ), rawurlencode( $value ) );

        $header = 'OAuth ' . implode( ',', $header_parts );
        return $header;
    }

    /**
     * Get a default set of OAuth arguments.
     *
     * @return array Default OAuth arguments.
     */
    private function _get_default_args() {
        return array(
            'oauth_nonce' => md5( wp_generate_password( 12 ) ),
            'oauth_consumer_key' => $this->consumer_key,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_version' => '1.0',
        );
    }

    /**
     * Get an OAuth signature.
     *
     * @see http://oauth.net/core/1.0a/#signing_process
     *
     * @param string $method The request method, GET, POST, etc.
     * @param string $url The request URL (without any query)
     * @param array $args An optional array of query or body args.
     *
     * @return string A base64-encoded hmac-sha1 signature.
     */
    private function _get_signature( $method, $url, $args ) {
        ksort( $args );

        // Don't sign a signature.
        unset( $args['oauth_signature'] );

        $parameter_string = '';
        foreach ( $args as $key => $value )
            $parameter_string .= sprintf( '&%s=%s', rawurlencode( $key ), rawurlencode( $value ) );

        $parameter_string = trim( $parameter_string, '&' );
        $signature_base = strtoupper( $method ) . '&' . rawurlencode( $url ) . '&' . rawurlencode( $parameter_string );
        $signing_key = rawurlencode( $this->consumer_secret ) . '&' . rawurlencode( $this->oauth_token_secret );

        return base64_encode( hash_hmac( 'sha1', $signature_base, $signing_key, true ) );
    }
}