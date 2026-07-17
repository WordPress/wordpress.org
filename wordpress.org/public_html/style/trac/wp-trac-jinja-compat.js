/*
 * wp-trac-jinja-compat.js
 *
 * JS half of the behaviours the old Genshi site.html did server-side with
 * py:match. Trac 1.6 uses Jinja2, whose site_*.html can only ADD content to
 * <head>/<body> — it cannot rewrite Trac's generated markup the way Genshi's
 * stream filter did. Everything that used to match-and-transform existing
 * elements now runs here on the client. Merge into wp-trac.js (this file assumes
 * Trac's bundled jQuery 1.x is present, same as wp-trac.js).
 *
 * Companion CSS: wp-trac-jinja-compat.css.
 *
 * NOTE: written against Trac 1.6 markup (trac/templates/theme.html) but NOT yet
 * run against a live instance. Items marked `TODO(verify)` touch markup that
 * should be confirmed on the running Trac before shipping.
 */
jQuery( function ( $ ) {
	'use strict';

	// --- shared helpers ------------------------------------------------------

	// Project slug: from the body class the theme sets (make-<slug>), else host.
	var slug = ( ( document.body.className.match( /\bmake-([a-z0-9-]+)/ ) || [] )[ 1 ] )
		|| location.hostname.split( '.' )[ 0 ];

	// Project display name: from the injected headline, else the slug.
	var projectName = $.trim( $( '#headline h2 a' ).first().text() )
		|| ( slug.charAt( 0 ).toUpperCase() + slug.slice( 1 ) );

	var currentUrl = encodeURIComponent( location.href );

	var supportLink = 'https://wordpress.org/support/';
	if ( 'bbpress' === slug ) {
		supportLink = 'https://bbpress.org/forums/';
	} else if ( 'buddypress' === slug ) {
		supportLink = 'https://buddypress.org/support/topics/';
	}

	// Port of wporg_sanitize_user_nicename() (see wporg_trac_helpers.py).
	function sanitizeNicename( name ) {
		return String( name )
			.toLowerCase()
			.replace( /[^%a-z0-9\u0080-\u00ff _-]/g, '' )
			.replace( /^\s+|\s+$/g, '' )
			.replace( /\s+/g, '-' )
			.replace( /-+/g, '-' )
			.replace( /_+/g, '_' );
	}

	function isWporgUrl( url ) {
		return /^([^>]*href=")?https?:\/\/([a-z0-9.-]+\.)?(wordpress\.(org|net)|buddypress\.org|bbpress\.org|wordcamp\.org)\//i
			.test( url );
	}

	// Expose the bug-gardener flag as a body class for CSS (see the .css file).
	if ( window.wpBugGardener ) {
		document.body.classList.add( 'wp-bug-gardener' );
	}

	var path = location.pathname;
	var isNewTicket = '/newticket' === path;

	// --- metanav: WP.org login / logout -------------------------------------

	$( '#metanav a[href="/login"]' )
		.attr( 'href', 'https://login.wordpress.org/?redirect_to=' + currentUrl )
		.addClass( 'login' );

	$( '#metanav form#logout' ).replaceWith(
		'<a href="https://login.wordpress.org/logout?redirect_to=' + currentUrl + '">Logout</a>'
	);

	// --- ticket: reporter / owner gravatars ---------------------------------
	// (was site-ticket.html; comment-author avatars stay server-side in
	//  ticket_change.html.) TODO(verify): confirm these cells hold the raw
	//  username as text.
	$( 'td[headers="h_reporter"], td[headers="h_owner"]' ).each( function () {
		var $td = $( this );
		var user = $.trim( $td.text() );
		if ( ! user ) {
			return; // e.g. an unassigned owner
		}
		var nice = sanitizeNicename( user );
		$td.prepend(
			'<a href="https://profiles.wordpress.org/' + nice + '/" data-nicename="' + nice + '">' +
				'<img class="avatar" src="https://wordpress.org/grav-redirect.php?user=' + nice + '&s=48" ' +
				'srcset="https://wordpress.org/grav-redirect.php?user=' + nice + '&s=96 2x" ' +
				'height="48" width="48" alt="" /></a> '
		);
	} );

	// --- ticket: reporter-feedback notice -----------------------------------
	( function () {
		var reporter = $.trim( $( 'td[headers="h_reporter"]' ).text() );
		var keywords = $( 'td[headers="h_keywords"]' ).text();
		var resolution = $.trim( $( 'td[headers="h_resolution"]' ).text() );
		if (
			window.wpTracCurrentUser &&
			reporter === window.wpTracCurrentUser &&
			/\breporter-feedback\b/.test( keywords ) &&
			'fixed' !== resolution
		) {
			$( '#ticketchange' ).first().after(
				'<div class="wp-notice" id="wp-reporter-feedback-notice">' +
					'<p><strong>Howdy!</strong></p>' +
					'<p>A contributor marked this ticket with the reporter-feedback keyword. ' +
					'<strong>That means we need feedback from you.</strong></p>' +
					'<p>Please answer their questions and address their concerns, then remove the keyword, below.</p>' +
					'<p>If this is a support question, you’re better off in the ' +
					'<a href="' + supportLink + '" class="ext-link"><span class="icon"> </span>support forums</a>.</p>' +
				'</div>'
			);
		}
	}() );

	// --- newticket: notices, forced preview, security alert -----------------
	if ( isNewTicket ) {
		var $form = $( '#content.ticket form' ).first();

		// "Are you in the right place?" notice.
		var securityHtml = 'plugins' === slug
			? '<strong>Do not report potential security vulnerabilities here.</strong><br />' +
			  'Please email <a class="mail-link" href="mailto:plugins@wordpress.org">plugins@wordpress.org</a>.'
			: '<strong>Do not report potential security vulnerabilities here.</strong><br />' +
			  'See the <a href="https://make.wordpress.org/core/handbook/reporting-security-vulnerabilities/">Security FAQ</a> ' +
			  'and visit the <a href="https://hackerone.com/wordpress">WordPress HackerOne program</a>.';

		$form.before(
			'<div class="wp-notice newticket-not-here">' +
				'<p><strong>ARE YOU IN THE RIGHT PLACE?</strong></p>' +
				'<p class="support"><span class="dashicons dashicons-editor-help"></span> ' +
				'<strong>This is not for support.</strong><br />Please try the ' +
				'<a href="' + supportLink + '">support forums</a>.</p>' +
				'<p class="security"><span class="dashicons dashicons-lock"></span> ' + securityHtml + '</p>' +
			'</div>'
		);

		// Bug-report instructions (core/bbpress/buddypress only).
		if ( [ 'core', 'bbpress', 'buddypress' ].indexOf( slug ) !== -1 ) {
			var gutenberg = 'core' === slug
				? '<li>Please create Gutenberg issues on the project’s GitHub ' +
				  '<a href="https://github.com/WordPress/gutenberg/issues">issue tracker</a>.</li>'
				: '';
			$form.before(
				'<div class="newticket-instructions">' +
					'<p><strong>This form is for suggesting enhancements and reporting bugs in ' + projectName +
					'.</strong> Here are some questions and tips to help you write a great bug report:</p>' +
					'<ul>' +
					'<li>Are you using either the latest version of ' + projectName +
					', or the latest development version? If not, please update first.</li>' +
					'<li>What steps should be taken to consistently reproduce the problem?</li>' +
					'<li>Does the problem occur even when you deactivate all plugins and use the default theme?</li>' +
					'<li>In case it’s relevant to the ticket, what is the expected output or result? What did you see instead?</li>' +
					'<li>Please provide any additional information that you think we’d find useful. ' +
					'(OS and browser for UI defects, server environment for crashes, etc.)</li>' +
					gutenberg +
					'</ul>' +
					'<p>You can <a href="/search">search for existing tickets here</a>. For more help, please see the ' +
					'<a href="https://make.wordpress.org/core/handbook/reporting-bugs/">contributor handbook</a>.</p>' +
				'</div>'
			);
		}

		// A preview page is showing when Trac has rendered the change/preview area.
		var isPreview = $( '#ticketbox.ticketdraft' ).length > 0;

		// Force non-gardeners to preview before posting.
		if ( ! window.wpBugGardener && ! isPreview ) {
			$( '#propertyform div.buttons' ).html(
				'<input type="submit" name="preview" value="Continue to Preview" />'
			);
		}

		// Security-component alert on preview.
		if ( isPreview && 'Security' === $( '#field-component' ).val() ) {
			$( '#propertyform div.buttons' ).before(
				'<div class="wp-notice" id="wp-security-notice">' +
					'<p><strong>Caution!</strong> This ticket was assigned to the Security component.</p>' +
					'<p><strong>If this is a potential security vulnerability, DO NOT REPORT IT HERE.</strong></p>' +
					'<p>Instead, read the <a class="ext-link" href="https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/">' +
					'<span class="icon"> </span>Security FAQ</a> and visit the ' +
					'<a href="https://hackerone.com/wordpress">WordPress HackerOne program</a>.</p>' +
				'</div>'
			);
		}
	}

	// --- attachment form: license note + button text ------------------------
	$( '#attachment div.buttons' ).before(
		'<div class="wp-caution" id="wp-contributions-caution"><p>By contributing code to ' +
		projectName + ', you grant its use under the GNU General Public License v2 (or later).</p></div>'
	);
	$( '#attachment input[type="submit"][value="Add attachment"]' ).val( 'Agree and Upload' );

	// --- search: "Create a new ticket" button -------------------------------
	$( '#content.search h1' ).append(
		'<span class="create-new-ticket button button-large button-primary">' +
			'<a href="https://login.wordpress.org/?redirect_to=https://' + location.host + '/newticket" rel="nofollow">' +
			'Create a new ticket</a></span>'
	);

	// --- query: show result count even on a single page ---------------------
	if ( '/query' === path && ! $( '.query_results h2.report-result' ).length ) {
		var numResults = $( '#query tbody.trac-tbody-content tr, table.listing tbody tr' ).length; // TODO(verify)
		if ( numResults ) {
			$( 'form#query' ).after(
				'<h2 class="report-result">Results <span class="numresults">(' + numResults + ')</span></h2>'
			);
		}
	}

	// --- report: hide spurious "arguments are missing" warning + collapse ----
	if ( '/report' === path || /^\/report\//.test( path ) ) {
		$( '#content.report #warning.system-message' ).filter( function () {
			return /arguments are missing/i.test( $( this ).text() );
		} ).hide(); // TODO(verify): only when the user lacks REPORT_MODIFY
	}

	// --- preferences: demote "Preferences", email-only notification pane -----
	$( '#altlinks' ).prepend(
		'<a class="preferences-link" href="' + ( window.tracBaseUrl || '' ) + '/prefs">Trac UI Preferences</a> '
	);
	// The email-only notification pane needs the session email/sid, which is not
	// available client-side. TODO: implement as a small prefs.html override, or
	// expose the email via a data attribute from a plugin. Left server-side.

	// --- textareas: prevent emoji <img> replacement -------------------------
	$( '#field-description, #comment, textarea[name="edited_comment"]' ).addClass( 'wp-exclude-emoji' );

	// --- user content: rel="ugc [nofollow]" on rendered links ---------------
	$( '.description, .comment, .attachments, td.message.searchable' )
		.find( 'a.ext-link' )
		.each( function () {
			var $a = $( this );
			if ( isWporgUrl( $a.attr( 'href' ) || '' ) ) {
				$a.removeClass( 'ext-link' ).attr( 'rel', 'ugc' );
			} else {
				$a.attr( 'rel', 'ugc nofollow' );
			}
		} );

	// --- home page: remove wiki cruft ---------------------------------------
	if ( '/' === path ) {
		$( '#pagepath, #ctxtnav, .trac-modifiedby, #altlinks h3, #altlinks ul' ).hide();
	}

	// --- bbPress / BuddyPress: move #metanav into a #subnav banner ----------
	if ( 'bbpress' === slug || 'buddypress' === slug ) {
		var $items = $( '#metanav ul' ).children( 'li' );
		if ( $items.length ) {
			var $subnav = $(
				'<div id="subnav"><div id="subnav-inner"><ul id="nav-user" class="menu"></ul></div></div>'
			);
			$subnav.find( 'ul' ).append( $items );
			$( '#banner' ).before( $subnav ); // TODO(verify) placement vs form#search
		}
	}

	// --- residual s.w.org CDN cache-busting ---------------------------------
	if ( window.wpTracScriptsVersion ) {
		$( 'link[rel="stylesheet"][href^="https://s.w.org/"], script[src^="https://s.w.org/"]' )
			.each( function () {
				var $el = $( this );
				var attr = this.tagName === 'LINK' ? 'href' : 'src';
				var url = $el.attr( attr );
				if ( url && url.indexOf( '?' ) === -1 ) {
					$el.attr( attr, url + '?v=' + window.wpTracScriptsVersion );
				}
			} );
	}
} );
