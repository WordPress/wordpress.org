<?php

/**
 * Turn off persistent caching for users.
 *
 * We use memcached for GlotPress because query calculations are very heavy and slow.
 *
 * However some issues were noticed with persistent user caching (very likely due to the WP.org setup)
 * so this turns it off. User queries in GlotPress are comparatively very light.
 *
 * We should test this and remove this plugin at a later date.
 *
 * @author Nacin
 */
wp_cache_add_non_persistent_groups( array( 'users', 'userlogins', 'usermeta', 'usermail', 'usernicename' ) );
