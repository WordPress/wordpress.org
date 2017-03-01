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
import { getSection, getSectionPlugins } from 'state/selectors';
import Pagination from 'components/pagination';
import PluginCard from 'components/plugin-card';

export const ArchiveBrowse = ( { plugins, section, translate } ) => {
	if ( plugins && plugins.length ) {
		return (
			<div>
				<header className="page-header">
					<h1 className="page-title">
						{ translate( 'Browse: {{strong}}%(name)s{{/strong}}', {
							args: { name: section.name },
							components: { strong: <strong /> },
						} ) }
					</h1>
					{ section.description &&
						<div className="taxonomy-description">{ section.description }</div>
					}
				</header>
				{ plugins.map( ( plugin ) => <PluginCard key={ plugin.id } plugin={ plugin } /> ) }
				<Pagination current={ 12 } total={ 30 } />
			</div>
		);
	}

	return <ContentNone />;
};

ArchiveBrowse.propTypes = {
	plugins: PropTypes.arrayOf( PropTypes.object ),
	section: PropTypes.object,
	translate: PropTypes.func,
	type: PropTypes.string.isRequired,
};

ArchiveBrowse.defaultProps = {
	plugins: [],
	section: {},
	translate: identity,
};

export default connect(
	( state, { type } ) => ( {
		plugins: getSectionPlugins( state, type ),
		section: getSection( state, type ),
	} ),
)( localize( ArchiveBrowse ) );
