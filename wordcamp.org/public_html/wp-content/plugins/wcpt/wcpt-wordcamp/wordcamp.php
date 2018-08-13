<?php

namespace WordPress_Community\Applications\WordCamp;
require_once 'class-wordcamp-application.php';
use WordPress_Community\Applications\WordCamp_Application;

defined( 'WPINC' ) or die();

$wordcamp_application = new WordCamp_Application();

add_shortcode( $wordcamp_application::SHORTCODE_SLUG, array( $wordcamp_application, 'render_application_shortcode' ) );

add_action( 'wp_enqueue_scripts', array( $wordcamp_application, 'enqueue_common_assets'), 11 );

add_action( 'wp_enqueue_scripts', array( $wordcamp_application, 'enqueue_assets' ) );

