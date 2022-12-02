/*
 * Block Editor: @mention support for thread users.
 * Replaces the 'bbPress: User Mention Autocomplete' plugin.
 */
(function($) {
	var completer = {
		name: 'threadUsers',
		// re-use the Gutenberg user mention styles..
		className: 'editor-autocompleters__user',
		triggerPrefix: '@',
		options: function() {
			var users = [], seen = [];
			$( '.bbp-topic-author, .bbp-reply-author' ).each( function() {
				var $this = $( this ),
					avatar = $this.find('img').prop('src'),
					name = $this.find( '.bbp-author-name' ).text(),
					slug = $this.find( '.bbp-user-nicename' ).text().replace( /[(@)]/g, '' );
	
				if ( -1 !== seen.indexOf( slug ) ) {
					return;
				}

				seen.push( slug );
				users.push( {
					avatar: avatar,
					name: name,
					slug: slug,
				} );
			} );

			return users;
		},
		getOptionKeywords: function( user ) {
			return [ user.slug ].concat( user.name.split( /\s+/ ) );
		},
		getOptionLabel: function( user ) {
			return wp.element.concatChildren( [
				wp.element.createElement(
					'img',
					{
						className: 'editor-autocompleters__user-avatar',
						src: user.avatar
					}
				),
				wp.element.createElement(
					'span',
					{
						className: 'editor-autocompleters__user-name'
					},
					user.name
				),
				wp.element.createElement(
					'span',
					{
						className: 'editor-autocompleters__user-slug'
					},
					user.slug
				)
			] );
		},
		getOptionCompletion: function( user ) {
			return `@${ user.slug } `;
		},
	};

	// Append the threadUsers mentioner.
	wp.hooks.addFilter(
		'editor.Autocomplete.completers',
		'wordpressdotorg/autocompleters/threadUsers',
		function( completers ) {
			return completers.concat( completer );
		}
	);

	// Remove the default users mentioner.
	wp.hooks.addFilter(
		'editor.Autocomplete.completers',
		'wordpressdotorg/autocompleters/threadUsersRemoveDefault',
		function( completers ) {
			return completers.filter( function( value ) {
				return 'users' !== value.name;
			} );
		}
	);
})(jQuery);
