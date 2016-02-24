<!DOCTYPE html>  

<!--[if lt IE 7 ]> <html <?php language_attributes(); ?> class="no-js ie6"> <![endif]-->
<!--[if IE 7 ]>    <html <?php language_attributes(); ?> class="no-js ie7"> <![endif]-->
<!--[if IE 8 ]>    <html <?php language_attributes(); ?> class="no-js ie8"> <![endif]-->
<!--[if IE 9 ]>    <html <?php language_attributes(); ?> class="no-js ie9"> <![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--> <html <?php language_attributes(); ?> class="no-js"> <!--<![endif]-->

<head>
	<meta charset="utf-8">
	<title><?php _e( 'Browse Happy', 'browsehappy' ); ?></title>
	<meta name="description" content="<?php esc_attr_e( 'Online. Worry-free. Upgrade your browser today!', 'browsehappy' ); ?>" />
	<meta name="author" content="WordPress" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />

	<link rel="shortcut icon" href="<?php echo esc_url( home_url( 'favicon.ico' ) ); ?>" />
	<link rel="apple-touch-icon" href="<?php echo get_template_directory_uri(); ?>/imgs/apple-touch-icon-57x57.png" />
	<link rel="apple-touch-icon" sizes="72x72" href="<?php echo get_template_directory_uri(); ?>/imgs/apple-touch-icon-72x72.png" />
	<link rel="apple-touch-icon" sizes="114x114" href="<?php echo get_template_directory_uri(); ?>/imgs/apple-touch-icon-114x114.png" />

	<!--[if (gt IE 6)|!(IE)]><!-->
		<link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/style.css?4" />
		<script src="<?php echo get_template_directory_uri(); ?>/js/modernizr-1.6.min.js"></script>
		<script src="https://use.typekit.com/lsw6yis.js"></script>
		<script type="text/javascript">try{Typekit.load();}catch(e){}</script>
	<!--<![endif]-->

<?php wp_head(); ?>
</head>

<body>
<?php do_action( 'browsehappy_locale_notice' ); ?>
<div id="body-wrap">

	<header>
		<hgroup class="wrap">
			<h1><?php _e( 'Browse <em>Happy</em>', 'browsehappy' ); ?></h1>
			<h2><?php _e( 'Online. Worry-free. <em>Upgrade your browser today</em>!', 'browsehappy' ); ?></h2>
		</hgroup>
	</header>
	<?php do_action( 'browsehappy_browser_notice' ); ?>
	<div id="main">
		<ul id="browserlist" class="wrap">
<?php foreach ( browsehappy_get_browser_data() as $browser => $data ) : ?>
			<li id="<?php echo $browser; ?>">
				<a href="<?php echo esc_url( $data->url ); ?>" title="<?php echo esc_attr( $data->long_name ); ?>">
					<div class="icon"></div>
					<h2><?php echo $data->name; ?></h2>
					<p class="info"><?php echo $data->info; ?></p>
					<p class="version"><?php printf( __( 'Latest Version: %s', 'browsehappy' ), '<strong>' . apply_filters( 'get_browsehappy_version', $browser ) . '</strong>' ); ?></p>
					<p class="website"><?php _e( 'Visit website for more info', 'browsehappy' ); ?></p>
				</a>
				<?php do_action( 'browsehappy_like_button', $browser ); ?>
			</li><!-- #<?php echo $browser; ?> -->
<?php endforeach; ?>
		</ul><!-- #browserlist -->
	</div><!-- #main -->

	<footer>
		<div class="wrap">
			<section id="about">
				<h2><?php _e( 'What is Browse Happy', 'browsehappy' ); ?></h2>
				<p><?php $what = __( 'Using an outdated browser makes your computer unsafe. Browse Happy is a way for you to find out what are the latest versions of the major browsers around. You can also learn about alternative browsers that may fit you even better than the one you are currently using.', 'browsehappy' );
echo $what; ?></p>
			</section><!-- #about -->
			<section id="share">
				<h2><?php _e( 'Share the Happiness', 'browsehappy' ); ?></h2>
				<nav>
					<ul>
						<li class="twitter"><a onclick="window.open(this.href, 'twittershare', 'status=0,toolbar=0,location=0,menubar=0,directories=0,resizable=0,scrollbars=0,height=250,width=500'); return false;" href="https://twitter.com/share?url=<?php echo urlencode( home_url( '/' ) ); ?>&amp;text=<?php echo urlencode( __( 'Browse Happy: Online. Worry-free. Upgrade your browser today!', 'browsehappy' ) ); ?>" title="<?php esc_attr_e( 'Share on Twitter', 'browsehappy' ); ?>">Twitter</a></li>
<?php
$redirect_uri = home_url( '/' );
if ( isset( $_GET['locale'] ) )
	$redirect_uri = add_query_arg( 'locale', urlencode( $_GET['locale'] ), $redirect_uri );
$facebook_pieces = array(
	'app_id=180651631983617', // Browse Happy app
	'link=' . home_url( '/' ),
	'picture=' . get_template_directory_uri() . '/imgs/apple-touch-icon-114x114.png',
	'name=' . urlencode( __( 'Browse Happy', 'browsehappy' ) ),
	'description=' . urlencode( $what ),
	'message=' . urlencode( __( 'Online. Worry-free. Upgrade your browser today!', 'browsehappy' ) ),
	'display=popup',
	'redirect_uri=' . $redirect_uri,
);
?>
						<li class="facebook"><a onclick="window.open(this.href, 'fbshare', 'status=0,toolbar=0,location=0,menubar=0,directories=0,resizable=0,scrollbars=0,height=325,width=540'); return false;" href="https://www.facebook.com/dialog/feed?<?php echo implode( '&', $facebook_pieces ); ?>" title="<?php esc_attr_e( 'Share on Facebook', 'browsehappy' ); ?>">Facebook</a></li>
					</ul>
				</nav>
			</section><!-- #share -->
			<div id="byline">
				<a href="<?php echo esc_url( __( 'https://wordpress.org/', 'browsehappy' ) ); ?>" title="WordPress"><?php printf( __( 'Brought to you by %s', 'browsehappy' ), 'WordPress' ); ?></a>
			</div><!-- #byline -->
		</div>
	</footer>

</div><!-- #body-wrap -->
<?php wp_footer(); ?>
</body>
</html>
