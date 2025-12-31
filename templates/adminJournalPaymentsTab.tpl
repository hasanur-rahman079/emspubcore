<tab id="emspubcoreJournalPayments" label="{translate key="plugins.generic.emspubcore.journalPayments"}">
    <div class="pkp_list_panel" style="padding: 20px;">
        <div class="pkp_header">
            <h3>Global Transaction Log</h3>
            <p style="color: #666; margin-bottom: 20px;">A complete history of all successful payments across all journals.</p>
        </div>

        <table class="pkpTable">
            <thead>
                <tr>
                    <th>INVOICE ID</th>
                    <th>JOURNAL</th>
                    <th>DATE</th>
                    <th>AMOUNT</th>
                    <th>PLAN</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$emspubcoreAllPayments item=payment}
                    <tr>
                        <td>
                            {if $payment->stripe_invoice_id}
                                <span title="{$payment->stripe_invoice_id}">{$payment->stripe_invoice_id|truncate:15:"..."}</span>
                            {else}
                                INV-{$payment->payment_id|string_format:"%06d"}
                            {/if}
                        </td>
                        <td style="font-weight: 600;">{$payment->journal_name|escape}</td>
                        <td>{$payment->payment_date|substr:0:10}</td>
                        <td>${$payment->amount / 100|string_format:"%.2f"}</td>
                        <td>
                            <span style="padding: 2px 8px; border-radius: 4px; background: #f8f9fa; font-size: 12px; border: 1px solid #eee;">
                                {$payment->plan_type|ucfirst}
                            </span>
                        </td>
                        <td>
                            <span style="color: #28a745; font-weight: bold; font-size: 11px;">
                                {if $payment->status == 'succeeded'}COMPLETED{else}{$payment->status|upper}{/if}
                            </span>
                        </td>
                    </tr>
                {foreachelse}
                    <tr><td colspan="6" style="text-align: center; padding: 30px; color: #777;">No payment history found.</td></tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</tab>
