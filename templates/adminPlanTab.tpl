<tab id="emspubcorePlan" label="{translate key="plugins.generic.emspubcore.plan"}">
    <!-- Force load the CSS file directly -->
    <link rel="stylesheet" href="{$baseUrl}/plugins/generic/emspubcore/styles/emspubcore.css" type="text/css" />
    
    <div class="pkp_form" style="padding: 20px;">
        
        <!-- 1. Header Section -->
        <div class="emspubcore-plan-header">
            <h3>Yearly Submission Plan for {$emspubcoreJournalName}</h3>
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
                        <span class="emspubcore-status-inactive">Inactive</span>
                    {/if}
                </span>
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

        <!-- Store current plan for JS comparison -->
        {assign var=currentPlanKey value="free"}
        {assign var=isPlanActive value=0}
        {if $emspubcoreCurrentPlan}
            {assign var=currentPlanKey value=$emspubcoreCurrentPlan->getPlanType()|lower|replace:' ':''}
            {assign var=isPlanActive value=$emspubcoreCurrentPlan->getIsActive()}
        {/if}
        
        <input type="hidden" id="currentActivePlan" value="{$currentPlanKey}" />
        <input type="hidden" id="selectedPlanInput" value="{$currentPlanKey}" />
        <input type="hidden" id="selectedPlanPrice" value="0" />
        <input type="hidden" id="emspubcoreJournalId" value="{$emspubcoreJournalId}" />
        <input type="hidden" id="emspubcoreBaseUrl" value="{$baseUrl}" />
        <input type="hidden" id="isPlanActive" value="{$isPlanActive}" />

        <!-- 2. Dynamic Plan Cards -->
        <div class="emspubcore-card-container">
            {foreach from=$emspubcorePlansObject item=plan}
                {assign var=planKey value=$plan->getName()|lower|replace:' ':''}
                {assign var=baseEffectivePrice value=$plan->getDiscountedPrice()|default:$plan->getPrice()}
                
                {* Apply Journal Discount *}
                {assign var=journalDiscountPct value=$emspubcoreJournalDiscount|default:0}
                {assign var=finalPlanPrice value=$baseEffectivePrice}
                {if $journalDiscountPct > 0}
                    {assign var=discountAmount value=$baseEffectivePrice * $journalDiscountPct / 100}
                    {assign var=finalPlanPrice value=$baseEffectivePrice - $discountAmount}
                {/if}

                <div class="emspubcore-plan-card {if $planKey == $currentPlanKey}selected current{/if}" 
                     data-plan="{$planKey}" 
                     data-price="{$finalPlanPrice}">
                    <div class="emspubcore-card-header">
                        <span class="emspubcore-card-title">{$plan->getName()|escape}</span>
                        <div class="emspubcore-radio"></div>
                    </div>
                    
                    <div class="emspubcore-price-container">
                        {if $journalDiscountPct > 0}
                            <span class="emspubcore-price" style="text-decoration: line-through; color: #999; font-size: 16px;">${$baseEffectivePrice|string_format:"%.0f"}</span>
                            <span class="emspubcore-price-discounted" style="font-size: 24px; color: #008a00; font-weight: 700;">${$finalPlanPrice|string_format:"%.0f"}</span>
                            <div style="font-size: 11px; color: #008a00; font-weight: bold; text-transform: uppercase; margin-top: 2px;">
                                Journal Discount ({$journalDiscountPct}%)
                            </div>
                        {elseif $plan->getDiscountedPrice() && $plan->getDiscountedPrice() > 0}
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
                    
                    {if $planKey == $currentPlanKey && $isPlanActive}
                        <div class="emspubcore-current-badge">Current Plan</div>
                    {/if}
                </div>
            {/foreach}
        </div>

        <!-- 3. Action Button - Dynamic based on selection -->
        <div class="emspubcore-save-actions" style="margin-top: 20px;">
            {if $emspubcoreCanEdit}
                <!-- Hidden inputs for JS -->
                <input type="hidden" id="currentUsage" value="{$emspubcoreCurrentUsage|default:0}" />
                <input type="hidden" id="currentLimit" value="{$emspubcoreCurrentLimit|default:0}" />
                
                <!-- Activate Free Plan button (for new journals) -->
                <form id="activateFreePlanForm" method="POST" action="{url router=$smarty.const.ROUTE_PAGE page="emspubcore" op="assignPlan"}" style="display: inline;">
                    {csrf}
                    <input type="hidden" name="journalId" value="{$emspubcoreJournalId}" />
                    <input type="hidden" name="planType" id="activatePlanType" value="free" />
                    <button class="pkp_button pkp_button_primary" id="emspubcoreActivateBtn" type="submit" style="display: none;">
                        Activate Free Plan
                    </button>
                </form>
                
                <!-- Upgrade button (for switching to paid plans) -->
                <button class="pkp_button pkp_button_primary" id="emspubcoreUpgradeBtn" style="display: none;">
                    Upgrade Plan
                </button>
                
                <!-- Renew button (for renewing current paid plan) -->
                <button class="pkp_button" id="emspubcoreRenewBtn" style="display: none; background: #28a745; color: white; border-color: #28a745;">
                    Renew Plan
                </button>
                
                <!-- Limit reached warning -->
                <div id="emspubcoreLimitWarning" class="pkp_notification" style="display: none; background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 12px 15px; border-radius: 4px; margin-bottom: 15px;">
                    <strong>⚠️ Submission Limit Reached:</strong> You have used all your submissions for this billing period. 
                    Renew your plan to reset the counter, or upgrade to a higher plan for more submissions.
                </div>
                
                <span id="emspubcoreCurrentPlanNote" style="color: #666; font-style: italic;">
                    You are on the {$currentPlanKey|ucfirst} plan. Select a different plan to upgrade.
                </span>
            {else}
                <div class="pkp_notification" style="background: #fcf8e3; border: 1px solid #faebcc; color: #8a6d3b; padding: 15px; border-radius: 4px; text-align: center;">
                    <strong>Upgrade Required:</strong> Please contact your Journal Manager or Site Administrator to upgrade the plan.
                </div>
            {/if}
        </div>

        <!-- 4. Payment History -->
        <h3 style="margin-top: 30px;">{translate key="plugins.generic.emspubcore.subscriptionHistory"}</h3>
        <div class="pkp_list_panel">
            <table class="pkpTable">
                <thead>
                    <tr>
                        <th style="width: 20%;">INVOICE ID</th>
                        <th style="width: 20%;">DATE</th>
                        <th style="width: 15%;">AMOUNT</th>
                        <th style="width: 15%;">STATUS</th>
                        <th style="width: 15%;">PLAN</th>
                        <th style="width: 15%;">DOWNLOAD</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$emspubcorePaymentHistory item=payment name=paymentLoop}
                        <tr>
                            <td>
                                {if $payment->stripe_invoice_id}
                                    {$payment->stripe_invoice_id|truncate:15:"..."}
                                {else}
                                    INV-{$payment->payment_id|string_format:"%06d"}
                                {/if}
                            </td>
                            <td>
                                {* Format: extract first 10 chars for YYYY-MM-DD *}
                                {$payment->payment_date|substr:0:10}
                            </td>
                            <td>${$payment->amount / 100|string_format:"%.0f"}</td>
                            <td>
                                <span class="emspubcore-history-status {if $payment->status == 'succeeded'}emspubcore-status-completed{else}emspubcore-status-failed{/if}">
                                    {if $payment->status == 'succeeded'}COMPLETED{else}{$payment->status|upper}{/if}
                                </span>
                            </td>
                            <td>{$payment->plan_type|ucfirst}</td>
                            <td>
                                <a href="{$baseUrl}/plugins/generic/emspubcore/invoice.php?payment_id={$payment->payment_id}&journal_id={$emspubcoreJournalId}" 
                                   class="emspubcore-invoice-link" 
                                   title="Download Invoice"
                                   target="_blank"
                                   style="display: inline-flex; align-items: center; gap: 4px; color: #006798; text-decoration: none; font-size: 13px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="7 10 12 15 17 10"></polyline>
                                        <line x1="12" y1="15" x2="12" y2="3"></line>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    {foreachelse}
                        <tr><td colspan="6" style="text-align: center; color: #777; padding: 20px;">No payment history found.</td></tr>
                    {/foreach}
                </tbody>
            </table>
        </div>

    </div>

    <script type="text/javascript">
        $(function() {
            var canEdit = {if $emspubcoreCanEdit}true{else}false{/if};
            var currentPlan = $('#currentActivePlan').val();
            var baseUrl = $('#emspubcoreBaseUrl').val();
            var journalId = $('#emspubcoreJournalId').val();
            var isPlanActive = $('#isPlanActive').val() === '1';
            var currentUsage = parseInt($('#currentUsage').val()) || 0;
            var currentLimit = parseInt($('#currentLimit').val()) || 0;
            var isLimitReached = (currentLimit > 0 && currentUsage >= currentLimit);
            
            function updateButtonState() {
                var selectedPlan = $('#selectedPlanInput').val();
                var $selectedCard = $('.emspubcore-plan-card.selected');
                var selectedPrice = parseFloat($selectedCard.data('price')) || 0;
                var $upgradeBtn = $('#emspubcoreUpgradeBtn');
                var $activateBtn = $('#emspubcoreActivateBtn');
                var $renewBtn = $('#emspubcoreRenewBtn');
                var $noteSpan = $('#emspubcoreCurrentPlanNote');
                var $limitWarning = $('#emspubcoreLimitWarning');
                
                // Hide all buttons and warning first
                $upgradeBtn.hide();
                $activateBtn.hide();
                $renewBtn.hide();
                $noteSpan.hide();
                $limitWarning.hide();
                
                // Show limit warning if applicable
                if (isLimitReached && isPlanActive && currentPlan !== 'free') {
                    $limitWarning.show();
                }
                
                if (!isPlanActive && selectedPrice === 0) {
                    // New journal with no active plan - show Activate Free Plan
                    $activateBtn.show().text('Activate ' + selectedPlan.charAt(0).toUpperCase() + selectedPlan.slice(1));
                    $('#activatePlanType').val(selectedPlan);
                } else if (!isPlanActive && selectedPrice > 0) {
                    // New journal selecting a paid plan - show upgrade
                    $upgradeBtn.show().text('Upgrade to ' + selectedPlan.charAt(0).toUpperCase() + selectedPlan.slice(1));
                } else if (selectedPlan === currentPlan && selectedPrice > 0) {
                    // Same as current active paid plan - show renew option
                    if (isLimitReached) {
                        $renewBtn.show().text('Renew ' + currentPlan.charAt(0).toUpperCase() + currentPlan.slice(1) + ' Plan');
                    } else {
                        $renewBtn.show().text('Renew ' + currentPlan.charAt(0).toUpperCase() + currentPlan.slice(1) + ' (Reset Counter)');
                        $noteSpan.show().text('You can renew early to reset your submission counter.');
                    }
                } else if (selectedPlan === currentPlan && selectedPrice === 0) {
                    // On free plan - suggest upgrade
                    $noteSpan.show().text('You are on the ' + selectedPlan.charAt(0).toUpperCase() + selectedPlan.slice(1) + ' plan. Select a paid plan to upgrade and get more submissions.');
                } else if (selectedPrice === 0) {
                    // Different free plan or activation of free plan when another was active (if allowed)
                    $activateBtn.show().text('Activate ' + selectedPlan.charAt(0).toUpperCase() + selectedPlan.slice(1));
                    $('#activatePlanType').val(selectedPlan);
                } else {
                    // Different paid plan selected - show upgrade
                    $upgradeBtn.show().text('Upgrade to ' + selectedPlan.charAt(0).toUpperCase() + selectedPlan.slice(1));
                }
            }
            
            // Plan card click handler
            $('.emspubcore-plan-card').click(function() {
                if (!canEdit) return;

                var plan = $(this).data('plan');
                var price = $(this).data('price');
                
                // Update UI selection
                $('.emspubcore-plan-card').removeClass('selected');
                $(this).addClass('selected');
                
                // Update hidden inputs
                $('#selectedPlanInput').val(plan);
                $('#selectedPlanPrice').val(price);
                
                // Update button state
                updateButtonState();
            });
            
            // Upgrade Button Click - Go to Stripe
            $('#emspubcoreUpgradeBtn').click(function(e) {
                e.preventDefault();
                var selectedPlan = $('#selectedPlanInput').val();
                
                if (selectedPlan === 'free' || selectedPlan === currentPlan) {
                    return;
                }
                
                if (confirm('You are about to upgrade to the ' + selectedPlan.charAt(0).toUpperCase() + selectedPlan.slice(1) + ' plan.\n\nYou will be redirected to complete the payment. Continue?')) {
                    var billing = 'yearly';
                    var url = baseUrl + '/plugins/generic/emspubcore/checkout.php?plan=' + selectedPlan + '&billing=' + billing + '&journalId=' + journalId;
                    window.location.href = url;
                }
            });
            
            // Renew Button Click - Go to Stripe for renewal
            $('#emspubcoreRenewBtn').click(function(e) {
                e.preventDefault();
                
                if (confirm('You are about to renew your ' + currentPlan.charAt(0).toUpperCase() + currentPlan.slice(1) + ' plan.\n\nThis will:\n• Charge your payment method\n• Reset your submission counter to 0\n• Extend your plan for another year\n\nContinue?')) {
                    var billing = 'yearly';
                    var url = baseUrl + '/plugins/generic/emspubcore/checkout.php?plan=' + currentPlan + '&billing=' + billing + '&journalId=' + journalId + '&renew=1';
                    window.location.href = url;
                }
            });
            
            // Visual cue for read-only
            if (!canEdit) {
                $('.emspubcore-plan-card').css('cursor', 'default');
            }
            
            // Initialize button state
            updateButtonState();
        });
    </script>

    <style>
        .emspubcore-status-inactive { color: #dc3545; font-weight: bold; }
        .emspubcore-current-badge { 
            position: absolute; 
            top: -10px; 
            right: -10px; 
            background: #28a745; 
            color: white; 
            font-size: 10px; 
            padding: 3px 8px; 
            border-radius: 10px; 
            text-transform: uppercase;
            font-weight: bold;
        }
        .emspubcore-plan-card { position: relative; }
        .emspubcore-plan-card.current { border-color: #28a745; }
    </style>
</tab>
