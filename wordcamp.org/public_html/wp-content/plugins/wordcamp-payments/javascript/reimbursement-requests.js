jQuery( document ).ready( function( $ ) {
	'use strict';

	var wcb = window.WordCampBudgets;
	var app = wcb.ReimbursementRequests = {

		/**
		 * Main entry point
		 */
		init : function () {
			try {
				var expensesContainer = $( '#wcbrr-expenses-container' );

				app.expenses     = new app.Expenses( JSON.parse( $( '#wcbrr-expenses-data' ).val() ) );
				app.expensesView = new app.ExpensesView( {
					el         : expensesContainer,
					collection : app.expenses }
				);
				expensesContainer.removeClass( 'loading-content' );

				app.registerEventHandlers();
				wcb.attachedFilesView = new wcb.AttachedFilesView( { el: $( '#wcbrr_general_information' ) } );
				wcb.setupSelect2( '#wcbrr_general_information select' );
				wcb.setupSelect2( '#wcbrr-expenses-container select' );
			} catch ( exception ) {
				wcb.log( exception );
			}
		},

		/**
		 * Registers event handlers
		 */
		registerEventHandlers : function() {
			var reason   = $( '#_wcbrr_reason'   ),
				currency = $( '#_wcbrr_currency' ),
				paymentMethod = $( '#row-payment-method' );

			reason.change( app.toggleOtherReasonDescription );
			reason.trigger( 'change' );   // Set the initial state

			currency.change( wcb.setDefaultPaymentMethod );
			currency.trigger( 'change' );   // Set the initial state

			paymentMethod.find( 'input[name=payment_method]' ).change( wcb.togglePaymentMethodFields );
			paymentMethod.find( 'input[name=payment_method]:checked' ).trigger( 'change' ); // Set the initial state

			$( '#wcbrr_general_information' ).find( 'a.wcb-insert-media' ).click(   wcb.showUploadModal           );
			$( '#wcbrr-add-another-expense' ).click(                                app.addNewExpense             );

			currency.change( function() {
				app.expenses.trigger( 'updateTotal' );
			} );

			$('[name="post_status"]').on('change', function() {
				var $notes = $('.wcb-mark-incomplete-notes'),
					state = $(this).val() == 'wcb-incomplete';

				$notes.toggle(state);
				$notes.find('textarea').attr('required', state);
			}).trigger('change');
		},

		/**
		 * Toggle the extra input field when the user selects the Other option for the Reason dropdown
		 *
		 * @param {object} event
		 */
		toggleOtherReasonDescription : function( event ) {
			try {
				var otherReasonContainer = $( '#_wcbrr_reason_other_container' );

				if ( 'other' == $( this ).find( 'option:selected' ).val() ) {
					$( otherReasonContainer ).removeClass( 'hidden' );
					$( otherReasonContainer ).find( 'input' ).prop( 'required', true );
				} else {
					$( otherReasonContainer ).addClass( 'hidden' );
					$( otherReasonContainer ).find( 'input' ).prop( 'required', false );
				}

				// todo make the transition smoother
			} catch ( exception ) {
				wcb.log( exception );
			}
		},

		/**
		 * Add a new, empty expense to the collection
		 *
		 * @param {object} event
		 */
		addNewExpense : function( event ) {
			try {
				event.preventDefault();

				app.expenses.add( {} );
			} catch ( exception ) {
				wcb.log( exception );
			}
		}
	};

	/*
	 * Model for an expense
	 */
	app.Expense = Backbone.Model.extend();

	/*
	 * Collection for Expense models
	 */
	app.Expenses = Backbone.Collection.extend( {
		model : app.Expense,

		/**
		 * Initialize the view
		 */
		initialize : function( models ) {
			_.each( models, function( element, index ) {
				element.id = index + 1;
			} );

			this.listenTo( this, 'add',         this.setId       );
			this.listenTo( this, 'syncToDom',   this.syncToDom   );
			this.listenTo( this, 'remove',      this.syncToDom   );
			this.listenTo( this, 'updateTotal', this.updateTotal );
		},

		/**
		 * Set the ID of new models added to the collection
		 *
		 * @param {object} model
		 */
		setId : function( model ) {
			model.set( { id : this.length } );
		},

		/**
		 * Sync the collection to the input field used as temporary storage
		 *
		 * @param {object} event
		 */
		syncToDom : function( event ) {
			$( '#wcbrr-expenses-data' ).val( JSON.stringify( this ) );
		},

		/**
		 * Update the calculated total amount of all expenses
		 */
		updateTotal : function () {
			var total    = 0.0,
				currency = $( '#_wcbrr_currency' ).val();

			this.each( function( expense ) {
				var value = parseFloat( expense.get( '_wcbrr_amount' ) );

				if ( ! isNaN( value ) ) {
					total += value;
				}
			} );
			total = total.toFixed( 2 );

			if ( 'null' === currency.substr( 0, 4 ) ) {
				currency = '';
			}

			$( '#total_amount_requested' ).html(
				_.escape( total )
				+ ' ' +
				_.escape( currency )
			);
		}
	} );

	/*
	 * View for an individual Expense model
	 */
	app.ExpenseView = Backbone.View.extend( {
		template : wp.template( 'wcbrr-expense' ),

		events : {
			'change input'                : 'update',
			'change select'               : 'update',
			'change textarea'             : 'update',
			'click .wcbrr-delete-expense' : 'removeFromCollection'
		},

		/**
		 * Initialize the view
		 */
		initialize : function() {
			this.render();

			this.listenTo( this.model, 'change',          this.render                   );
			this.listenTo( this,       'categoryChanged', this.toggleOtherCategoryInput );

			_.bindAll( this, 'update' );
		},

		/**
		 * Render the view
		 */
		render : function() {
			this.$el.html( this.template( this.model.toJSON() ) );

			// todo add a transition so it's obvious a new one is being added, otherwise can be hard to tell
		},

		/**
		 * Update the model's attributes when it's corresponding input field changes
		 *
		 * @param {object} event
		 */
		update : function( event ) {
			var attribute    = event.currentTarget.name,
				updatedModel = {},
				value        = $( event.currentTarget ).val();

			/*
			 * Remove the unique ID from the DOM element attribute, so that it matches the model's attribute name.
		     * e.g., _wcbrr_vendor_location_4 becomes _wcbrr_vendor_location
			 */
			attribute = attribute.slice( 0, attribute.lastIndexOf( '_' ) );

			updatedModel[ attribute ] = value;

			/*
			 * Silently update the model to avoid re-rendering the entire view, which would break input focus
		     * while tabbing between fields.
			 */
			this.model.set( updatedModel, { silent : true } );

			/*
			 * Because the update above was silent, we need to manually trigger some changes
			 */
			this.model.trigger( 'syncToDom' );

			if ( '_wcbrr_amount' === attribute ) {
				this.model.trigger( 'updateTotal' );
			}

			if ( '_wcbrr_category' === attribute ) {
				this.trigger( 'categoryChanged', value );
			}
		},

		/**
		 * Manually toggle the display of the Other category field
		 *
		 * See this.update for why this doesn't happen automatically.
		 *
		 * @param category
		 */
		toggleOtherCategoryInput : function( category ) {
			var otherCategoryContainer = this.$( '#_wcbrr_category_other_container' );

			if ( 'other' === category ) {
				otherCategoryContainer.removeClass( 'hidden' );
				otherCategoryContainer.find( 'input' ).prop( 'required', true );
			} else {
				otherCategoryContainer.addClass( 'hidden' );
				otherCategoryContainer.find( 'input' ).prop( 'required', false );
			}
		},

		/**
		 * Remove this model from its collection
		 *
		 * @param {object} event
		 */
		removeFromCollection : function( event ) {
			this.model.collection.remove( this.model );
		}
	} );

	/*
	 * View for a collection of Expense models
	 */
	app.ExpensesView = Backbone.View.extend( {
		/**
		 * Initialize the view
		 */
		initialize : function() {
			this.render();

			this.listenTo( this.collection, 'add',    this.render );
			this.listenTo( this.collection, 'remove', this.render );
		},

		/**
		 * Render the view
		 */
		render : function() {
			this.$el.html( '' );

			this.collection.each( function( expense ) {
				var expenseView = new app.ExpenseView( { model : expense } );
				this.$el.append( expenseView.el );
			}, this );

			this.collection.updateTotal();

			wcb.setupDatePicker( '#wcbrr-expenses-container' );
		}
	} );

	app.init();
} );
