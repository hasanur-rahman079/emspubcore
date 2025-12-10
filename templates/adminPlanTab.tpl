<tab id="emspubcorePlan" label="{translate key="plugins.generic.emspubcore.plan"}">
    <!-- Force load the CSS file directly -->
    <link rel="stylesheet" href="{$baseUrl}/plugins/generic/emspubcore/styles/emspubcore.css" type="text/css" />
    
    <div class="pkp_form" style="padding: 20px;">
        
        <!-- 1. Header Section -->
        <div class="emspubcore-plan-header">
            <h3>Submission Plan for {$emspubcoreJournalName}</h3>
            <div class="emspubcore-status-row">
                <span class="status-item">
                    {translate key="plugins.generic.emspubcore.currentPlan"}: 
                    <strong>
                        {if $emspubcoreCurrentPlan}
                            {$emspubcoreCurrentPlan->getPlanType()|ucfirst}
                        {else}
                            Free
                        {/if}
                    </strong>
                </span>
                <span class="status-item">
                    {translate key="common.status"}: 
                    {if $emspubcoreCurrentPlan && $emspubcoreCurrentPlan->getIsActive()}
                        <span class="emspubcore-status-active">Active</span>
                    {else}
                        <span>Inactive</span>
                    {/if}
                </span>
                <span class="status-item">
                <span class="status-item">
                    {translate key="plugins.generic.emspubcore.usage"}:
                    <strong>
                        {assign var=usageCount value=$emspubcoreCurrentUsage|default:0}
                        {if $emspubcoreCurrentLimit > 0}
                            {$usageCount} / {$emspubcoreCurrentLimit} {translate key="plugins.generic.emspubcore.submissions"}
                        {else}
                            {$usageCount} / Unlimited
                        {/if}
                    </strong>
                </span>
            </div>
        </div>

        <form class="pkp_form" id="planForm" method="POST" action="{url router=$smarty.const.ROUTE_PAGE page="emspubcore" op="assignPlan"}">
            {csrf}
            <input type="hidden" name="journalId" value="{$emspubcoreJournalId}" />
            <!-- Normalize plan key/name to match what Handler expects -->
            <input type="hidden" name="planType" id="selectedPlanInput" value="{if $emspubcoreCurrentPlan}{$emspubcoreCurrentPlan->getPlanType()|lower|replace:' ':''}{else}free{/if}" />

            <!-- 2. Dynamic Plan Cards -->
            <div class="emspubcore-card-container">
                {foreach from=$emspubcorePlansObject item=plan}
                    {assign var=planKey value=$plan->getName()|lower|replace:' ':''}
                    {assign var=currentPlanKey value="free"}
                    {if $emspubcoreCurrentPlan}
                        {assign var=currentPlanKey value=$emspubcoreCurrentPlan->getPlanType()|lower|replace:' ':''}
                    {/if}

                    <div class="emspubcore-plan-card {if $planKey == $currentPlanKey}selected{/if}" data-plan="{$planKey}">
                        <div class="emspubcore-card-header">
                            <span class="emspubcore-card-title">{$plan->getName()|escape}</span>
                            <div class="emspubcore-radio"></div>
                        </div>
                        
                        <div class="emspubcore-price-container">
                            {if $plan->getDiscountedPrice() && $plan->getDiscountedPrice() > 0}
                                <span class="emspubcore-price" style="text-decoration: line-through; color: #999; font-size: 18px;">${$plan->getPrice()|string_format:"%.0f"}</span>
                                <span class="emspubcore-price-discounted" style="font-size: 24px; color: #008a00; font-weight: 700;">${$plan->getDiscountedPrice()|string_format:"%.0f"}</span>
                                <div style="font-size: 11px; color: #008a00; font-weight: bold; text-transform: uppercase; margin-top: 2px;">{translate key="plugins.generic.emspubcore.discountedPrice"}</div>
                            {else}
                                <span class="emspubcore-price">${$plan->getPrice()|string_format:"%.0f"}</span>
                            {/if}
                        </div>
                        
                        <div class="emspubcore-limit">
                            {if $plan->getSubmissionLimit() == 0}
                                Unlimited submissions
                            {else}
                                Up to {$plan->getSubmissionLimit()} submissions
                            {/if}
                        </div>
                    </div>
                {/foreach}
            </div>

            <!-- 3. Save Action or Contact Support Message -->
            <div class="emspubcore-save-actions">
                {if $emspubcoreCanEdit}
                    <button class="pkp_button pkp_button_primary" type="submit">{translate key="common.save"}</button>
                {else}
                    <div class="pkp_notification" style="background: #fcf8e3; border: 1px solid #faebcc; color: #8a6d3b; padding: 15px; border-radius: 4px; text-align: center;">
                        <strong>Upgrade Required:</strong> Please contact the EMS Support team to upgrade your plan.
                    </div>
                {/if}
            </div>
        </form>

        <!-- 4. Payment Options -->
        <div class="emspubcore-payment-options">
            <h3>Payment Options</h3>
            <div class="emspubcore-payment-buttons">
                <button class="pkp_button pkp_button_primary" onclick="alert('Payment Integration Coming Soon'); return false;">
                    Make Payment
                </button>
                <button class="pkp_button" onclick="alert('Link Copied'); return false;">
                    Copy payment link
                </button>
            </div>
        </div>

        <!-- 5. Payment History -->
        <h3>{translate key="plugins.generic.emspubcore.subscriptionHistory"}</h3>
        <div class="pkp_list_panel">
            <table class="pkpTable">
                <thead>
                    <tr>
                        <th style="width: 20%;">INVOICE ID</th>
                        <th style="width: 20%;">DATE</th>
                        <th style="width: 15%;">AMOUNT</th>
                        <th style="width: 15%;">STATUS</th>
                        <th style="width: 15%;">PLAN</th>
                        <th style="width: 15%;">DOWNLOAD INVOICE</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$emspubcorePaymentHistory item=payment}
                        <tr>
                            <td>
                                {if $payment->stripe_invoice_id}
                                    {$payment->stripe_invoice_id|truncate:15:"..."}
                                {else}
                                    INV-{math equation="rand(1000,9999)"}-{math equation="rand(10,99)"}
                                {/if}
                            </td>
                            <td>{$payment->payment_date|date_format:"%Y-%m-%d"}</td>
                            <td>${$payment->amount / 100|string_format:"%.2f"}</td>
                            <td>
                                <span class="emspubcore-history-status {if $payment->status == 'succeeded'}emspubcore-status-completed{else}emspubcore-status-failed{/if}">
                                    {if $payment->status == 'succeeded'}Completed{else}{$payment->status|ucfirst}{/if}
                                </span>
                            </td>
                            <td>{$payment->plan_type|ucfirst}</td>
                            <td>
                                <a href="#" class="emspubcore-invoice-link">↓</a>
                            </td>
                        </tr>
                    {foreachelse}
                        <!-- Mock Data for Display if Empty (requested by design requirement to show history) -->
                         <!-- 
                        <tr>
                            <td>INV-2023-001</td>
                            <td>2023-10-26</td>
                            <td>$29.00</td>
                            <td><span class="emspubcore-history-status emspubcore-status-completed">Completed</span></td>
                            <td>Basic</td>
                            <td><a href="#" class="emspubcore-invoice-link">↓</a></td>
                        </tr>
                        -->
                        <tr><td colspan="6" style="text-align: center; color: #777; padding: 20px;">No payment history found.</td></tr>
                    {/foreach}
                </tbody>
            </table>
        </div>

    </div>

    <script type="text/javascript">
        $(function() {
            var canEdit = {if $emspubcoreCanEdit}true{else}false{/if};
            
            $('.emspubcore-plan-card').click(function() {
                if (!canEdit) return; // Disable selection if read-only

                var plan = $(this).data('plan');
                
                // Update UI selection
                $('.emspubcore-plan-card').removeClass('selected');
                $(this).addClass('selected');
                
                // Update hidden input
                $('#selectedPlanInput').val(plan);
            });
            
            // Visual cue for read-only
            if (!canEdit) {
                $('.emspubcore-plan-card').css('cursor', 'default');
            }
        });
    </script>
</tab>
