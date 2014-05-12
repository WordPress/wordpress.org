// Enable jQuery noConflict mode so SpreadJS doesn't crash
var $ = jQuery.noConflict();


/*
 * Manage client-side interactions with the spreadsheet
 */
jQuery( document ).ready( function( $ ) {
	
	var WordCamp_Spreadsheets = {

		/**
		 * Constructor
		 */
		init: function () {
			this.renderSpreadsheet();
			this.registerEventHandlers();
		},

		/**
		 * Register Event Handlers
		 */
		renderSpreadsheet : function() {
			this.container = $( '#wcss_spreadsheet_container' );
			this.container.wijspread( { sheetCount: 1 } );
			this.spreadsheet = this.container.wijspread( 'spread' );
			this.spreadsheet.fromJSON( wcssSpreadSheetData );
		},

		/**
		 * Register Event Handlers
		 */
		registerEventHandlers : function() {
			$( document ).keyup( this.exportSpreadSheetData );			// todo look for better event to hook into. there should be something specific to spreadjs, when a cell changes
		},

		/**
		 * Export the spreadsheet's data whenever it changes
		 *
		 * @param event
		 */
		exportSpreadSheetData : function( event ) {
			wcssSpreadSheetData = JSON.stringify( WordCamp_Spreadsheets.spreadsheet.toJSON() );
			$( '#wcss_spreadsheet_data' ).val( wcssSpreadSheetData );
		}

	}; // end WordCamp_Spreadsheets
	
	WordCamp_Spreadsheets.init();
} );
