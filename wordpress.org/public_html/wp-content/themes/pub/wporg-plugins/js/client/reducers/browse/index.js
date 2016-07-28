import { combineReducers } from 'redux';

/**
 * Internal dependencies.
 */
import beta from './beta';
import favorites from './favorites';
import featured from './featured';
import popular from './popular';

export default combineReducers( {
	beta,
	favorites,
	featured,
	popular
} );
