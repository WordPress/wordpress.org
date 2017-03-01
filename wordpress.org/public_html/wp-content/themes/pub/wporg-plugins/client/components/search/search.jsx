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
import ContentNone from 'components/content-none';
import PluginCard from 'components/plugin-card';
import { getSearchResults } from 'state/selectors';

export const Search = ( { params, plugins, translate } ) => {
	if ( ! plugins ) {
		return <div>{ translate( 'Loading&hellip;' ) }</div>;
	}

	if ( 0 === plugins.length ) {
		return <ContentNone />;
	}

	return (
		<div>
			<header className="page-header">
				<h1 className="page-title">
					{ translate( 'Search results for: {{strong}}%(search)s{{/strong}}', {
						args: { search: params.search },
						components: { strong: <strong /> },
					} ) }
				</h1>
				<div className="taxonomy-description" />
			</header>
			{ plugins.map( ( slug ) => <PluginCard key={ slug } slug={ slug } /> ) }
		</div>
	);
};

Search.propTypes = {
	params: PropTypes.object,
	plugins: PropTypes.arrayOf( PropTypes.string ),
	translate: PropTypes.func,
};

Search.defaultProps = {
	params: {},
	plugins: [],
	translate: identity,
};

export default connect(
	( state ) => ( {
		plugins: getSearchResults( state ),
	} ),
)( localize( Search ) );
