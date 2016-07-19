import React from 'react';
import { connect } from 'react-redux';
import { Route, IndexRoute } from 'react-router';

/**
 * Internal dependencies.
 */
import Page from 'containers/page';
import FrontPage from 'components/front-page';
import PluginDirectory from 'components/plugin-directory';
import ArchiveBrowse from 'components/archive-browse';

export default (
	<Route path="/" component={ PluginDirectory } >
		<IndexRoute component={ FrontPage } />
		<Route path="browse/:type" component={ ArchiveBrowse } />
		<Route path="developers" component={ Page } />
		<Route path=":plugin" component={ FrontPage } />
		<Route path="search/:searchTerm" component={ FrontPage } />
	</Route>
);
