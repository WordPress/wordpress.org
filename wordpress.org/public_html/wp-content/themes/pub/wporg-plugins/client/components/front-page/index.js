/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { identity } from 'lodash';
import { localize } from 'i18n-calypso';

/**
 * Internal dependencies.
 */
import PluginSection from './plugin-section';

const FrontPage = ( { sections } ) => (
	<div>
		{ sections.map( ( type ) => <PluginSection key={ type } type={ type } /> ) }
	</div>
);

FrontPage.propTypes = {
	sections: PropTypes.arrayOf( PropTypes.string ),
	translate: PropTypes.func,
};

FrontPage.defaultProps = {
	sections: [ 'featured', 'popular', 'beta' ],
	translate: identity,
};

export default localize( FrontPage );
