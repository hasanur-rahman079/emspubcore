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

        <!-- 4. Payment History - Professional Design with Inline Styles -->
        <div style="margin-top: 40px; padding-top: 30px; border-top: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 600; color: #1e293b; margin: 0;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0ABF96" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                    Payment History
                </h3>
                <span style="background: #f1f5f9; color: #64748b; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 500;">
                    {$emspubcorePaymentHistory|@count} transactions
                </span>
            </div>
            
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <table style="width: 100%; border-collapse: collapse;" id="paymentHistoryTable">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
                            <th style="padding: 14px 16px; text-align: left; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0;">Invoice</th>
                            <th style="padding: 14px 16px; text-align: left; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0;">Date</th>
                            <th style="padding: 14px 16px; text-align: left; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0;">Amount</th>
                            <th style="padding: 14px 16px; text-align: left; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0;">Status</th>
                            <th style="padding: 14px 16px; text-align: left; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0;">Plan</th>
                            <th style="padding: 14px 16px; text-align: center; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="paymentHistoryBody">
                        {foreach from=$emspubcorePaymentHistory item=payment name=paymentLoop}
                            <tr class="payment-row" data-index="{$smarty.foreach.paymentLoop.index}" style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 16px; vertical-align: middle;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="background: linear-gradient(135deg, #0ABF96 0%, #057F5F 100%); color: #fff; font-size: 9px; font-weight: 700; padding: 3px 6px; border-radius: 4px; letter-spacing: 0.5px;">INV</span>
                                        <span style="font-family: monospace; font-size: 13px; color: #1e293b; font-weight: 500;">{$payment->payment_id|string_format:"%06d"}</span>
                                    </div>
                                </td>
                                <td style="padding: 16px; vertical-align: middle;">
                                    <span style="font-size: 13px; color: #475569;">{$payment->payment_date|substr:0:10}</span>
                                </td>
                                <td style="padding: 16px; vertical-align: middle;">
                                    <span style="font-size: 14px; font-weight: 600; color: #1e293b;">${$payment->amount / 100|string_format:"%.2f"}</span>
                                    <span style="font-size: 11px; color: #94a3b8; margin-left: 4px;">USD</span>
                                </td>
                                <td style="padding: 16px; vertical-align: middle;">
                                    {if $payment->status == 'succeeded'}
                                        <span style="display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #047857;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                            Completed
                                        </span>
                                    {else}
                                        <span style="display: inline-flex; align-items: center; padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #fef3c7; color: #d97706;">{$payment->status|ucfirst}</span>
                                    {/if}
                                </td>
                                <td style="padding: 16px; vertical-align: middle;">
                                    {assign var=planColor value="#f1f5f9"}
                                    {assign var=planTextColor value="#64748b"}
                                    {if $payment->plan_type|lower == 'basic'}
                                        {assign var=planColor value="#dbeafe"}
                                        {assign var=planTextColor value="#1d4ed8"}
                                    {elseif $payment->plan_type|lower == 'premium'}
                                        {assign var=planColor value="#fae8ff"}
                                        {assign var=planTextColor value="#a21caf"}
                                    {elseif $payment->plan_type|lower == 'enterprise'}
                                        {assign var=planColor value="#fef3c7"}
                                        {assign var=planTextColor value="#b45309"}
                                    {elseif $payment->plan_type|lower == 'bsmiab'}
                                        {assign var=planColor value="#e0e7ff"}
                                        {assign var=planTextColor value="#4338ca"}
                                    {/if}
                                    <span style="display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; background: {$planColor}; color: {$planTextColor};">{$payment->plan_type|ucfirst}</span>
                                </td>
                                <td style="padding: 16px; vertical-align: middle; text-align: center;">
                                    <a href="{$baseUrl}/plugins/generic/emspubcore/invoice.php?payment_id={$payment->payment_id}&journal_id={$emspubcoreJournalId}" 
                                       title="Download Invoice"
                                       target="_blank"
                                       style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: #f1f5f9; color: #64748b; text-decoration: none;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="7 10 12 15 17 10"></polyline>
                                            <line x1="12" y1="15" x2="12" y2="3"></line>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        {foreachelse}
                            <tr>
                                <td colspan="6" style="padding: 60px 20px; text-align: center;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5">
                                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                        <line x1="1" y1="10" x2="23" y2="10"></line>
                                    </svg>
                                    <p style="margin: 15px 0 5px; font-size: 15px; font-weight: 500; color: #64748b;">No payment history found</p>
                                    <span style="font-size: 13px; color: #94a3b8;">Your transactions will appear here after your first payment</span>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <div id="paginationControls" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <div style="font-size: 13px; color: #64748b;">
                        <span id="paginationInfo">Showing 1-5 of {$emspubcorePaymentHistory|@count}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button id="prevPage" disabled style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; font-weight: 500; color: #475569; cursor: pointer;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                            Previous
                        </button>
                        <div id="pageNumbers" style="display: flex; gap: 4px;"></div>
                        <button id="nextPage" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; font-weight: 500; color: #475569; cursor: pointer;">
                            Next
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
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
            var checkoutUrl = "{url router=$smarty.const.ROUTE_PAGE page="emspubcore" op="checkout"}";
            
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
                    var url = checkoutUrl + (checkoutUrl.indexOf('?') === -1 ? '?' : '&') + 'plan=' + selectedPlan + '&billing=' + billing + '&journalId=' + journalId;
                    window.location.href = url;
                }
            });
            
            // Renew Button Click - Go to Stripe for renewal
            $('#emspubcoreRenewBtn').click(function(e) {
                e.preventDefault();
                
                if (confirm('You are about to renew your ' + currentPlan.charAt(0).toUpperCase() + currentPlan.slice(1) + ' plan.\n\nThis will:\n• Charge your payment method\n• Reset your submission counter to 0\n• Extend your plan for another year\n\nContinue?')) {
                    var billing = 'yearly';
                    var url = checkoutUrl + (checkoutUrl.indexOf('?') === -1 ? '?' : '&') + 'plan=' + currentPlan + '&billing=' + billing + '&journalId=' + journalId + '&renew=1';
                    window.location.href = url;
                }
            });
            
            // Visual cue for read-only
            if (!canEdit) {
                $('.emspubcore-plan-card').css('cursor', 'default');
            }
            
            // Initialize button state
            updateButtonState();
            
            // ============ PAGINATION ============
            var itemsPerPage = 5;
            var currentPage = 1;
            var $rows = $('.payment-row');
            var totalItems = $rows.length;
            var totalPages = Math.ceil(totalItems / itemsPerPage);
            
            function showPage(page) {
                currentPage = page;
                var start = (page - 1) * itemsPerPage;
                var end = start + itemsPerPage;
                
                $rows.hide().slice(start, end).show();
                
                // Update info text
                var showingEnd = Math.min(end, totalItems);
                $('#paginationInfo').text('Showing ' + (start + 1) + '-' + showingEnd + ' of ' + totalItems);
                
                // Update buttons
                $('#prevPage').prop('disabled', page === 1);
                $('#nextPage').prop('disabled', page === totalPages);
                
                // Update page numbers
                renderPageNumbers();
            }
            
            function renderPageNumbers() {
                var $container = $('#pageNumbers');
                $container.empty();
                
                for (var i = 1; i <= totalPages; i++) {
                    var isActive = (i === currentPage);
                    var btnStyle = 'width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border: 1px solid ' + (isActive ? '#0ABF96' : '#e2e8f0') + '; border-radius: 6px; background: ' + (isActive ? '#0ABF96' : '#fff') + '; font-size: 13px; font-weight: 500; color: ' + (isActive ? '#fff' : '#475569') + '; cursor: pointer;';
                    var $btn = $('<button style="' + btnStyle + '">' + i + '</button>');
                    $btn.data('page', i);
                    $container.append($btn);
                }
            }
            
            $('#prevPage').click(function() {
                if (currentPage > 1) showPage(currentPage - 1);
            });
            
            $('#nextPage').click(function() {
                if (currentPage < totalPages) showPage(currentPage + 1);
            });
            
            $(document).on('click', '.page-num', function() {
                showPage($(this).data('page'));
            });
            
            // Hide pagination if not enough items
            if (totalItems <= itemsPerPage) {
                $('#paginationControls').hide();
            } else {
                showPage(1);
            }
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
        
        /* Payment History Section */
        .emspubcore-history-section {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #e2e8f0;
        }
        
        .emspubcore-history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .emspubcore-history-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }
        
        .emspubcore-history-header h3 svg {
            color: #0ABF96;
        }
        
        .emspubcore-history-count {
            background: #f1f5f9;
            color: #64748b;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .emspubcore-history-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .emspubcore-history-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .emspubcore-history-table thead {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        
        .emspubcore-history-table th {
            padding: 14px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .emspubcore-history-table th.text-center,
        .emspubcore-history-table td.text-center {
            text-align: center;
        }
        
        .emspubcore-history-table td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        
        .emspubcore-history-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .emspubcore-history-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .invoice-id {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .invoice-badge {
            background: linear-gradient(135deg, #0ABF96 0%, #057F5F 100%);
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            padding: 3px 6px;
            border-radius: 4px;
            letter-spacing: 0.5px;
        }
        
        .invoice-number {
            font-family: 'SF Mono', 'Monaco', monospace;
            font-size: 13px;
            color: #1e293b;
            font-weight: 500;
        }
        
        .date-main {
            font-size: 13px;
            color: #475569;
        }
        
        .amount-cell {
            display: flex;
            align-items: baseline;
            gap: 4px;
        }
        
        .amount-value {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .amount-currency {
            font-size: 11px;
            color: #94a3b8;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .status-completed {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #047857;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }
        
        .plan-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .plan-free { background: #f1f5f9; color: #64748b; }
        .plan-basic { background: #dbeafe; color: #1d4ed8; }
        .plan-premium { background: #fae8ff; color: #a21caf; }
        .plan-enterprise { background: #fef3c7; color: #b45309; }
        .plan-bsmiab { background: #e0e7ff; color: #4338ca; }
        
        .download-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #f1f5f9;
            color: #64748b;
            transition: all 0.2s ease;
        }
        
        .download-btn:hover {
            background: #0ABF96;
            color: #fff;
            transform: translateY(-1px);
        }
        
        .empty-state td {
            padding: 60px 20px !important;
        }
        
        .empty-message {
            text-align: center;
        }
        
        .empty-message p {
            margin: 15px 0 5px;
            font-size: 15px;
            font-weight: 500;
            color: #64748b;
        }
        
        .empty-message span {
            font-size: 13px;
            color: #94a3b8;
        }
        
        /* Pagination */
        .emspubcore-pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        
        .pagination-info {
            font-size: 13px;
            color: #64748b;
        }
        
        .pagination-buttons {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pagination-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .pagination-btn:hover:not(:disabled) {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .page-numbers {
            display: flex;
            gap: 4px;
        }
        
        .page-num {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #fff;
            font-size: 13px;
            font-weight: 500;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .page-num:hover {
            background: #f1f5f9;
        }
        
        .page-num.active {
            background: #0ABF96;
            color: #fff;
            border-color: #0ABF96;
        }
    </style>
</tab>
