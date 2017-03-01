/**
 * External dependencies.
 */
import React from 'react';
import { Route, IndexRoute } from 'react-router';
import { ReduxRouter } from 'redux-router';

/**
 * Internal dependencies.
 */
import ArchiveBrowse from 'components/archive/browse';
import FrontPage from 'components/front-page';
import NotFound from 'components/404';
import Page from 'components/page';
import Plugin from 'components/plugin';
import PluginDirectory from 'components';
import Search from 'components/search';
import SiteHeader from 'components/site-header';
import SiteMain from 'components/site-main';

const onUpdate = () => window.scrollTo( 0, 0 );

export const routes = (
	<Route name="root" component={ PluginDirectory }>
		<Route path="/" components={ { header: SiteHeader, main: SiteMain } }>
			<IndexRoute component={ FrontPage } />
			<Route path="browse/favorites/:username" component={ ArchiveBrowse } />
			<Route path="browse/:type/page/:page" component={ ArchiveBrowse } />
			<Route path="browse/:type" component={ ArchiveBrowse } />
			<Route path="developers" component={ Page } />
			<Route path="search/:search" component={ Search } />
			<Route path=":slug" component={ Plugin } />
			<Route path="*" component={ NotFound } />
		</Route>
	</Route>
);

export default (
	<ReduxRouter onUpdate={ onUpdate }>
		{ routes }
	</ReduxRouter>
);
