/**
 * WordPress dependencies
 */

import { Fragment, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { AppContext, useAppContext } from './store/context';
import Actions from './actions';
import BaseControl from './base-control';

const Block = ( { data } ) => {
	const [ chartData, setChartData ] = useState( [] );
	const { startDate } = useAppContext();
	const { title, url, headings, notes, type, options } = data;

	return (
		<BaseControl
			cardTitle={
				<Fragment>
					<div>{ title }</div>

					<Actions />
				</Fragment>
			}
            chartType={type}
			chartData={ chartData }
			chartHeadings={ headings.split(',') }
			chartOptions={options.trim().length ? JSON.parse(options) : {} }
			chartNotes={ notes.trim().length ? notes.split(',') : []  }
			url={ `${url}?startDate=${startDate}` }
			mapFunction={ setChartData }
		/>
	);
};

export default ( props ) => (
	<AppContext>
		<Block {...props} />
	</AppContext>
);
