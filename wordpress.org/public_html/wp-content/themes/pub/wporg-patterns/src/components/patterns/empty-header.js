/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

function EmptyHeader() {
	return (
		<div className="pattern-grid__empty-header">
			<h2>{ __( 'No results found', 'wporg-patterns' ) }</h2>
			<p>
				{ createInterpolateElement(
					__(
						'View <a>all block patterns</a> or browse some of our recent patterns.',
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
