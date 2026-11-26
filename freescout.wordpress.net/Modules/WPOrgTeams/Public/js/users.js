/**
 * Adapts core's forms to access that comes from teams.
 *
 * - A team's permissions can all be checked, or none, like its mailboxes.
 * - "Create a New User" asks for teams instead of mailboxes.
 * - A mailbox's Permissions page shows who has access, without letting anyone change it there.
 * - A user's menu leaves out core's Permissions page, which redirects to their Teams page.
 *
 * The server enforces all of this; these only keep the pages from offering what they can’t do.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	$( function () {
		const $permissions = $( '#wporgteams-permissions' );

		$permissions.on(
			'click',
			'.wporgteams-sel-all, .wporgteams-sel-none',
			function ( event ) {
				event.preventDefault();
				$permissions
					.find( 'input[type="checkbox"]' )
					.prop(
						'checked',
						$( this ).hasClass( 'wporgteams-sel-all' )
					);
			}
		);
	} );

	$( function () {
		const $teams = $( '#wporgteams-create-user' );

		if ( ! $teams.length ) {
			return;
		}

		const $mailboxes = $teams
			.closest( 'form' )
			.find( 'input[name="mailboxes[]"]' )
			.closest( '.form-group' );

		if ( $mailboxes.length ) {
			$mailboxes.replaceWith( $teams );
		}
	} );

	$( function () {
		const $teams = $( '#wporgteams-user-menu' );

		if ( $teams.length ) {
			$teams
				.closest( '.sidebar-menu' )
				.find( 'a[href*="/users/permissions/"]' )
				.closest( 'li' )
				.remove();
		}
	} );

	$( function () {
		const $note = $( '#wporgteams-mailbox-permissions' );
		const $users = $( 'input[name="users[]"]' );

		if ( ! $note.length || ! $users.length ) {
			return;
		}

		// Disabled checkboxes aren't sent; the server takes the teams' members instead.
		$users.prop( 'disabled', true );

		const $fieldset = $users.closest( 'fieldset' );
		$fieldset.prev( 'p' ).has( '.sel-all' ).remove();
		$fieldset.before( $note.children() );
		$note.remove();
	} );
} )( jQuery );
