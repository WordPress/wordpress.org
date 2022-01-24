<?php

/**
 * Template Name: Global Search
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

/* See inc/page-meta-descriptions.php for the meta description for this page. */

wp_enqueue_script( 'jquery' );

get_header( 'top-level-page' );
the_post();

$terms = urldecode( wp_unslash( $_GET['s'] ?? '' ) );
$terms = htmlspecialchars_decode( $terms );
$terms = explode( '?', $terms )[0];
$terms = trim( $terms, "/ \r\n\t" );

$search_config = array(
	'div'        => 'gsce-search',
	'gname'      => 'wordpressorg-search',
	'attributes' => array(
		'queryParameterName' => 'search',	
		'linkTarget'         => '_parent',
		'enableHistory'      => false,
		'enableOrderBy'      => true,
	),
);

if ( isset( $_REQUEST['in'] ) && in_array( $_REQUEST['in'], [ 'support_forums', 'support_docs', 'developer_documentation' ] ) ) {
	$search_config['attributes']['defaultToRefinement'] = $_REQUEST['in'];
}

?>

<main id="main" class="site-main col-12" role="main">
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<div id="gsce-search" class="entry-content col-12 google-custom-search">
			<p>Loading..</p>
		</div>
	</article>
</main>

<script>
	window.__gcse = {
		parsetags: 'explicit',
		callback: function() {
			var executeSearch = function() {
				document.getElementById( 'gsce-search' ).innerHTML = '';
				google.search.cse.element.render(<?php echo json_encode( $search_config, true ); ?>);
				google.search.cse.element.getElement('wordpressorg-search').execute( <?php echo json_encode( $terms ); ?> );
			}

			if ( document.readyState == 'complete' ) {
				executeSearch();
			} else {
				google.setOnLoadCallback(function() {
					executeSearch();
				}, true);
			}

		},
		searchCallbacks: {
			web: {
				starting: function( gname, searchTerm ) {
					wporg_search_update_url( searchTerm, jQuery('.gsc-refinementBlock .gsc-tabhActive') );
				},
				rendered: function( gname, searchTerm ) {
					jQuery('.gsc-refinementBlock .gsc-tabHeader')
						.off( 'click.refinement, keypress.refinement' )
						.on( 'click.refinement, keypress.refinement', function() {
							wporg_search_update_url( searchTerm, jQuery( this ) );
						} );
				}
			}
		}
	};

	function wporg_search_update_url( term, refinement_obj = false ) {
		var refinement = 'all',
			state;
		if ( refinement_obj && refinement_obj.length ) {
			refinement = refinement_obj.text().trim().toLowerCase().replace( /[^a-z]/g, '_' );
		}
		if ( 'all' === refinement ) {
			refinement = '';
		}

		state = {
			'search': term,
			'refinement': refinement
		};

		if (
			! window.history.state ||
			window.history.state.search != state.search ||
			window.history.state.refinement != state.refinement
		) {
			wporg_record_search( term, refinement );
		}

		window.history.replaceState(
			state,
			document.title,
			'/search/' +
			encodeURIComponent( term ).replace( /%20/g, '+' ) + '/' +
			( refinement ? '?in=' + refinement : '' )
		);
	}

	function wporg_record_search( term, refinement ) {
		jQuery.post(
			'https://api.wordpress.org/search/1.0/',
			{
				term: term,
				in: refinement
			}
		);
	}

	(function() {
		var cx = '012566942813864066925:bnbfebp99hs';
		var gcse = document.createElement('script'); gcse.type = 'text/javascript'; gcse.async = true;
		gcse.src = 'https://cse.google.com/cse.js?cx=' + cx;
		var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(gcse, s);
	})();
</script>

<?php

get_footer();
