const AuthorDetails = ( { name, url, avatar } ) => {
	return (
		<a href={ url } className="pattern__author-avatar">
			<img alt="" src={ avatar } />
			{ name }
		</a>
	);
};

export default AuthorDetails;
