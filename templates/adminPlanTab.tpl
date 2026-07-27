<tab id="emspubcorePlan" label="{translate key="plugins.generic.emspubcore.plan"}">
    <link rel="stylesheet" href="{$baseUrl}/plugins/generic/emspubcore/styles/emspubcore.css" type="text/css" />

    <div class="ems-tab-content">

        <!-- ============================================================
             PLAN HEADER — Journal name, status chips, usage progress
             ============================================================ -->
        <div class="emspubcore-plan-header">
            <div class="ems-plan-header-top">
                <div class="ems-plan-header-title">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#006798" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                        </svg>
                        {translate key="plugins.generic.emspubcore.plan"} &mdash; {$emspubcoreJournalName}
                    </h3>
                    {if $emspubcoreCurrentPlan && $emspubcoreCurrentPlan->getIsActive()}
                        <span class="ems-plan-status-pill ems-plan-status-active">
                            <span class="ems-status-dot"></span>
                            Active
                        </span>
                    {else}
                        <span class="ems-plan-status-pill ems-plan-status-inactive">
                            <span class="ems-status-dot"></span>
                            No Active Plan
                        </span>
                    {/if}
                </div>
            </div>

            <!-- Status metrics row -->
            <div class="ems-plan-metrics">
                <div class="ems-metric-item">
                    <span class="ems-metric-label">Current Plan</span>
                    <span class="ems-metric-value">
                        {if $emspubcoreCurrentPlan}
                            {$emspubcoreCurrentPlan->getPlanType()|ucfirst}
                        {else}
                            Free
                        {/if}
                    </span>
                </div>
                <div class="ems-metric-divider"></div>
                <div class="ems-metric-item">
                    <span class="ems-metric-label">Billing</span>
                    <span class="ems-metric-value">Yearly</span>
                </div>
                <div class="ems-metric-divider"></div>
                <div class="ems-metric-item ems-metric-usage">
                    <span class="ems-metric-label">Submissions Used</span>
                    <span class="ems-metric-value">
                        {assign var=usageCount value=$emspubcoreCurrentUsage|default:0}
                        {if $emspubcoreCurrentLimit > 0}
                            {$usageCount} <span class="ems-metric-sep">/</span> {$emspubcoreCurrentLimit}
                        {else}
                            {$usageCount} <span class="ems-metric-sep">/</span> &infin;
                        {/if}
                    </span>
                    {if $emspubcoreCurrentLimit > 0}
                        {assign var=usagePct value=$usageCount * 100 / $emspubcoreCurrentLimit}
                        <div class="ems-usage-bar">
                            <div class="ems-usage-fill {if $usagePct >= 90}ems-usage-danger{elseif $usagePct >= 70}ems-usage-warning{/if}"
                                 style="width:{$usagePct|round}%;" title="{$usagePct|round}% used"></div>
                        </div>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Hidden state inputs (keep ALL for JS functionality) -->
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

        <!-- ============================================================
             PLAN CARDS
             ============================================================ -->
        <div class="ems-section-label">{translate key="plugins.generic.emspubcore.selectPlan"}</div>
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

                {* Determine tier class for color accent *}
                {assign var=tierClass value="tier-default"}
                {if $plan->getPrice() == 0}
                    {assign var=tierClass value="tier-free"}
                {elseif $plan->getPrice() <= 100}
                    {assign var=tierClass value="tier-basic"}
                {elseif $plan->getPrice() <= 500}
                    {assign var=tierClass value="tier-premium"}
                {else}
                    {assign var=tierClass value="tier-enterprise"}
                {/if}

                <div class="emspubcore-plan-card {$tierClass} {if $planKey == $currentPlanKey}selected current{/if}"
                     data-plan="{$planKey}"
                     data-price="{$finalPlanPrice}">

                    {if $planKey == $currentPlanKey && $isPlanActive}
                        <div class="emspubcore-current-badge">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            Current Plan
                        </div>
                    {/if}

                    <div class="emspubcore-card-header">
                        <span class="emspubcore-card-icon">
                            {if $plan->getPrice() == 0}
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
                            {elseif $plan->getPrice() <= 100}
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                            {elseif $plan->getPrice() <= 500}
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            {else}
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
                            {/if}
                        </span>
                        <span class="emspubcore-card-title">{$plan->getName()|escape}</span>
                        <div class="emspubcore-radio"></div>
                    </div>

                    <div class="emspubcore-price-container">
                        {if $journalDiscountPct > 0}
                            <div class="emspubcore-price-original">${$baseEffectivePrice|string_format:"%.0f"}</div>
                            <div class="emspubcore-price-row">
                                <span class="emspubcore-price-discounted">${$finalPlanPrice|string_format:"%.0f"}</span>
                                <span class="emspubcore-price-period">/year</span>
                            </div>
                            <span class="ems-badge ems-badge-success" style="margin-top:6px;">Journal Discount {$journalDiscountPct}%</span>
                        {elseif $plan->getDiscountedPrice() && $plan->getDiscountedPrice() > 0}
                            <div class="emspubcore-price-original">${$plan->getPrice()|string_format:"%.0f"}</div>
                            <div class="emspubcore-price-row">
                                <span class="emspubcore-price-discounted">${$plan->getDiscountedPrice()|string_format:"%.0f"}</span>
                                <span class="emspubcore-price-period">/year</span>
                            </div>
                            <span class="ems-badge ems-badge-success" style="margin-top:6px;">{translate key="plugins.generic.emspubcore.discountedPrice"}</span>
                        {elseif $plan->getPrice() == 0}
                            <div class="emspubcore-price-row">
                                <span class="emspubcore-price-free">Free</span>
                            </div>
                        {else}
                            <div class="emspubcore-price-row">
                                <span class="emspubcore-price">${$plan->getPrice()|string_format:"%.0f"}</span>
                                <span class="emspubcore-price-period">/year</span>
                            </div>
                        {/if}
                    </div>

                    <div class="emspubcore-limit">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                        {if $plan->getSubmissionLimit() == 0}
                            Unlimited submissions / year
                        {else}
                            Up to {$plan->getSubmissionLimit()} submissions / year
                        {/if}
                    </div>

                    {if $plan->getDescription()}
                        <div class="emspubcore-card-desc">
                            {$plan->getDescription()|escape}
                        </div>
                    {/if}
                </div>
            {/foreach}
        </div>

        <!-- ============================================================
             ACTION BUTTONS
             ============================================================ -->
        <div class="emspubcore-save-actions">
            {if $emspubcoreCanEdit}
                <input type="hidden" id="currentUsage" value="{$emspubcoreCurrentUsage|default:0}" />
                <input type="hidden" id="currentLimit" value="{$emspubcoreCurrentLimit|default:0}" />

                <div id="emspubcoreLimitWarning" class="ems-warning-card" style="display:none;">
                    <div class="ems-warning-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    </div>
                    <div class="ems-warning-body">
                        <strong>Submission Limit Reached</strong>
                        <p>You have used all your submissions for this billing period. Renew your plan to reset the counter, or upgrade to a higher plan.</p>
                    </div>
                </div>

                <div class="ems-action-group">
                    <form id="activateFreePlanForm" method="POST"
                          action="{url router=$smarty.const.ROUTE_PAGE page="emspubcore" op="assignPlan"}"
                          style="display:inline;">
                        {csrf}
                        <input type="hidden" name="journalId" value="{$emspubcoreJournalId}" />
                        <input type="hidden" name="planType" id="activatePlanType" value="free" />
                        <button class="ems-btn ems-btn-primary" id="emspubcoreActivateBtn" type="submit" style="display:none;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            Activate Free Plan
                        </button>
                    </form>

                    <button class="ems-btn ems-btn-primary" id="emspubcoreUpgradeBtn" style="display:none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>
                        Upgrade Plan
                    </button>

                    <button class="ems-btn ems-btn-success" id="emspubcoreRenewBtn" style="display:none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                        Renew Plan
                    </button>

                    <span id="emspubcoreCurrentPlanNote" style="display:none; color:#64748b; font-size:13px; font-style:italic; padding:8px 0;"></span>
                </div>
            {else}
                <div class="ems-info-card">
                    <div class="ems-info-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    </div>
                    <div class="ems-info-body">
                        <strong>Upgrade Required</strong>
                        <p>Please contact your Journal Manager or Site Administrator to upgrade or renew the plan.</p>
                    </div>
                </div>
            {/if}
        </div>

        <!-- ============================================================
             SITE ADMIN — Manual Plan Assignment
             ============================================================ -->
        {if $emspubcoreIsSiteAdmin}
        <div class="ems-admin-panel">
            <div class="ems-admin-panel-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="m7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <h4>Site Administrator Actions</h4>
            </div>
            <p class="ems-admin-panel-desc">Manually assign a plan without requiring payment. Useful for comp accounts, partnerships, or troubleshooting.</p>
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

        <!-- ============================================================
             PAYMENT HISTORY
             ============================================================ -->
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
                                <td class="ems-col-date">
                                    {$payment->payment_date|substr:0:10}
                                </td>
                                <td>
                                    <strong class="ems-amount">
                                        ${$payment->amount / 100|string_format:"%.2f"}
                                    </strong>
                                    <span class="ems-currency">USD</span>
                                </td>
                                <td>
                                    {if $payment->status == 'succeeded'}
                                        <span class="ems-badge ems-badge-success">
                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="margin-right:4px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
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
                                    <div class="ems-empty-state">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.2" style="display:block;margin:0 auto 12px;">
                                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                            <line x1="1" y1="10" x2="23" y2="10"></line>
                                        </svg>
                                        <span class="ems-empty-title">No payment history yet</span>
                                        <span class="ems-empty-sub">Transactions will appear here after your first payment.</span>
                                    </div>
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
                $('#paginationInfo').text('Showing ' + (start + 1) + '–' + showEnd + ' of ' + totalItems);
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
