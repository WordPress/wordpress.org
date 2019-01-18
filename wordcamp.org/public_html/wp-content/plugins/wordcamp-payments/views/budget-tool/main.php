<script>
window.wcb = window.wcb || {models:{}, input:[]};
wcb.input = <?php echo json_encode( $budget[ $view ] ); ?>;
wcb.urls = <?php echo json_encode( $inspire_urls ); ?>;
wcb.currencies = <?php echo json_encode( $currencies ); ?>;
wcb.categories = <?php echo wp_json_encode( WordCamp_Budgets::get_payment_categories() ); ?>;
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

            <span><?php esc_html_e( 'Preliminary Budget', 'wordcamporg' ); ?></span>
        </a>
        <?php elseif ( $budget['status'] == 'approved' ) : ?>
		<a href="<?php echo esc_url( add_query_arg( 'wcb-view', 'approved' ) ); ?>"
            class="nav-tab <?php if ( $view == 'approved' ) { ?>nav-tab-active<?php } ?>">
            <svg width="20" height="20" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M640 768h512v-192q0-106-75-181t-181-75-181 75-75 181v192zm832 96v576q0 40-28 68t-68 28h-960q-40 0-68-28t-28-68v-576q0-40 28-68t68-28h32v-192q0-184 132-316t316-132 316 132 132 316v192h32q40 0 68 28t28 68z"/></svg>

            <span><?php esc_html_e( 'Approved Budget', 'wordcamporg' ); ?></span>
        </a>
		<a href="<?php echo esc_url( add_query_arg( 'wcb-view', 'working' ) ); ?>"
            class="nav-tab <?php if ( $view == 'working' ) { ?>nav-tab-active<?php } ?>">
            <span><?php esc_html_e( 'Working Budget', 'wordcamporg' ); ?></span>
        </a>
        <?php endif; ?>
		<!--<a href="#" class="nav-tab"><?php esc_html_e( 'Working Budget', 'wordcamporg' ); ?></a>-->
	</h2>

    <?php if ( $budget['status'] == 'draft' ) : ?>
		<p style="max-width: 800px;"><?php esc_html_e( 'Welcome to your WordCamp budget, it\'s time to crunch some numbers! When you\'re done with the preliminary budget, hit the "Submit for Approval" button below – a WordCamp deputy will be notified and will review your work. If you\'re having trouble with these numbers, or if you have any questions, don\'t hesitate to reach out to your mentor or Central.', 'wordcamporg' ); ?></p>
		<?php elseif ( $budget['status'] == 'pending' ) : ?>
		<p style="max-width: 800px;"><?php esc_html_e( 'This budget has been submitted for approval. You will be notified when it is reviewed.', 'wordcamporg' ); ?></p>
		<?php elseif ( $budget['status'] == 'approved' && $view == 'approved' ) : ?>
		<p style="max-width: 800px;"><?php esc_html_e( "This budget has been approved and cannot be modified. Use the working budget if you'd like to play around with numbers.", 'wordcamporg' ); ?></p>
		<?php elseif ( $view == 'working' ) : ?>
		<p style="max-width: 800px;"><?php esc_html_e( 'Welcome to your working budget. Feel free to play around with numbers here. They will not affect your approved budget.', 'wordcamporg' ); ?></p>
    <?php endif; ?>

    <div class="left">
        <h2><?php esc_html_e( 'Event Data', 'wordcamporg' ); ?></h2>
        <table class="wcb-budget-container">
            <tbody>
                <tr class="wcb-group-header">
                    <th style="width: 50%;"><?php esc_html_e( 'Name',  'wordcamporg' ); ?></th>
                    <th style="width: 50%;"><?php esc_html_e( 'Value', 'wordcamporg' ); ?></th>
                </tr>
                <tr class="wcb-meta-placeholder" style="display: none;">
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="right">
        <h2><?php esc_html_e( 'Summary', 'wordcamporg' ); ?></h2>
        <div class="wcb-summary-placeholder"></div>
    </div>

    <div class="clear"></div>

    <h2><?php esc_html_e( 'Expenses', 'wordcamporg' ); ?></h2>
    <table class="wcb-budget-container">
        <tbody>
            <tr class="wcb-group-header">
                <th style="width: 20%;"><?php esc_html_e( 'Category', 'wordcamporg' ); ?></th>
                <th style="width: 40%;"><?php esc_html_e( 'Detail',   'wordcamporg' ); ?></th>
                <th style="width: 25%;" class="amount"><?php esc_html_e( 'Amount', 'wordcamporg' ); ?></th>
                <th style="width: 15%;"></th>
            </tr>

            <tr class="wcb-expense-placeholder">
                <?php if ( $editable ) : ?>
                <td colspan="4"><?php esc_html_e( 'New Expense Item', 'wordcamporg' ); ?></td>
                <?php endif; ?>
            </tr>
        </tbody>
    </table>

    <h2><?php esc_html_e( 'Income', 'wordcamporg' ); ?></h2>
    <table class="wcb-budget-container">
        <tbody>
            <tr class="wcb-group-header">
                <th style="width: 20%;"><?php esc_html_e( 'Category', 'wordcamporg' ); ?></th>
                <th style="width: 40%;"><?php esc_html_e( 'Detail',   'wordcamporg' ); ?></th>
                <th style="width: 25%;" class="amount"><?php esc_html_e( 'Amount', 'wordcamporg' ); ?></th>
                <th style="width: 15%;"></th>
            </tr>

            <tr class="wcb-income-placeholder">
                <?php if ( $editable ) : ?>
                <td colspan="4"><?php esc_html_e( 'New Income Item', 'wordcamporg' ); ?></td>
                <?php endif; ?>
            </tr>
        </tbody>
    </table>

    <form class="wcb-submit-form" action="options.php" method="post">
        <?php settings_fields( 'wcb_budget_noop' ); ?>
        <input type="hidden" name="_wcb_budget_data" value="<?php echo esc_attr( json_encode( $budget ) ); ?>" />

        <?php if ( $budget['status'] == 'draft' && current_user_can( WordCamp_Budgets::ADMIN_CAP ) ) : ?>
        <p class="submit">
            <?php submit_button( esc_html__( 'Save Draft', 'wordcamporg' ), 'secondary', 'wcb-budget-save-draft', false ); ?>
            <?php submit_button( esc_html__( 'Save &amp; Request Review', 'wordcamporg' ), 'secondary', 'wcb-budget-request-review', false ); ?>
            <a href="<?php echo admin_url( 'admin.php?page=wordcamp-budget' ); ?>" class="button"><?php esc_html_e( 'Cancel Changes', 'wordcamporg' ); ?></a>
            <?php submit_button( esc_html__( 'Submit for Approval', 'wordcamporg' ), 'primary', 'wcb-budget-submit', false ); ?>
        </p>
        <?php elseif ( $budget['status'] == 'pending' && current_user_can( 'wcb_approve_budget' ) ) : ?>
        <p class="submit">
            <?php submit_button( esc_html__( 'Approve', 'wordcamporg' ), 'primary', 'wcb-budget-approve', false ); ?>
            <?php submit_button( esc_html__( 'Reject', 'wordcamporg' ), 'primary', 'wcb-budget-reject', false ); ?>
        </p>
        <?php elseif ( $budget['status'] == 'approved' && $view == 'working' && current_user_can( WordCamp_Budgets::ADMIN_CAP ) ) : ?>
        <p class="submit">
            <?php submit_button( esc_html__( 'Update Working Budget', 'wordcamporg' ), 'primary', 'wcb-budget-update-working', false ); ?>
            <a href="<?php echo admin_url( 'admin.php?page=wordcamp-budget&wcb-view=working' ); ?>" class="button"><?php esc_html_e( 'Cancel Changes', 'wordcamporg' ); ?></a>
            <?php submit_button( esc_html__( 'Reset to Approved Budget', 'wordcamporg' ), 'secondary', 'wcb-budget-reset', false ); ?>
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
            <td><?php esc_html_e( 'Income', 'wordcamporg' ); ?></td>
            <td class="amount">{{data.income}}</td>
        </tr>
        <tr>
            <td><?php esc_html_e( 'Expenses', 'wordcamporg' ); ?></td>
            <td class="amount">{{data.expenses}}</td>
        </tr>
        <tr>
            <td><?php esc_html_e( 'Variance', 'wordcamporg' ); ?></td>
            <td class="amount <# if (data.variance_raw < 0) { #>wcb-negative<# } #>">{{data.variance}}</td>
        </tr>
        <tr>
            <td><?php esc_html_e( 'Cost Per Person Per Day', 'wordcamporg' ); ?></td>
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
                <a href="#" target="_blank" class="inspire"><?php esc_html_e( 'inspire me', 'wordcamporg' ); ?></a>
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
					<option value="">
						<?php _e( '-- Select a Currency --', 'wordcamporg' ); ?>
					</option>

					<option value=""></option>

					<# _.each( wcb.currencies, function( value, key ) { #>
						<option value="{{key}}" <# if( key === data.value ) { #> selected <# } #> >
							{{value}}

							<# if ( key ) { #>
								({{key}})
							<# } #>
						</option>
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
                <?php esc_html_e( 'Income', 'wordcamporg' ); ?>
                <input type="hidden" class="category" value="{{data.category}}" />
            </td>
            <# } #>
        <# } else { #>
            <td style="width: 20%">
            <# if (data.type == 'expense') { #>
                {{wcb.categories[data.category]}}
            <# } else { #>
                <?php esc_html_e( 'Income', 'wordcamporg' ); ?>
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
            <a href="#" class="move"><?php esc_html_e( 'Move', 'wordcamporg' ); ?></a>
            <a href="#" class="delete"><?php esc_html_e( 'Delete', 'wordcamporg' ); ?></a>
            <# } #>
        </td>
    <# } #>
</script>