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
 * For galleries with more than `REVEAL_THRESHOLD` screenshots, the first
 * `COMPACT_VISIBLE` tiles render upfront and a single "Show all N
 * screenshots" button below the grid reveals the rest on click. The
 * button is removed after expand (one-time reveal). On mobile the
 * collapse logic is suppressed entirely — vertical scroll is natural,
 * so every screenshot renders straight away.
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
	 * Visible figure count when collapse is active.
	 *
	 * Eight figures across three columns settles into a 3+3+2 brick — the
	 * partial bottom row reads as "more below" under the fade overlay.
	 *
	 * @var int
	 */
	const COMPACT_VISIBLE = 8;

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
	 * Object-cache group for the per-plugin uniform-aspect verdict.
	 *
	 * Registered as global so the verdict survives across the multisite
	 * Plugin Directory subsites (mirrors the `wporg-plugins` group set up
	 * in {@see Plugin_Directory::init()}).
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'plugin-screenshot-aspect';

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

		// Probe every screenshot for its real width / height once and
		// pass the result down. `get_dimensions()` keeps a Memcached
		// cache keyed by an md5 of the URL list so the network round-
		// trip happens at most once per screenshot revision; on
		// subsequent renders the call costs a single Memcached lookup.
		// Knowing the dimensions lets every `<img>` ship with `width`
		// and `height` attributes — the browser reserves slot height
		// from the intrinsic aspect ratio, so the gallery has zero
		// layout shift no matter how slowly the screenshots decode.
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
		add_filter( 'render_block_core/gallery', array( __CLASS__, 'strip_layout_classes' ), 20, 1 );
		$markup   = self::build_gallery_markup( $screenshots, $count, $dimensions );
		$rendered = do_blocks( $markup );
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
	 * Enqueues the shortcode's own CSS and toggle script.
	 *
	 * Assets live next to the shortcode (under `shortcodes/assets/`) so the
	 * Plugin Directory's screenshot UX stays self-contained and does not
	 * leak into the active theme.
	 */
	protected static function enqueue_assets() {
		$css_rel = 'shortcodes/assets/screenshots.css';
		$js_rel  = 'shortcodes/assets/screenshots.js';

		$plugin_dir  = dirname( __DIR__ );
		$plugin_main = $plugin_dir . '/plugin-directory.php';

		$css_path = $plugin_dir . '/' . $css_rel;
		$js_path  = $plugin_dir . '/' . $js_rel;

		wp_enqueue_style(
			'wporg-plugins-screenshots',
			plugins_url( $css_rel, $plugin_main ),
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0'
		);

		wp_enqueue_script(
			'wporg-plugins-screenshots',
			plugins_url( $js_rel, $plugin_main ),
			array(),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1.0',
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
	 * @return string Block markup ready for do_blocks().
	 */
	protected static function build_gallery_markup( $screenshots, $count, $dimensions = array() ) {
		$inner = '';
		$index = 0;

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
		 * always for N=2-4, probe-based for N>=5 (server-side detection
		 * cached in Memcached). Default layout is the CSS-columns brick
		 * masonry that keeps every screenshot's natural aspect ratio;
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
	 *                                  or null when the probe failed.
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

		$figure  = '<figure class="' . esc_attr( $class ) . '">';
		$figure .= sprintf(
			'<img src="%1$s" alt="%2$s" class="wp-image-%3$d"%4$s%5$s loading="eager" fetchpriority="%6$s" decoding="async"/>',
			$src,
			esc_attr( $alt ),
			$id,
			$srcset,
			$dim_attrs,
			esc_attr( $priority )
		);

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
	 * options. The lightbox `src` (the unprefixed `ps.w.org` URL) stays the
	 * full-resolution original so users get the lossless image when they
	 * enlarge a screenshot.
	 *
	 * @param string $src Original asset URL.
	 * @return string Attribute fragment ready to interpolate into `<img>`,
	 *                including the leading space, or empty string.
	 */
	protected static function photon_srcset( $src ) {
		if ( ! preg_match( '#^https?://ps\.w\.org/#', $src ) ) {
			return '';
		}

		// Photon (i0.wp.com) is a production-only optimisation. In local
		// or staging environments the proxy may not be reachable, which
		// would leave the gallery silently empty until the cold cache
		// warmed up. Fall back to the unoptimised `ps.w.org` URL there.
		$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		if ( 'production' !== $env ) {
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
	 *   - N>=5: probe the screenshots. Each URL gets a partial
	 *     `Range: bytes=0-32767` GET in parallel via the Requests
	 *     transport — enough for PNG / JPEG / GIF dimensions to land in
	 *     header bytes. The verdict is keyed by an MD5 of the URL list,
	 *     so a screenshot revision bump (the `?rev=` query arg changes)
	 *     auto-invalidates the cache; on subsequent renders only a
	 *     Memcached lookup runs.
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
	 * Network probes run via the WordPress HTTP API's Requests
	 * transport and read the dimensions out of the header bytes
	 * (Range: bytes=0-32767). The verdict is keyed by an md5 of the
	 * URL list and cached in Memcached for a day; a screenshot revision
	 * bump (the `?rev=` query arg changes) auto-invalidates the cache.
	 * On a partial probe failure the method caches the partial result
	 * for an hour so a transient network blip doesn't lock us into a
	 * "no dimensions" state until the next revision.
	 *
	 * @param array $screenshots Screenshots returned by Template::get_screenshots().
	 * @return array Map of screenshot URL → `[ width, height ]`. Missing
	 *               entries indicate the probe failed for that URL.
	 */
	private static function get_dimensions( $screenshots ) {
		$urls = array();
		foreach ( $screenshots as $shot ) {
			if ( ! empty( $shot['src'] ) ) {
				$urls[] = (string) $shot['src'];
			}
		}

		if ( empty( $urls ) ) {
			return array();
		}

		wp_cache_add_global_groups( self::CACHE_GROUP );

		$signature = md5( implode( '|', $urls ) );
		$cache_key = 'dims_' . $signature;

		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$dimensions = self::fetch_dimensions( $urls );

		// Cache for a day on full success, for an hour on partial
		// probe failure so retries pick up changes inside the day.
		$ttl = ( count( $dimensions ) === count( $urls ) ) ? DAY_IN_SECONDS : HOUR_IN_SECONDS;
		wp_cache_set( $cache_key, $dimensions, self::CACHE_GROUP, $ttl );

		return $dimensions;
	}

	/**
	 * Fetches the first 32 KB of every screenshot URL in parallel and
	 * parses the image dimensions out of the header bytes. Returns
	 * `[ url => [ width, height ] ]`; URLs whose probe failed are
	 * absent from the result.
	 *
	 * @param array $urls Screenshot URLs.
	 * @return array
	 */
	private static function fetch_dimensions( $urls ) {
		$dimensions = array();

		// `\WpOrg\Requests\Requests` is canonical in WP 6.2+; the
		// `\Requests` global remains as a back-compat alias. Prefer
		// the namespaced class so this code keeps working when the
		// alias is eventually removed.
		if ( class_exists( '\\WpOrg\\Requests\\Requests' ) ) {
			$requests_class = '\\WpOrg\\Requests\\Requests';
		} elseif ( class_exists( '\\Requests' ) ) {
			$requests_class = '\\Requests';
		} else {
			return $dimensions;
		}

		$requests = array();
		// Preserve URL keys so the response loop can correlate each
		// reply back to its source URL — the result map needs URL =>
		// [ w, h ] for downstream consumers (img attrs, layout
		// detection).
		foreach ( $urls as $url ) {
			$requests[ $url ] = array(
				'url'     => $url,
				'type'    => 'GET',
				'headers' => array( 'Range' => 'bytes=0-32767' ),
			);
		}

		try {
			$responses = $requests_class::request_multiple(
				$requests,
				array(
					'timeout'         => 3,
					'connect_timeout' => 2,
					'useragent'       => 'WordPress.org Plugin Directory',
					'redirects'       => 2,
				)
			);
		} catch ( \Exception $e ) {
			return $dimensions;
		}

		foreach ( $responses as $url => $response ) {
			if ( ! is_object( $response ) || ! isset( $response->body ) ) {
				continue;
			}
			// Range request → 206 Partial Content; some hosts return
			// 200 with the whole body when they ignore the header.
			$body = (string) $response->body;
			if ( '' === $body ) {
				continue;
			}
			$dim = self::parse_image_dimensions( $body );
			if ( $dim && $dim[0] > 0 && $dim[1] > 0 ) {
				$dimensions[ $url ] = array( (int) $dim[0], (int) $dim[1] );
			}
		}

		return $dimensions;
	}

	/**
	 * Reads the width and height from an image's header bytes, without
	 * touching GD or the PHP warning surface that `getimagesizefromstring`
	 * emits on partial / malformed input. Supports PNG, JPEG, and GIF —
	 * the formats Plugin Directory accepts as screenshots.
	 *
	 * @param string $bytes Raw image bytes (the first 32 KB is enough).
	 * @return array|false `[ $width, $height ]` on success, false otherwise.
	 */
	private static function parse_image_dimensions( $bytes ) {
		$length = strlen( $bytes );
		if ( $length < 24 ) {
			return false;
		}

		// PNG: 8-byte signature, then an IHDR chunk that encodes width
		// and height as big-endian uint32 starting at byte 16.
		if ( "\x89PNG\r\n\x1a\n" === substr( $bytes, 0, 8 ) ) {
			$header = unpack( 'Nwidth/Nheight', substr( $bytes, 16, 8 ) );
			if ( $header && $header['width'] > 0 && $header['height'] > 0 ) {
				return array( $header['width'], $header['height'] );
			}
			return false;
		}

		// GIF: "GIF87a" or "GIF89a" signature, then width/height as
		// little-endian uint16 at bytes 6 and 8.
		$gif_sig = substr( $bytes, 0, 6 );
		if ( 'GIF87a' === $gif_sig || 'GIF89a' === $gif_sig ) {
			$header = unpack( 'vwidth/vheight', substr( $bytes, 6, 4 ) );
			if ( $header && $header['width'] > 0 && $header['height'] > 0 ) {
				return array( $header['width'], $header['height'] );
			}
			return false;
		}

		// JPEG: 0xFFD8 SOI marker, then a stream of segments. The first
		// SOFn marker (0xFFC0-0xFFC3, 0xFFC5-0xFFC7, 0xFFC9-0xFFCB,
		// 0xFFCD-0xFFCF) carries height + width as big-endian uint16
		// at offsets 5 and 7 inside the segment.
		if ( "\xFF\xD8" === substr( $bytes, 0, 2 ) ) {
			$offset = 2;
			while ( $offset + 8 < $length ) {
				if ( "\xFF" !== $bytes[ $offset ] ) {
					return false;
				}
				$marker = ord( $bytes[ $offset + 1 ] );
				++$offset;
				// Skip 0xFF padding bytes between segments.
				while ( 0xFF === $marker && $offset < $length ) {
					$marker = ord( $bytes[ $offset ] );
					++$offset;
				}
				$is_sof = (
					( $marker >= 0xC0 && $marker <= 0xC3 ) ||
					( $marker >= 0xC5 && $marker <= 0xC7 ) ||
					( $marker >= 0xC9 && $marker <= 0xCB ) ||
					( $marker >= 0xCD && $marker <= 0xCF )
				);
				if ( $is_sof && $offset + 7 < $length ) {
					$dim = unpack( 'nheight/nwidth', substr( $bytes, $offset + 3, 4 ) );
					if ( $dim && $dim['width'] > 0 && $dim['height'] > 0 ) {
						return array( $dim['width'], $dim['height'] );
					}
					return false;
				}
				if ( $offset + 1 >= $length ) {
					return false;
				}
				$segment_length = unpack( 'nlen', substr( $bytes, $offset, 2 ) );
				if ( ! $segment_length || $segment_length['len'] < 2 ) {
					return false;
				}
				$offset += $segment_length['len'];
			}
			return false;
		}

		return false;
	}
}
