<?php
/**
 * Plugin Name: Abilities Fixture
 * Description: Test plugin for ability detection. Registers abilities the way Jetpack, WooCommerce, Yoast SEO and Elementor do. For test sites only: one ability creates draft pages.
 * Version: 1.0.0
 * Requires at least: 6.9
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: abilities-fixture
 *
 * The patterns, and where each one comes from:
 *
 * - includes/direct-literal.php     Literal arguments at the call site (vendored mcp-adapter, every plugin's categories).
 * - includes/registrar-map.php      A slug => spec map registered in a loop (Jetpack).
 * - includes/static-definition.php  Static get_name() / get_registration_args() classes (WooCommerce).
 * - includes/const-concat-name.php  Names built from a class constant (Yoast SEO).
 * - includes/definition-object.php  Positional value-object constructor (Elementor).
 * - includes/getter-methods.php     A getter per value, registered from a parent constructor (SureCart, Akismet, Easy Digital Downloads).
 * - includes/wrapper-functions.php  The plugin's own wrapper around wp_register_ability() (LearnPress, Formidable Forms, MailPoet).
 * - includes/ability-class.php      'ability_class' => a WP_Ability subclass that sets the category (AI plugin).
 * - includes/injected-category.php  The category is passed in by the registrar (Rank Math, Fluent Forms).
 * - includes/inherited-const.php    Name and category from inherited class constants (WPForms Lite).
 * - includes/loops-and-variables.php Categories from a loop, a category in a local variable (All in One SEO, WordPress core).
 * - includes/plain-strings.php      Untranslated labels, a generic category and nested schema labels (Ninja Forms).
 * - includes/other-plugin-hooks.php Registering through WooCommerce's filter, and wp_register_ability_args (MailPoet, Formidable Forms).
 * - includes/assembled-definitions.php Arguments assembled from loops, ternaries, a DI container and a file (Fluent Forms, WooCommerce, MailPoet, Elementor).
 * - includes/not-abilities.php      Look-alikes and runtime-only names a scanner must not report.
 * - jetpack_vendor/                 A bundled library that registers its own abilities.
 *
 * expected-abilities.json lists what WordPress registers when this plugin runs, which
 * is the answer a static scanner should reproduce.
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/direct-literal.php';
require_once __DIR__ . '/includes/registrar-map.php';
require_once __DIR__ . '/includes/static-definition.php';
require_once __DIR__ . '/includes/const-concat-name.php';
require_once __DIR__ . '/includes/definition-object.php';
require_once __DIR__ . '/includes/getter-methods.php';
require_once __DIR__ . '/includes/wrapper-functions.php';
require_once __DIR__ . '/includes/ability-class.php';
require_once __DIR__ . '/includes/injected-category.php';
require_once __DIR__ . '/includes/inherited-const.php';
require_once __DIR__ . '/includes/loops-and-variables.php';
require_once __DIR__ . '/includes/plain-strings.php';
require_once __DIR__ . '/includes/other-plugin-hooks.php';
require_once __DIR__ . '/includes/assembled-definitions.php';
require_once __DIR__ . '/includes/not-abilities.php';
require_once __DIR__ . '/jetpack_vendor/acme/mcp-tools/register.php';

/**
 * Shared permission check, so each pattern file stays focused on how it names and labels abilities.
 *
 * @return bool
 */
function can_manage() {
	return current_user_can( 'manage_options' );
}
