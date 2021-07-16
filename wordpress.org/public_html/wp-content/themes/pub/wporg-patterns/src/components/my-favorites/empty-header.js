/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

function EmptyHeader() {
	return (
		<div className="pattern-grid__empty-header">
			<h2>{ __( 'No favorites here', 'wporg-patterns' ) }</h2>
			<p>
				{ createInterpolateElement(
					__(
						'You haven’t favorited any patterns yet. Take a look at the <a>block patterns</a> to find some you like, and click the heart to save them.',
						'wporg-patterns'
					),
					{
						// eslint-disable-next-line jsx-a11y/anchor-has-content -- Content interpolated above.
						a: <a href={ wporgPatternsUrl.site } />,
					}
				) }
			</p>
		</div>
	);
}

export default EmptyHeader;
