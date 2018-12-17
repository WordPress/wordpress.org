import 'babel-polyfill';

import React           from 'react';
import ReactDOM        from 'react-dom';
import FilterableTable from './components/filterable-table/filterable-table.jsx';

require( './style.scss' );

/**
 * Custom render function for lastUpdatedColumn. Will display X time ago instead of unix timestamp. Use `humanizedTime` field sent from server.
 */
const renderHumanizedTime = ( row ) => {
	return row['humanizedTime'];
};


ReactDOM.render(
	<FilterableTable
		initialSortField = { wpcApplicationTracker.initialSortField }
		columns          = { wpcApplicationTracker.displayColumns }
		customRender     = {
			{
				lastUpdate: renderHumanizedTime
			}
		}
	/>,
	document.getElementById( 'wpc-application-tracker' )
);
