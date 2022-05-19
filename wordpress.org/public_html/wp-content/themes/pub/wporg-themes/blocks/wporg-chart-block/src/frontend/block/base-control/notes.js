/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default ( { notes } ) => {
	const [ showNotes, setShowNotes ] = useState( false );

	return (
		<div className="wporg-chart-block__notes">
			<Button
				className="wporg-chart-block__notes__toggle"
				onClick={ () => setShowNotes( ! showNotes ) }
			>
				{ __( 'See notes', 'wporg' ) } ({ notes.length })
			</Button>
			{ showNotes && (
				<ul>
					{ notes.map( ( note ) => (
						<li key={ note }>{ note }</li>
					) ) }
				</ul>
			) }
		</div>
	);
};
