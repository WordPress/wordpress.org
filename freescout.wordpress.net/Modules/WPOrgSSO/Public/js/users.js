/**
 * Adapts FreeScout's user forms to accounts that come from WordPress.org.
 *
 * - "Create a New User" asks for a WordPress.org username; name and email come from the account.
 * - The profile doesn't offer to change what WordPress.org keeps up to date, or passwords.
 *
 * The server enforces all of this; these only keep the forms from offering it.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	$( function () {
		const $account = $( '#wporgsso-account' );

		if ( ! $account.length ) {
			return;
		}

		if ( $account.data( 'connected' ) ) {
			// Disabled fields aren't submitted; the server fills them in from the user.
			$( '#first_name, #last_name, #email' ).prop( 'disabled', true );

			// The photo is the WordPress.org avatar: show it, without upload or delete, or nothing if there's none.
			const $photo = $( 'input[name="photo_url"]' ).closest(
				'.form-group'
			);
			if ( $photo.find( '#user-profile-photo' ).length ) {
				$photo
					.find(
						'input[name="photo_url"], .block-help, #user-photo-delete'
					)
					.remove();
			} else {
				$photo.remove();
			}
		}

		if ( ! $account.data( 'password-login' ) ) {
			$( 'a[href*="/users/password/"]' )
				.closest( '.form-group' )
				.remove();
		}

		if ( ! $account.data( 'password-emails' ) ) {
			$(
				'.reset-password-trigger, .send-invite-trigger, .resend-invite-trigger'
			).remove();
		}
	} );

	$( function () {
		const $username = $( '#wporgsso-username[data-lookup-url]' );

		if ( ! $username.length ) {
			return;
		}

		const $status = $( '#wporgsso-status' );
		const $fields = $( '#first_name, #last_name, #email' );
		const strings = $( '#wporgsso-user' ).data( 'strings' ) || {};
		let lookup = null;

		// First thing in the form, after the role.
		const $form = $username.closest( 'form' );
		const $role = $form.find( '#role' ).closest( '.form-group' );
		if ( $role.length ) {
			$role.after( $( '#wporgsso-user' ) );
		} else {
			$form.find( '.form-group' ).first().before( $( '#wporgsso-user' ) );
		}

		// Adds someone who already has an account, rather than creating one; core has no hook for these strings.
		$form
			.closest( '.panel-wizard' )
			.find( '.wizard-header h1' )
			.text( strings.heading );
		$form.find( 'button[type="submit"]' ).text( strings.submit );
		document.title = document.title.replace(
			strings.core_title,
			strings.title
		);

		// Agents log in with WordPress.org: no password to set, and no invite to set one, once that's enforced.
		if ( ! $( '#wporgsso-user' ).data( 'passwords' ) ) {
			$form
				.find( '#password' )
				.prop( 'required', false )
				.closest( '.form-group' )
				.remove();
			$form.find( '#send_invite' ).closest( '.form-group' ).remove();
		}
		$fields
			.prop( 'required', false )
			.removeAttr( 'autofocus' )
			.each( function () {
				const $group = $( this ).closest( '.form-group' );

				// Keep a field visible if the server rejected its value, e.g. an email another user has.
				if ( ! $group.hasClass( 'has-error' ) ) {
					$group.hide();
				}
			} );
		$username.trigger( 'focus' );

		$username.on( 'change', function () {
			const username = $.trim( $username.val() );

			if ( lookup ) {
				lookup.abort();
			}

			$fields.val( '' );
			$status.text( '' );

			if ( ! username ) {
				return;
			}

			lookup = $.getJSON( $username.data( 'lookup-url' ), {
				username,
			} )
				.done( function ( response ) {
					const user = response.user;
					const name = $.trim(
						user.first_name + ' ' + user.last_name
					);
					// Only administrators get the email address.
					const notes = [
						user.email ? name + ' <' + user.email + '>' : name,
					];

					$username.val( user.username );
					$( '#first_name' ).val( user.first_name );
					$( '#last_name' ).val( user.last_name );
					$( '#email' ).val( user.email || '' );

					if ( response.connected_to ) {
						notes.push(
							// A function, so "$&" and the like in a name stay as they are.
							strings.connected_to.replace(
								':name',
								() => response.connected_to
							)
						);
					}
					if ( user.blocked ) {
						notes.push( strings.blocked );
					}
					// Only administrators get the account's status, too.
					if ( false === user.two_factor ) {
						notes.push( strings.no_two_factor );
					}

					$status.text( notes.join( ' ' ) );
				} )
				.fail( function ( xhr, textStatus ) {
					if ( 'abort' !== textStatus ) {
						$status.text(
							( xhr.responseJSON && xhr.responseJSON.error ) ||
								strings.lookup_failed
						);
					}
				} );
		} );
	} );
} )( jQuery );
