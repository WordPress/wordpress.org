=== Abilities Fixture ===
Tags: abilities, testing
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Test plugin that registers abilities the way 16 popular plugins and WordPress core do.

== Description ==

A test case for tools that find the abilities a plugin registers with the WordPress Abilities API, such as a plugin directory scanner. It is for test sites only.

Popular plugins rarely call `wp_register_ability()` with literal arguments. Each file in `includes/` copies one way they do it instead:

* `direct-literal.php`: the name and arguments are written into the call. Bundled libraries such as wordpress/mcp-adapter do this, and so does almost every plugin when registering a category.
* `registrar-map.php`: a base class registers a `name => arguments` array in a loop, like Jetpack.
* `static-definition.php`: one class per ability, with static `get_name()` and `get_registration_args()` methods, like WooCommerce.
* `const-concat-name.php`: names built from a class constant, like Yoast SEO.
* `definition-object.php`: the label and description passed in order to a constructor, like Elementor.
* `getter-methods.php`: a `get_name()`, `get_label()` and `get_description()` method for each value, registered from a parent class's constructor, like SureCart, Akismet and Easy Digital Downloads.
* `wrapper-functions.php`: the plugin's own function around `wp_register_ability()`, with arguments in order or by key, and defaults filled in, like LearnPress, Formidable Forms, Easy Digital Downloads and MailPoet.
* `ability-class.php`: `'ability_class' =>` a `WP_Ability` subclass that sets the category from a `define()`d constant, like the AI plugin.
* `injected-category.php`: the category is passed in by whatever registers the ability, like Rank Math and Fluent Forms.
* `inherited-const.php`: the name and category come from constants a subclass inherits, like WPForms Lite.
* `loops-and-variables.php`: categories registered in a loop over an array, and a category held in a local variable, like All in One SEO and WordPress core.
* `plain-strings.php`: untranslated labels, a namespace that differs from the plugin's slug, a generic `forms` category, and `label` keys inside the input schema, like Ninja Forms.
* `other-plugin-hooks.php`: an ability WooCommerce registers on the plugin's behalf, and a `wp_register_ability_args` filter, like MailPoet and Formidable Forms.
* `assembled-definitions.php`: arguments assembled at runtime: definitions merged in a loop, a name chosen by a ternary, abilities injected by a dependency injection container, and a description read from a file, like Fluent Forms, WooCommerce, MailPoet and Elementor.
* `not-abilities.php`: code that looks like an ability but isn't one, and two abilities whose names are only known at runtime.

`jetpack_vendor/` holds a bundled library that registers its own ability, the way Elementor ships the MCP adapter.

The patterns come from the releases available on November 4, 2026: Jetpack 16.4, WooCommerce 11.3, Yoast SEO 28.9, Elementor 4.3, and the current versions of SureCart, Akismet, Easy Digital Downloads, LearnPress, Formidable Forms, MailPoet, AI, Rank Math, Fluent Forms, WPForms Lite, All in One SEO and Ninja Forms.

= Not covered =

* Replacing WordPress's own `core/*` abilities. The AI plugin unregisters them and registers its own copies. Doing that here would change core behaviour on any site that runs this plugin.
* Abilities registered in JavaScript with `registerAbility()` from `@wordpress/abilities`. None of the plugins above do it, and WordPress 7.2 doesn't load that script without the Gutenberg plugin.
* A `wp_register_ability_args` filter that changes a label. The filter here only swaps a callback, as Formidable Forms' does.

= The answer file =

`expected-abilities.json` lists what WordPress registers when the plugin runs, taken from `wp_get_abilities()` and `wp_get_ability_categories()`. It is grouped as:

* `abilities` and `categories`: what a scanner should report, each marked with the pattern it uses.
* `vendored`: registered by the bundled library.
* `runtime_only`: registered, but with names that come from the site's post types, so a scanner reading the code can't recover them. The list is what a default install registers.
* `requires_woocommerce`: registered only when WooCommerce is active.
* `unreadable`, on an ability: fields that only exist at runtime, such as a description read from a file. A scanner should report them as null.
* `must_not_report`: look-alikes, such as a REST route namespace and a commented-out call.

Some entries have a `note` where the registered value isn't written in the code, such as a description the wrapper builds from the label.

= Checking the answer file =

With the plugin active, this WP-CLI command lists every ability it registers:

`wp eval 'echo implode( "\n", preg_grep( "#^(abilities-?fixture|acme-mcp-tools)#", array_keys( wp_get_abilities() ) ) );'`

= Data it changes =

The abilities only run when called through the Abilities API or its REST endpoints, and only for users who can `manage_options`. "Create page" creates a draft page. The others read data or do nothing.

== Changelog ==

= 1.0.0 =
* First release.
