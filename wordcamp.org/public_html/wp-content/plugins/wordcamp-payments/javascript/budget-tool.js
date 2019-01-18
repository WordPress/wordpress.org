window.wcb = window.wcb || {models:{}, input:[]};

(function($){
    var $document = $(document),
        $container = $('.wcb-budget-container tbody'),
        $income = $container.find('.wcb-income-placeholder'),
        $expense = $container.find('.wcb-expense-placeholder'),
        $meta = $container.find('.wcb-meta-placeholder'),
        $summary = $('.wcb-summary-placeholder'),
        $form = $('.wcb-submit-form');

    var template_options = {
		evaluate:    /<#([\s\S]+?)#>/g,
		interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
		escape:      /\{\{([^\}]+?)\}\}(?!\})/g,
		variable:    'data'
    };

    var Entry = Backbone.Model.extend({
		defaults: {
			type: 'expense',
            category: 'other',
            amount: 0,
            note: '',
            new: false,
            link: null,

            // metadata
            name: '',
            value: null
		},

        initialize: function() {
            this._realAmount = this.getRealAmount();
            this._attr = _.clone(this.attributes);
        },

        getRealAmount: function() {
            if (!this.get('link'))
                return this.get('amount');

            if (this.get('link') in wcb.linkData) {
                var link = wcb.linkData[this.get('link')]
                return link.callback(this.get('amount'));
            }

            return 0;
        },

        getLinkLabel: function() {
            if (!this.get('link'))
                return '';

            if (this.get('link') in wcb.linkData)
                return wcb.linkData[this.get('link')].label;

            return '';
        },

        linkHasValue: function() {
            if (!this.get('link'))
                return false;

            if (this.get('link') in wcb.linkData)
                return wcb.linkData[this.get('link')].hasValue;

            return false;
        },

        hasChanged: function() {
            // console.log(this._attr);
            // console.log(this.attributes);
            // console.log(this._realAmount)

            var _stringify = function(v) {
                if (!v) return v;
                return v.toString();
            }

            var changed = _.isEqual(
                _.map(this._attr, _stringify),
                _.map(this.attributes, _stringify)
            ) && this._realAmount == this.getRealAmount();
            return !changed;
        },

        editStart: function() {
            this.trigger('edit-start.wordcamp-budgets');
        }
    });

    var SummaryView = Backbone.View.extend({
        className: 'wcb-budget-container wcb-budget-summary',
        tagName: 'table',
        urls: [],

        events: {
            'click .inspire': 'inspire'
        },

        render: function() {
	        var attendees = wcb.table.collection.findWhere({type: 'meta', name: 'attendees'}),
	            days      = wcb.table.collection.findWhere({type: 'meta', name: 'days'}),
                data = {
                    'income': 0,
                    'expenses': 0,
                    'variance': 0,
                    'variance_raw': 0,
                    'per_person': 0
                };

            _.each(wcb.table.collection.where({type: 'income'}), function(item) {
                data['income'] += item.getRealAmount();
            });

            _.each(wcb.table.collection.where({type: 'expense'}), function(item) {
                data['expenses'] += item.getRealAmount();
            });

            data['variance'] = data['income'] - data['expenses'];
            data['variance_raw'] = data['variance'];
            data['per_person'] = (attendees && days) ? data['expenses'] / attendees.get('value') / days.get('value'): 0;

            data = _.mapObject(data, function(v, k) {
                if (k == 'variance_raw')
                    return v;

                return v.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            });

            this.template = _.template($('#wcb-tmpl-summary').html(), null, template_options);
            this.$el.html(this.template(data));
            return this;
        },

        initialize: function() {
            $summary.append(this.render().el);
            return this;
        },

        inspire: function(e) {
            e.target.href = this.urls[Math.floor(Math.random()*this.urls.length)];
            return true;
        }
    });

    var EntryView = Backbone.View.extend({
        className: 'wcb-entry',
        tagName: 'tr',
        cancel: false,

        events: {
            'keyup': 'keyup',
            'click .delete': 'delete',
            'click .move': 'move',
            'change input': 'editSave',
            'change select.category': 'editSave',
            'change select.link-value': 'linkChange',
            'change select.value': 'editSave',

            'focus input, select': 'focus',
            'blur input, select': 'blur'
        },

        initialize: function() {
            this.model.bind('destroy', this.remove, this);
        },

        linkChange: function() {
            this.model.set('link', this.$el.find('.link-value').val() || null);
            return this;
        },

        focus: function(e) {
            var $target = $(e.target);
            $target.parents('td').addClass('focused');

            if (($target.hasClass('amount') || $target.hasClass('link-value')) && this.model.get('link') && this.model.linkHasValue()) {
                this.$el.find('.amount').val(this.model.get('amount').toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }));
            }

            if ($target.hasClass('note') && $target.parents('tr').hasClass('is-new')) {
                if (_.contains(['New Expense Item', 'New Income Item'], this.model.get('note'))) {
                    this.$el.find('.note').val('');
                }
            }

            return this;
        },

        blur: function(e) {
            var $target = $(e.target);
            $target.parents('td').removeClass('focused');

            if (($target.hasClass('amount') || $target.hasClass('link-value')) && this.model.get('link')) {
                this.$el.find('.amount').val(this.model.getRealAmount().toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }));
            }

            if ($target.hasClass('note') && $target.parents('tr').hasClass('is-new')) {
                if (_.contains(['New Expense Item', 'New Income Item'], this.model.get('note'))) {
                    this.$el.find('.note').val(this.model.get('note'));
                }
            }

            return this;
        },

        render: function() {
            var data = this.model.toJSON();
            data.realAmount = this.model.getRealAmount();
            data.realAmountFormatted = data.realAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            data.amountFormatted = data.amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            data.linkLabel = this.model.getLinkLabel();
            data.linkHasValue = this.model.linkHasValue();

            this.template = _.template($('#wcb-tmpl-entry').html(), null, template_options);
            this.$el.html(this.template(data));
            this.$el.toggleClass('has-changed', this.model.hasChanged() && ! this.model.get('new'));
            this.$el.toggleClass('is-new', this.model.get('new'));
            this.$el.data('wcb-cid', this.model.cid);

			if( data.name === 'currency' && $.fn.hasOwnProperty( 'select2' ) ){
				var currSelect2Box = this.$el.find( 'select' ).select2( { width: '100%' } );
				var initializedSelectBox = currSelect2Box.data( 'select2' );
				if ( initializedSelectBox ) {
					initializedSelectBox.$dropdown.addClass( 'select2-currency-dropdown' );
				}
			}

            return this;
        },

        keyup: function(e) {
            if (e.keyCode == 27) {
                return this.editCancel.apply(this, arguments);
            } else if (e.keyCode == 13) {
                return this.editSave.apply(this, arguments);
            }

            return this;
        },

        editSave: function(e) {
            if (this.model.get('type') == 'meta') {
                var value = this.$el.find('.value').val(),
                    name = this.model.get('name');

                if (_.contains(['attendees', 'days', 'tracks', 'speakers', 'volunteers'], name)) {
                    value = parseInt(value.replace(/[^\d.-]/g, '')) || 0;
                } else if (_.contains(['ticket-price'], name)) {
                    value = parseFloat(value.replace(/[^\d.-]/g, '')) || 0;
                }

                this.model.set('value', value);
            } else {
                this.model.set('note', this.$el.find('.note').val());
                this.model.set('category', this.$el.find('.category').val());

                var $target = $(e.target);
                if ($target.hasClass('amount') || $target.hasClass('link-value')) {
                    var amount = parseFloat(this.$el.find('.amount').val().replace(/[^\d.-]/g, ''));
                    this.model.set('amount', amount || 0);
                }
				this.render.apply( this );
			}

            this.clearSelection.apply(this);
            return false;
        },

        editCancel: function(e) {
            this.clearSelection.apply(this);

            if (this.model.get('type') == 'meta') {
                this.$el.find('.value').val(this.model.get('value'));
            } else {
                this.$el.find('.amount').val(this.model.get('amount'));
                this.$el.find('.note').val(this.model.get('note'));
            }

            this.editSave.apply(this, arguments);
            return false;
        },

        clearSelection: function() {
            if (window.getSelection) {
                if (window.getSelection().empty) {
                    window.getSelection().empty();
                } else if (window.getSelection().removeAllRanges) {
                    window.getSelection().removeAllRanges();
                }
            } else if (document.selection) {
                document.selection.empty();
            }
        },

        delete: function() {
            if (!confirm('Delete this line item?'))
                return false;

            this.model.destroy();
            wcb.summary.render.apply(wcb.summary);
            return false;
        },

        move: function() {
            return false;
        }
    });

    var Entries = Backbone.Collection.extend({
        model: Entry
    });

    var EntriesView = Backbone.View.extend({
        tagName: 'table',
        className: 'wcb-table',

        initialize: function() {
            this.collection.bind('add', this.addOne, this);
            this.collection.bind('change reset', this.refresh, this);
        },

        refresh: function(model) {
            if (model.get('type') == 'meta') {
                this.render.apply(this);
            }

            wcb.summary.render.apply(wcb.summary);
            return this;
        },

        addOne: function(item) {
            var view = new EntryView({model: item});
            switch (view.model.get('type')) {
                case 'expense':
                    var $c = $expense;
                    break;
                case 'income':
                    var $c = $income;
                    break;
                case 'meta':
                default:
                    var $c = $meta;
            }

            $c.before(view.render().el);
        },

        render: function() {
            $container.find('.wcb-entry').remove();
            this.collection.each(this.addOne, this);
            return this;
        }
    });

	// Decode HTML entities in category names
	_.each( wcb.categories, function( name, slug, list ) {
		list[ slug ] = name.replace( '&amp;', '&' );
	} );

    wcb.metaLabels = {
        'attendees': 'Attendees',
        'days': 'Days',
        'tracks': 'Tracks',
        'speakers': 'Speakers',
        'volunteers': 'Volunteers',
        'currency': 'Currency',
        'ticket-price': 'Ticket Price'
    };

    wcb.linkData = {
        'per-speaker': {
            'label': 'per speaker',
            'hasValue': true,
            'callback': function(value) {
                return parseFloat(value) * parseInt(wcb.table.collection.findWhere({type: 'meta', name: 'speakers'}).get('value'));
            }
        },

        'per-volunteer': {
            'label': 'per volunteer',
            'hasValue': true,
            'callback': function(value) {
                return parseFloat(value) * parseInt(wcb.table.collection.findWhere({type: 'meta', name: 'volunteers'}).get('value'));
            }
        },

        'per-speaker-volunteer': {
            'label': 'per speakers + volunteers',
            'hasValue': true,
            'callback': function(value) {
                return parseFloat(value) * (
                    parseInt(wcb.table.collection.findWhere({type: 'meta', name: 'volunteers'}).get('value'))
                    + parseInt(wcb.table.collection.findWhere({type: 'meta', name: 'speakers'}).get('value'))
                );
            }
        },

        'per-attendee': {
            'label': 'per attendee',
            'hasValue': true,
            'callback': function(value) {
                return parseFloat(value) * parseInt(wcb.table.collection.findWhere({type: 'meta', name: 'attendees'}).get('value'));
            }
        },

        'per-day': {
            'label': 'per day',
            'hasValue': true,
            'callback': function(value) {
                return parseFloat(value) * parseInt(wcb.table.collection.findWhere({type: 'meta', name: 'days'}).get('value'));
            }
        },

        'per-track': {
            'label': 'per track',
            'hasValue': true,
            'callback': function(value) {
                return parseFloat(value) * parseInt(wcb.table.collection.findWhere({type: 'meta', name: 'tracks'}).get('value'));
            }
        },

        'ticket-price-x-attendees': {
            'label': 'ticket price &times; attendees',
            'hasValue': false,
            'callback': function(value) {
                var attendees = wcb.table.collection.findWhere({type: 'meta', name: 'attendees'}).get('value');
                var price = wcb.table.collection.findWhere({type: 'meta', name: 'ticket-price'}).get('value');
                return parseInt(attendees) * parseFloat(price);
            }
        }
    };

    var table = new EntriesView({collection: new Entries()});

    $income.on('click', function() {
        table.collection.add(new wcb.models.Entry({
            type: 'income',
            amount: 0,
            note: 'New Income Item',
            category: 'other',
            new: true
        })).editStart();
        return false;
    });

    $expense.on('click', function() {
        table.collection.add(new wcb.models.Entry({
            type: 'expense',
            amount: 0,
            note: 'New Expense Item',
            category: 'other',
            new: true
        })).editStart();
        return false;
    });

    $form.on('submit', function() {
        $container.find('.wcb-entry').each(function(el){
            var $this = $(this);
                model = wcb.table.collection.get($this.data('wcb-cid'));

            model.set({'order': $this.index()}, {silent: true});
        });

        var sorted = JSON.stringify(wcb.table.collection.sortBy(function(m){
            return m.get('type') + ':' + (m.get('order')/Math.pow(10,10)).toFixed(10); // Don't ask.
        }));

        $form.find('[name="_wcb_budget_data"]').val(sorted);
        return true;
    });

    wcb.models.Entry = Entry;
    wcb.table = table;
    wcb.summary = new SummaryView();

    // Sort all the input by types, meta first, because linked data in
    // income and expenses rely on meta values.
    var types = ['meta', 'expense', 'income'];
    wcb.input = _.sortBy(wcb.input, function(i) {
        return types.indexOf(i.type);
    });

    _.each(wcb.input, function(i){
        wcb.table.collection.add(new wcb.models.Entry(i));
    });

    wcb.summary.urls = wcb.urls;
    wcb.summary.render();

    // Allow sorting entries.
    $container.sortable({
        items: '.wcb-entry',
        handle: '.move',
        axis: 'y',
        placeholder: 'wcb-entry-placeholder',
        start: function(e, ui) {
            ui.placeholder.height(ui.item.height());
        }
    });

    // Update nonces when necessary.
    // TODO: Add post locking.
    $document.on('heartbeat-send', function(e, data) {
        data['wcb_budgets_heartbeat'] = 1;
    });

    $document.on('heartbeat-tick', function(e, data) {
        $('#_wpnonce').val(data.wcb_budgets.nonce);
    });

    $document.on('click', '#wcb-budget-submit', function() {
        if (!confirm('Are you sure you would like to submit this budget for approval?'))
            return false;

        return true;
    });

    $document.on('click', '#wcb-budget-approve, #wcb-budget-reject', function() {
        if (!confirm('Are you sure?'))
            return false;

        return true;
    });
}(jQuery));