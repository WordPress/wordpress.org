<?php

namespace WordCamp\Theme_Templates;

defined( 'WPINC' ) || die();

// todo location of venue, directions, etc
// how to get without hardcoding?
	// can pull address from `wordcamp` post type, maybe create a link to Google Maps driving directions with the starting place blank so they type that
	// it'd be nice to pull in content from the Location page, but don't have a way to programatically detect that, and could have lots of non-essential content mixed in with it
		// could identify location page by post meta inserted into stub when site created
		// if can't find page w/ that, then fall back to address from wordcamp post
	// maybe the full location page would have too much content for this context though? it'd still be good to automatically cache that for offline use, though, and the above approach could work well for that
		// for low-bandwidth users, probably only want to install if add to home screen
			// maybe that kind of functionality should be built into pwa feature plugin instead of custom

// include this from day-of-event template and from offline template
	// link to gmaps wouldn't make sense in offline scenario, though. maybe detect which template it's being included from and show link or not based on that
