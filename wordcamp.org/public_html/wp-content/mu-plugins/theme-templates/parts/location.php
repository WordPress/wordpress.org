<?php

namespace WordCamp\Theme_Templates;

defined( 'WPINC' ) || die();

// todo location of venue, directions, etc
// how to get without hardcoding?
	// can pull address from `wordcamp` post type, maybe create a link to Google Maps driving directions with the starting place blank so they type that
	// it'd be nice to pull in content from the Location page, but don't have a way to programatically detect that, and could have lots of non-essential content mixed in with it

// include from day-of-event template and offline template
	// link to gmaps wouldn't make sense in offline scenario, though. maybe detect which template it's being included from and show link or not based on that