<tab id="emspubcoreJournalPayments" label="{translate key="plugins.generic.emspubcore.journalPayments"}">
    <div class="pkp_list_panel" style="padding: 20px;">
        <table class="pkpTable">
            <thead>
                <tr>
                    <th>{translate key="context.context"}</th>
                    <th>{translate key="plugins.generic.emspubcore.subscriptionDate"}</th>
                    <th>{translate key="plugins.generic.emspubcore.currentPlan"}</th>
                    <th>{translate key="plugins.generic.emspubcore.lastPayment"}</th>
                    <th>{translate key="plugins.generic.emspubcore.nextPayment"}</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$emspubcoreJournalPayments item=journalPayment}
                    <tr>
                        <td>{$journalPayment.name|escape}</td>
                        <td>{$journalPayment.subscriptionDate}</td>
                        <td>{$journalPayment.planName}</td>
                        <td>{$journalPayment.lastPayment}</td>
                        <td>{$journalPayment.nextPayment}</td>
                    </tr>
                {foreachelse}
                    <tr><td colspan="5" style="text-align: center;">{translate key="common.none"}</td></tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</tab>
