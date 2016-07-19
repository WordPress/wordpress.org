import { connect } from 'react-redux';

/**
 * Internal dependencies.
 */
import SiteHeader from 'components/site-header';

const mapStateToProps = ( state ) => ( {
	isHome: '/' === state.routing.locationBeforeTransitions.pathname
} );

export default connect( mapStateToProps )( SiteHeader );
