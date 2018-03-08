<?php

/*
 * Everything that can be open-sourced should be, including configuration. That promotes transparency and
 * collaboration; keeps the VCS layout simple, and makes dev environments simple and consistent.
 *
 * All of the files for this site are stored in `meta.svn`, except for `config-private.php`, which is ignored.
 *
 * Naming the config files explicitly with `public` and `private` removes any ambiguity that could lead to sensitive
 * information accidentally being added to the public file, or to `wp-config.php`, which is also public.
 */
require_once( dirname( __DIR__ ). '/config-private.php' );
require_once( dirname( __DIR__ ). '/config-public.php'  );

require_once( ABSPATH . 'wp-settings.php' );
