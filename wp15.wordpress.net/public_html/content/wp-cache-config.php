<?php

$dismiss_htaccess_warning      = 1;
$wpsc_save_headers             = 0;
$wp_cache_mfunc_enabled        = 0;
$cached_direct_pages           = array();
$wp_cache_home_path            = '/wordpress/';
$wp_cache_preload_on           = 1;
$wp_cache_preload_taxonomies   = 1;
$wp_cache_preload_email_volume = 'none';
$wp_cache_preload_email_me     = 0;
$wp_cache_preload_interval     = 30;
$wp_cache_preload_posts        = 48;
$cache_schedule_interval       = 'hourly';
$cache_gc_email_me             = 0;
$cache_time_interval           = '300';
$cache_scheduled_time          = '00:00';
$cache_schedule_type           = 'interval';
$wp_cache_make_known_anon      = 0;
$wp_cache_no_cache_for_get     = 0;
$wp_cache_disable_utf8         = 0;
$cache_page_secret             = WPSC_SECRET;
$cache_domain_mapping          = '1';
$wp_cache_mobile_groups        = '';
$wp_cache_mobile_prefixes      = 'w3c , w3c-, acs-, alav, alca, amoi, audi, avan, benq, bird, blac, blaz, brew, cell, cldc, cmd-, dang, doco, eric, hipt, htc_, inno, ipaq, ipod, jigs, kddi, keji, leno, lg-c, lg-d, lg-g, lge-, lg/u, maui, maxo, midp, mits, mmef, mobi, mot-, moto, mwbp, nec-, newt, noki, palm, pana, pant, phil, play, port, prox, qwap, sage, sams, sany, sch-, sec-, send, seri, sgh-, shar, sie-, siem, smal, smar, sony, sph-, symb, t-mo, teli, tim-, tosh, tsm-, upg1, upsi, vk-v, voda, wap-, wapa, wapi, wapp, wapr, webc, winw, winw, xda , xda-';
$wp_cache_refresh_single_only  = 0;
$wp_cache_mod_rewrite          = 1;
$wp_cache_front_page_checks    = 0;
$wp_supercache_304             = 0;
$wp_cache_slash_check          = 1;

if ( ! defined( 'WPCACHEHOME' ) ) {
	define( 'WPCACHEHOME', WP_CONTENT_DIR . "/plugins/wp-super-cache/" );
}

$cache_compression   = 0;
$cache_enabled       = true;
$super_cache_enabled = true;
$cache_max_time      = 1300;
$cache_path          = WP_CONTENT_DIR . '/cache/';
$file_prefix         = 'wp-cache-';
$ossdlcdn            = 0;
//$use_flock = true; // Set it true or false if you know what to use

// Array of files that have 'wp-' but should still be cached
$cache_acceptable_files    = array( 'wp-comments-popup.php', 'wp-links-opml.php', 'wp-locations.php' );
$cache_rejected_uri        = array( 'wp-.*\\.php', 'index\\.php' );
$cache_rejected_user_agent = array(
	0 => 'bot',
	1 => 'ia_archive',
	2 => 'slurp',
	3 => 'crawl',
	4 => 'spider',
	5 => 'Yandex'
);

$cache_rebuild_files = 1;

// Disable the file locking system.
// If you are experiencing problems with clearing or creating cache files
// uncommenting this may help.
$wp_cache_mutex_disabled = 1;

// Just modify it if you have conflicts with semaphores
$sem_id = 691930456;

if ( '/' != substr( $cache_path, -1 ) ) {
	$cache_path .= '/';
}

$wp_cache_mobile           = 0;
$wp_cache_mobile_whitelist = 'Stand Alone/QNws';
$wp_cache_mobile_browsers  = '2.0 MMP, 240x320, 400X240, AvantGo, BlackBerry, Blazer, Cellphone, Danger, DoCoMo, Elaine/3.0, EudoraWeb, Googlebot-Mobile, hiptop, IEMobile, KYOCERA/WX310K, LG/U990, MIDP-2., MMEF20, MOT-V, NetFront, Newt, Nintendo Wii, Nitro, Nokia, Opera Mini, Palm, PlayStation Portable, portalmmm, Proxinet, ProxiNet, SHARP-TQ-GX10, SHG-i900, Small, SonyEricsson, Symbian OS, SymbianOS, TS21i-10, UP.Browser, UP.Link, webOS, Windows CE, WinWAP, YahooSeeker/M1A1-R2D2, iPhone, iPod, Android, BlackBerry9530, LG-TU915 Obigo, LGE VX, webOS, Nokia5800';

$wp_cache_plugins_dir = WP_CONTENT_DIR . '/wp-super-cache-plugins';

// set to 1 to do garbage collection during normal process shutdown instead of wp-cron
$wp_cache_shutdown_gc     = 0;
$wp_super_cache_late_init = 0;

// uncomment the next line to enable advanced debugging features
$wp_super_cache_advanced_debug          = 0;
$wp_super_cache_front_page_text         = '';
$wp_super_cache_front_page_clear        = 0;
$wp_super_cache_front_page_check        = 0;
$wp_super_cache_front_page_notification = '0';

$wp_cache_object_cache       = 0;
$wp_cache_anon_only          = 0;
$wp_supercache_cache_list    = 0;
$wp_cache_debug_to_file      = 0;
$wp_super_cache_debug        = 0;
$wp_cache_debug_level        = 5;
$wp_cache_debug_ip           = '';
$wp_cache_debug_log          = '';
$wp_cache_debug_email        = '';
$wp_cache_pages["search"]    = 0;
$wp_cache_pages["feed"]      = 0;
$wp_cache_pages["category"]  = 0;
$wp_cache_pages["home"]      = 0;
$wp_cache_pages["frontpage"] = 0;
$wp_cache_pages["tag"]       = 0;
$wp_cache_pages["archives"]  = 0;
$wp_cache_pages["pages"]     = 0;
$wp_cache_pages["single"]    = 0;
$wp_cache_hide_donation      = 0;
$wp_cache_not_logged_in      = 1;
$wp_cache_clear_on_post_edit = 1;
$wp_cache_hello_world        = 0;
$wp_cache_mobile_enabled     = 1;
$wp_cache_cron_check         = 1;
