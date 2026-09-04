<?php
/**
 * Super 'basic' Trac oEmbed handler.
 * 
 * This allows for WordPress Core & Meta trac to be embedded onto WordPress sites.
 * Supported endpoints:
 *  - /ticket/
 *  - /changeset/
 *  - /ticketgraph
 *  - /milestone/
 * 
 * Can be enabled on a site by adding:
 *  - wp_oembed_add_provider( '#https://(meta|core)\.trac\.wordpress\.org/.*#', 'https://api.wordpress.org/dotorg/trac/oembed/?api_key=...', true );
 * 
 * oEmbed Discovery is not enabled, as although adding the tag to trac is possible, it requires inline Javascript.
 * 
 * Please do not abuse this API, otherwise an API KEY will become required.
 */
include dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-init.php';

// Avoid warnings from DomDocument.
libxml_use_internal_errors( true );

// Mark this as an oEmbed response for caching.
header( 'X-WP-Embed: true' );

$url = $_GET['url'] ?? '';
$url = is_string( $url ) ? wp_unslash( $url ) : '';

header( 'Allow: GET' );
header( 'Expires: ' . gmdate( 'D, d M Y H:i:s \G\M\T', time() + HOUR_IN_SECONDS ), true );

// meta|core are the only tracs embedable.
$allowed_hosts = [
	'core.trac.wordpress.org',
	'meta.trac.wordpress.org',
];

if (
	! $url ||
	'GET' !== $_SERVER['REQUEST_METHOD'] ||
	! in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ), $allowed_hosts, true )
) {
	header( 'HTTP/1.1 404 Not Found', true, 404 );
	die();
}

// milestone|ticketgraph|ticket|changeset are the only endpoints allowable.
$trac_baseurl = '^(?P<baseurl>https://(?P<trac>meta|core)\.trac\.wordpress\.org/)';
$allowed_urls = [
	'!' . $trac_baseurl . '(?P<type>ticket|changeset)/\d+$!iD',
	'!' . $trac_baseurl . '(?P<type>query)[?].+$!iD',
	'!' . $trac_baseurl . '(?P<type>milestone)/[a-z0-9.]+[ ]?[a-z0-9.]*$!iD',
	'!' . $trac_baseurl . '(?P<type>ticketgraph)([?]component=[^&]+)?$!iD',
];

$m = [];
foreach ( $allowed_urls as $allowed_url ) {
	if ( preg_match( $allowed_url, $url, $m ) ) {
		break;
	}
}

if ( ! $m ) {
	header( 'HTTP/1.1 404 Not Found', true, 404 );
	die();
}

$type = $m['type'];

// Reject Trac output-format selectors (e.g. ?format=csv), which return non-HTML bytes rather than an embeddable page.
$query_args = [];
wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query_args );
if ( array_key_exists( 'format', $query_args ) && '' !== $query_args['format'] ) {
	header( 'HTTP/1.1 404 Not Found', true, 404 );
	die();
}

// if not iframe embed, respond with oembed payload.
if ( ! isset( $_GET['embed'] ) ) {
	header( 'Content-Type: application/json; charset=UTF-8' );

	// Unique ID for this instance of the iframe
	$id = sha1( $url . microtime() );

	$embed = [
		'version'       => '1.0',
		'provider_name' => 'WordPress.org Trac',
		'provider_url'  => $m['baseurl'],
		'title'         => 'WordPress.org Trac',
		'type'          => 'rich',
		'width'         => 600,
		'height'        => 300,
		'html'          => '',
	];

	// Default milestone embeds to 120px height.
	if ( 'milestone' === $type ) {
		$embed['height'] = 120;
	}

	$embed_url = add_query_arg(
		[
			'url'   => urlencode( $url ),
			'embed' => 'true',
		],
		'https://api.wordpress.org/dotorg/trac/oembed/'
	);

	if ( ! empty( $_GET['api_key'] ) ) {
		$embed_url = add_query_arg( 'api_key', wp_unslash( $_GET['api_key'] ), $embed_url );
	}

	$embed_url .= '#el=' . $id;

	$html = sprintf(
		'<iframe sandbox="allow-scripts allow-top-navigation-by-user-activation" security="restricted" src="%s" id="%s" width="%d" height="%d" title="WordPress.org Trac" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" class="wp-embedded-content wporg-trac"></iframe>',
		esc_url( $embed_url ),
		esc_attr( 'el-' . $id ),
		esc_attr( $embed['width'] ),
		esc_attr( $embed['height'] )
	);

	$html .= sprintf(
		// Note: Cannot have &
		'<script type="text/javascript">
		(function(id) {
			var el = document.getElementById( "el-" + id );
			window.addEventListener( "message", function(e) {
				if ( ! e.data ) return;
				if ( ! e.data.el || e.data.el != id ) return;
				if ( ! e.data.height ) return;

				el.height = e.data.height;
			}, false );
			el.contentWindow.postMessage( { action: "send" }, "*" );
		})("%s");
		</script>',
		esc_attr( $id ),
	);

	$embed['html'] = $html;

	echo wp_json_encode( $embed );
	die();
}

// The remainder of this request outputs the HTML document displayed within the iframe.
header( 'Content-Type: text/html; charset=UTF-8' );
header( 'X-Content-Type-Options: nosniff' );

// The iframe's sandbox covers that element, not this response, which is reachable directly.
header( 'Content-Security-Policy: sandbox allow-scripts allow-top-navigation-by-user-activation' );

$cache_key = sha1( $url );
if ( $data = wp_cache_get( $cache_key, 'trac-oembed' ) ) {
	die( $data );
}

$response = wp_safe_remote_get(
	$url,
	[
		'user_agent'          => 'WordPress.org Trac oEmbed; https://api.wordpress.org/dotorg/trac/oembed',
		'timeout'             => 15,
		'limit_response_size' => 500 * KB_IN_BYTES,
	]
);

$html = wp_remote_retrieve_body( $response );
// A duplicated header comes back as an array; every value must declare HTML.
$content_types = [];
foreach ( (array) wp_remote_retrieve_header( $response, 'content-type' ) as $content_type ) {
	// Reduce to the bare media type, e.g. `text/html; charset=utf-8` => `text/html`.
	$content_types[] = strtolower( trim( explode( ';', (string) $content_type )[0] ) );
}

if (
	! $html ||
	200 !== wp_remote_retrieve_response_code( $response ) ||
	// Only reparse what Trac serves as HTML — anything else could become executable markup on this origin.
	[ 'text/html' ] !== array_unique( $content_types ) ||
	(
		! str_starts_with( $html, '<' ) &&
		str_contains( $html, 'TracError: ' )
	)
) {
	$output = '<h1>Temporarily Unavailable</h1>';
	wp_cache_set( $cache_key, $output, 'trac-oembed', MINUTE_IN_SECONDS );
	die( $output );
}

$doc = new DOMDocument();
$doc->loadHTML( $html );

// IDs of elements to remove
$remove_elements = [
	'headline', 'banner', 'mainnav',
	'ctxtnav', 'help', 'altlinks',
	'prefs',
	'wporg-global-header-script-js',
	'wporg-global-header-script-js-extra',
	'wporg-global-header-footer-css',
];

// Tags, with optional SINGLE class specification to just strip out.
$remove_tags = [
	'form',
	'header.global-header',
	'footer.global-footer',
];

// Additional elements per type of page.
switch ( $type ) {
	case 'milestone':
		$remove_elements[] = 'stats';
		break;
	case 'ticket':
		$remove_elements[] = 'changelog';
		$remove_elements[] = 'attachments';

		// Remove the 'Change History' element, which doesn't have an ID
		foreach ( $doc->getElementById( 'content' )->childNodes as $node ) {
			if ( false !== stripos( $node->textContent, 'Change History' ) ) {
				$node->parentNode->removeChild( $node );
				break;
			}
		}
		break;
	case 'query':
		$remove_tags[] = 'h1';
		$remove_tags[] = 'h2';
		$remove_tags[] = 'div.paging';
		break;
}

// Remove any elements that are not needed.
foreach ( $remove_elements as $id ) {
	$el = $doc->getElementById( $id );
	if ( $el ) {
		$el->parentNode->removeChild( $el );
	}
}

// Remove any tags
foreach ( $remove_tags as $tag ) {
	$class = false;
	if ( str_contains( $tag, '.' ) ) {
		list( $tag, $class ) = explode( '.', $tag );
	}

	$elements_to_remove = [];
	foreach ( $doc->getElementsByTagName( $tag ) as $el ) {
		if ( $class && ! str_contains( $el->getAttribute( 'class' ), $class ) ) {
			continue;
		}

		$elements_to_remove[] = $el;
	}

	foreach ( $elements_to_remove as $el ) {
		$el->parentNode->removeChild( $el );
	}
}

// Ensure all URLs are absolute to the trac host.
$rewrite_attrs = [
	'script' => 'src',
	'link'   => 'href',
	'a'      => 'href',
	'img'    => 'src',
];
foreach ( $rewrite_attrs as $tag => $attr ) {
	foreach ( $doc->getElementsByTagName( $tag ) as $el ) {
		$v = (string) $el->getAttribute( $attr );
		if (
			! $v ||
			false !== strpos( $v, '://' ) ||
			0 === strpos( $v, '//' )
		) {
			continue;
		}

		$new = WP_Http::make_absolute_url( $v, $url );
		if ( $new !== $v ) {
			$el->setAttribute( $attr, $new );
		}
	}
}

// Ensure all links target the parent window.
foreach ( $doc->getElementsByTagName( 'a' ) as $el ) {
	$el->setAttribute( 'target', '_top' );
}

// Remove wp-trac.js, we don't need it here - It alters the page too much and adds elements on load.
$elements_to_remove = [];
foreach ( $doc->getElementsByTagName( 'script' ) as $script ) {
	$src = (string) $script->getAttribute( 'src' );

	if (
		false !== stripos( $src, 'wp-trac.js' ) ||
		false !== stripos( $script->textContent, 'wpTrac' )
	) {
		$elements_to_remove[] = $script;
	}
}
foreach ( $elements_to_remove as $el ) {
	$el->parentNode->removeChild( $el );
}

// Add a script to the header.
$js = <<<JS
(function() {
	var id = ( document.location.hash.match(/el=([0-9a-f]+)(&|$)/) || [ '', '' ] )[1];

	function send() {
		window.parent.postMessage( {
			height: Math.max( document.getElementsByTagName('html')[0].offsetHeight, document.documentElement.offsetHeight ),
			el: id
		}, '*' );
	}

	window.addEventListener( 'message', send );
	window.addEventListener( 'DOMNodeInserted', send );
	window.addEventListener( 'load', send );
	window.addEventListener( 'DOMContentLoaded', send );
})();
JS;

$reporter = $doc->createElement( 'script' );
$reporter->appendChild( $doc->createTextNode( $js ) );
$doc->getElementsByTagName( 'head' )[0]->appendChild( $reporter );

$css = <<<CSS
html {
	--wp-global-header-height: 0;
	--wp-admin--admin-bar--height: 0;
}
CSS;
$doc->getElementsByTagName( 'head' )[0]->appendChild( $doc->createElement( 'style', $css ) );

// Finally add a CSS class to target.
$body = $doc->getElementsByTagName( 'body' )[0];
$body->setAttribute( 'class', $body->getAttribute( 'class' ) . ' is-oembed' );

$data = $doc->saveHTML();

wp_cache_set( $cache_key, $data, 'trac-oembed', HOUR_IN_SECONDS );

echo $data;