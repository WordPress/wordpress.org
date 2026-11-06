/**
 * Filters the Users page by role and status, each choice with how many users it matches. The filters are kept in the
 * URL, so going back from a profile, or opening a shared link, shows the same users. The heading's count is of the
 * users shown, after core's search too.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	const HIDDEN = 'wporgsite-users-filtered';

	const GROUPS = [
		{
			key: 'role',
			label: 'Role',
			options: [
				{ value: '', text: 'All', test: () => true },
				{
					value: 'admin',
					text: 'Admins',
					test: ( user ) => user.admin,
				},
				{
					value: 'user',
					text: 'Users',
					test: ( user ) => ! user.admin,
				},
			],
		},
		{
			key: 'status',
			label: 'Status',
			options: [
				{ value: '', text: 'All', test: () => true },
				{
					value: 'active',
					text: 'Active',
					test: ( user ) => ! user.inactive,
				},
				{
					value: 'inactive',
					text: 'Inactive',
					test: ( user ) => user.inactive,
				},
			],
		},
	];

	$( function () {
		const $cards = $( '#users-list > .card' );

		// Core shows its search only with more than one user; there's nothing to filter either.
		if ( $cards.length < 2 ) {
			return;
		}

		const $count = $( '.heading small' ).first();
		const users = $cards.toArray().map( function ( card ) {
			const $card = $( card );

			return {
				$card,
				admin: $card.children( '.user-admin-badge' ).length > 0,
				// Invites not accepted yet, which core grays out, and disabled users.
				inactive:
					$card.hasClass( 'card-inactive' ) ||
					$card.children( '.glyphicon-ban-circle' ).length > 0,
			};
		} );

		// Agents who aren't admins don't see any admins, so there's no role to choose.
		const groups = GROUPS.filter(
			( group ) =>
				'role' !== group.key || users.some( ( user ) => user.admin )
		);

		const params = new window.URLSearchParams( window.location.search );
		const selected = {};
		groups.forEach( function ( group ) {
			selected[ group.key ] =
				group.options.find(
					( option ) => option.value === params.get( group.key )
				) || group.options[ 0 ];
		} );

		const $filters = $(
			'<div class="flexy-item wporgsite-users-filters"></div>'
		);
		groups.forEach( function ( group ) {
			const $group = $(
				'<div class="btn-group" role="group"></div>'
			).attr( 'aria-label', group.label );

			group.options.forEach( function ( option ) {
				option.$count = $(
					'<span class="wporgsite-users-count"></span>'
				);
				option.$button = $(
					'<button type="button" class="btn btn-default"></button>'
				)
					.text( option.text + ' ' )
					.append( option.$count )
					.on( 'click', function () {
						selected[ group.key ] = option;
						updateUrl();
						apply();
					} )
					.appendTo( $group );
			} );

			$filters.append( $group );
		} );

		const $empty = $(
			'<p class="text-help wporgsite-users-empty" hidden></p>'
		).text( 'No users match.' );
		const $live = $( '<span class="sr-only" aria-live="polite"></span>' );

		/**
		 * Whether a user matches the chosen filters, with one group's choice swapped for another.
		 *
		 * @param {Object} user   User.
		 * @param {Object} group  Group to swap the choice of.
		 * @param {Object} option Choice to swap in.
		 * @return {boolean} Whether they match.
		 */
		function matches( user, group, option ) {
			return groups.every( ( each ) =>
				( each === group ? option : selected[ each.key ] ).test( user )
			);
		}

		/**
		 * Shows the users the filters match, each choice's count, and the heading's.
		 */
		function apply() {
			users.forEach( ( user ) =>
				user.$card.toggleClass( HIDDEN, ! matches( user ) )
			);

			groups.forEach( function ( group ) {
				group.options.forEach( function ( option ) {
					const on = selected[ group.key ] === option;

					option.$button
						.toggleClass( 'active', on )
						.attr( 'aria-pressed', String( on ) );
					option.$count.text(
						users.filter( ( user ) =>
							matches( user, group, option )
						).length
					);
				} );
			} );

			count();
		}

		/**
		 * Counts the users shown in the heading, after core's search, which hides cards inline.
		 */
		function count() {
			const shown = users.filter(
				( user ) =>
					! user.$card.hasClass( HIDDEN ) &&
					'none' !== user.$card[ 0 ].style.display
			).length;
			const text = shown + ( 1 === shown ? ' user' : ' users' );

			$count.text( '(' + shown + ')' );
			$empty.prop( 'hidden', shown > 0 );
			if ( $live.text() !== text ) {
				$live.text( text );
			}
		}

		/**
		 * Keeps the filters in the URL, without a history entry each.
		 */
		function updateUrl() {
			const url = new window.URL( window.location.href );

			groups.forEach( function ( group ) {
				if ( selected[ group.key ].value ) {
					url.searchParams.set(
						group.key,
						selected[ group.key ].value
					);
				} else {
					url.searchParams.delete( group.key );
				}
			} );

			window.history.replaceState( window.history.state, '', url );
		}

		// Delegated, so it runs after core's search has shown and hidden the cards; the clear button triggers keypress.
		$( document ).on( 'keyup keypress', '#search-users', count );

		// Before the live region is in the page, so the first count isn't announced.
		apply();
		$( '#search-users' ).closest( '.flexy-item' ).before( $filters );
		$( '#users-list' ).after( $empty, $live );
	} );
} )( jQuery );
