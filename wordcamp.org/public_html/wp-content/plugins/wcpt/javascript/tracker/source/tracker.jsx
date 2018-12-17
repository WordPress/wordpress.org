import 'babel-polyfill';

import React           from 'react';
import ReactDOM        from 'react-dom';
import FilterableTable from './components/filterable-table/filterable-table.jsx';

require( './style.scss' );

/**
 * Courtesy: https://stackoverflow.com/a/3177838/1845153
 *
 * Converts unix seconds into human readable time.
 * Looks like exact javascript convert of WordPress's human_time_diff, except this always compares from current time, instead of getting two arguments.
 *
 * @param {int} seconds Seconds ago to convert to human readable time
 *
 * @returns {string} Human readable time ago
 */
const timeSince = ( seconds ) => {

	let interval = Math.floor(seconds / 31536000);

	if (interval > 1) {
		return interval + " years";
	}
	interval = Math.floor(seconds / 2592000);
	if (interval > 1) {
		return interval + " months";
	}
	interval = Math.floor(seconds / 86400);
	if (interval > 1) {
		return interval + " days";
	}
	interval = Math.floor(seconds / 3600);
	if (interval > 1) {
		return interval + " hours";
	}
	interval = Math.floor(seconds / 60);
	if (interval > 1) {
		return interval + " minutes";
	}
	return Math.floor(seconds) + " seconds";
};

/**
 * Custom render function for lastUpdatedColumn. Will display X time ago instead of unix timestamp
 */
const renderHumanizeTime = ( time ) => {
	return timeSince( time ) + " ago";
};


ReactDOM.render(
	<FilterableTable
		initialSortField = { wpcApplicationTracker.initialSortField }
		columns          = { wpcApplicationTracker.displayColumns }
		customRender     = {
			{
				lastUpdate: renderHumanizeTime
			}
		}
	/>,
	document.getElementById( 'wpc-application-tracker' )
);
