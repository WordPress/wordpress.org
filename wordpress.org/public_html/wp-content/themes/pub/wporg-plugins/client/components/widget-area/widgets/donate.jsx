/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { connect } from 'react-redux';
import { identity } from 'lodash';
import { localize } from 'i18n-calypso';

/**
 * Internal dependencies.
 */
import { getPlugin } from 'state/selectors';

/**
 * Donate Widget component.
 *
 * @param {Object}   plugin    Plugin object.
 * @param {Function} translate Translation function.
 * @return {*}                 Component or null.
 * @constructor
 */
export const DonateWidget = ( { plugin, translate } ) => {
	if ( plugin.donate_link ) {
		return (
			<div className="widget plugin-donate">
				<h4 className="widget-title">{ translate( 'Donate' ) }</h4>
				<p className="aside">{ translate( 'Would you like to support the advancement of this plugin?' ) }</p>
				<p>
					<a className="button button-secondary" href={ plugin.donate_link } rel="nofollow">
						{ translate( 'Donate to this plugin' ) }
					</a>
				</p>
			</div>
		);
	}

	return null;
};

DonateWidget.propTypes = {
	plugin: PropTypes.object,
	translate: PropTypes.func,
};

DonateWidget.defaultProps = {
	plugin: {},
	translate: identity,
};

export default connect(
	( state ) => ( {
		plugin: getPlugin( state ),
	} ),
)( localize( DonateWidget ) );
