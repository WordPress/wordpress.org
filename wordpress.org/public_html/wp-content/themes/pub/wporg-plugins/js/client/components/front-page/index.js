import { connect } from 'react-redux';

/**
 * Internal dependencies.
 */
import FrontPage from './front-page';

const mapStateToProps = () => ( {
	sections: [
		{
			path: 'browse/featured/',
			title: 'Featured Plugins',
			type: 'featured'
		},
		{
			path: 'browse/popular/',
			title: 'Popular Plugins',
			type: 'popular'
		},
		{
			path: 'browse/beta/',
			title: 'Beta Plugins',
			type: 'beta'
		}
	]
} );

export default connect( mapStateToProps )( FrontPage );
