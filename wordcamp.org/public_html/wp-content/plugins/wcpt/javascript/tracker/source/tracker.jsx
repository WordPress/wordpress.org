import 'babel-polyfill';

import React           from 'react';
import ReactDOM        from 'react-dom';
import FilterableTable from './components/filterable-table/filterable-table.jsx';

require( './style.scss' );

ReactDOM.render(
	<FilterableTable
		initialSortField = { wpcApplicationTracker.initialSortField }
		columns          = { wpcApplicationTracker.displayColumns }
	/>,
	document.getElementById( 'wpc-application-tracker' )
);
