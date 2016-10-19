import React from 'react';

import DeveloperList from './list';

export default React.createClass( {
	displayName: 'Developers',

	render() {
		return (
			<div>
				<div id="developers" className="read-more" aria-expanded="false">
					<h2>Authors</h2>
					<DeveloperList contributors={ this.props.contributors } />

					<h5>Browse the Code</h5>
					<ul>
						<li>
							<a href={ `https://plugins.trac.wordpress.org/log/${ this.props.slug }/` } rel="nofollow">Development Log</a>
							<a href={ `https://plugins.trac.wordpress.org/log/${ this.props.slug }/?limit=100&mode=stop_on_copy&format=rss` } rel="nofollow">
								<img src="https://s.w.org/style/images/feedicon.png" />
							</a>
						</li>
						<li><a href={ `https://plugins.svn.wordpress.org/${ this.props.slug }/` } rel="nofollow">Subversion Repository</a></li>
						<li><a href={ `https://plugins.trac.wordpress.org/browser/${ this.props.slug }/` } rel="nofollow">Browse in Trac</a></li>
						<li><a href={ `https://translate.wordpress.org/projects/wp-plugins/${ this.props.slug }/` } rel="nofollow">Translation Contributors</a></li>
					</ul>
				</div>
				<button type="button" className="button-link section-toggle" aria-controls="developers">Read more</button>
			</div>
		)
	}
} );
