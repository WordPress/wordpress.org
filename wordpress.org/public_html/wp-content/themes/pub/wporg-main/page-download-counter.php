<?php
/**
 * Template Name: Download -> Counter
 *
 * Page template for displaying the download counter.
 *
 * @package WordPressdotorg\MainTheme
 */

// phpcs:disable WordPress.WP.EnqueuedResources, WordPress.CSRF.NonceVerification.NoNonceVerification, WordPress.VIP.SuperGlobalInputUsage.AccessDetected

$branch = WP_CORE_STABLE_BRANCH;

if (
	isset( $_GET['branch'] )
	&& preg_match( '/^[0-9]\.[0-9]$/', wp_unslash( $_GET['branch'] ), $matches ) // phpcs:ignore WordPress.VIP
	&& version_compare( WP_CORE_STABLE_BRANCH, $matches[0], '>' )
) {
	$branch = $matches[0];
}

// phpcs:ignore WordPress.VIP.DirectDatabaseQuery
$num = $wpdb->get_var( $wpdb->prepare(
	'SELECT SUM(downloads) FROM download_counts WHERE `release` LIKE %s AND `release` NOT LIKE %s',
	$wpdb->esc_like( $branch ) . '%',
	'%-%'
) );

if ( ! empty( $_GET['ajaxupdate'] ) ) {
	header( 'Link: <' . get_permalink()  . '>; rel="canonical"' );

	die( esc_html( number_format_i18n( $num ) ) );
}

if ( ! empty( $_GET['json'] ) ) {
	header( 'Content-Type: application/json' );
	header( 'Link: <' . get_permalink() . '>; rel="canonical"' );

	?>
	{"wpcounter": {
	"branch": "<?php echo esc_js( $branch ); ?>",
	"downloads": "<?php echo esc_js( $num ); ?>"
	}}
	<?php
	die;
}

if ( WP_CORE_STABLE_BRANCH === $branch ) {
	/* translators: 1: version number; 2: download count; */
	$text = __( 'WordPress %1$s has been&nbsp;downloaded %2$s times', 'wporg' );

	/* translators: 1: date; 2: version number; 3: download count; */
	$meta_desc_text = __( 'As of %1$s, WordPress %2$s has been downloaded over %3$s times - watch that number increase in real time!', 'wporg' );
} else {
	/* translators: 1: version number; 2: download count; */
	$text = __( 'WordPress %1$s was&nbsp;downloaded %2$s times', 'wporg' );

	/* translators: 1: date; 2: version number; 3: download count; */
	$meta_desc_text = __( 'As of %1$s, WordPress %2$s was downloaded over %3$s times.', 'wporg' );
}

// Page meta description
$meta_desc_text = sprintf(
	$meta_desc_text,
	date_i18n( 'F jS Y' ),
	$branch,
	number_format_i18n( $num )
);

// Remove some headers we don't need:
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_head', 'wp_print_styles', 8 );
remove_action( 'wp_head', 'wp_print_head_scripts', 9 );
remove_action( 'wp_head', '_admin_bar_bump_cb' );
remove_action( 'wp_body_open', 'wp_admin_bar_render', 0 );

// Call the get_header action to similate get_header() being called. It's required for some translation support.
do_action( 'get_header' );

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta content="charset=utf-8"/>
	<title><?php echo esc_html( wp_get_document_title() ); ?></title>
	<link href="//fonts.googleapis.com/css?family=Open+Sans:300" rel="stylesheet" type="text/css">
	<meta name="viewport" content="width=device-width,initial-scale=1.0">
	<meta name="description" content="<?php echo esc_attr( $meta_desc_text ); ?>">
	<style type="text/css">
		html,
		body {
			height: 100%;
			margin: 0;
		}

		body {
			color: #464646;
			font-family: "Open Sans", sans-serif;
			font-weight: 300;
			font-size: 1em;
			background: #f1f1f1;
		}

		a {
			color: #0074a2;
			font-size: 0.7em;
			text-decoration: none;
		}

		a:hover {
			color: #2ea2cc;
		}

		h1 {
			font-weight: normal;
			font-size: 1.5em;
		}

		#wporg-skip-link {
			display: none;
		}

		#numnumnum, #numnumnum2 {
			color: #2ea2cc;
			float: none;
			font-family: Georgia, "Times New Roman", Times, serif;
			margin: 0 auto;
			position: absolute;
			left: 0;
			right: 0;
			text-align: center;
			width: 100%;
		}

		#numnumnum2 {
			display: none;
		}

		#wrap {
			color: black;
			height: 1.35em;
			font-size: 2em;
			font-weight: normal;
			margin: 0.25em 0;
		}

		.something-semantic {
			display: table;
			height: 100%;
			width: 100%;
		}

		.something-else-semantic {
			display: table-cell;
			text-align: center;
			vertical-align: middle;
		}

		.counter-inner {
			border: 1px solid #e5e5e5;
			-webkit-box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
			box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
			background: #fff;
			margin: 0 4%;
			padding: 6% 10%;
		}

		@media screen and (min-width: 360px) and (min-height: 400px) {
			body {
				font-size: 125%;
			}
		}

		@media screen and (min-width: 500px) and (min-height: 400px) {
			body {
				font-size: 150%;
			}

			#wrap {
				font-size: 2.25em;
			}
		}

		@media screen and (min-width: 600px) and (min-height: 500px) {
			body {
				font-size: 175%;
			}
		}

		@media screen and (min-width: 700px) and (min-height: 700px) {
			body {
				font-size: 200%;
			}
		}

		@media screen and (min-width: 900px) and (min-height: 900px) {
			body {
				font-size: 225%;
			}

			#wrap {
				font-size: 2.5em;
			}
		}

		@media screen and (min-width: 1200px) and (min-height: 1000px) {
			body {
				font-size: 300%;
			}

			.counter-inner {
				padding: 4% 10%;
			}
		}

		@media screen and (min-width: 1800px) and (min-height: 1200px) {
			body {
				font-size: 350%;
			}

			.counter-inner {
				padding: 4% 10%;
			}
		}
	</style>
	<?php wp_head(); ?>
</head>

<body>
	<?php wp_body_open(); ?>
	<div class="something-semantic">
		<div class="something-else-semantic">
			<div class="counter-inner">
				<h1>
					<?php
					printf(
						esc_html( $text ),
						esc_html( $branch ),
						wp_kses_post( '<div id="wrap"><span id="numnumnum">' . number_format_i18n( $num ) . '</span><span id="numnumnum2"></span></div>' )
					);
					?>
				</h1>
				<p>
					<a href="<?php echo esc_url( home_url( '/download/' ) ); ?>"><?php esc_html_e( '&larr; Back to WordPress.org', 'wporg' ); ?></a>
				</p>
			</div>
		</div>
	</div>

	<script type="text/javascript" src="//code.jquery.com/jquery-latest.min.js"></script>
	<script type="text/javascript">
		jQuery( function( $ ) {
			var numnums = $( '#numnumnum, #numnumnum2' ),
				numnumpos = $( '#numnumnum' ).position(),
				dataLen = $( '#numnumnum' ).text().length,

				recenter = function( data ) {
					var visible = numnums.filter( ':visible' );

					visible.fadeOut( 500 ).queue( function() {
						visible.css( {
							position: 'absolute',
							display: 'inline',
							visibility: 'hidden',
						} ).html( data );

						numnumpos = visible.position();

						numnums.css( {
							position: 'absolute',
							display: 'none',
							visibility: 'visible',
						} );

						visible.dequeue();
					} ).fadeIn( 350 );
				};

			numnums.css( {
				position: 'absolute',
			} );

			<?php if ( WP_CORE_STABLE_BRANCH === $branch ) : ?>
			setInterval( function() {
				$.post( '/download/counter/?ajaxupdate=1', function( data ) {
					if ( data.length !== dataLen ) {
						recenter( data );
						dataLen = data.length;
					} else {
						if ( $( '#numnumnum2' ).is( ':hidden' ) ) {
							$( '#numnumnum' ).fadeOut( 500 );
							$( '#numnumnum2' ).html( data ).fadeIn( 350 );
						} else {
							$( '#numnumnum2' ).fadeOut( 500 );
							$( '#numnumnum' ).html( data ).fadeIn( 350 );
						}
					}
				} );
			}, 4000 );
			<?php endif; ?>
		} );
	</script>
</body>
</html>
