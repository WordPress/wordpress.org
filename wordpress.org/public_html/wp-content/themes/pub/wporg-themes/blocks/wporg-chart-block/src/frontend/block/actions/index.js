/**
 * Internal dependencies
 */
import { useAppContext } from '../store/context';

export default () => {
	const { startDate, setStartDate } = useAppContext();
	return (
		<div className="wporg-theme-review-stats__actions">
			<label htmlFor="startDate">From:</label>
			<input
				type="month"
				id="startDate"
				value={ startDate }
				onChange={ ( event ) => setStartDate( event.target.value ) }
			/>
		</div>
	);
};
