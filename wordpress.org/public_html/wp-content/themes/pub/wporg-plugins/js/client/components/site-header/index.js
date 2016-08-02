import { connect } from 'react-redux';
import { withRouter } from 'react-router';

/**
 * Internal dependencies.
 */
import SiteHeader from './site-header';

const mapStateToProps = ( state, ownProps ) => ( {
	isHome: ownProps.router.isActive( '/', true ),
	searchTerm: ownProps.params.searchTerm || ''
} );

export default withRouter( connect( mapStateToProps )( SiteHeader ) );
