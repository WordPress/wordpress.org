/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { connect } from 'react-redux';
import { identity } from 'lodash';
import { Link } from 'react-router';
import { localize } from 'i18n-calypso';

/**
 * Internal dependencies.
 */
import { getSection, getSectionPlugins } from 'state/selectors';
import PluginCard from 'components/plugin-card';

/**
 *
 * @param {Array}       plugins   Plugins
 * @param {Object}      section   Section
 * @param {Function}    translate Translation function
 * @return {(XML|null)}           Component or null.
 * @constructor
 */
export const PluginSection = ( { plugins, section, translate } ) => {
	if ( plugins ) {
		return (
			<section className="plugin-section">
				<header className="section-header">
					<h2 className="section-title">{ section.name }</h2>
					<Link className="section-link" to={ `/browse/${ section.slug }/` }>{ translate( 'See all' ) }</Link>
				</header>
				{ plugins.map( ( plugin ) => <PluginCard key={ plugin.id } plugin={ plugin } /> ) }
			</section>
		);
	}

	return null;
};

PluginSection.propTypes = {
	plugins: PropTypes.array,
	section: PropTypes.object,
	translate: PropTypes.func,
};

PluginSection.defaultProps = {
	plugins: [],
	section: {},
	translate: identity,
};

export default connect(
	( state, { type } ) => ( {
		plugins: getSectionPlugins( state, type ).slice( 0, 4 ),
		section: getSection( state, type ),
	} ),
)( localize( PluginSection ) );
