<head profile="http://gmpg.org/xfn/11">

	<title><?php wp_title( '&middot;', true, 'right' ); bloginfo( 'name' ); ?></title>

	<meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php bloginfo( 'charset' ); ?>" />
	<meta http-equiv="Content-Style-Type" content="text/css" />
	<meta name="distribution" content="global" />
	<meta name="robots" content="follow, all" />
	<meta name="language" content="en, sv" />
	<meta name="description" content="<?php bloginfo( 'description' ); ?>" />
	<meta name="keywords" content="wordpress buddypress bbpress community support forums" />

	<link rel="home" title="Home" href="<?php bloginfo( 'url' ); ?>" />
	<link rel="index" title="Index" href="<?php bloginfo( 'url' ); ?>/about/index/" />
	<link rel="contents" title="Contents" href="<?php bloginfo( 'url' ); ?>/about/contents/" />
	<link rel="search" title="Search" href="#searchform" />
	<link rel="glossary" title="Glossary" href="<?php bloginfo( 'url' ); ?>/about/glossary/" />
	<link rel="help" title="Help" href="<?php bloginfo( 'url' ); ?>/about/help/" />
	<link rel="first" title="First" href="" />
	<link rel="last" title="Last" href="" />
	<link rel="up" title="Top" href="#top" />
	<link rel="copyright" title="Copyright" href="<?php bloginfo( 'url' ); ?>/about/copyright/" />
	<link rel="author" title="Author" href="<?php bloginfo( 'url' ); ?>/about/author/" />

	<link rel="Shortcut Icon" href="<?php bloginfo( 'url' ); ?>" />
	<link rel="alternate" type="application/rss+xml" title="RSS 2.0" href="<?php bloginfo( 'rss2_url' ); ?>" />
	<link rel="alternate" type="text/xml" title="RSS .92" href="<?php bloginfo( 'rss_url' ); ?>" />
	<link rel="alternate" type="application/atom+xml" title="Atom 0.3" href="<?php bloginfo( 'atom_url' ); ?>" />
<?php wp_get_archives( 'type=monthly&format=link' ); ?>
	<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>" />
	<?php if ( is_singular( 'post' ) ) wp_enqueue_script( 'comment-reply' ); ?>
	<?php wp_head(); ?>
</head>
