<?php
/**
 * The template for displaying the Dashicons resource page
 *
 * Template Name: Dashicons Resource
 *
 * @package wporg-developer
 */

wp_enqueue_style(  'dashicons-page', get_template_directory_uri() . '/stylesheets/page-dashicons.css', array(), '20200427' );
wp_enqueue_script( 'dashicons-page', get_template_directory_uri() . '/js/page-dashicons.js', array( 'jquery', 'wp-util' ), '20200427' );

get_header(); ?>

	<div id="content-area" <?php body_class( 'dashicons-page' ); ?>>
		<?php while ( have_posts() ) : the_post(); ?>
		<main id="main" <?php post_class( 'site-main' ); ?> role="main">

			<div class="details clear">
				<div id="glyph"></div>

				<div class="entry-content">
					<?php the_content(); ?>
				</div><!-- .entry-content -->

				<div class="icon-filter">
					<input placeholder="<?php esc_attr_e( 'Filter&hellip;', 'wporg' ); ?>" name="search" id="search" type="text" value="" maxlength="150">
				</div>

			</div>

			<div id="icons">
				<div id="iconlist">

					<h4><?php _e( 'Admin Menu', 'wporg' ); ?></h4>

					<!-- admin menu -->
					<div alt="f333" class="dashicons dashicons-menu">menu</div>
					<div alt="f228" class="dashicons dashicons-menu-alt">menu (alt)</div>
					<div alt="f329" class="dashicons dashicons-menu-alt2">menu (alt2)</div>
					<div alt="f349" class="dashicons dashicons-menu-alt3">menu (alt3)</div>
					<div alt="f319" class="dashicons dashicons-admin-site">site</div>
					<div alt="f11d" class="dashicons dashicons-admin-site-alt">site (alt)</div>
					<div alt="f11e" class="dashicons dashicons-admin-site-alt2">site (alt2)</div>
					<div alt="f11f" class="dashicons dashicons-admin-site-alt3">site (alt3)</div>
					<div alt="f226" class="dashicons dashicons-dashboard">dashboard</div>
					<div alt="f109" class="dashicons dashicons-admin-post">post</div>
					<div alt="f104" class="dashicons dashicons-admin-media">media</div>
					<div alt="f103" class="dashicons dashicons-admin-links">links</div>
					<div alt="f105" class="dashicons dashicons-admin-page">page</div>
					<div alt="f101" class="dashicons dashicons-admin-comments">comments</div>
					<div alt="f100" class="dashicons dashicons-admin-appearance">appearance</div>
					<div alt="f106" class="dashicons dashicons-admin-plugins">plugins</div>
					<div alt="f485" class="dashicons dashicons-plugins-checked">plugins checked</div>
					<div alt="f110" class="dashicons dashicons-admin-users">users</div>
					<div alt="f107" class="dashicons dashicons-admin-tools">tools</div>
					<div alt="f108" class="dashicons dashicons-admin-settings">settings</div>
					<div alt="f112" class="dashicons dashicons-admin-network">network</div>
					<div alt="f102" class="dashicons dashicons-admin-home">home</div>
					<div alt="f111" class="dashicons dashicons-admin-generic">generic</div>
					<div alt="f148" class="dashicons dashicons-admin-collapse">collapse</div>
					<div alt="f536" class="dashicons dashicons-filter">filter</div>
					<div alt="f540" class="dashicons dashicons-admin-customizer">customizer</div>
					<div alt="f541" class="dashicons dashicons-admin-multisite">multisite</div>

					<h4><?php _e( 'Welcome Screen', 'wporg' ); ?></h4>

					<!-- welcome screen -->
					<div alt="f119" class="dashicons dashicons-welcome-write-blog">write blog</div>
					<!--<div alt="f119" class="dashicons dashicons-welcome-edit-page"></div> Duplicate -->
					<div alt="f133" class="dashicons dashicons-welcome-add-page">add page</div>
					<div alt="f115" class="dashicons dashicons-welcome-view-site">view site</div>
					<div alt="f116" class="dashicons dashicons-welcome-widgets-menus">widgets and menus</div>
					<div alt="f117" class="dashicons dashicons-welcome-comments">comments</div>
					<div alt="f118" class="dashicons dashicons-welcome-learn-more">learn more</div>

					<h4><?php _e( 'Post Formats', 'wporg' ); ?></h4>

					<!-- post formats -->
					<!--<div alt="f109" class="dashicons dashicons-format-standard"></div> Duplicate -->
					<div alt="f123" class="dashicons dashicons-format-aside">aside</div>
					<div alt="f128" class="dashicons dashicons-format-image">image</div>
					<div alt="f161" class="dashicons dashicons-format-gallery">gallery</div>
					<div alt="f126" class="dashicons dashicons-format-video">video</div>
					<div alt="f130" class="dashicons dashicons-format-status">status</div>
					<div alt="f122" class="dashicons dashicons-format-quote">quote</div>
					<!--<div alt="f103" class="dashicons dashicons-format-links">links</div> Duplicate -->
					<div alt="f125" class="dashicons dashicons-format-chat">chat</div>
					<div alt="f127" class="dashicons dashicons-format-audio">audio</div>
					<div alt="f306" class="dashicons dashicons-camera">camera</div>
					<div alt="f129" class="dashicons dashicons-camera-alt">camera (alt)</div>
					<div alt="f232" class="dashicons dashicons-images-alt">images (alt)</div>
					<div alt="f233" class="dashicons dashicons-images-alt2">images (alt 2)</div>
					<div alt="f234" class="dashicons dashicons-video-alt">video (alt)</div>
					<div alt="f235" class="dashicons dashicons-video-alt2">video (alt 2)</div>
					<div alt="f236" class="dashicons dashicons-video-alt3">video (alt 3)</div>

					<h4><?php _e( 'Media', 'wporg' ); ?></h4>

					<!-- media -->
					<div alt="f501" class="dashicons dashicons-media-archive">archive</div>
					<div alt="f500" class="dashicons dashicons-media-audio">audio</div>
					<div alt="f499" class="dashicons dashicons-media-code">code</div>
					<div alt="f498" class="dashicons dashicons-media-default">default</div>
					<div alt="f497" class="dashicons dashicons-media-document">document</div>
					<div alt="f496" class="dashicons dashicons-media-interactive">interactive</div>
					<div alt="f495" class="dashicons dashicons-media-spreadsheet">spreadsheet</div>
					<div alt="f491" class="dashicons dashicons-media-text">text</div>
					<div alt="f490" class="dashicons dashicons-media-video">video</div>
					<div alt="f492" class="dashicons dashicons-playlist-audio">audio playlist</div>
					<div alt="f493" class="dashicons dashicons-playlist-video">video playlist</div>
					<div alt="f522" class="dashicons dashicons-controls-play">play player</div>
					<div alt="f523" class="dashicons dashicons-controls-pause">player pause</div>
					<div alt="f519" class="dashicons dashicons-controls-forward">player forward</div>
					<div alt="f517" class="dashicons dashicons-controls-skipforward">player skip forward</div>
					<div alt="f518" class="dashicons dashicons-controls-back">player back</div>
					<div alt="f516" class="dashicons dashicons-controls-skipback">player skip back</div>
					<div alt="f515" class="dashicons dashicons-controls-repeat">player repeat</div>
					<div alt="f521" class="dashicons dashicons-controls-volumeon">player volume on</div>
					<div alt="f520" class="dashicons dashicons-controls-volumeoff">player volume off</div>

					<h4><?php _e( 'Image Editing', 'wporg' ); ?></h4>

					<!-- image editing -->
					<div alt="f165" class="dashicons dashicons-image-crop">crop</div>
					<div alt="f531" class="dashicons dashicons-image-rotate">rotate</div>
					<div alt="f166" class="dashicons dashicons-image-rotate-left">rotate left</div>
					<div alt="f167" class="dashicons dashicons-image-rotate-right">rotate right</div>
					<div alt="f168" class="dashicons dashicons-image-flip-vertical">flip vertical</div>
					<div alt="f169" class="dashicons dashicons-image-flip-horizontal">flip horizontal</div>
					<div alt="f533" class="dashicons dashicons-image-filter">filter</div>
					<div alt="f171" class="dashicons dashicons-undo">undo</div>
					<div alt="f172" class="dashicons dashicons-redo">redo</div>

					<h4><?php _e( 'Databases', 'wporg' ); ?></h4>

					<!-- databases -->
					<div alt="f170" class="dashicons dashicons-database-add">database add</div>
					<div alt="f17e" class="dashicons dashicons-database">database</div>
					<div alt="f17a" class="dashicons dashicons-database-export">database export</div>
					<div alt="f17b" class="dashicons dashicons-database-import">database import</div>
					<div alt="f17c" class="dashicons dashicons-database-remove">database remove</div>
					<div alt="f17d" class="dashicons dashicons-database-view">database view</div>

					<h4><?php _e( 'Block Editor', 'wporg' ); ?></h4>

					<!-- block editor -->
					<div alt="f134" class="dashicons dashicons-align-full-width">align full width</div>
					<div alt="f10a" class="dashicons dashicons-align-pull-left">align pull left</div>
					<div alt="f10b" class="dashicons dashicons-align-pull-right">align pull right</div>
					<div alt="f11b" class="dashicons dashicons-align-wide">align wide</div>
					<div alt="f12b" class="dashicons dashicons-block-default">block default</div>
					<div alt="f11a" class="dashicons dashicons-button">button</div>
					<div alt="f137" class="dashicons dashicons-cloud-saved">cloud saved</div>
					<div alt="f13b" class="dashicons dashicons-cloud-upload">cloud upload</div>
					<div alt="f13c" class="dashicons dashicons-columns">columns</div>
					<div alt="f13d" class="dashicons dashicons-cover-image">cover image</div>
					<div alt="f11c" class="dashicons dashicons-ellipsis">ellipsis</div>
					<div alt="f13e" class="dashicons dashicons-embed-audio">embed audio</div>
					<div alt="f13f" class="dashicons dashicons-embed-generic">embed generic</div>
					<div alt="f144" class="dashicons dashicons-embed-photo">embed photo</div>
					<div alt="f146" class="dashicons dashicons-embed-post">embed post</div>
					<div alt="f149" class="dashicons dashicons-embed-video">embed video</div>
					<div alt="f14a" class="dashicons dashicons-exit">exit</div>
					<div alt="f10e" class="dashicons dashicons-heading">heading</div>
					<div alt="f14b" class="dashicons dashicons-html">html</div>
					<div alt="f14c" class="dashicons dashicons-info-outline">info outline</div>
					<div alt="f10f" class="dashicons dashicons-insert">insert</div>
					<div alt="f14d" class="dashicons dashicons-insert-after">insert after</div>
					<div alt="f14e" class="dashicons dashicons-insert-before">insert before</div>
					<div alt="f14f" class="dashicons dashicons-remove">remove</div>
					<div alt="f15e" class="dashicons dashicons-saved">saved</div>
					<div alt="f150" class="dashicons dashicons-shortcode">shortcode</div>
					<div alt="f151" class="dashicons dashicons-table-col-after">table col after</div>
					<div alt="f152" class="dashicons dashicons-table-col-before">table col before</div>
					<div alt="f15a" class="dashicons dashicons-table-col-delete">table col delete</div>
					<div alt="f15b" class="dashicons dashicons-table-row-after">table row after</div>
					<div alt="f15c" class="dashicons dashicons-table-row-before">table row before</div>
					<div alt="f15d" class="dashicons dashicons-table-row-delete">table row delete</div>

					<h4><?php _e( 'TinyMCE', 'wporg' ); ?></h4>

					<!-- tinymce -->
					<div alt="f200" class="dashicons dashicons-editor-bold">bold</div>
					<div alt="f201" class="dashicons dashicons-editor-italic">italic</div>
					<div alt="f203" class="dashicons dashicons-editor-ul">ul</div>
					<div alt="f204" class="dashicons dashicons-editor-ol">ol</div>
					<div alt="f12c" class="dashicons dashicons-editor-ol-rtl">ol rtl</div>
					<div alt="f205" class="dashicons dashicons-editor-quote">quote</div>
					<div alt="f206" class="dashicons dashicons-editor-alignleft">alignleft</div>
					<div alt="f207" class="dashicons dashicons-editor-aligncenter">aligncenter</div>
					<div alt="f208" class="dashicons dashicons-editor-alignright">alignright</div>
					<div alt="f209" class="dashicons dashicons-editor-insertmore">insertmore</div>
					<div alt="f210" class="dashicons dashicons-editor-spellcheck">spellcheck</div>
					<!-- <div alt="f211" class="dashicons dashicons-editor-distractionfree"></div> Duplicate -->
					<div alt="f211" class="dashicons dashicons-editor-expand">expand</div>
					<div alt="f506" class="dashicons dashicons-editor-contract">contract</div>
					<div alt="f212" class="dashicons dashicons-editor-kitchensink">kitchen sink</div>
					<div alt="f213" class="dashicons dashicons-editor-underline">underline</div>
					<div alt="f214" class="dashicons dashicons-editor-justify">justify</div>
					<div alt="f215" class="dashicons dashicons-editor-textcolor">textcolor</div>
					<div alt="f216" class="dashicons dashicons-editor-paste-word">paste</div>
					<div alt="f217" class="dashicons dashicons-editor-paste-text">paste</div>
					<div alt="f218" class="dashicons dashicons-editor-removeformatting">remove formatting</div>
					<div alt="f219" class="dashicons dashicons-editor-video">video</div>
					<div alt="f220" class="dashicons dashicons-editor-customchar">custom character</div>
					<div alt="f221" class="dashicons dashicons-editor-outdent">outdent</div>
					<div alt="f222" class="dashicons dashicons-editor-indent">indent</div>
					<div alt="f223" class="dashicons dashicons-editor-help">help</div>
					<div alt="f224" class="dashicons dashicons-editor-strikethrough">strikethrough</div>
					<div alt="f225" class="dashicons dashicons-editor-unlink">unlink</div>
					<div alt="f320" class="dashicons dashicons-editor-rtl">rtl</div>
					<div alt="f10c" class="dashicons dashicons-editor-ltr">ltr</div>
					<div alt="f474" class="dashicons dashicons-editor-break">break</div>
					<div alt="f475" class="dashicons dashicons-editor-code">code</div>
					<!-- <div alt="f494" class="dashicons dashicons-editor-code-duplicate">code</div> Duplicate -->
					<div alt="f476" class="dashicons dashicons-editor-paragraph">paragraph</div>
					<div alt="f535" class="dashicons dashicons-editor-table">table</div>

					<h4><?php _e( 'Posts Screen', 'wporg' ); ?></h4>

					<!-- posts -->
					<div alt="f135" class="dashicons dashicons-align-left">align left</div>
					<div alt="f136" class="dashicons dashicons-align-right">align right</div>
					<div alt="f134" class="dashicons dashicons-align-center">align center</div>
					<div alt="f138" class="dashicons dashicons-align-none">align none</div>
					<div alt="f160" class="dashicons dashicons-lock">lock</div>
					<!-- <div alt="f315" class="dashicons dashicons-lock-duplicate">lock</div> Duplicate -->
					<div alt="f528" class="dashicons dashicons-unlock">unlock</div>
					<div alt="f145" class="dashicons dashicons-calendar">calendar</div>
					<div alt="f508" class="dashicons dashicons-calendar-alt">calendar</div>
					<div alt="f177" class="dashicons dashicons-visibility">visibility</div>
					<div alt="f530" class="dashicons dashicons-hidden">hidden</div>
					<div alt="f173" class="dashicons dashicons-post-status">post status</div>
					<div alt="f464" class="dashicons dashicons-edit">edit pencil</div>
					<div alt="f182" class="dashicons dashicons-trash">trash remove delete</div>
					<div alt="f537" class="dashicons dashicons-sticky">sticky</div>

					<h4><?php _e( 'Sorting', 'wporg' ); ?></h4>

					<!-- sorting -->
					<div alt="f504" class="dashicons dashicons-external">external</div>
					<div alt="f142" class="dashicons dashicons-arrow-up">arrow-up</div>
					<!-- <div alt="f143" class="dashicons dashicons-arrow-up-duplicate">arrow up duplicate</div> Duplicate -->
					<div alt="f140" class="dashicons dashicons-arrow-down">arrow-down</div>
					<div alt="f139" class="dashicons dashicons-arrow-right">arrow-right</div>
					<div alt="f141" class="dashicons dashicons-arrow-left">arrow-left</div>
					<div alt="f342" class="dashicons dashicons-arrow-up-alt">arrow-up</div>
					<div alt="f346" class="dashicons dashicons-arrow-down-alt">arrow-down</div>
					<div alt="f344" class="dashicons dashicons-arrow-right-alt">arrow-right</div>
					<div alt="f340" class="dashicons dashicons-arrow-left-alt">arrow-left</div>
					<div alt="f343" class="dashicons dashicons-arrow-up-alt2">arrow-up</div>
					<div alt="f347" class="dashicons dashicons-arrow-down-alt2">arrow-down</div>
					<div alt="f345" class="dashicons dashicons-arrow-right-alt2">arrow-right</div>
					<div alt="f341" class="dashicons dashicons-arrow-left-alt2">arrow-left</div>
					<div alt="f156" class="dashicons dashicons-sort">sort</div>
					<div alt="f229" class="dashicons dashicons-leftright">left right</div>
					<div alt="f503" class="dashicons dashicons-randomize">randomize shuffle</div>
					<div alt="f163" class="dashicons dashicons-list-view">list view</div>
					<div alt="f164" class="dashicons dashicons-excerpt-view">excerpt view</div>
					<div alt="f509" class="dashicons dashicons-grid-view">grid view</div>
					<div alt="f545" class="dashicons dashicons-move">move</div>

					<h4><?php _e( 'Social', 'wporg' ); ?></h4>

					<!-- social -->
					<div alt="f237" class="dashicons dashicons-share">share</div>
					<div alt="f240" class="dashicons dashicons-share-alt">share</div>
					<div alt="f242" class="dashicons dashicons-share-alt2">share</div>
					<div alt="f303" class="dashicons dashicons-rss">rss</div>
					<div alt="f465" class="dashicons dashicons-email">email</div>
					<div alt="f466" class="dashicons dashicons-email-alt">email (alt)</div>
					<div alt="f467" class="dashicons dashicons-email-alt2">email (alt2)</div>
					<div alt="f325" class="dashicons dashicons-networking">networking social</div>
					<div alt="f162" class="dashicons dashicons-amazon">amazon</div>
					<div alt="f304" class="dashicons dashicons-facebook">facebook social</div>
					<div alt="f305" class="dashicons dashicons-facebook-alt">facebook social</div>
					<div alt="f18b" class="dashicons dashicons-google">google social</div>
					<!-- <div alt="f462" class="dashicons dashicons-googleplus">googleplus social</div> Defunct -->
					<div alt="f12d" class="dashicons dashicons-instagram">instagram social</div>
					<div alt="f18d" class="dashicons dashicons-linkedin">linkedin social</div>
					<div alt="f192" class="dashicons dashicons-pinterest">pinterest social</div>
					<div alt="f19c" class="dashicons dashicons-podio">podio</div>
					<div alt="f195" class="dashicons dashicons-reddit">reddit social</div>
					<div alt="f196" class="dashicons dashicons-spotify">spotify social</div>
					<div alt="f199" class="dashicons dashicons-twitch">twitch social</div>
					<div alt="f301" class="dashicons dashicons-twitter">twitter social</div>
					<div alt="f302" class="dashicons dashicons-twitter-alt">twitter social</div>
					<div alt="f19a" class="dashicons dashicons-whatsapp">whatsapp social</div>
					<div alt="f19d" class="dashicons dashicons-xing">xing</div>
					<div alt="f19b" class="dashicons dashicons-youtube">youtube social</div>

					<h4><?php _e( 'WordPress.org Specific: Jobs, Profiles, WordCamps', 'wporg' ); ?></h4>

					<!-- WPorg specific icons: Jobs, Profiles, WordCamps -->
					<div alt="f308" class="dashicons dashicons-hammer">hammer development</div>
					<div alt="f309" class="dashicons dashicons-art">art design</div>
					<div alt="f310" class="dashicons dashicons-migrate">migrate migration</div>
					<div alt="f311" class="dashicons dashicons-performance">performance</div>
					<div alt="f483" class="dashicons dashicons-universal-access">universal access accessibility</div>
					<div alt="f507" class="dashicons dashicons-universal-access-alt">universal access accessibility</div>
					<div alt="f486" class="dashicons dashicons-tickets">tickets</div>
					<div alt="f484" class="dashicons dashicons-nametag">nametag</div>
					<div alt="f481" class="dashicons dashicons-clipboard">clipboard</div>
					<div alt="f487" class="dashicons dashicons-heart">heart</div>
					<div alt="f488" class="dashicons dashicons-megaphone">megaphone</div>
					<div alt="f489" class="dashicons dashicons-schedule">schedule</div>
					<div alt="f10d" class="dashicons dashicons-tide">Tide</div>
					<div alt="f124" class="dashicons dashicons-rest-api">REST API</div>
					<div alt="f13a" class="dashicons dashicons-code-standards">code standards</div>

					<h4><?php _e( 'Buddicons' ); ?></h4>

					<!-- BuddyPress and bbPress specific icons -->
					<div alt="f452" class="dashicons dashicons-buddicons-activity">activity</div>
					<div alt="f477" class="dashicons dashicons-buddicons-bbpress-logo">bbPress logo</div>
					<div alt="f448" class="dashicons dashicons-buddicons-buddypress-logo">BuddyPress logo</div>
					<div alt="f453" class="dashicons dashicons-buddicons-community">community</div>
					<div alt="f449" class="dashicons dashicons-buddicons-forums">forums</div>
					<div alt="f454" class="dashicons dashicons-buddicons-friends">friends</div>
					<div alt="f456" class="dashicons dashicons-buddicons-groups">groups</div>
					<div alt="f457" class="dashicons dashicons-buddicons-pm">private message</div>
					<div alt="f451" class="dashicons dashicons-buddicons-replies">replies</div>
					<div alt="f450" class="dashicons dashicons-buddicons-topics">topics</div>
					<div alt="f455" class="dashicons dashicons-buddicons-tracking">tracking</div>

					<h4><?php _e( 'Products', 'wporg' ); ?></h4>

					<!-- internal/products -->
					<div alt="f120" class="dashicons dashicons-wordpress">WordPress</div>
					<div alt="f324" class="dashicons dashicons-wordpress-alt">WordPress</div>
					<div alt="f157" class="dashicons dashicons-pressthis">press this</div>
					<div alt="f463" class="dashicons dashicons-update">update</div>
					<div alt="f113" class="dashicons dashicons-update-alt">update (alt)</div>
					<div alt="f180" class="dashicons dashicons-screenoptions">screenoptions</div>
					<div alt="f348" class="dashicons dashicons-info">info</div>
					<div alt="f174" class="dashicons dashicons-cart">cart shopping</div>
					<div alt="f175" class="dashicons dashicons-feedback">feedback form</div>
					<div alt="f176" class="dashicons dashicons-cloud">cloud</div>
					<div alt="f326" class="dashicons dashicons-translation">translation language</div>

					<h4><?php _e( 'Taxonomies', 'wporg' ); ?></h4>

					<!-- taxonomies -->
					<div alt="f323" class="dashicons dashicons-tag">tag</div>
					<div alt="f318" class="dashicons dashicons-category">category</div>

					<h4><?php _e( 'Widgets', 'wporg' ); ?></h4>

					<!-- widgets -->
					<div alt="f480" class="dashicons dashicons-archive">archive</div>
					<div alt="f479" class="dashicons dashicons-tagcloud">tagcloud</div>
					<div alt="f478" class="dashicons dashicons-text">text</div>

					<h4><?php _e( 'Notifications', 'wporg' ); ?></h4>

					<!-- alerts/notifications/flags -->
					<div alt="f16d" class="dashicons dashicons-bell">bell</div>
					<div alt="f147" class="dashicons dashicons-yes">yes check checkmark</div>
					<div alt="f12a" class="dashicons dashicons-yes-alt">yes check checkmark (alt)</div>
					<div alt="f158" class="dashicons dashicons-no">no x</div>
					<div alt="f335" class="dashicons dashicons-no-alt">no x</div>
					<div alt="f132" class="dashicons dashicons-plus">plus add increase</div>
					<div alt="f502" class="dashicons dashicons-plus-alt">plus add increase</div>
					<div alt="f543" class="dashicons dashicons-plus-alt2">plus add increase</div>
					<div alt="f460" class="dashicons dashicons-minus">minus decrease</div>
					<div alt="f153" class="dashicons dashicons-dismiss">dismiss</div>
					<div alt="f159" class="dashicons dashicons-marker">marker</div>
					<div alt="f155" class="dashicons dashicons-star-filled">filled star</div>
					<div alt="f459" class="dashicons dashicons-star-half">half star</div>
					<div alt="f154" class="dashicons dashicons-star-empty">empty star</div>
					<div alt="f227" class="dashicons dashicons-flag">flag</div>
					<div alt="f534" class="dashicons dashicons-warning">warning</div>

					<h4><?php _e( 'Misc', 'wporg' ); ?></h4>

					<!-- misc/cpt -->
					<div alt="f230" class="dashicons dashicons-location">location pin</div>
					<div alt="f231" class="dashicons dashicons-location-alt">location</div>
					<div alt="f178" class="dashicons dashicons-vault">vault safe</div>
					<div alt="f332" class="dashicons dashicons-shield">shield</div>
					<div alt="f334" class="dashicons dashicons-shield-alt">shield</div>
					<div alt="f468" class="dashicons dashicons-sos">sos help</div>
					<div alt="f179" class="dashicons dashicons-search">search</div>
					<div alt="f181" class="dashicons dashicons-slides">slides</div>
					<div alt="f121" class="dashicons dashicons-text-page">text page</div>
					<div alt="f183" class="dashicons dashicons-analytics">analytics</div>
					<div alt="f184" class="dashicons dashicons-chart-pie">pie chart</div>
					<div alt="f185" class="dashicons dashicons-chart-bar">bar chart</div>
					<div alt="f238" class="dashicons dashicons-chart-line">line chart</div>
					<div alt="f239" class="dashicons dashicons-chart-area">area chart</div>
					<div alt="f307" class="dashicons dashicons-groups">groups</div>
					<div alt="f338" class="dashicons dashicons-businessman">businessman</div>
					<div alt="f12f" class="dashicons dashicons-businesswoman">businesswoman</div>
					<div alt="f12e" class="dashicons dashicons-businessperson">businessperson</div>
					<div alt="f336" class="dashicons dashicons-id">id</div>
					<div alt="f337" class="dashicons dashicons-id-alt">id</div>
					<div alt="f312" class="dashicons dashicons-products">products</div>
					<div alt="f313" class="dashicons dashicons-awards">awards</div>
					<div alt="f314" class="dashicons dashicons-forms">forms</div>
					<div alt="f473" class="dashicons dashicons-testimonial">testimonial</div>
					<div alt="f322" class="dashicons dashicons-portfolio">portfolio</div>
					<div alt="f330" class="dashicons dashicons-book">book</div>
					<div alt="f331" class="dashicons dashicons-book-alt">book</div>
					<div alt="f316" class="dashicons dashicons-download">download</div>
					<div alt="f317" class="dashicons dashicons-upload">upload</div>
					<div alt="f321" class="dashicons dashicons-backup">backup</div>
					<div alt="f469" class="dashicons dashicons-clock">clock</div>
					<div alt="f339" class="dashicons dashicons-lightbulb">lightbulb</div>
					<div alt="f482" class="dashicons dashicons-microphone">microphone mic</div>
					<div alt="f472" class="dashicons dashicons-desktop">desktop monitor</div>
					<div alt="f547" class="dashicons dashicons-laptop">laptop</div>
					<div alt="f471" class="dashicons dashicons-tablet">tablet ipad</div>
					<div alt="f470" class="dashicons dashicons-smartphone">smartphone iphone</div>
					<div alt="f525" class="dashicons dashicons-phone">phone</div>
					<div alt="f510" class="dashicons dashicons-index-card">index card</div>
					<div alt="f511" class="dashicons dashicons-carrot">carrot food vendor</div>
					<div alt="f512" class="dashicons dashicons-building">building</div>
					<div alt="f513" class="dashicons dashicons-store">store</div>
					<div alt="f514" class="dashicons dashicons-album">album</div>
					<div alt="f527" class="dashicons dashicons-palmtree">palm tree</div>
					<div alt="f524" class="dashicons dashicons-tickets-alt">tickets (alt)</div>
					<div alt="f526" class="dashicons dashicons-money">money</div>
					<div alt="f18e" class="dashicons dashicons-money-alt">money alt</div>
					<div alt="f328" class="dashicons dashicons-smiley">smiley smile</div>
					<div alt="f529" class="dashicons dashicons-thumbs-up">thumbs up</div>
					<div alt="f542" class="dashicons dashicons-thumbs-down">thumbs down</div>
					<div alt="f538" class="dashicons dashicons-layout">layout</div>
					<div alt="f546" class="dashicons dashicons-paperclip">paperclip</div>
					<div alt="f131" class="dashicons dashicons-color-picker">color picker</div>
					<div alt="f327" class="dashicons dashicons-edit-large">edit large</div>
					<div alt="f186" class="dashicons dashicons-edit-page">edit page</div>
					<div alt="f15f" class="dashicons dashicons-airplane">airplane</div>
					<div alt="f16a" class="dashicons dashicons-bank">bank</div>
					<div alt="f16c" class="dashicons dashicons-beer">beer</div>
					<div alt="f16e" class="dashicons dashicons-calculator">calculator</div>
					<div alt="f16b" class="dashicons dashicons-car">car</div>
					<div alt="f16f" class="dashicons dashicons-coffee">coffee</div>
					<div alt="f17f" class="dashicons dashicons-drumstick">drumstick</div>
					<div alt="f187" class="dashicons dashicons-food">food</div>
					<div alt="f188" class="dashicons dashicons-fullscreen-alt">fullscreen alt</div>
					<div alt="f189" class="dashicons dashicons-fullscreen-exit-alt">fullscreen exit alt</div>
					<div alt="f18a" class="dashicons dashicons-games">games</div>
					<div alt="f18c" class="dashicons dashicons-hourglass">hourglass</div>
					<div alt="f18f" class="dashicons dashicons-open-folder">open folder</div>
					<div alt="f190" class="dashicons dashicons-pdf">pdf</div>
					<div alt="f191" class="dashicons dashicons-pets">pets</div>
					<div alt="f193" class="dashicons dashicons-printer">printer</div>
					<div alt="f194" class="dashicons dashicons-privacy">privacy</div>
					<div alt="f198" class="dashicons dashicons-superhero">superhero</div>
					<div alt="f197" class="dashicons dashicons-superhero-alt">superhero</div>

				</div>

			</div>

			<div id="instructions">

				<h3><?php _e( 'WordPress Usage', 'wporg' ); ?></h3>

				<p><?php  printf(
					__( 'Admin menu items can be added with <code><a href="%s">register_post_type()</a></code> and <code><a href="%s">add_menu_page()</a></code>, which both have an option to set an icon. To show the current icon, you should pass in %s.', 'wporg' ),
					'https://developer.wordpress.org/reference/functions/register_post_type/',
					'https://developer.wordpress.org/reference/functions/add_menu_page/',
					'<code>\'dashicons-<span id="wp-class-example">{icon}</span>\'</code>'
				); ?></p>

				<h4><?php _e( 'Examples', 'wporg' ); ?></h4>

				<p><?php printf(
					__( 'In <code><a href="%s">register_post_type()</a></code>, set <code>menu_icon</code> in the arguments array.', 'wporg' ),
					'https://developer.wordpress.org/reference/functions/register_post_type/'
				); ?></p>

<pre>&lt;?php
/**
 * Register the Product post type with a Dashicon.
 *
 * @see register_post_type()
 */
function wpdocs_create_post_type() {
    register_post_type( 'acme_product',
        array(
            'labels' => array(
                'name'          => __( 'Products', 'textdomain' ),
                'singular_name' => __( 'Product', 'textdomain' )
            ),
            'public'      => true,
            'has_archive' => true,
            'menu_icon'   => 'dashicons-products',
        )
    );
}
add_action( 'init', 'wpdocs_create_post_type', 0 );
</pre>

				<p><?php printf(
					__( 'The function <code><a href="%s">add_menu_page()</a></code> accepts a parameter after the callback function for an icon URL, which can also accept a dashicons class.', 'wporg' ),
					'https://developer.wordpress.org/reference/functions/add_menu_page/'
				); ?></p>

<pre>&lt;?php
/**
 * Register a menu page with a Dashicon.
 *
 * @see add_menu_page()
 */
function wpdocs_add_my_custom_menu() {
    // Add an item to the menu.
    add_menu_page(
        __( 'My Page', 'textdomain' ),
        __( 'My Title', 'textdomain' ),
        'manage_options',
        'my-page',
        'my_admin_page_function',
        'dashicons-admin-media'
    );
}</pre>

				<h3><?php _e( 'CSS/HTML Usage', 'wporg' ); ?></h3>

				<p><?php _e( "If you want to use dashicons in the admin outside of the menu, there are two helper classes you can use. These are <code>dashicons-before</code> and <code>dashicons</code>, and they can be thought of as setting up dashicons (since you still need your icon's class, too).", 'wporg' ); ?></p>

				<h4><?php _e( 'Examples', 'wporg' ); ?></h4>

				<p><?php _e( 'Adding an icon to a header, with the <code>dashicons-before</code> class. This can be added right to the element with text.', 'wporg' ); ?></p>

<pre>
&lt;h2 class="dashicons-before dashicons-smiley"&gt;<?php _e( 'A Cheerful Headline', 'wporg' ); ?>&lt;/h2&gt;
</pre>

				<p><?php _e( 'Adding an icon to a header, with the <code>dashicons</code> class. Note that here, you need extra markup specifically for the icon.', 'wporg' ); ?></p>

<pre>
&lt;h2&gt;&lt;span class="dashicons dashicons-smiley"&gt;&lt;/span&gt; <?php _e( 'A Cheerful Headline', 'wporg' ); ?>&lt;/h2&gt;
</pre>

				<h3><?php _e( 'Photoshop Usage', 'wporg' ); ?></h3>

				<p><?php _e( 'Use the .OTF version of the font for Photoshop mockups, the web-font versions won\'t work. For most accurate results, pick the "Sharp" font smoothing.', 'wporg' ); ?></p>

			</div><!-- /#instructions -->

		</main><!-- #main -->

		<!-- Required for the Copy Glyph functionality -->
		<div id="temp" style="display:none;"></div>

		<script type="text/html" id="tmpl-glyphs">
			<div class="dashicons {{data.cssClass}}"></div>
			<div class="info">
				<span><strong>{{data.sectionName}}</strong></span>
				<span class="name"><code>{{data.cssClass}}</code></span>
				<span class="link"><a href='javascript:dashicons.copy( "content: \"\\{{data.attr}}\";", "css" )'><?php _e( 'Copy CSS', 'wporg' ); ?></a></span>
				<span class="link"><a href="javascript:dashicons.copy( '{{data.html}}', 'html' )"><?php _e( 'Copy HTML', 'wporg' ); ?></a></span>
				<span class="link"><a href="javascript:dashicons.copy( '{{data.glyph}}' )"><?php _e( 'Copy Glyph', 'wporg' ); ?></a></span>
			</div>
		</script>

		<?php endwhile; // end of the loop. ?>

	</div><!-- #primary -->

<?php get_footer(); ?>
