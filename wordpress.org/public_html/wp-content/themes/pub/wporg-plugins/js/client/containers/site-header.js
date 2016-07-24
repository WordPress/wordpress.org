import { connect } from 'react-redux';
import { withRouter } from 'react-router';

/**
 * Internal dependencies.
 */
import SiteHeader from 'components/site-header';

const mapStateToProps = ( state, ownProps ) => ( {
	isHome: ownProps.router.isActive( '/', true )
} );

export default withRouter( connect( mapStateToProps )( SiteHeader ) );
