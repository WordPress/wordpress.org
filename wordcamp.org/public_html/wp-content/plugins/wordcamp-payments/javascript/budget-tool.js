window.wcb = window.wcb || {models:{}, input:[]};

(function($){
    var $container = $('.wcb-budget-container tbody'),
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
            var data = {
                'income': 0,
                'expenses': 0,
                'variance': 0,
                'per_person': 0
            };

            _.each(wcb.table.collection.where({type: 'income'}), function(item) {
                data['income'] += item.getRealAmount();
            });

            _.each(wcb.table.collection.where({type: 'expense'}), function(item) {
                data['expenses'] += item.getRealAmount();
            });

            data['variance'] = data['income'] - data['expenses'];
            var attendees = wcb.table.collection.findWhere({type: 'meta', name: 'attendees'});
            data['per_person'] = attendees ? data['expenses'] / attendees.get('value') : 0;

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
            'change input': 'editSave',
            'change select.category': 'editSave',
            'change select.link-value': 'linkChange',

            'focus input, select': 'focus',
            'blur input, select': 'blur'
        },

        initialize: function() {
            this.model.bind('change', this.render, this);
            this.model.bind('destroy', this.remove, this);
        },

        linkChange: function() {
            this.model.set('link', this.$el.find('.link-value').val() || null);
            return this;
        },

        focus: function(e) {
            var $target = $(e.target);
            $target.parents('td').addClass('focused');

            if (($target.hasClass('amount') || $target.hasClass('link-value')) && this.model.get('link') && this.model.linkHasValue())
                this.$el.find('.amount').val(this.model.get('amount').toFixed(2));

            return this;
        },

        blur: function(e) {
            var $target = $(e.target);
            $target.parents('td').removeClass('focused');

            if (($target.hasClass('amount') || $target.hasClass('link-value')) && this.model.get('link'))
                this.$el.find('.amount').val(this.model.getRealAmount().toFixed(2));

            return this;
        },

        render: function() {
            var data = this.model.toJSON();
            data.realAmount = this.model.getRealAmount();
            data.linkLabel = this.model.getLinkLabel();
            data.linkHasValue = this.model.linkHasValue();

            this.template = _.template($('#wcb-tmpl-entry').html(), null, template_options);
            this.$el.html(this.template(data));
            this.$el.toggleClass('has-changed', this.model.hasChanged() && ! this.model.get('new'));
            this.$el.toggleClass('is-new', this.model.get('new'));
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
                this.model.set('value', this.$el.find('.value').val());
            } else {
                this.model.set('note', this.$el.find('.note').val());
                this.model.set('category', this.$el.find('.category').val());

                var $target = $(e.target);
                if ($target.hasClass('amount') || $target.hasClass('link-value'))
                    this.model.set('amount', parseFloat(this.$el.find('.amount').val()));
            }

            this.clearSelection.apply(this);
            this.render.apply(this);
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
            this.model.destroy();
            wcb.summary.render.apply(wcb.summary);
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

    wcb.categories = {
        'venue': 'Venue',
        'audio-visual': 'Audio Visual',
        'after-party': 'After Party',
        'camera-shipping': 'Camera Shipping',
        'food-beverage': 'Food & Beverage',
        'office-supplies': 'Office Supplies',
        'signage-badges': 'Signage & Badges',
        'speaker-event': 'Speaker Event',
        'swag': 'Swag',
        'other': 'Other'
    };

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
                return value * wcb.table.collection.findWhere({type: 'meta', name: 'speakers'}).get('value');
            }
        },

        'per-volunteer': {
            'label': 'per volunteer',
            'hasValue': true,
            'callback': function(value) {
                return value * wcb.table.collection.findWhere({type: 'meta', name: 'volunteers'}).get('value');
            }
        },

        'per-attendee': {
            'label': 'per attendee',
            'hasValue': true,
            'callback': function(value) {
                return value * wcb.table.collection.findWhere({type: 'meta', name: 'attendees'}).get('value');
            }
        },

        'per-day': {
            'label': 'per day',
            'hasValue': true,
            'callback': function(value) {
                return value * wcb.table.collection.findWhere({type: 'meta', name: 'days'}).get('value');
            }
        },

        'per-track': {
            'label': 'per track',
            'hasValue': true,
            'callback': function(value) {
                return value * wcb.table.collection.findWhere({type: 'meta', name: 'tracks'}).get('value');
            }
        },

        'ticket-price-x-attendees': {
            'label': 'ticket price &times; attendees',
            'hasValue': false,
            'callback': function(value) {
                var attendees = wcb.table.collection.findWhere({type: 'meta', name: 'attendees'}).get('value');
                var price = wcb.table.collection.findWhere({type: 'meta', name: 'ticket-price'}).get('value');
                return attendees * price;
            }
        },

        'random': {
            'label': 'random',
            'hasValue': false,
            'callback': function(value) {
                return Math.floor((Math.random() * 500) + 1);
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
        $form.find('[name="_wcb_budget_data"]').val(JSON.stringify(wcb.table.collection));
        return true;
    });

    wcb.models.Entry = Entry;
    wcb.table = table;
    wcb.summary = new SummaryView();

    _.each(wcb.input, function(i){
        wcb.table.collection.add(new wcb.models.Entry(i));
    });

    wcb.summary.urls = wcb.urls;
    wcb.summary.render();
}(jQuery));