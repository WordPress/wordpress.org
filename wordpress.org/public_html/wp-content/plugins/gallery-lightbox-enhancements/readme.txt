=== Gallery Lightbox Enhancements ===
Contributors: alexodiy
Tags: gallery, masonry, lightbox, captions, block-editor
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Polyfill for two pending Gutenberg pull requests: lightbox captions in the Image block and a masonry style for the Gallery block. Self-disables when the upstream changes land in core.

== Description ==

This plugin restores two missing Gallery and Image block features while their upstream Gutenberg pull requests work their way through review.

* **Lightbox captions** — `figcaption` text becomes visible inside the core lightbox overlay when an image is enlarged. Restores behaviour that existed before WordPress 6.5 (see [issue #60469](https://github.com/WordPress/gutenberg/issues/60469) and [PR #77477](https://github.com/WordPress/gutenberg/pull/77477)).
* **Masonry style for the Gallery block** — adds an `is-style-masonry` block style variation that lays the gallery out using CSS multi-column flow, preserving each image's natural aspect ratio. Closes [issue #28247](https://github.com/WordPress/gutenberg/issues/28247) at the user-facing level (see [PR #77615](https://github.com/WordPress/gutenberg/pull/77615)).

The plugin is intentionally scoped as a polyfill: when the upstream pull requests land in core, the plugin auto-disables to avoid duplicate behaviour. After that point you can deactivate and remove it.

== Installation ==

1. Upload the `gallery-lightbox-enhancements` folder to `/wp-content/plugins/`, or install via Plugins → Add New.
2. Activate the plugin through the Plugins screen in WordPress.
3. In any Gallery block, switch to the Styles tab in the block sidebar and pick **Masonry**. Lightbox captions are enabled automatically wherever an Image block has `Enlarge on click` turned on and a caption.

== Frequently Asked Questions ==

= Why a polyfill plugin? =

Both upstream pull requests track long-standing issues — #28247 has been open since January 2021, and the captions regression has shipped since WordPress 6.5. This plugin lets sites use those features today without waiting on the core review cycle.

= What happens when the pull requests are merged into core? =

The plugin checks the active Gutenberg version and disables itself when core ships the features. You will see an admin notice and can deactivate / delete the plugin.

= Does this affect existing Gallery blocks? =

No. The default flex layout is unchanged. Masonry is opt-in via the Styles tab.

= Does the plugin add any controls or settings pages? =

No. There is no settings page; the plugin only registers the masonry block style and restores the lightbox caption rendering.

== Screenshots ==

1. Gallery block with the Masonry style applied — images flow top-to-bottom, keeping their natural aspect ratios.
2. Lightbox overlay showing a caption that the plugin restores.

== Changelog ==

= 1.0.0 =
* Initial release: lightbox captions polyfill and Masonry block style for Gallery.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
