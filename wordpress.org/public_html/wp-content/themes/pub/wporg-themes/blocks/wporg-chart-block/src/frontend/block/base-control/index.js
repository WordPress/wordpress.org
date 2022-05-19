/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardBody,
	CardHeader,
	Flex,
	Spinner,
} from '@wordpress/components';
import { Fragment, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Chart from '../../chart';
import Notes from './notes';

export default ( {
	cardTitle,
	chartData = [],
	chartOptions,
	chartType,
	chartHeadings,
	chartNotes = [],
	mapFunction,
	url,
} ) => {
	const [ error, setError ] = useState();
	const [ fetchingData, setFetchingData ] = useState();

	useEffect( () => {
		setFetchingData( true );
		apiFetch( {
			path: url,
		} )
			.then( ( data ) => {
				setFetchingData( false );
				mapFunction( data );
			} )
			.catch( () => {
				setFetchingData( false );
				setError();
			} );
	}, [ url ] );

	const getContent = () => {
		if ( error ) {
			return <p>{ error.message }</p>;
		}

		if ( fetchingData ) {
			return (
				<Flex align="center" justify="center">
					<Spinner />
				</Flex>
			);
		}

		if ( ! chartData.length ) {
			return <p>{ __( 'No Data', 'wporg' ) }</p>;
		}

		return (
			<Fragment>
				<Chart
					type={ chartType }
					headings={ chartHeadings }
					data={ chartData }
					options={ chartOptions }
				/>
				<Notes notes={ chartNotes } />
			</Fragment>
		);
	};

	return (
		<Card className="wporg-chart-block__card">
			<CardHeader>{ cardTitle }</CardHeader>
			<CardBody>{ getContent() }</CardBody>
		</Card>
	);
};
