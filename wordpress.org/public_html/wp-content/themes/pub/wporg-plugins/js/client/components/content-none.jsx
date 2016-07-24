import React from 'react';

/**
 * Internal dependencies.
 */
import SearchForm from 'components/search-form';

export default  React.createClass( {
	displayName: 'ContentNone',

	render() {
		let helpText, userHelp;

		if ( -1 !== this.props.location.pathname.indexOf( 'search' ) ) {
			helpText = (
				<div className="page-content">
					<p>Sorry, but nothing matched your search terms.</p>
					<p>Please try again with some different keywords.</p>
					<SearchForm />
				</div>
			);

		} else if ( -1 !== this.props.location.pathname.indexOf( 'browse/favorites' ) ) {
			if ( true /*user_logged_in*/ ) {
				helpText = <p>No favorites have been added, yet.</p>;

				if ( -1 !== this.props.location.pathname.indexOf( 'browse/favorites/' + this.props.params.username ) ) {
					userHelp = (
						<div>
							<p>Find a plugin and mark it as a favorite to see it here.</p>
							<p>Your favorite plugins are also shared on <a href={ 'https://profile.wordpress.org/' + this.props.params.username }>your profile</a></p>
						</div>
					);
				}

				helpText = <div className="page-content">{ helpText }{ userHelp }</div>;
			} else {
				helpText = (
					<div className="page-content">
						<p><a href="https://login.wordpress.org/">Loginto WordPress.org</a> to mark plugins as favorites.</p>
					</div>
				);
			}
		} else {
			helpText =(
				<div className="page-content">
					<p>It seems we can&#8217;t find what you&#8217;re looking for. Perhaps searching can help.</p>
				</div>
			);
		}

		return (
			<section className="no-results not-found">
				<header className="page-header">
					<h1 className="page-title">Nothing Found</h1>
				</header>
				{ helpText }
			</section>
		)
	}
} );
