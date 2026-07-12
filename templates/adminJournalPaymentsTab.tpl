<tab id="emspubcoreJournalPayments" label="{translate key="plugins.generic.emspubcore.journalPayments"}">
    <link rel="stylesheet" href="{$baseUrl}/plugins/generic/emspubcore/styles/emspubcore.css" type="text/css" />

    <div class="ems-tab-content">

        <div class="ems-section-header">
            <div class="ems-section-title-group">
                <h3>Global Transaction Log</h3>
                <p>All successful subscription payments across every journal on this platform.</p>
            </div>
            <div class="ems-section-meta">
                <span class="ems-tx-count">{$emspubcoreAllPayments|@count} transactions</span>
            </div>
        </div>

        <div class="ems-card">
            <table class="ems-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Journal</th>
                        <th class="ems-col-date">Date</th>
                        <th class="ems-col-num">Amount</th>
                        <th>Plan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$emspubcoreAllPayments item=payment}
                        {assign var=planLower value=$payment->plan_type|lower}
                        <tr>
                            <td>
                                <div class="ems-inv-label">
                                    <span class="ems-inv-tag">INV</span>
                                    <span class="ems-inv-num">
                                        {if $payment->stripe_invoice_id}
                                            <span title="{$payment->stripe_invoice_id}">{$payment->stripe_invoice_id|truncate:18:"..."}</span>
                                        {else}
                                            {$payment->payment_id|string_format:"%06d"}
                                        {/if}
                                    </span>
                                </div>
                            </td>
                            <td class="ems-table-label">{$payment->journal_name|escape}</td>
                            <td class="ems-col-date ems-text-muted">{$payment->payment_date|substr:0:10}</td>
                            <td class="ems-col-num">
                                <strong class="ems-amount">${$payment->amount / 100|string_format:"%.2f"}</strong>
                                <span class="ems-currency">USD</span>
                            </td>
                            <td>
                                <span class="ems-badge ems-plan-{$planLower}">
                                    {$payment->plan_type|ucfirst}
                                </span>
                            </td>
                            <td>
                                {if $payment->status == 'succeeded'}
                                    <span class="ems-badge ems-badge-success">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="margin-right:3px;">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                        Completed
                                    </span>
                                {elseif $payment->status == 'pending'}
                                    <span class="ems-badge ems-badge-warning">Pending</span>
                                {else}
                                    <span class="ems-badge ems-badge-neutral">{$payment->status|ucfirst}</span>
                                {/if}
                            </td>
                        </tr>
                    {foreachelse}
                        <tr class="ems-table-empty">
                            <td colspan="6">
                                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" style="display:block;margin:0 auto 10px;">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                    <line x1="1" y1="10" x2="23" y2="10"></line>
                                </svg>
                                No payment history found.
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>

    </div>
</tab>
