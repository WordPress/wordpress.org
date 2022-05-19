/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Chart from '../../chart';
import Notes from './notes';

export default ({
	cardTitle,
	chartData = [],
	chartOptions,
	chartType,
	chartHeadings,
	chartNotes = [],
	mapFunction,
	url,
}) => {
	const [error, setError] = useState();
	const [fetchingData, setFetchingData] = useState();

	useEffect(() => {
		//setFetchingData(true);
		apiFetch({
			path: url,
		})
			.then(mapFunction)
			.catch(setError);
	}, [url]);

    if( !url ) {
        return null;
    }

	if (error) {
		return <p>{error.message}</p>;
	}

	if (fetchingData) {
		return <p>{__('Loading ...', 'wporg')}</p>;
	}

	if (!chartData.length) {
		return <p>{__('No Data', 'wporg')}</p>;
	}

	return (
		<Card>
			<CardHeader>{cardTitle}</CardHeader>
			<CardBody>
				<Chart
					type={chartType}
					headings={chartHeadings}
					data={chartData}
					options={chartOptions}
				/>
				<div className="wporg-theme-review-stats__notes">
					{chartNotes.length > 0 && <Notes notes={chartNotes} />}
				</div>
			</CardBody>
		</Card>
	);
};
