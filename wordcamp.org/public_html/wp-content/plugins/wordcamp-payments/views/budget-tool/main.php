<?php
    $budget = array(
        array( 'type' => 'meta', 'name' => 'attendees', 'value' => 300 ),
        array( 'type' => 'meta', 'name' => 'days', 'value' => 2 ),
        array( 'type' => 'meta', 'name' => 'tracks', 'value' => 4 ),
        array( 'type' => 'meta', 'name' => 'speakers', 'value' => 25 ),
        array( 'type' => 'meta', 'name' => 'volunteers', 'value' => 10 ),
        array( 'type' => 'meta', 'name' => 'currency', 'value' => 'USD' ),
        array( 'type' => 'meta', 'name' => 'ticket-price', 'value' => 20.00 ),

        array( 'type' => 'income', 'category' => 'other', 'note' => 'Tickets Income', 'amount' => 3500 ),
        array( 'type' => 'income', 'category' => 'other', 'note' => 'Community Sponsorships', 'amount' => 4300 ),
        array( 'type' => 'income', 'category' => 'other', 'note' => 'Local Sponsorships', 'amount' => 7000 ),
        array( 'type' => 'income', 'category' => 'other', 'note' => 'Microsponsors', 'amount' => 500 ),

        array( 'type' => 'expense', 'category' => 'venue', 'note' => 'Venue', 'amount' => 7500 ),
        array( 'type' => 'expense', 'category' => 'venue', 'note' => 'Wifi Costs', 'amount' => 300, 'link' => 'per-day' ),
        array( 'type' => 'expense', 'category' => 'other', 'note' => 'Comped Tickets', 'amount' => 300 ),
        array( 'type' => 'expense', 'category' => 'audio-visual', 'note' => 'Video recording', 'amount' => 500 ),
        array( 'type' => 'expense', 'category' => 'audio-visual', 'note' => 'Projector rental', 'amount' => 300 ),
        array( 'type' => 'expense', 'category' => 'audio-visual', 'note' => 'Livestream', 'amount' => 200 ),
        array( 'type' => 'expense', 'category' => 'signage-badges', 'note' => 'Printing', 'amount' => 800 ),
        array( 'type' => 'expense', 'category' => 'signage-badges', 'note' => 'Badges', 'amount' => 8.21, 'link' => 'per-attendee' ),
        array( 'type' => 'expense', 'category' => 'food-beverage', 'note' => 'Snacks', 'amount' => 300 ),
        array( 'type' => 'expense', 'category' => 'food-beverage', 'note' => 'Lunch', 'amount' => 2350 ),
        array( 'type' => 'expense', 'category' => 'food-beverage', 'note' => 'Coffee', 'amount' => 500 ),
        array( 'type' => 'expense', 'category' => 'swag', 'note' => 'T-shirts', 'amount' => 780 ),
        array( 'type' => 'expense', 'category' => 'speaker-event', 'note' => 'Speakers Dinner', 'amount' => 20, 'link' => 'per-speaker' ),
    );
?>
<script>var wcb_data = <?php echo json_encode( $budget ); ?>;</script>

<div class="wrap">
    <h1>WordCamp Budget</h1>
    <table id="wcb-budget-container">
        <tbody>
            <tr class="wcb-group-header">
                <th colspan="4">Event Data</th>
            </tr>
            <tr class="wcb-meta-placeholder" style="display: none;">
                <td colspan="4"></td>
            </tr>
            <tr class="wcb-group-header">
                <th style="width: 200px;">Category</th>
                <th>Detail</th>
                <th style="width: 200px;" class="amount">Amount</th>
                <th style="width: 100px;"></th>
            </tr>
            <tr class="wcb-group-header">
                <th colspan="4">Expenses</th>
            </tr>
            <tr class="wcb-expense-placeholder">
                <td colspan="4">New Expense Item</td>
            </tr>
            <tr class="wcb-group-header">
                <th colspan="4">Income</th>
            </tr>
            <tr class="wcb-income-placeholder">
                <td colspan="4">New Income Item</td>
            </tr>
        </tbody>
    </table>

    <p class="submit">
        <?php submit_button( 'Save Budget', 'primary', 'submit', false ); ?>
        <a href="#" class="button">Cancel Changes</a>
    </p>
</div>

<style>
#wcb-budget-container {
    width: 100%;
    table-layout: fixed;
    white-space:nowrap;
    text-align: left;
    border-collapse: collapse;
    background: white;
    margin: 12px 0;
}

#wcb-budget-container,
#wcb-budget-container td,
#wcb-budget-container th {
    overflow: hidden;
    text-overflow: ellipsis;
    border: solid 1px #ccc;
    height: 30px;
    line-height: 30px;
}

#wcb-budget-container th {
    background: #f8f8f8;
}

#wcb-budget-container td,
#wcb-budget-container th {
    vertical-align: top;
    padding: 0 4px;
}

#wcb-budget-container tr.has-changed td {
    background: #f7ecdc;
}

#wcb-budget-container tr.is-new td {
    background: #dcf7e0;
}

#wcb-budget-container .wcb-entry-editor td {
    background: #d5e7f4;
}

#wcb-budget-container .wcb-entry-editor td.editable {
    padding: 0;
}

#wcb-budget-container .wcb-entry-editor input,
#wcb-budget-container .wcb-entry-editor select {
    float: left;
    padding: 0 4px;
    width: 100%;
    border: 0;
    margin: 0;
    background: transparent;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    border: none;
    box-shadow: none;
    border-radius: 0;
    height: 30px;
    line-height: 30px;
    font-size: inherit;
}

#wcb-budget-container .wcb-entry-editor input:focus {
    background: #e8f1f7;
}

#wcb-budget-container .dashicons {
    color: inherit;
    text-decoration: none;
    line-height: 30px;
    color: #aaa;
}

#wcb-budget-container .actions {
    text-align: right;
}

#wcb-budget-container tr:hover .dashicons {
    color: #444;
}

#wcb-budget-container .amount {
    text-align: right;
}

#wcb-budget-container .link-value {
    display: block;
    clear: left;
    color: #aaa;
    margin-top: -12px;
}

#wcb-budget-container .link-toggle {
    position: absolute;
    text-decoration: none;
    color: #444;
}

#wcb-budget-container .link-toggle .dashicons {
    font-size: 16px;
    padding-left: 2px;
    padding-right: 2px;
    padding-top: 1px;
}

.wcb-expense-placeholder,
.wcb-income-placeholder {
    color: #aaa;
    font-style: italic;
    cursor: pointer;
}
</style>

<script type="text/template" id="wcb-tmpl-entry">
    <# if (data.type == 'meta' ) { #>
        <td>{{wcb.metaLabels[data.name]}}</td>
        <td>{{data.value}}</td>
        <td></td>
        <td class="actions">
            <a href="#" class="edit"><span class="dashicons dashicons-edit"></span></a>
        </td>
    <# } else { #>
        <td>{{wcb.categories[data.category]}}</td>
        <td>{{data.note}}</td>
        <td class="amount">
            <span>{{data.realAmount.toFixed(2)}}</span>
            <# if (data.link) { #>
            <!--<span class="link-value">{{data.amount.toFixed(2)}} per attendee</span>-->
            <# } #>
        </td>
        <td class="actions">
            <a href="#" class="edit"><span class="dashicons dashicons-edit"></span></a>
            <a href="#" class="delete"><span class="dashicons dashicons-trash"></span></a>
        </td>
    <# } #>
</script>
<script type="text/template" id="wcb-tmpl-entry-editor">
    <# if (data.type == 'meta' ) { #>
        <td>{{wcb.metaLabels[data.name]}}</td>
        <td class="editable">
            <input class="value" type="text" value="{{data.value}}" />
        </td>
        <td></td>
        <td class="actions">
            <a href="#" class="save"><span class="dashicons dashicons-yes"></span></a>
            <a href="#" class="cancel"><span class="dashicons dashicons-no-alt"></span></a>
        </td>
    <# } else { #>
        <td class="editable">
            <select class="category">
                <# _.each(wcb.categories, function(label,key){ #>
                <option value="{{key}}" <#if(key==data.category){#>selected<#}#>>{{label}}</option>
                <#}); #>
            </select>
        </td>
        <td class="editable">
            <input class="note" type="text" value="{{data.note}}" />
        </td>
        <td class="editable">
            <a href="#" class="link-toggle"><span class="dashicons dashicons-admin-links"></span> {{data.link}}</a>
            <input class="amount" type="text" value="{{data.amount.toFixed(2)}}" />
        </td>
        <td class="actions">
            <a href="#" class="save"><span class="dashicons dashicons-yes"></span></a>
            <a href="#" class="cancel"><span class="dashicons dashicons-no-alt"></span></a>
        </td>
    <# } #>
</script>
<script type="text/template" id="wcb-tmpl-entry-link">
    <td colspan="2"></td>
    <td class="editable">
        <select class="link-value">
            <option value="" <#if(!data.link){#>selected<#}#>>None</option>
            <option value="per-attendee" <#if(data.link=='per-attendee'){#>selected<#}#>>Per Attendee</option>
            <option value="per-speaker" <#if(data.link=='per-speaker'){#>selected<#}#>>Per Speaker</option>
            <option value="per-day" <#if(data.link=='per-day'){#>selected<#}#>>Per Day</option>
        </select>
    </td>
    <td></td>
</script>

<script>
window.wcb = window.wcb || {models:{}};

(function($){
    $(document).on('budget-tool-render.wordcamp-budgets', function() {
        var $container = $('#wcb-budget-container tbody'),
            $income = $container.find('.wcb-income-placeholder'),
            $expense = $container.find('.wcb-expense-placeholder'),
            $meta = $container.find('.wcb-meta-placeholder');

        var template_options = {
    		evaluate:    /<#([\s\S]+?)#>/g,
    		interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
    		escape:      /\{\{([^\}]+?)\}\}(?!\})/g,
    		variable:    'data'
        };

        var Category = Backbone.Model.extend({
            defaults: {
                label: 'Category',
                value: 'category'
            }
        });

        var Categories = Backbone.Collection.extend({
            model: Category
        });

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

                if (this.get('link') === 'per-attendee') {
                    var attendees = wcb.table.collection.findWhere({type: 'meta', name: 'attendees'}).get('value');
                    return this.get('amount') * attendees;
                }

                if (this.get('link') === 'per-speaker') {
                    var speakers = wcb.table.collection.findWhere({type: 'meta', name: 'speakers'}).get('value');
                    return this.get('amount') * speakers;
                }

                if (this.get('link') === 'per-day') {
                    var days = wcb.table.collection.findWhere({type: 'meta', name: 'days'}).get('value');
                    return this.get('amount') * days;
                }

                return 0;
            },

            hasChanged: function() {
                // console.log(this._attr);
                // console.log(this.attributes);
                // console.log(this._realAmount)
                var changed = _.isEqual(this._attr, this.attributes) && this._realAmount == this.getRealAmount()
                return !changed;
            },

            editStart: function() {
                this.trigger('edit-start.wordcamp-budgets');
            }
        });

        var EntryView = Backbone.View.extend({
            className: 'wcb-entry',
            tagName: 'tr',

            events: {
                'dblclick td': 'editStart',
                'click .edit': 'editStart',
                'click .delete': 'delete',
            },

            initialize: function() {
                this.model.bind('change', this.render, this);
                this.model.bind('destroy', this.remove, this);
                this.model.bind('edit-start.wordcamp-budgets', this.editStart, this);
            },

            render: function() {
                var data = this.model.toJSON();
                data.realAmount = this.model.getRealAmount();

                this.template = _.template($('#wcb-tmpl-entry').html(), null, template_options);
                this.$el.html(this.template(data));
                this.$el.toggleClass('has-changed', this.model.hasChanged() && ! this.model.get('new'));
                this.$el.toggleClass('is-new', this.model.get('new'));
                return this;
            },

            editStart: function() {
                if (this.editing)
                    return false;

                this.editing = true;
                this.editor = new EntryEditorView({model: this.model});
                this.editor.on('edit-save.wordcamp-budgets', this.editSave, this);
                this.editor.on('edit-cancel.wordcamp-budgets', this.editCancel, this);

                this.$el.after(this.editor.render().el);
                this.$el.hide();
                return false;
            },

            editSave: function() {
                this.clearSelection.apply(this);
                this.model = this.editor.model;
                this.editor.remove();
                this.$el.show();
                this.editing = false;
            },

            editCancel: function() {
                this.clearSelection.apply(this);
                this.editor.remove();
                this.$el.show();
                this.editing = false;
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
                return false;
            }
        });

        var EntryEditorView = Backbone.View.extend({
            className: 'wcb-entry wcb-entry-editor',
            tagName: 'tr',

            events: {
                'click .save': 'editSave',
                'click .cancel': 'editCancel',
                'click .link-toggle': 'linkToggle',
                'keyup': 'keyup'
            },

            render: function() {
                this.template = _.template($('#wcb-tmpl-entry-editor').html(), null, template_options);
                this.$el.html(this.template(this.model.toJSON()));
                return this;
            },

            editSave: function() {
                this.model.set('amount', parseFloat(this.$el.find('.amount').val()));
                this.model.set('note', this.$el.find('.note').val());
                this.model.set('category', this.$el.find('.category').val());
                this.model.set('value', this.$el.find('.value').val() || null);

                if (this.linkView)
                    this.model.set('link', this.linkView.$el.find('.link-value').val() || null);

                this.trigger('edit-save.wordcamp-budgets', this);
                if (this.linkView)
                    this.linkView.remove();

                return false;
            },

            editCancel: function() {
                this.trigger('edit-cancel.wordcamp-budgets', this);
                if (this.linkView)
                    this.linkView.remove();

                return false;
            },

            keyup: function(e) {
                if (e.keyCode == 27) {
                    return this.editCancel.apply(this);
                } else if (e.keyCode == 13) {
                    return this.editSave.apply(this);
                }
            },

            linkToggle: function(e) {
                if (!this.linkView) {
                    this.linkView = new EntryLinkView({model: this.model});
                    this.$el.after(this.linkView.render().el);
                } else {
                    this.linkView.$el.toggle();
                }

                return false;
            }
        });

        var EntryLinkView = Backbone.View.extend({
            className: 'wcb-entry wcb-entry-editor wcb-entry-link',
            tagName: 'tr',

            events: {

            },

            render: function() {
                this.template = _.template($('#wcb-tmpl-entry-link').html(), null, template_options);
                this.$el.html(this.template(this.model.toJSON()));
                return this;
            }
        })

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

        wcb.models.Entry = Entry;
        wcb.table = table;
    });
}(jQuery));

jQuery(document).ready(function(){
    jQuery(document).trigger('budget-tool-render.wordcamp-budgets');

    _.each(wcb_data, function(i){
        wcb.table.collection.add(new wcb.models.Entry(i));
    });
});
</script>