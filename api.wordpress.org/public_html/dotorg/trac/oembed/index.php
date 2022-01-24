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
 *  - wp_oembed_add_provider( '#https://(meta|core)\.trac\.wordpress.org/.*#', 'https://api.wordpress.org/dotorg/trac/oembed/?api_key=...' );
 * 
 * oEmbed Discovery is not enabled, as although adding the tag to trac is possible, it requires inline Javascript.
 * 
 * Please do not abuse this API, otherwise an API KEY will become required.
 */
include dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-init.php';

// Mark this as an oEmbed response for caching.
header( 'X-WP-Embed: true' );

$url = wp_unslash( $_REQUEST['url'] ?? '' );

header( 'Allow: GET' );
header( 'Expires: ' . gmdate( 'D, d M Y H:i:s \G\M\T', time() + HOUR_IN_SECONDS ), true );

if (
	// meta|core are the only tracs embedable.
	// milestone|ticketgraph|ticket|changeset are the only endpoints allowable.
	! preg_match( '!^(?P<baseurl>https://(?P<trac>meta|core).trac.wordpress.org/)(?P<type>milestone|ticketgraph|ticket|changeset)([/?]|$)!i', $url, $m ) ||
	'GET' !== $_SERVER['REQUEST_METHOD']
) {
	header( 'HTTP/1.1 404 Not Found', true, 404 );
	die();
}

$type = $m['type'];

// if not iframe embed, respond with oembed payload.
if ( ! isset( $_GET['embed'] ) ) {
	header( 'Content-Type: application/json; charset=UTF-8' );

	// Unique ID for this instance of the iframe
	$id = sha1( $url . microtime() );

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
		'<iframe sandbox="allow-scripts allow-top-navigation-by-user-activation" security="restricted" src="%s" id="%s" width="600" height="300" title="WordPress.org Trac" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" class="wp-embedded-content wporg-trac"></iframe>',
		esc_url( $embed_url ),
		esc_attr( 'el-' . $id )
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

	$embed = [
		'version'       => '1.0',
		'provider_name' => 'WordPress.org Trac',
		'provider_url'  => $m['baseurl'],
		'title'         => 'WordPress.org Trac',
		'type'          => 'rich',
		'width'         => 600,
		'height'        => 300,
		'html'          => $html,
	];

	// Default milestone embeds to 120px.
	if ( 'milestone' === $type ) {
		$embed['height'] = 120;
	}

	echo wp_json_encode( $embed );
	die();
}

$cache_key = sha1( $url );
if ( $data = wp_cache_get( $cache_key, 'trac-oembed' ) ) {
	die( $data );
}

$html = wp_remote_retrieve_body(
	wp_safe_remote_get(
		$url,
		[
			'user_agent'          => 'WordPress.org Trac oEmbed; https://api.wordpress.org/dotorg/trac/oembed',
			'timeout'             => 15,
			'limit_response_size' => 500 * KB_IN_BYTES,
		]
	)
);

if ( ! $html ) {
	$output = '<h1>Temporarily Unavailable</h1>';
	wp_cache_set( $cache_key, $output, 'trac-oembed', MINUTE_IN_SECONDS );
	die( $output );
}

$doc = new DOMDocument();
$doc->loadHTML( $html );

// IDs of elements to remove
$remove_elements = [
	'wporg-header', 'wporg-footer',
	'headline', 'banner', 'mainnav',
	'ctxtnav', 'help', 'altlinks',
	'prefs',
];

// Tags to just strip out.
$remove_tags = [
	'form'
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
	foreach ( $doc->getElementsByTagName( $tag ) as $el ) {
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
// Recursive as it can't iterate over the document while altering it properly.
do {
	$removed = 0;
	foreach ( $doc->getElementsByTagName( 'script' ) as $script ) {
		$src = (string) $script->getAttribute( 'src' );

		if (
			false !== stripos( $src, 'wp-trac.js' ) ||
			false !== stripos( $script->textContent, 'wpTrac' )
		) {
			$script->parentNode->removeChild( $script );
			$removed++;
		}
	}
} while ( $removed );

// Add a script to the header.
$js = <<<JS
(function() {
	var id = ( document.location.hash.match(/el=([0-9a-f]+)(&|$)/) || [ '', '' ] )[1];

	function send() {
		window.parent.postMessage( {
			height: document.getElementById('main').offsetHeight || 0,
			el: id
		}, '*' );
	}

	window.addEventListener( 'message', send );
	window.addEventListener( 'DOMNodeInserted', send );
	window.addEventListener( 'load', send );
	window.addEventListener( 'DOMContentLoaded', send );
})();
JS;
$doc->getElementsByTagName( 'head' )[0]->appendChild( $doc->createElement( 'script', $js ) );

$data = $doc->saveHTML();

wp_cache_set( $cache_key, $data, 'trac-oembed', HOUR_IN_SECONDS );

echo $data;