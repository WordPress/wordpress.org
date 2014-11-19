jQuery( document ).ready( function( $ ) {

	/*
	 * Model for an attached file
	 */
	$.wordcampPayments.AttachedFile = Backbone.Model.extend( {
		defaults: {
			'ID':       0,
			'filename': '',
			'url':      ''
		}
	} );

	/*
	 * Collection of attached files
	 */
	$.wordcampPayments.AttachedFiles = Backbone.Collection.extend( {
		model: $.wordcampPayments.AttachedFile
	} );

	/*
	 * View for a single attached file
	 */
	$.wordcampPayments.AttachedFileView = Backbone.View.extend( {
		tagName: 'li',
		template: wp.template( 'wcp-attached-file' ),

		initialize: function() {
			_.bindAll( this, 'render' );
		},

		render: function() {
			$( this.el ).html( this.template( this.model.toJSON() ) );
			return this;
		}
	} );

	/*
	 * View for a collection of attached files
	 */
	$.wordcampPayments.AttachedFilesView = Backbone.View.extend( {
		el: $( '#wcp_files' ),

		initialize: function() {
			_.bindAll( this, 'render', 'appendFile' );

			this.collection = new $.wordcampPayments.AttachedFiles( wcpAttachedFiles );
			this.collection.bind( 'add', this.appendFile );

			this.render();
		},

		render: function() {
			var self = this;

			_( this.collection.models ).each( function( file ) {
				self.appendFile( file );
			} );
		},

		appendFile: function( file ) {
			var noFilesUploaded  = $( '.wcp_no_files_uploaded' );
			var attachedFileView = new $.wordcampPayments.AttachedFileView( { model: file } );

			$( '.wcp_files_list' ).append( attachedFileView.render().el );
			noFilesUploaded.removeClass( 'active' );
			noFilesUploaded.addClass( 'hidden' );
		}
	} )

	$.wordcampPayments.attachedFilesView = new $.wordcampPayments.AttachedFilesView();

} );
