<?php
/**
 * Plugin Name: Bozo-protection for WordCamp.org
 * Description: Automatically spams some IP-addresses and e-mails.
 */

add_filter( 'contact_form_is_spam', function( $akismet_values ) {
	$banned_ips = array(
		// mohamedmdetelle5@gmail.com per jenmylo
		'41.222.177.104',
		'41.222.180.121',
		'41.222.180.121',
		'41.222.179.185',
	);

	$banned_emails = array(
		'mohamedmdetelle5@gmail.com', // per jenmylo
	);


	if ( isset( $_SERVER['REMOTE_ADDR'] ) && in_array( $_SERVER['REMOTE_ADDR'], $banned_ips ) )
		$akismet_values = true;

	if ( isset( $akismet_values['comment_author_email'] ) && in_array( $akismet_values['comment_author_email'], $banned_emails ) )
		$akismet_values = true;

	if ( true === $akismet_values ) {
		remove_all_filters( 'contact_form_is_spam' );
		return true;
	}

	return $akismet_values;
}, 9 );
