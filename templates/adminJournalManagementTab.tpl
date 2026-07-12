<tab id="emspubcoreJournalManagement" label="{translate key="plugins.generic.emspubcore.journalManagement"}">
    <link rel="stylesheet" href="{$baseUrl}/plugins/generic/emspubcore/styles/emspubcore.css" type="text/css" />

    <div class="ems-tab-content">

        <div class="ems-section-header">
            <div class="ems-section-title-group">
                <h3>{translate key="plugins.generic.emspubcore.journalManagement"}</h3>
                <p>View subscription status and set per-journal discount overrides. Discounts apply on top of any plan-level discount price.</p>
            </div>
        </div>

        <div class="ems-card">
            <table class="ems-table">
                <thead>
                    <tr>
                        <th>{translate key="context.context"}</th>
                        <th>{translate key="plugins.generic.emspubcore.currentPlan"}</th>
                        <th class="ems-col-date">{translate key="plugins.generic.emspubcore.subscriptionDate"}</th>
                        <th class="ems-col-date">{translate key="plugins.generic.emspubcore.nextPayment"}</th>
                        <th class="ems-col-num" style="width:150px;">Discount (%)</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$emspubcoreJournalManagement item=journal}
                        {assign var=planLower value=$journal.planName|lower}
                        <tr data-journal-id="{$journal.id}">
                            <td class="ems-table-label">{$journal.name|escape}</td>
                            <td>
                                <span class="ems-badge ems-plan-{$planLower}">
                                    {$journal.planName}
                                </span>
                            </td>
                            <td class="ems-col-date ems-text-muted">{$journal.subscriptionDate}</td>
                            <td class="ems-col-date ems-text-muted">{$journal.nextPayment}</td>
                            <td class="ems-col-num">
                                <div class="ems-discount-wrap">
                                    <input type="number" class="ems-discount-input journal-discount-input"
                                           value="{$journal.discount|default:0}" min="0" max="100" />
                                    <button class="pkp_button save-discount-btn">
                                        {translate key="common.save"}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    {foreachelse}
                        <tr class="ems-table-empty">
                            <td colspan="5">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" style="display:block;margin:0 auto 10px;">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                    <polyline points="9 22 9 12 15 12 15 22"/>
                                </svg>
                                {translate key="common.none"}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>

    </div>

    <script type="text/javascript">
        $(function() {
            $('.save-discount-btn').click(function() {
                var $btn = $(this);
                var $row = $btn.closest('tr');
                var journalId = $row.data('journal-id');
                var discount = $row.find('.journal-discount-input').val();

                $btn.prop('disabled', true).text('...');

                $.ajax({
                    url: '{url router=$smarty.const.ROUTE_PAGE page="emspubcore" op="saveJournalDiscount"}',
                    type: 'POST',
                    data: {
                        csrfToken: '{$csrfToken}',
                        journalId: journalId,
                        discount: discount
                    },
                    success: function(response) {
                        if (response.status) {
                            $btn.removeClass('pkp_button_offset').addClass('pkp_button_primary').text('Saved!');
                            setTimeout(function() {
                                $btn.removeClass('pkp_button_primary').addClass('pkp_button_offset')
                                    .prop('disabled', false).text('{translate key="common.save"}');
                            }, 2000);
                        } else {
                            alert('Error: ' + (response.message || 'Unknown error'));
                            $btn.prop('disabled', false).text('{translate key="common.save"}');
                        }
                    },
                    error: function() {
                        alert('Failed to save discount.');
                        $btn.prop('disabled', false).text('{translate key="common.save"}');
                    }
                });
            });
        });
    </script>
</tab>
