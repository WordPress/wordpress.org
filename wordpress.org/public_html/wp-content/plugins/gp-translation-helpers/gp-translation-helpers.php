<?php
/**
 * @package gp-translation-helpers
 */

/**
 * Plugin name:     GP Translation Helpers
 * Plugin URI:      https://github.com/GlotPress/gp-translation-helpers
 * Description:     GlotPress plugin to discuss the strings that are being translated in GlotPress.
 * Version:         0.0.3
 * Requires PHP:    7.4
 * Author:          the GlotPress team
 * Author URI:      https://glotpress.blog
 * License:         GPLv2 or later
 * Text Domain:     gp-translation-helpers
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

require_once __DIR__ . '/includes/class-gp-route-translation-helpers.php';
require_once __DIR__ . '/includes/class-gp-sidebar.php';
require_once __DIR__ . '/includes/class-gp-translation-helpers.php';
require_once __DIR__ . '/includes/class-gth-temporary-post.php';
require_once __DIR__ . '/includes/class-gp-notifications.php';
require_once __DIR__ . '/includes/class-wporg-notifications.php';
require_once __DIR__ . '/includes/class-wporg-customizations.php';
require_once __DIR__ . '/includes/class-gp-custom-locale-reasons.php';
require_once __DIR__ . '/includes/class-gp-openai-review.php';

add_action( 'gp_init', array( 'GP_Translation_Helpers', 'init' ) ); // todo: remove this when this plugin will be merged in the GlotPress core.
add_action( 'gp_init', array( 'GP_Sidebar', 'init' ) );    // todo: remove this when this plugin will be merged in the GlotPress core.
add_action( 'gp_init', array( 'WPorg_GlotPress_Notifications', 'init' ) );    // todo: include this class in a different plugin.
add_filter( 'gp_enable_changesrequested_status', '__return_true' ); // todo: remove this filter when this plugin will be merged in the GlotPress core.

add_action( 'gp_init', array( 'WPorg_GlotPress_Customizations', 'init' ) );    // todo: include this class in a different plugin.
add_action( 'wp_ajax_fetch_openai_review', array( 'GP_Translation_Helpers', 'fetch_openai_review' ) );
