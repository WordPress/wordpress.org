<script>
window.wcb = window.wcb || {models:{}, input:[]};
wcb.input = <?php echo json_encode( $budget[ $view ] ); ?>;
wcb.urls = <?php echo json_encode( $inspire_urls ); ?>;
wcb.currencies = <?php echo json_encode( $currencies ); ?>;
wcb.status = <?php echo json_encode( $budget['status'] ); ?>;
wcb.view = <?php echo json_encode( $view ); ?>;
wcb.editable = <?php echo json_encode( $editable ); ?>;
</script>

<div class="wrap wcb-budget-tool">
    <h2 class="nav-tab-wrapper wp-clearfix">

        <?php if ( $budget['status'] == 'draft' || $budget['status'] == 'pending' ) : ?>
		<a href="<?php echo esc_url( add_query_arg( 'wcb-view', 'prelim' ) ); ?>"
            class="nav-tab <?php if ( $view == 'prelim' ) { ?>nav-tab-active<?php } ?>">

            <?php if ( $budget['status'] == 'pending' ) : ?>
            <svg width="20" height="20" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M640 768h512v-192q0-106-75-181t-181-75-181 75-75 181v192zm832 96v576q0 40-28 68t-68 28h-960q-40 0-68-28t-28-68v-576q0-40 28-68t68-28h32v-192q0-184 132-316t316-132 316 132 132 316v192h32q40 0 68 28t28 68z"/></svg>
            <?php endif; ?>

            <span>Preliminary Budget</span>
        </a>
        <?php elseif ( $budget['status'] == 'approved' ) : ?>
		<a href="<?php echo esc_url( add_query_arg( 'wcb-view', 'approved' ) ); ?>"
            class="nav-tab <?php if ( $view == 'approved' ) { ?>nav-tab-active<?php } ?>">
            <svg width="20" height="20" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M640 768h512v-192q0-106-75-181t-181-75-181 75-75 181v192zm832 96v576q0 40-28 68t-68 28h-960q-40 0-68-28t-28-68v-576q0-40 28-68t68-28h32v-192q0-184 132-316t316-132 316 132 132 316v192h32q40 0 68 28t28 68z"/></svg>

            <span>Approved Budget</span>
        </a>
		<a href="<?php echo esc_url( add_query_arg( 'wcb-view', 'working' ) ); ?>"
            class="nav-tab <?php if ( $view == 'working' ) { ?>nav-tab-active<?php } ?>">
            <span>Working Budget</span>
        </a>
        <?php endif; ?>
		<!--<a href="#" class="nav-tab">Working Budget</a>-->
	</h2>

    <?php if ( $budget['status'] == 'draft' ) : ?>
    <p style="max-width: 800px;">Welcome to your WordCamp budget, it's time to crunch some numbers! When you're done with the preliminary budget, hit the "Submit for Approval" button below – a WordCamp deputy will be notified and will review your work. If you're having trouble with these numbers, or if you have any questions, don't hesitate to reach out to your mentor or Central.</p>
    <?php elseif ( $budget['status'] == 'pending' ) : ?>
    <p style="max-width: 800px;">This budget has been submitted for approval. You will be notified when it is approved. Or not.</p>
    <?php elseif ( $budget['status'] == 'approved' && $view == 'approved' ) : ?>
    <p style="max-width: 800px;">This budget has been approved and can not be modified. Use the working budget if you'd like to play around with numbers.</p>
    <?php elseif ( $view == 'working' ) : ?>
    <p style="max-width: 800px;">Welcome to your working budget. Feel free to play around with numbers here. They will not affect your approved budget.</p>
    <?php endif; ?>

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
                <th style="width: 20%;">Category</th>
                <th style="width: 40%;">Detail</th>
                <th style="width: 25%;" class="amount">Amount</th>
                <th style="width: 15%;"></th>
            </tr>

            <tr class="wcb-expense-placeholder">
                <?php if ( $editable ) : ?>
                <td colspan="4">New Expense Item</td>
                <?php endif; ?>
            </tr>
        </tbody>
    </table>

    <h2>Income</h2>
    <table class="wcb-budget-container">
        <tbody>
            <tr class="wcb-group-header">
                <th style="width: 20%;">Category</th>
                <th style="width: 40%;">Detail</th>
                <th style="width: 25%;" class="amount">Amount</th>
                <th style="width: 15%;"></th>
            </tr>

            <tr class="wcb-income-placeholder">
                <?php if ( $editable ) : ?>
                <td colspan="4">New Income Item</td>
                <?php endif; ?>
            </tr>
        </tbody>
    </table>

    <form class="wcb-submit-form" action="options.php" method="post">
        <?php settings_fields( 'wcb_budget_noop' ); ?>
        <input type="hidden" name="_wcb_budget_data" value="<?php echo esc_attr( json_encode( $budget ) ); ?>" />

        <?php if ( $budget['status'] == 'draft' ) : ?>
        <p class="submit">
            <?php submit_button( 'Save Draft', 'secondary', 'wcb-budget-save-draft', false ); ?>
            <a href="<?php echo admin_url( 'admin.php?page=wordcamp-budget' ); ?>" class="button">Cancel Changes</a>
            <?php submit_button( 'Submit for Approval', 'primary', 'wcb-budget-submit', false ); ?>
        </p>
        <?php elseif ( $budget['status'] == 'pending' && current_user_can( 'wcb_approve_budget' ) ) : ?>
        <p class="submit">
            <?php submit_button( 'Approve', 'primary', 'wcb-budget-approve', false ); ?>
            <?php submit_button( 'Reject', 'primary', 'wcb-budget-reject', false ); ?>
        </p>
        <?php elseif ( $budget['status'] == 'approved' && $view == 'working' ) : ?>
        <p class="submit">
            <?php submit_button( 'Update Working Budget', 'primary', 'wcb-budget-update-working', false ); ?>
            <a href="<?php echo admin_url( 'admin.php?page=wordcamp-budget&wcb-view=working' ); ?>" class="button">Cancel Changes</a>
            <?php submit_button( 'Reset to Approved Budget', 'secondary', 'wcb-budget-reset', false ); ?>
        </p>
        <?php endif; ?>
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
            <td class="amount">{{data.income}}</td>
        </tr>
        <tr>
            <td>Expenses</td>
            <td class="amount">{{data.expenses}}</td>
        </tr>
        <tr>
            <td>Variance</td>
            <td class="amount <# if (data.variance_raw < 0) { #>wcb-negative<# } #>">{{data.variance}}</td>
        </tr>
        <tr>
            <td>Cost Per Person Per Day</td>
            <td class="amount">{{data.per_person}}</td>
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
                <# if (data.variance_raw < 0) { #>
                <a href="#" target="_blank" class="inspire">inspire me</a>
                <# } #>
            </td>
        </tr>
    </tbody>
</script>
<script type="text/template" id="wcb-tmpl-entry">
    <# if (data.type == 'meta') { #>
        <td>{{wcb.metaLabels[data.name]}}</td>

        <# if (wcb.editable) { #>
            <td class="editable">

            <# if (data.name == 'currency') { #>
                <select class="value">
                    <# _.each(wcb.currencies, function(v, k) { #>
                    <option value="{{k}}" <#if(k==data.value){#>selected<#}#>>{{k}} - {{v}}</option>
                    <# }); #>
                </select>
            <# } else { #>
                <input class="value" type="text" value="{{data.value}}" />
            <# } #>
            </td>
        <# } else { #>
            <td>
                <# if (data.name == 'currency') { #>
                <div class="value">{{data.value}} - {{wcb.currencies[data.value]}}</div>
                <# } else { #>
                <div class="value">{{data.value}}</div>
                <# } #>
            </td>
        <# } #>

    <# } else { #>

        <# if (wcb.editable) { #>
            <# if (data.type == 'expense') { #>
            <td style="width: 20%" class="editable">
                <select class="category">
                    <# _.each(wcb.categories, function(label,key){ #>
                    <option value="{{key}}" <#if(key==data.category){#>selected<#}#>>{{label}}</option>
                    <#}); #>
                </select>
            </td>
            <# } else { #>
            <td style="width: 20%">
                Income
                <input type="hidden" class="category" value="{{data.category}}" />
            </td>
            <# } #>
        <# } else { #>
            <td style="width: 20%">
            <# if (data.type == 'expense') { #>
                {{wcb.categories[data.category]}}
            <# } else { #>
                Income
            <# } #>
            </td>
        <# } #>

        <# if (wcb.editable) { #>
        <td style="width: 40%" class="editable">
            <input class="note" type="text" value="{{data.note}}" />
        </td>
        <# } else { #>
        <td style="width: 40%">
            {{data.note}}
        </td>
        <# } #>

        <# if (wcb.editable) { #>
        <td style="width: 25%" class="editable">
            <div class="link-toggle">
                <select class="link-value">
                    <option value="" <#if(!data.link){#>selected<#}#>>none</option>
                    <# _.each(wcb.linkData, function(item, k) { #>
                    <option value="{{k}}" <# if (data.link==k) { #>selected<# } #>>{{{item.label}}}</option>
                    <# }); #>
                </select>
                <span class="dashicons dashicons-admin-links"></span>
            </div>
            <input class="amount" type="text" value="{{data.realAmountFormatted}}" />

            <# if (data.link) { #>
                <div class="link">
                    <# if (data.linkHasValue) { #>
                    <span>{{data.amountFormatted}}</span>
                    <# } #>

                    {{{data.linkLabel}}}
                </div>
            <# } #>
        </td>
        <# } else { #>
        <td style="width: 25%;">
            <div class="amount">{{data.realAmountFormatted}}</div>

            <# if (data.link) { #>
                <div class="link">
                    <# if (data.linkHasValue) { #>
                    <span>{{data.amountFormatted}}</span>
                    <# } #>

                    {{{data.linkLabel}}}
                </div>
            <# } #>
        </td>
        <# } #>

        <td style="width: 15%" class="actions">
            <# if (wcb.editable) { #>
            <a href="#" class="move">Move</a>
            <a href="#" class="delete">Delete</a>
            <# } #>
        </td>
    <# } #>
</script>