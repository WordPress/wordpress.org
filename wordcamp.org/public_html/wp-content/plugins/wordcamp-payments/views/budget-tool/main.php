<script>
window.wcb = window.wcb || {models:{}, input:[]};
wcb.input = <?php echo json_encode( $budget ); ?>;
wcb.urls = <?php echo json_encode( $inspire_urls ); ?>;
</script>

<div class="wrap wcb-budget-tool">
    <h2 class="nav-tab-wrapper wp-clearfix">
		<a href="#" class="nav-tab nav-tab-active">Preliminary Budget</a>
		<!--<a href="#" class="nav-tab">Working Budget</a>-->
	</h2>

    <p style="max-width: 800px;">Welcome to your WordCamp budget, it's time to crunch some numbers! When you're done with the preliminary budget, hit the "Submit for Approval" button below – a WordCamp deputy will be notified and will review your work. If you're having trouble with these numbers, or if you have any questions, don't hesitate to reach out to your mentor or Central.</p>

    <div class="left">
        <h2>Event Data</h2>
        <table class="wcb-budget-container">
            <tbody>
                <tr class="wcb-group-header">
                    <th style="width: 50%;">Name</th>
                    <th style="width: 50%;">Value</th>
                </tr>
                <tr class="wcb-meta-placeholder" style="display: none;">
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="right">
        <h2>Summary</h2>
        <div class="wcb-summary-placeholder"></div>
    </div>

    <div class="clear"></div>

    <h2>Expenses</h2>
    <table class="wcb-budget-container">
        <tbody>
            <tr class="wcb-group-header">
                <th style="width: 25%;">Category</th>
                <th style="width: 25%;">Detail</th>
                <th style="width: 25%;" class="amount">Amount</th>
                <th style="width: 25%;"></th>
            </tr>
            <tr class="wcb-expense-placeholder">
                <td colspan="4">New Expense Item</td>
            </tr>
        </tbody>
    </table>

    <h2>Income</h2>
    <table class="wcb-budget-container">
        <tbody>
            <tr class="wcb-group-header">
                <th style="width: 25%;">Category</th>
                <th style="width: 25%;">Detail</th>
                <th style="width: 25%;" class="amount">Amount</th>
                <th style="width: 25%;"></th>
            </tr>

            <tr class="wcb-income-placeholder">
                <td colspan="4">New Income Item</td>
            </tr>
        </tbody>
    </table>

    <form class="wcb-submit-form" action="options.php" method="post">
        <?php settings_fields( 'wcb_budget_noop' ); ?>
        <input type="hidden" name="_wcb_budget_data" value="<?php echo esc_attr( json_encode( $budget ) ); ?>" />

        <p class="submit">
            <?php submit_button( 'Save Draft', 'secondary', 'wcb-budget-save-draft', false ); ?>
            <a href="<?php echo admin_url( 'admin.php?page=wordcamp-budget' ); ?>" class="button">Cancel Changes</a>
            <?php submit_button( 'Submit for Approval', 'primary', 'wcb-budget-submit', false ); ?>
        </p>
    </form>
</div>

<script type="text/template" id="wcb-tmpl-summary">
    <tbody>
        <tr class="wcb-group-header">
            <th style="width: 50%;"></th>
            <th style="width: 50%;"></th>
        </tr>
        <tr>
            <td>Income</td>
            <td class="amount">{{data.income.toFixed(2)}}</td>
        </tr>
        <tr>
            <td>Expenses</td>
            <td class="amount">{{data.expenses.toFixed(2)}}</td>
        </tr>
        <tr>
            <td>Variance</td>
            <td class="amount <# if (data.variance < 0) { #>wcb-negative<# } #>">{{data.variance.toFixed(2)}}</td>
        </tr>
        <tr>
            <td>Cost Per Person Per Day</td>
            <td class="amount">{{data.per_person.toFixed(2)}}</td>
        </tr>
        <tr>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td></td>
            <td class="amount">
                <# if (data.variance < 0) { #>
                <a href="#" target="_blank" class="inspire">inspire me</a>
                <# } #>
            </td>
        </tr>
    </tbody>
</script>
<script type="text/template" id="wcb-tmpl-entry">
    <# if (data.type == 'meta' ) { #>
        <td>{{wcb.metaLabels[data.name]}}</td>
        <td class="editable">
            <input class="value" type="text" value="{{data.value}}" />
        </td>
    <# } else { #>

        <# if (data.type == 'expense') { #>
        <td class="editable">
            <select class="category">
                <# _.each(wcb.categories, function(label,key){ #>
                <option value="{{key}}" <#if(key==data.category){#>selected<#}#>>{{label}}</option>
                <#}); #>
            </select>
        </td>
        <# } else { #>
        <td>
            Income
            <input type="hidden" class="category" value="{{data.category}}" />
        </td>
        <# } #>

        <td class="editable">
            <input class="note" type="text" value="{{data.note}}" />
        </td>
        <td class="editable">
            <div class="link-toggle">
                <select class="link-value">
                    <option value="" <#if(!data.link){#>selected<#}#>>none</option>
                    <# _.each(wcb.linkData, function(item, k) { #>
                    <option value="{{k}}" <# if (data.link==k) { #>selected<# } #>>{{{item.label}}}</option>
                    <# }); #>
                </select>
                <span class="dashicons dashicons-admin-links"></span>
            </div>
            <input class="amount" type="text" value="{{data.realAmount.toFixed(2)}}" />

            <# if (data.link) { #>
                <div class="link">
                    <# if (data.linkHasValue) { #>
                    <span>{{data.amount.toFixed(2)}}</span>
                    <# } #>

                    {{{data.linkLabel}}}
                </div>
            <# } #>
        </td>
        <td class="actions">
            <a href="#" class="delete"><span class="dashicons dashicons-trash"></span></a>
        </td>
    <# } #>
</script>