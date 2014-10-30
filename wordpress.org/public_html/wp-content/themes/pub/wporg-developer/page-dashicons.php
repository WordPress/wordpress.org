<?php
/**
 * The template for displaying the Dashicons resource page
 *
 * Template Name: Dashicons Resource
 *
 * @package wporg-developer
 */

wp_enqueue_style(  'dashicons-page', get_template_directory_uri() . '/stylesheets/page-dashicons.css', array(), '20140821' );
wp_enqueue_script( 'dashicons-page', get_template_directory_uri() . '/js/page-dashicons.js', array( 'jquery', 'wp-util' ), '20140821' );

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
					<div alt="f319" class="dashicons dashicons-admin-site">site</div>
					<div alt="f226" class="dashicons dashicons-dashboard">dashboard</div>
					<div alt="f109" class="dashicons dashicons-admin-post">post</div>
					<div alt="f104" class="dashicons dashicons-admin-media">media</div>
					<div alt="f103" class="dashicons dashicons-admin-links">links</div>
					<div alt="f105" class="dashicons dashicons-admin-page">page</div>
					<div alt="f101" class="dashicons dashicons-admin-comments">comments</div>
					<div alt="f100" class="dashicons dashicons-admin-appearance">appearance</div>
					<div alt="f106" class="dashicons dashicons-admin-plugins">plugins</div>
					<div alt="f110" class="dashicons dashicons-admin-users">users</div>
					<div alt="f107" class="dashicons dashicons-admin-tools">tools</div>
					<div alt="f108" class="dashicons dashicons-admin-settings">settings</div>
					<div alt="f112" class="dashicons dashicons-admin-network">network</div>
					<div alt="f102" class="dashicons dashicons-admin-home">home</div>
					<div alt="f111" class="dashicons dashicons-admin-generic">generic</div>
					<div alt="f148" class="dashicons dashicons-admin-collapse">collapse</div>

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

					<h4><?php _e( 'Image Editing', 'wporg' ); ?></h4>

					<!-- image editing -->
					<div alt="f165" class="dashicons dashicons-image-crop">crop</div>
					<div alt="f166" class="dashicons dashicons-image-rotate-left">rotate left</div>
					<div alt="f167" class="dashicons dashicons-image-rotate-right">rotate right</div>
					<div alt="f168" class="dashicons dashicons-image-flip-vertical">flip vertical</div>
					<div alt="f169" class="dashicons dashicons-image-flip-horizontal">flip horizontal</div>
					<div alt="f171" class="dashicons dashicons-undo">undo</div>
					<div alt="f172" class="dashicons dashicons-redo">redo</div>

					<h4><?php _e( 'TinyMCE', 'wporg' ); ?></h4>

					<!-- tinymce -->
					<div alt="f200" class="dashicons dashicons-editor-bold">bold</div>
					<div alt="f201" class="dashicons dashicons-editor-italic">italic</div>
					<div alt="f203" class="dashicons dashicons-editor-ul">ul</div>
					<div alt="f204" class="dashicons dashicons-editor-ol">ol</div>
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
					<div alt="f220" class="dashicons dashicons-editor-customchar">custom chararcter</div>
					<div alt="f221" class="dashicons dashicons-editor-outdent">outdent</div>
					<div alt="f222" class="dashicons dashicons-editor-indent">indent</div>
					<div alt="f223" class="dashicons dashicons-editor-help">help</div>
					<div alt="f224" class="dashicons dashicons-editor-strikethrough">strikethrough</div>
					<div alt="f225" class="dashicons dashicons-editor-unlink">unlink</div>
					<div alt="f320" class="dashicons dashicons-editor-rtl">rtl</div>
					<div alt="f474" class="dashicons dashicons-editor-break">break</div>
					<div alt="f475" class="dashicons dashicons-editor-code">code</div>
					<div alt="f476" class="dashicons dashicons-editor-paragraph">paragraph</div>

					<h4><?php _e( 'Posts Screen', 'wporg' ); ?></h4>

					<!-- posts -->
					<div alt="f135" class="dashicons dashicons-align-left">align left</div>
					<div alt="f136" class="dashicons dashicons-align-right">align right</div>
					<div alt="f134" class="dashicons dashicons-align-center">align center</div>
					<div alt="f138" class="dashicons dashicons-align-none">align none</div>
					<div alt="f160" class="dashicons dashicons-lock">lock</div>
					<div alt="f145" class="dashicons dashicons-calendar">calendar</div>
					<div alt="f508" class="dashicons dashicons-calendar-alt">calendar</div>
					<div alt="f177" class="dashicons dashicons-visibility">visibility</div>
					<div alt="f173" class="dashicons dashicons-post-status">post status</div>
					<div alt="f464" class="dashicons dashicons-edit">edit pencil</div>
					<div alt="f182" class="dashicons dashicons-trash">trash remove delete</div>

					<h4><?php _e( 'Sorting', 'wporg' ); ?></h4>

					<!-- sorting -->
					<div alt="f504" class="dashicons dashicons-external">external</div>
					<div alt="f142" class="dashicons dashicons-arrow-up">arrow-up</div>
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
					<div alt="f164" class="dashicons dashicons-exerpt-view">exerpt view</div>
					<div alt="f509" class="dashicons dashicons-grid-view">grid view</div>

					<h4><?php _e( 'Social', 'wporg' ); ?></h4>

					<!-- social -->
					<div alt="f237" class="dashicons dashicons-share">share</div>
					<div alt="f240" class="dashicons dashicons-share-alt">share</div>
					<div alt="f242" class="dashicons dashicons-share-alt2">share</div>
					<div alt="f301" class="dashicons dashicons-twitter">twitter social</div>
					<div alt="f303" class="dashicons dashicons-rss">rss</div>
					<div alt="f465" class="dashicons dashicons-email">email</div>
					<div alt="f466" class="dashicons dashicons-email-alt">email</div>
					<div alt="f304" class="dashicons dashicons-facebook">facebook social</div>
					<div alt="f305" class="dashicons dashicons-facebook-alt">facebook social</div>
					<div alt="f462" class="dashicons dashicons-googleplus">googleplus social</div>
					<div alt="f325" class="dashicons dashicons-networking">networking social</div>

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

					<h4><?php _e( 'Products', 'wporg' ); ?></h4>

					<!-- internal/products -->
					<div alt="f120" class="dashicons dashicons-wordpress">wordpress</div>
					<div alt="f324" class="dashicons dashicons-wordpress-alt">wordpress</div>
					<div alt="f157" class="dashicons dashicons-pressthis">press this</div>
					<div alt="f463" class="dashicons dashicons-update">update</div>
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
					<div alt="f147" class="dashicons dashicons-yes">yes check checkmark</div>
					<div alt="f158" class="dashicons dashicons-no">no x</div>
					<div alt="f335" class="dashicons dashicons-no-alt">no x</div>
					<div alt="f132" class="dashicons dashicons-plus">plus add increase</div>
					<div alt="f502" class="dashicons dashicons-plus-alt">plus add increase</div>
					<div alt="f460" class="dashicons dashicons-minus">minus decrease</div>
					<div alt="f153" class="dashicons dashicons-dismiss">dismiss</div>
					<div alt="f159" class="dashicons dashicons-marker">marker</div>
					<div alt="f155" class="dashicons dashicons-star-filled">filled star</div>
					<div alt="f459" class="dashicons dashicons-star-half">half star</div>
					<div alt="f154" class="dashicons dashicons-star-empty">empty star</div>
					<div alt="f227" class="dashicons dashicons-flag">flag</div>

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
					<div alt="f183" class="dashicons dashicons-analytics">analytics</div>
					<div alt="f184" class="dashicons dashicons-chart-pie">pie chart</div>
					<div alt="f185" class="dashicons dashicons-chart-bar">bar chart</div>
					<div alt="f238" class="dashicons dashicons-chart-line">line chart</div>
					<div alt="f239" class="dashicons dashicons-chart-area">area chart</div>
					<div alt="f307" class="dashicons dashicons-groups">groups</div>
					<div alt="f338" class="dashicons dashicons-businessman">businessman</div>
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
					<div alt="f471" class="dashicons dashicons-tablet">tablet ipad</div>
					<div alt="f470" class="dashicons dashicons-smartphone">smartphone iphone</div>
					<div alt="f510" class="dashicons dashicons-index-card">index card</div>
					<div alt="f511" class="dashicons dashicons-carrot">carrot food vendor</div>
					<div alt="f328" class="dashicons dashicons-smiley">smiley smile</div>
				</div>

			</div>

			<div id="instructions">

				<h3><?php _e( 'Photoshop Usage', 'wporg' ); ?></h3>

				<p><?php _e( 'Use the .OTF version of the font for Photoshop mockups, the web-font versions won\'t work. For most accurate results, pick the "Sharp" font smoothing.', 'wporg' ); ?></p>

				<h3><?php _e( 'CSS Usage', 'wporg' ); ?></h3>

				<p><?php _e( 'Link the stylesheet:', 'wporg' ); ?></p>

				<pre>&lt;link rel="stylesheet" href="css/dashicons.css"></pre>

				<p><?php printf( __( 'Now add the icons using the %s selector. You can insert the Star icon like this:', 'wporg' ), '<code>:before</code>' ); ?></p>

<textarea class="code" onclick="select();">
.myicon:before {
	content: "\f155";
	display: inline-block;
	-webkit-font-smoothing: antialiased;
	font: normal 20px/1 'dashicons';
	vertical-align: top;
}</textarea>

			</div><!-- /#instructions -->

		</main><!-- #main -->

		<!-- Required for the Copy Glyph functionality -->
		<div id="temp" style="display:none;"></div>

		<script type="text/html" id="tmpl-glyphs">
			<div class="dashicons {{data.cssClass}}"></div>
			<div class="info">
				<span class="name">← {{data.cssClass}}</span>
				<span><a href='javascript:dashicons.copy( "content: \"\\{{data.attr}}\";", "css" )'>Copy CSS</a></span>
				<span><a href="javascript:dashicons.copy( '{{data.html}}', 'html' )">Copy HTML</a></span>
				<span><a href="javascript:dashicons.copy( '{{data.glyph}}' )">Copy Glyph</a></span>
			</div>
		</script>

		<?php endwhile; // end of the loop. ?>

	</div><!-- #primary -->

<?php get_footer(); ?>
