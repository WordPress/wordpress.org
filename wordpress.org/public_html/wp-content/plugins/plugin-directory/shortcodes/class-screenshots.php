<?php
/**
 * The [wporg-plugins-screenshots] shortcode.
 *
 * @package WordPressdotorg\Plugin_Directory\Shortcodes
 */

namespace WordPressdotorg\Plugin_Directory\Shortcodes;

use WordPressdotorg\Plugin_Directory\Template;

/**
 * The [wporg-plugins-screenshots] shortcode handler.
 *
 * Renders the screenshot gallery server-side via core/gallery + core/image
 * blocks with the lightbox enabled on each image. The gallery uses CSS
 * multi-column flow ("brick" / masonry layout) so every screenshot keeps
 * its natural aspect ratio — important because plugin authors upload
 * shots in widely different proportions (portrait phone, landscape
 * desktop, panoramas) and a fixed-aspect grid would crop them.
 *
 * For galleries with more than `REVEAL_THRESHOLD` screenshots, every
 * figure still renders into the DOM upfront and CSS clips the wrap at
 * a fixed `max-height: 32rem`. A single "Show all N screenshots"
 * button below the grid releases the clip on click and is removed
 * afterwards (one-time reveal). On mobile the collapse logic is
 * suppressed entirely — vertical scroll is natural, so every
 * screenshot renders straight away.
 *
 * Lightbox captions and the optional masonry style variation for any
 * other Gallery block ship through the Gallery Lightbox Enhancements
 * plugin (force-loaded via `mu-plugins/`), polyfilling pending core
 * pull requests until they land in Gutenberg.
 *
 * @package WordPressdotorg\Plugin_Directory\Shortcodes
 */
class Screenshots {

	/**
	 * At this count or below we render every screenshot upfront — no button.
	 *
	 * Per dd32's brief: "optimize for fewer than 10 screenshots (95% of
	 * plugins)". Inside that 95% the simplest possible UI is best — the
	 * grid alone, no extra moving parts.
	 *
	 * @var int
	 */
	const REVEAL_THRESHOLD = 9;

	/**
	 * Pseudo attachment-id offset for screenshots.
	 *
	 * Plugin screenshots are external assets on `ps.w.org` and have no real
	 * attachment ID. The core Image block's lightbox needs a stable numeric
	 * key in `state.metadata.{id}`, so we mint one from this offset plus the
	 * screenshot index.
	 *
	 * @var int
	 */
	const SYNTHETIC_ID_OFFSET = 9000000;

	/**
	 * Per-request lightbox metadata, keyed by synthetic attachment id.
	 *
	 * Each entry holds the full-resolution `ps.w.org` URL and intrinsic
	 * width / height for one screenshot. {@see self::fix_lightbox_metadata()}
	 * reads this map to repair the core Image block's lightbox state — see
	 * that method for why the repair is necessary.
	 *
	 * @var array<int, array{url:string,width:(int|string),height:(int|string)}>
	 */
	protected static $lightbox_meta = array();

	/**
	 * Renders the shortcode output.
	 *
	 * @return string
	 */
	public static function display() {
		$screenshots = Template::get_screenshots();

		if ( empty( $screenshots ) ) {
			return '';
		}

		self::enqueue_assets();

		$count      = count( $screenshots );
		$use_reveal = ( $count > self::REVEAL_THRESHOLD );

		// Pluck each screenshot's intrinsic width / height from the
		// `assets_screenshots` post meta — populated once during plugin
		// import (see `Import::enrich_asset_dimensions()`, r14866) and
		// already inlined on every entry of $screenshots by
		// `Template::get_screenshots()`. Shipping the dimensions on the
		// `<img>` tag lets the browser reserve slot height from the
		// aspect ratio at parse time, so the gallery has zero layout
		// shift no matter how slowly the screenshots decode.
		$dimensions = self::get_dimensions( $screenshots );

		// Always render every figure — masonry balances across columns
		// once on first paint, never again. Collapse is purely a CSS
		// `max-height` clip on the wrap, so the click doesn't re-flow
		// figures (which is what made the previous "display:none on
		// hidden tiles" implementation read as "screenshots reshuffle"
		// after Show all).
		//
		// Strip the auto-injected layout classes from our gallery only.
		// Gutenberg's flex layout system adds `is-layout-flex` /
		// `wp-block-gallery-is-layout-flex` and a per-block container
		// class; they fight our row-aligned grid and would otherwise
		// force every layout rule to use `!important`. The filter
		// scopes the change to galleries that carry our marker class,
		// so other Gallery blocks on the page render unchanged.
		//
		// Pair the layout filter with the lightbox-metadata repair. Our
		// screenshots are external `ps.w.org` assets with synthetic
		// attachment ids, so core cannot resolve the full-resolution
		// source or dimensions the lightbox needs; fix_lightbox_metadata()
		// supplies them once core has assigned each image its render-time
		// state key. It hooks at priority 20 so it runs after core's own
		// lightbox filter (priority 15) and overrides the broken values.
		add_filter( 'render_block_core/gallery', array( __CLASS__, 'strip_layout_classes' ), 20, 1 );
		add_filter( 'render_block_core/image', array( __CLASS__, 'fix_lightbox_metadata' ), 20, 2 );
		$markup   = self::build_gallery_markup( $screenshots, $count, $dimensions );
		$rendered = do_blocks( $markup );
		remove_filter( 'render_block_core/image', array( __CLASS__, 'fix_lightbox_metadata' ), 20 );
		remove_filter( 'render_block_core/gallery', array( __CLASS__, 'strip_layout_classes' ), 20 );

		if ( $use_reveal ) {
			$rendered = self::wrap_with_show_all_button( $rendered, $count );
		}

		return $rendered;
	}

	/**
	 * Strips Gutenberg's flex layout helper classes from our gallery
	 * wrapper before it reaches the browser.
	 *
	 * The block layout system injects `is-layout-flex` and the
	 * per-block `wp-block-gallery-is-layout-flex` / `wp-block-gallery-N`
	 * classes; together they pull a flex container with row/wrap
	 * defaults that fights every CSS rule we write for the screenshot
	 * grid. Removing them at render time lets `screenshots.css` use
	 * plain (non-`!important`) selectors and keeps the change scoped
	 * to galleries that carry our `is-style-screenshots` marker — any
	 * other Gallery block on the page renders unchanged.
	 *
	 * @param string $content Rendered Gallery block markup.
	 * @return string
	 */
	public static function strip_layout_classes( $content ) {
		if ( ! is_string( $content ) || false === strpos( $content, 'is-style-screenshots' ) ) {
			return $content;
		}

		$processor = new \WP_HTML_Tag_Processor( $content );
		while ( $processor->next_tag( 'figure' ) ) {
			if ( ! $processor->has_class( 'is-style-screenshots' ) ) {
				continue;
			}

			$processor->remove_class( 'is-layout-flex' );
			$processor->remove_class( 'wp-block-gallery-is-layout-flex' );

			// Also drop `wp-block-gallery-N` — auto-generated by the
			// layout system, useful only for the styles we're disabling.
			$class_attr = $processor->get_attribute( 'class' );
			if ( is_string( $class_attr ) ) {
				$cleaned = preg_replace( '/\s*\bwp-block-gallery-\d+\b\s*/', ' ', $class_attr );
				$cleaned = trim( preg_replace( '/\s+/', ' ', $cleaned ) );
				if ( $cleaned !== $class_attr ) {
					$processor->set_attribute( 'class', $cleaned );
				}
			}
			break;
		}

		return $processor->get_updated_html();
	}

	/**
	 * Adds preconnect / dns-prefetch hints to the Photon CDN host on
	 * single-plugin pages so the browser can warm up the TLS handshake
	 * while the page HTML is still streaming. Saves ~50–150 ms on the
	 * first thumbnail paint for cold visitors. Hooked from
	 * `class-plugin-directory.php` via the `wp_resource_hints` filter.
	 *
	 * @param array  $urls          Resource hint URLs already queued for $relation_type.
	 * @param string $relation_type One of preconnect / dns-prefetch / prerender / prefetch.
	 * @return array
	 */
	public static function add_resource_hints( $urls, $relation_type ) {
		if ( ! is_singular( 'plugin' ) ) {
			return $urls;
		}

		if ( 'preconnect' === $relation_type ) {
			$urls[] = array(
				'href'        => 'https://i0.wp.com',
				'crossorigin' => 'anonymous',
			);
		} elseif ( 'dns-prefetch' === $relation_type ) {
			$urls[] = 'https://i0.wp.com';
		}

		return $urls;
	}

	/**
	 * Enqueues the shortcode's own CSS and toggle script. Assets live
	 * under the plugin's `css/` and `js/` directories alongside the
	 * other Plugin Directory stylesheets and scripts.
	 */
	protected static function enqueue_assets() {
		wp_enqueue_style(
			'wporg-plugins-screenshots',
			plugins_url( 'css/screenshots.css', __DIR__ . '/../plugin-directory.php' ),
			array(),
			(string) filemtime( __DIR__ . '/../css/screenshots.css' )
		);

		wp_enqueue_script(
			'wporg-plugins-screenshots',
			plugins_url( 'js/screenshots.js', __DIR__ . '/../plugin-directory.php' ),
			array(),
			(string) filemtime( __DIR__ . '/../js/screenshots.js' ),
			true
		);
	}

	/**
	 * Builds the block markup string for the Gallery + Image blocks.
	 *
	 * Every screenshot renders into the markup — collapse / expand is
	 * a CSS `max-height` clip on the gallery wrap, not a DOM hide. This
	 * keeps the masonry balanced across columns once and prevents the
	 * "figures reshuffle" effect the user saw on click previously.
	 *
	 * @param array $screenshots Screenshots returned by Template::get_screenshots().
	 * @param int   $count       Total screenshot count, used to size columns.
	 * @param array $dimensions  Optional `[ url => [ width, height ] ]` map
	 *                           from {@see self::get_dimensions()}; consumed by
	 *                           build_image_block() to emit intrinsic width /
	 *                           height attrs and prevent layout shift.
	 * @return string Block markup ready for do_blocks().
	 */
	protected static function build_gallery_markup( $screenshots, $count, $dimensions = array() ) {
		$inner = '';
		$index = 0;

		// Reset the lightbox metadata map for this render — build_image_block()
		// fills it as it mints each synthetic id.
		self::$lightbox_meta = array();

		foreach ( $screenshots as $screenshot_num => $screenshot ) {
			// Every figure loads eagerly — this is a detail page, not
			// a list, so users land here already committed to seeing
			// the screenshots. Lazy-loading would force a layout-shift
			// growth animation after the user clicks "Show all" and
			// would also hide the screenshots from third-party
			// scrapers / SEO crawlers that read the rendered HTML.
			// Tiles past the visible cap drop to `fetchpriority="low"`
			// so they don't fight the LCP image for bandwidth, but
			// the request still fires immediately.
			$is_above_fold = ( $index < self::REVEAL_THRESHOLD );
			$shot_url      = isset( $screenshot['src'] ) ? (string) $screenshot['src'] : '';
			$shot_dims     = ( $shot_url && isset( $dimensions[ $shot_url ] ) ) ? $dimensions[ $shot_url ] : null;
			$inner        .= self::build_image_block(
				$screenshot,
				self::SYNTHETIC_ID_OFFSET + (int) $screenshot_num,
				$is_above_fold,
				$shot_dims
			);

			++$index;
		}

		/*
		 * Layout class drives CSS rules on the gallery wrapper.
		 *
		 * `is-count-N` (1-3): tiny galleries fit a single row at full
		 * size, no anchor logic. `has-tail-1` (count % 3 == 1): when
		 * `has-uniform-aspect` is also present, the last figure spans
		 * the full row instead of orphaning in the rightmost column.
		 * Without `has-uniform-aspect` the anchor class is a no-op, so
		 * the CSS-columns brick layout is unaffected.
		 *
		 * `has-uniform-aspect` is set by self::is_uniform_aspect() —
		 * always for N=2-4, dimensions-based for N>=5 (read straight
		 * from each screenshot's `assets_screenshots` record). Default
		 * layout is the CSS-columns brick masonry that keeps every
		 * screenshot's natural aspect ratio;
		 * the uniform-aspect upgrade swaps to a row-aligned CSS grid
		 * when figures share a shape so rows do not orphan tiles. Output
		 * stays identical across viewports — fully cacheable by Varnish
		 * and object cache.
		 */
		$layout_class = '';
		if ( $count >= 1 && $count <= 3 ) {
			// Tiny galleries — `is-count-N` rules in CSS pin the column
			// count. N=1 = single tile; N=2 = row of 2; N=3 mixed flows
			// across three columns, uniform-aspect renders as 2 + 1
			// full-width.
			$layout_class = ' is-count-' . (int) $count;
		} elseif ( 1 === ( $count % 3 ) ) {
			// N=4, 7, 10, 13… — last figure anchors as full-width on
			// uniform-aspect galleries; the class is a no-op when the
			// CSS-columns brick remains the active layout (mixed
			// aspect ratios).
			$layout_class = ' has-tail-1';
		}

		if ( self::is_uniform_aspect( $screenshots ) ) {
			$layout_class .= ' has-uniform-aspect';
		}

		$gallery_attrs = wp_json_encode(
			array(
				'linkTo'    => 'none',
				'className' => trim( 'is-style-screenshots' . $layout_class ),
			)
		);

		$markup  = "<!-- wp:gallery {$gallery_attrs} -->\n";
		$markup .= '<figure class="wp-block-gallery has-nested-images columns-default is-style-screenshots' . esc_attr( $layout_class ) . '">' . "\n";
		$markup .= $inner;
		$markup .= "</figure>\n";
		$markup .= "<!-- /wp:gallery -->\n";

		return $markup;
	}

	/**
	 * Builds a single core/image block with the lightbox enabled.
	 *
	 * @param array      $screenshot    Screenshot metadata.
	 * @param int        $id            Synthetic attachment id used by the lightbox state.
	 * @param bool       $above_fold    Whether the figure sits inside the visible window
	 *                                  before the user clicks "Show all" (drives
	 *                                  fetchpriority).
	 * @param array|null $dimensions    `[ width, height ]` of the source PNG / JPEG / GIF,
	 *                                  or null when the post meta did not carry both values.
	 * @return string Image block markup.
	 */
	protected static function build_image_block( $screenshot, $id, $above_fold = false, $dimensions = null ) {
		$src     = isset( $screenshot['src'] ) ? esc_url( $screenshot['src'] ) : '';
		$caption = isset( $screenshot['caption'] ) ? (string) $screenshot['caption'] : '';

		if ( '' === $src ) {
			return '';
		}

		$alt = wp_strip_all_tags( $caption );

		$attrs = wp_json_encode(
			array(
				'id'              => $id,
				'sizeSlug'        => 'large',
				'linkDestination' => 'none',
				'lightbox'        => array( 'enabled' => true ),
			)
		);

		$srcset = self::photon_srcset( $src );
		$class  = 'wp-block-image size-large';

		// Record the full-resolution source and intrinsic dimensions for the
		// lightbox-state repair in fix_lightbox_metadata(). The grid thumbnail
		// loads a Photon-shrunk srcset candidate, so core (which has no real
		// attachment to query) would otherwise enlarge that small image; this
		// hands the lightbox the lossless original at its true size.
		self::$lightbox_meta[ (int) $id ] = array(
			'url'    => $src,
			'width'  => ( is_array( $dimensions ) && ! empty( $dimensions[0] ) ) ? (int) $dimensions[0] : 'none',
			'height' => ( is_array( $dimensions ) && ! empty( $dimensions[1] ) ) ? (int) $dimensions[1] : 'none',
		);

		// `width` and `height` ship the screenshot's intrinsic
		// dimensions so the browser reserves layout space from the
		// aspect ratio at parse time. Without them every figure
		// collapses to a one-pixel slot until the image decodes — and
		// the gallery gallops down the page as figures resolve, an
		// awful CLS story for a high-traffic page like wordpress.org/
		// plugins/{slug}/.
		$dim_attrs = '';
		if ( is_array( $dimensions ) && isset( $dimensions[0], $dimensions[1] ) && $dimensions[0] > 0 && $dimensions[1] > 0 ) {
			$dim_attrs = sprintf(
				' width="%d" height="%d"',
				(int) $dimensions[0],
				(int) $dimensions[1]
			);
		}

		// Every screenshot loads eagerly — this is a detail page where
		// the whole gallery is intentionally part of the document.
		// `fetchpriority` shapes the request order: the cap-window
		// thumbnails compete with the LCP image (high), the rest sit
		// behind them (low) so they download as bandwidth becomes
		// available without delaying the first paint.
		$priority = $above_fold ? 'high' : 'low';

		// Wrap the `<img>` in an `<a href="{src}" target="_blank">` so
		// the no-JS reader still gets a path to the full-resolution
		// screenshot. The JS path cancels only this fallback navigation;
		// core's image lightbox click handlers still receive the event.
		$figure  = '<figure class="' . esc_attr( $class ) . '">';
		$figure .= sprintf(
			'<a class="plugin-screenshots__fallback-link" href="%1$s" target="_blank" rel="noopener">',
			$src
		);
		$figure .= sprintf(
			'<img src="%1$s" alt="%2$s" class="wp-image-%3$d"%4$s%5$s loading="eager" fetchpriority="%6$s" decoding="async"/>',
			$src,
			esc_attr( $alt ),
			$id,
			$srcset,
			$dim_attrs,
			esc_attr( $priority )
		);
		$figure .= '</a>';

		if ( '' !== $caption ) {
			$figure .= sprintf(
				'<figcaption class="wp-element-caption">%s</figcaption>',
				wp_kses_post( $caption )
			);
		}

		$figure .= '</figure>';

		return "<!-- wp:image {$attrs} -->\n{$figure}\n<!-- /wp:image -->\n";
	}

	/**
	 * Repairs the core Image block lightbox state for screenshot images.
	 *
	 * Plugin screenshots are external `ps.w.org` assets with no real
	 * attachment, so each block carries a *synthetic* id (see
	 * {@see self::SYNTHETIC_ID_OFFSET}). Core's lightbox renderer
	 * (`block_core_image_render_lightbox()`, priority 15 on this same
	 * filter) treats that id as a genuine attachment:
	 *
	 *   $uploadedSrc = wp_get_attachment_url( $id );        // → false
	 *   $meta        = wp_get_attachment_metadata( $id );   // → false
	 *   $targetWidth = $meta['width']  ?? 'none';           // → 'none'
	 *   $targetHeight= $meta['height'] ?? 'none';           // → 'none'
	 *
	 * The empty `uploadedSrc` leaves the lightbox with no full-resolution
	 * image to enlarge, and the `'none'` dimensions make core's view
	 * script fall back to the *thumbnail's* natural size — which on
	 * production is a Photon-shrunk srcset candidate (≤900px, often the
	 * 300px tile). The enlarged view therefore renders tiny. On
	 * environments without Photon the thumbnail is the full-resolution
	 * original, which is why the bug is invisible on local / staging.
	 *
	 * Core keys its lightbox metadata by a per-render `uniqid()` (exposed
	 * on the figure's `data-wp-context`), not by the attachment id, so the
	 * only way to correct it is to read that generated key back out of the
	 * rendered markup and re-set the affected fields. `wp_interactivity_state()`
	 * merges with `array_replace_recursive()` (later call wins), and this
	 * filter runs at priority 20 — after core's priority-15 pass — so the
	 * corrected values override the broken ones. `lightboxSrcset` is
	 * cleared so the enlarged image loads the lossless original rather than
	 * a capped Photon candidate.
	 *
	 * @param string $block_content Rendered Image block markup.
	 * @param array  $parsed_block  Parsed block, including `attrs['id']`.
	 * @return string The unmodified markup (only interactivity state is changed).
	 */
	public static function fix_lightbox_metadata( $block_content, $parsed_block ) {
		$id = isset( $parsed_block['attrs']['id'] ) ? (int) $parsed_block['attrs']['id'] : 0;
		if ( $id < self::SYNTHETIC_ID_OFFSET || ! isset( self::$lightbox_meta[ $id ] ) ) {
			return $block_content;
		}

		if ( ! function_exists( 'wp_interactivity_state' ) ) {
			return $block_content;
		}

		// Recover the render-time state key core assigned to this image.
		$processor = new \WP_HTML_Tag_Processor( $block_content );
		if ( ! $processor->next_tag( 'figure' ) ) {
			return $block_content;
		}
		$context = $processor->get_attribute( 'data-wp-context' );
		if ( ! is_string( $context ) ) {
			return $block_content;
		}
		$decoded  = json_decode( $context, true );
		$image_id = ( is_array( $decoded ) && isset( $decoded['imageId'] ) ) ? $decoded['imageId'] : '';
		if ( '' === $image_id ) {
			return $block_content;
		}

		$meta = self::$lightbox_meta[ $id ];
		wp_interactivity_state(
			'core/image',
			array(
				'metadata' => array(
					$image_id => array(
						'uploadedSrc'    => $meta['url'],
						'targetWidth'    => $meta['width'],
						'targetHeight'   => $meta['height'],
						'lightboxSrcset' => false,
					),
				),
			)
		);

		return $block_content;
	}

	/**
	 * Wraps the rendered gallery in a reveal container with a single
	 * "Show all N screenshots" button below it. Click reveals every
	 * hidden figure with a per-tile staggered fade-in and removes the
	 * button (one-time reveal — no collapse-back affordance).
	 *
	 * The button sits below the gallery, matching the convention used
	 * by other Plugin Directory pages with long lists (see "Show more"
	 * on Description / Reviews sections).
	 *
	 * @param string $rendered_gallery Output of do_blocks() for the gallery.
	 * @param int    $count            Total number of screenshots.
	 * @return string
	 */
	protected static function wrap_with_show_all_button( $rendered_gallery, $count ) {
		$label = sprintf(
			/* translators: %s: total number of screenshots in the gallery. */
			_n( 'Show all %s screenshot', 'Show all %s screenshots', $count, 'wporg-plugins' ),
			number_format_i18n( $count )
		);

		$button = sprintf(
			'<button type="button" class="plugin-screenshots__show-all" aria-expanded="false">'
			. '<span class="plugin-screenshots__show-all-label">%1$s</span>'
			. '<span class="plugin-screenshots__show-all-chevron" aria-hidden="true"></span>'
			. '</button>',
			esc_html( $label )
		);

		// The wrap clips the gallery via `max-height` + `overflow:
		// hidden`. Fade veil and button live on the reveal root so
		// they aren't clipped by that overflow — they absolute-position
		// to the wrap's bottom edge from outside.
		return '<div class="plugin-screenshots__reveal">'
			. '<div class="plugin-screenshots__gallery-wrap">'
			. $rendered_gallery
			. '</div>'
			. '<div class="plugin-screenshots__fade" aria-hidden="true"></div>'
			. $button
			. '</div>';
	}

	/**
	 * Builds a Photon-powered `srcset` (and matching `sizes`) attribute string
	 * for a `ps.w.org` screenshot URL. Returns an empty string when the source
	 * URL is not on `ps.w.org`, so the original `src` is used unchanged.
	 *
	 * Plugin authors upload screenshots at full resolution but we render them
	 * inside a 3-column grid, so the browser otherwise downloads the full
	 * asset (often 300–800 KB) only to scale it down to a ~250 px tile.
	 * Routing the URL through `i0.wp.com` (Photon) returns a re-encoded,
	 * width-bound copy at ~10× smaller payload — see
	 * https://developer.wordpress.com/docs/photon/ for the resize/optim
	 * options. The grid thumbnail therefore loads a small Photon candidate;
	 * the lightbox is pointed back at the full-resolution `ps.w.org` original
	 * separately by {@see self::fix_lightbox_metadata()} so users still get
	 * the lossless image when they enlarge a screenshot.
	 *
	 * @param string $src Original asset URL.
	 * @return string Attribute fragment ready to interpolate into `<img>`,
	 *                including the leading space, or empty string.
	 */
	protected static function photon_srcset( $src ) {
		if ( ! preg_match( '#^https?://ps\.w\.org/#', $src ) ) {
			return '';
		}

		// Photon (i0.wp.com) only runs on production and staging. In local
		// or other environments the proxy may not be reachable, which would
		// leave the gallery silently empty until the cold cache warmed up.
		// Fall back to the unoptimised `ps.w.org` URL there.
		$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		if ( 'production' !== $env && 'staging' !== $env ) {
			return '';
		}

		$photon_base = preg_replace( '#^https?://#', 'https://i0.wp.com/', $src );
		$widths      = array( 300, 600, 900 );
		$srcset      = array();

		foreach ( $widths as $width ) {
			$srcset[] = add_query_arg( 'w', $width, $photon_base ) . ' ' . $width . 'w';
		}

		return sprintf(
			' srcset="%1$s" sizes="(max-width: 599px) 50vw, 33vw"',
			esc_attr( implode( ', ', $srcset ) )
		);
	}

	/**
	 * Whether the gallery should swap from brick masonry to row-aligned grid.
	 *
	 * The default layout is the CSS multi-column "brick" flow — every
	 * screenshot keeps its natural aspect ratio and figures stack like
	 * masonry, which is what plugin authors usually upload (a mix of
	 * portrait, landscape, and square shots). Grid is the better choice
	 * only when the figure heights match, otherwise rows leave awkward
	 * vertical gaps.
	 *
	 * Two paths:
	 *   - N=2..4: always return true. Tiny galleries do not have enough
	 *     figures for the brick effect to read, so a row-aligned grid
	 *     gives a more predictable result. Same N now renders the same
	 *     way regardless of upload — `prantorpay` and `iconic-copy-text-blocks`
	 *     are both 4-tile + 1 full-width anchor, no surprises.
	 *   - N>=5: compare aspect ratios pulled from each screenshot's
	 *     `assets_screenshots` record. Width / height land in that meta
	 *     during plugin import (`Import::enrich_asset_dimensions()`,
	 *     r14866) and propagate to every $screenshot entry via
	 *     `Template::get_screenshots()`, so the lookup is in-process
	 *     and no network call runs at render time. A missing pair on
	 *     any single screenshot trips the fallback to brick masonry —
	 *     the safer layout when shapes might disagree.
	 *
	 * @param array $screenshots Output of {@see Template::get_screenshots()}.
	 * @return bool True when the row-aligned grid layout should activate.
	 */
	private static function is_uniform_aspect( $screenshots ) {
		$count = count( $screenshots );
		if ( $count < 2 ) {
			return false;
		}
		if ( $count <= 4 ) {
			// Tiny galleries always render as a tidy grid; brick masonry
			// needs more figures to look like masonry.
			return true;
		}

		$dimensions = self::get_dimensions( $screenshots );
		if ( count( $dimensions ) !== $count ) {
			// Probe missed at least one figure — fall back to brick
			// masonry. The CSS-columns flow handles mixed aspects
			// gracefully without us guessing.
			return false;
		}

		$aspects = array();
		foreach ( $dimensions as $dim ) {
			if ( ! is_array( $dim ) || empty( $dim[1] ) ) {
				return false;
			}
			$aspects[] = (float) $dim[0] / (float) $dim[1];
		}

		$min = min( $aspects );
		$max = max( $aspects );

		return ( $min > 0 ) && ( ( $max - $min ) / $min ) < 0.05;
	}

	/**
	 * Returns `[ url => [ width, height ] ]` for every screenshot.
	 *
	 * Width and height come straight off each screenshot record — they
	 * are written into the `assets_screenshots` post meta during plugin
	 * import (`Import::enrich_asset_dimensions()`, r14866) and inlined
	 * onto every entry returned by `Template::get_screenshots()`, so the
	 * lookup is a plain array read with no I/O. Records that did not
	 * carry both dimensions (e.g. the import retrieve failed and a
	 * backfill pass has not yet run) drop out of the map; downstream
	 * code already treats missing entries as "no dimensions" and
	 * degrades to brick masonry without `<img>` width/height.
	 *
	 * @param array $screenshots Screenshots returned by Template::get_screenshots().
	 * @return array Map of screenshot URL → `[ width, height ]`. Entries
	 *               are absent for screenshots whose meta record is
	 *               missing width or height.
	 */
	private static function get_dimensions( $screenshots ) {
		$dimensions = array();

		foreach ( $screenshots as $shot ) {
			if ( empty( $shot['src'] ) || empty( $shot['width'] ) || empty( $shot['height'] ) ) {
				continue;
			}
			$dimensions[ (string) $shot['src'] ] = array( (int) $shot['width'], (int) $shot['height'] );
		}

		return $dimensions;
	}
}
