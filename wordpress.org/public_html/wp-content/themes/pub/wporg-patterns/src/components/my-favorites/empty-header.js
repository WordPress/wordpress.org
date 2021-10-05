/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

function EmptyHeader( { isLoggedIn } ) {
	const loginUrl = addQueryArgs( wporgPatternsUrl.login, { redirect_to: window.location } );
	const registerUrl = addQueryArgs( wporgPatternsUrl.register, { redirect_to: window.location } );

	return isLoggedIn ? (
		<div className="pattern-grid__empty-header pattern-favorites__empty-header">
			<h2>{ __( 'Collect and view your favorite patterns.', 'wporg-patterns' ) }</h2>
			<p>
				{ __(
					'Tap the heart on any pattern to mark it as a favorite. All your favorite patterns will appear here.',
					'wporg-patterns'
				) }
			</p>
		</div>
	) : (
		<div className="pattern-grid__empty-header pattern-favorites__empty-header">
			<h2>{ __( 'Collect and view your favorite patterns.', 'wporg-patterns' ) }</h2>
			<p>
				{ __(
					'Log in to your WordPress.org account and you’ll be able to see all your favorite patterns in one place.',
					'wporg-patterns'
				) }
			</p>
			<p>
				<a className="button button-primary button-large" href={ loginUrl }>
					{ __( 'Log in', 'wporg-patterns' ) }
				</a>
			</p>
			<p>
				<a className="button-link link-create-account" href={ registerUrl }>
					{ __( 'Create an account', 'wporg-patterns' ) }
				</a>
			</p>
		</div>
	);
}

export default EmptyHeader;
