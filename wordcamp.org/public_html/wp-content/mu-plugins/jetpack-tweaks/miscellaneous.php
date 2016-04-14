<?php

namespace WordCamp\Jetpack_Tweaks;
defined( 'WPINC' ) or die();

add_filter( 'jetpack_photon_reject_https',    '__return_false' );
add_filter( 'jetpack_is_holiday_snow_season', '__return_false' );
