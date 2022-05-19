/**
 * External dependencies
 */
import { Chart } from 'react-google-charts';

export default ( { headings, data, type = 'LineChart', options } ) => {
	const defaultOptions = {
		chartArea: { left: 50, top: 30, width: '95%', height: '200' },
		legend: { position: 'bottom' },
	};

	return (
		<Chart
			chartType={ type }
			data={ [ headings, ...data ] }
			width="100%"
			height="300px"
			options={ { ...options, ...defaultOptions } }
			legendToggle
		/>
	);
};
