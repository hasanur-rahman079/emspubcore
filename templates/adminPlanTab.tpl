<tab id="emspubcorePlan" label="{translate key="plugins.generic.emspubcore.plan"}">
    <link rel="stylesheet" href="{$baseUrl}/plugins/generic/emspubcore/styles/emspubcore.css" type="text/css" />

    <div class="ems-tab-content">

        <!-- Plan Header / Status Row -->
        <div class="emspubcore-plan-header">
            <h3>Yearly Submission Plan &mdash; {$emspubcoreJournalName}</h3>
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

        <!-- Hidden state inputs -->
        {assign var=currentPlanKey value="free"}
        {assign var=isPlanActive value=0}
        {if $emspubcoreCurrentPlan}
            {assign var=currentPlanKey value=$emspubcoreCurrentPlan->getPlanType()|lower|replace:' ':''}
            {assign var=isPlanActive value=$emspubcoreCurrentPlan->getIsActive()}
        {/if}
        <input type="hidden" id="currentActivePlan"   value="{$currentPlanKey}" />
        <input type="hidden" id="selectedPlanInput"   value="{$currentPlanKey}" />
        <input type="hidden" id="selectedPlanPrice"   value="0" />
        <input type="hidden" id="emspubcoreJournalId" value="{$emspubcoreJournalId}" />
        <input type="hidden" id="emspubcoreBaseUrl"   value="{$baseUrl}" />
        <input type="hidden" id="isPlanActive"        value="{$isPlanActive}" />

        <!-- Plan Cards -->
        <div class="emspubcore-card-container">
            {foreach from=$emspubcorePlansObject item=plan}
                {assign var=planKey value=$plan->getName()|lower|replace:' ':''}
                {assign var=baseEffectivePrice value=$plan->getDiscountedPrice()|default:$plan->getPrice()}
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
                            <div style="text-decoration:line-through; color:#94a3b8; font-size:15px; font-weight:500;">
                                ${$baseEffectivePrice|string_format:"%.0f"}
                            </div>
                            <span class="emspubcore-price-discounted">${$finalPlanPrice|string_format:"%.0f"}</span>
                            <div style="margin-top:4px;">
                                <span class="ems-badge ems-badge-success">Journal Discount {$journalDiscountPct}%</span>
                            </div>
                        {elseif $plan->getDiscountedPrice() && $plan->getDiscountedPrice() > 0}
                            <div style="text-decoration:line-through; color:#94a3b8; font-size:15px; font-weight:500;">
                                ${$plan->getPrice()|string_format:"%.0f"}
                            </div>
                            <span class="emspubcore-price-discounted">${$plan->getDiscountedPrice()|string_format:"%.0f"}</span>
                            <div style="margin-top:4px;">
                                <span class="ems-badge ems-badge-success">{translate key="plugins.generic.emspubcore.discountedPrice"}</span>
                            </div>
                        {else}
                            <span class="emspubcore-price">${$plan->getPrice()|string_format:"%.0f"}</span>
                        {/if}
                    </div>

                    <div class="emspubcore-limit">
                        {if $plan->getSubmissionLimit() == 0}
                            Unlimited submissions / year
                        {else}
                            Up to {$plan->getSubmissionLimit()} submissions / year
                        {/if}
                    </div>

                    {if $planKey == $currentPlanKey && $isPlanActive}
                        <div class="emspubcore-current-badge">Current Plan</div>
                    {/if}
                </div>
            {/foreach}
        </div>

        <!-- Action Buttons -->
        <div class="emspubcore-save-actions">
            {if $emspubcoreCanEdit}
                <input type="hidden" id="currentUsage" value="{$emspubcoreCurrentUsage|default:0}" />
                <input type="hidden" id="currentLimit" value="{$emspubcoreCurrentLimit|default:0}" />

                <div id="emspubcoreLimitWarning" class="ems-warning-card" style="display:none;">
                    <p><strong>Submission Limit Reached</strong> &mdash;
                    You have used all your submissions for this billing period.
                    Renew your plan to reset the counter, or upgrade to a higher plan.</p>
                </div>

                <form id="activateFreePlanForm" method="POST"
                      action="{url router=$smarty.const.ROUTE_PAGE page="emspubcore" op="assignPlan"}"
                      style="display:inline;">
                    {csrf}
                    <input type="hidden" name="journalId" value="{$emspubcoreJournalId}" />
                    <input type="hidden" name="planType" id="activatePlanType" value="free" />
                    <button class="pkp_button pkp_button_primary" id="emspubcoreActivateBtn" type="submit" style="display:none;">
                        Activate Free Plan
                    </button>
                </form>

                <button class="pkp_button pkp_button_primary" id="emspubcoreUpgradeBtn" style="display:none;">
                    Upgrade Plan
                </button>

                <button class="ems-btn ems-btn-success" id="emspubcoreRenewBtn" style="display:none;">
                    Renew Plan
                </button>

                <span id="emspubcoreCurrentPlanNote" style="color:#64748b; font-size:13px; font-style:italic;"></span>
            {else}
                <div class="ems-info-card">
                    <p><strong>Upgrade Required</strong> &mdash;
                    Please contact your Journal Manager or Site Administrator to upgrade or renew the plan.</p>
                </div>
            {/if}
        </div>

        <!-- Site Admin: Manual Plan Assignment -->
        {if $emspubcoreIsSiteAdmin}
        <div class="ems-admin-panel">
            <div class="ems-admin-panel-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="m7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <h4>Site Administrator Actions</h4>
            </div>
            <p>Manually assign a plan without requiring payment. Useful for comp accounts, partnerships, or troubleshooting.</p>
            <div class="ems-admin-panel-actions">
                <select id="adminPlanSelect" class="ems-admin-select">
                    {foreach from=$emspubcorePlansObject item=plan}
                        {assign var=planKey value=$plan->getName()|lower|replace:' ':''}
                        <option value="{$planKey}" {if $planKey == $currentPlanKey}selected{/if}>
                            {$plan->getName()|escape} (${$plan->getPrice()|string_format:"%.0f"}/yr)
                        </option>
                    {/foreach}
                </select>
                <button id="adminAssignPlanBtn" type="button" class="ems-btn-admin-assign">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Assign Plan (No Payment)
                </button>
                <span id="adminAssignStatus" style="font-size:12px; color:#94a3b8;"></span>
            </div>
        </div>
        {/if}

        <!-- Payment History -->
        <div class="ems-payment-history">
            <div class="ems-payment-history-header">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0ABF96" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                    Payment History
                </h3>
                <span class="ems-tx-count">{$emspubcorePaymentHistory|@count} transactions</span>
            </div>

            <div class="ems-card">
                <table class="ems-table" id="paymentHistoryTable">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Plan</th>
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="paymentHistoryBody">
                        {foreach from=$emspubcorePaymentHistory item=payment name=paymentLoop}
                            <tr class="payment-row" data-index="{$smarty.foreach.paymentLoop.index}">
                                <td>
                                    <div class="ems-inv-label">
                                        <span class="ems-inv-tag">INV</span>
                                        <span class="ems-inv-num">{$payment->payment_id|string_format:"%06d"}</span>
                                    </div>
                                </td>
                                <td style="color:#64748b; font-size:13px; white-space:nowrap;">
                                    {$payment->payment_date|substr:0:10}
                                </td>
                                <td>
                                    <strong style="font-size:14px; color:#1e293b;">
                                        ${$payment->amount / 100|string_format:"%.2f"}
                                    </strong>
                                    <span style="font-size:11px; color:#94a3b8; margin-left:3px;">USD</span>
                                </td>
                                <td>
                                    {if $payment->status == 'succeeded'}
                                        <span class="ems-badge ems-badge-success">
                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="margin-right:3px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                            Completed
                                        </span>
                                    {else}
                                        <span class="ems-badge ems-badge-warning">{$payment->status|ucfirst}</span>
                                    {/if}
                                </td>
                                <td>
                                    {assign var=planLower value=$payment->plan_type|lower}
                                    <span class="ems-badge ems-plan-{$planLower}">
                                        {$payment->plan_type|ucfirst}
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <a href="{$baseUrl}/plugins/generic/emspubcore/invoice.php?payment_id={$payment->payment_id}&journal_id={$emspubcoreJournalId}"
                                       target="_blank" title="Download Invoice"
                                       class="ems-btn-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="7 10 12 15 17 10"></polyline>
                                            <line x1="12" y1="15" x2="12" y2="3"></line>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        {foreachelse}
                            <tr class="ems-table-empty">
                                <td colspan="6">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" style="display:block;margin:0 auto 10px;">
                                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                        <line x1="1" y1="10" x2="23" y2="10"></line>
                                    </svg>
                                    No payment history yet.
                                    <div style="font-size:12px; color:#94a3b8; margin-top:4px;">Transactions will appear here after your first payment.</div>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>

                <!-- Pagination -->
                <div id="paginationControls" class="ems-pagination">
                    <span id="paginationInfo" style="font-size:13px; color:#64748b;"></span>
                    <div class="ems-page-controls">
                        <button id="prevPage" disabled class="ems-page-btn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                            Prev
                        </button>
                        <div id="pageNumbers" class="ems-page-controls"></div>
                        <button id="nextPage" class="ems-page-btn">
                            Next
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- .ems-tab-content -->

    <script type="text/javascript">
        $(function() {
            var canEdit     = {if $emspubcoreCanEdit}true{else}false{/if};
            var currentPlan = $('#currentActivePlan').val();
            var journalId   = $('#emspubcoreJournalId').val();
            var isPlanActive   = $('#isPlanActive').val() === '1';
            var currentUsage   = parseInt($('#currentUsage').val()) || 0;
            var currentLimit   = parseInt($('#currentLimit').val()) || 0;
            var isLimitReached = (currentLimit > 0 && currentUsage >= currentLimit);
            var checkoutUrl    = "{url router=$smarty.const.ROUTE_PAGE page="emspubcore" op="checkout"}";

            function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

            function updateButtonState() {
                var selectedPlan  = $('#selectedPlanInput').val();
                var selectedPrice = parseFloat($('.emspubcore-plan-card.selected').data('price')) || 0;
                var $upgradeBtn   = $('#emspubcoreUpgradeBtn');
                var $activateBtn  = $('#emspubcoreActivateBtn');
                var $renewBtn     = $('#emspubcoreRenewBtn');
                var $note         = $('#emspubcoreCurrentPlanNote');
                var $limitWarn    = $('#emspubcoreLimitWarning');

                $upgradeBtn.hide(); $activateBtn.hide(); $renewBtn.hide();
                $note.hide();       $limitWarn.hide();

                if (isLimitReached && isPlanActive && currentPlan !== 'free') {
                    $limitWarn.show();
                }

                if (!isPlanActive && selectedPrice === 0) {
                    $activateBtn.show().text('Activate ' + cap(selectedPlan));
                    $('#activatePlanType').val(selectedPlan);
                } else if (!isPlanActive && selectedPrice > 0) {
                    $upgradeBtn.show().text('Upgrade to ' + cap(selectedPlan));
                } else if (selectedPlan === currentPlan && selectedPrice > 0) {
                    if (isLimitReached) {
                        $renewBtn.show().text('Renew ' + cap(currentPlan) + ' Plan');
                    } else {
                        $renewBtn.show().text('Renew ' + cap(currentPlan) + ' (Reset Counter)');
                        $note.show().text('You can renew early to reset your submission counter.');
                    }
                } else if (selectedPlan === currentPlan && selectedPrice === 0) {
                    $note.show().text('You are on the ' + cap(selectedPlan) + ' plan. Select a paid plan to get more submissions.');
                } else if (selectedPrice === 0) {
                    $activateBtn.show().text('Activate ' + cap(selectedPlan));
                    $('#activatePlanType').val(selectedPlan);
                } else {
                    $upgradeBtn.show().text('Upgrade to ' + cap(selectedPlan));
                }
            }

            // Card click
            $('.emspubcore-plan-card').click(function() {
                if (!canEdit) return;
                $('.emspubcore-plan-card').removeClass('selected');
                $(this).addClass('selected');
                $('#selectedPlanInput').val($(this).data('plan'));
                $('#selectedPlanPrice').val($(this).data('price'));
                updateButtonState();
            });

            // Upgrade
            $('#emspubcoreUpgradeBtn').click(function(e) {
                e.preventDefault();
                var p = $('#selectedPlanInput').val();
                if (p === 'free' || p === currentPlan) return;
                if (confirm('Upgrade to ' + cap(p) + ' plan?\nYou will be redirected to complete payment.')) {
                    var url = checkoutUrl + (checkoutUrl.indexOf('?') === -1 ? '?' : '&') + 'plan=' + p + '&billing=yearly&journalId=' + journalId;
                    window.location.href = url;
                }
            });

            // Renew
            $('#emspubcoreRenewBtn').click(function(e) {
                e.preventDefault();
                if (confirm('Renew ' + cap(currentPlan) + ' plan?\n\nThis will:\n• Charge your payment method\n• Reset your submission counter\n• Extend your plan for another year\n\nContinue?')) {
                    var url = checkoutUrl + (checkoutUrl.indexOf('?') === -1 ? '?' : '&') + 'plan=' + currentPlan + '&billing=yearly&journalId=' + journalId + '&renew=1';
                    window.location.href = url;
                }
            });

            if (!canEdit) { $('.emspubcore-plan-card').css('cursor', 'default'); }
            updateButtonState();

            // Admin assign plan
            $('#adminAssignPlanBtn').click(function() {
                var p = $('#adminPlanSelect').val();
                var $btn = $(this);
                if (confirm('Assign the "' + cap(p) + '" plan to this journal without payment?\n\nThis will set the plan to ' + p + ', activate for 1 year, and reset the submission counter.')) {
                    $btn.prop('disabled', true).css('opacity', '0.6');
                    $('#adminAssignStatus').text('Assigning...').css('color', '#fbbf24');
                    var form = $('<form>', { method: 'POST', action: '{url router=$smarty.const.ROUTE_PAGE page="emspubcore" op="assignPlan"}' });
                    form.append('{csrf}');
                    form.append($('<input>', { type: 'hidden', name: 'journalId', value: journalId }));
                    form.append($('<input>', { type: 'hidden', name: 'planType',  value: p }));
                    form.appendTo('body').submit();
                }
            });

            // Pagination
            var itemsPerPage = 5;
            var currentPage  = 1;
            var $rows        = $('.payment-row');
            var totalItems   = $rows.length;
            var totalPages   = Math.ceil(totalItems / itemsPerPage);

            function showPage(page) {
                currentPage = page;
                var start = (page - 1) * itemsPerPage;
                var end   = start + itemsPerPage;
                $rows.hide().slice(start, end).show();
                var showEnd = Math.min(end, totalItems);
                $('#paginationInfo').text('Showing ' + (start + 1) + '\u2013' + showEnd + ' of ' + totalItems);
                $('#prevPage').prop('disabled', page === 1);
                $('#nextPage').prop('disabled', page === totalPages);
                renderPageNumbers();
            }

            function renderPageNumbers() {
                var $c = $('#pageNumbers').empty();
                for (var i = 1; i <= totalPages; i++) {
                    var $btn = $('<button class="ems-page-btn page-num' + (i === currentPage ? ' active' : '') + '">' + i + '</button>');
                    $btn.data('page', i);
                    $c.append($btn);
                }
            }

            $('#prevPage').click(function() { if (currentPage > 1) showPage(currentPage - 1); });
            $('#nextPage').click(function() { if (currentPage < totalPages) showPage(currentPage + 1); });
            $(document).on('click', '.page-num', function() { showPage($(this).data('page')); });

            if (totalItems <= itemsPerPage) {
                $('#paginationControls').hide();
            } else {
                showPage(1);
            }
        });
    </script>
</tab>
