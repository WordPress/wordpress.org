<?php

namespace WordPress_Community\Applications\Meetup;

require_once WCPT_DIR . 'wcpt-meetup/class-meetup-application.php';
use WordPress_Community\Applications\Meetup_Application;

defined( 'WPINC' ) or die();

/*
 * todo
 * grant access to regular deputies to vet meetups
 * also do that for wordcamps, so prob use same system
	maybe just create array for regular deupties like the ones we have for super deputies , but it doesn't requier proxy access?
 */

$meetup_application = new Meetup_Application();

add_shortcode( $meetup_application::SHORTCODE_SLUG, array( $meetup_application, 'render_application_shortcode' ) );

add_action( 'wp_enqueue_scripts', array( $meetup_application, 'enqueue_assets' ), 11 );
