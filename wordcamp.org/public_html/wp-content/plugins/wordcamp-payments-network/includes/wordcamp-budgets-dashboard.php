<?php

namespace WordCamp\Budgets_Dashboard;
defined( 'WPINC' ) or die();

/*
 * Core functionality and helper functions shared between modules
 */


/**
 * Format an amount for display
 *
 * @param float  $amount
 * @param string $currency
 *
 * @return string
 */
function format_amount( $amount, $currency ) {
	$formatted_amount = '';
	$amount           = \WordCamp_Budgets::validate_amount( $amount );

	if ( false === strpos( $currency, 'null' ) && $amount ) {
		$formatted_amount = sprintf( '%s&nbsp;%s', number_format( $amount, 2 ), $currency );

		if ( 'USD' !== $currency ) {
			$usd_amount = convert_currency( $currency, 'usd', $amount );

			if ( $usd_amount ) {
				$formatted_amount .= sprintf( '<br />~&nbsp;%s&nbsp;USD', number_format( $usd_amount, 2 ) );
			}
		}
	}

	return $formatted_amount;
}

/**
 * Currency Conversion
 *
 * @param string $from   What currency are we selling.
 * @param string $to     What currency are we buying.
 * @param float  $amount How much we're selling.
 *
 * @return float Converted amount.
 */
function convert_currency( $from, $to, $amount ) {
	global $wpdb;

	$from      = strtolower( $from );
	$to        = strtolower( $to );
	$cache_key = md5( sprintf( 'wcp-exchange-rate-%s:%s', $from, $to ) );

	$rate = 0;

	if ( false === ( $rate = get_transient( $cache_key ) ) ) {
		$url = 'https://query.yahooapis.com/v1/public/yql';
		$url = add_query_arg( 'format', 'json', $url );
		$url = add_query_arg( 'env', rawurlencode( 'store://datatables.org/alltableswithkeys' ), $url );
		$url = add_query_arg( 'q',   rawurlencode( $wpdb->prepare( 'select * from yahoo.finance.xchange where pair = %s', $from . $to ) ), $url );

		$request = wp_remote_get( esc_url_raw( $url ) );
		$body    = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( ! empty( $body['query']['results']['rate']['Ask'] ) ) {
			$rate = floatval( $body['query']['results']['rate']['Ask'] );
		}

		set_transient( $cache_key, $rate, 24 * HOUR_IN_SECONDS );
	}

	if ( $rate < 0.0000000001 ) {
		return 0;
	}

	return $amount * $rate;
}
