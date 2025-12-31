<tab id="emspubcoreJournalManagement" label="{translate key="plugins.generic.emspubcore.journalManagement"}">
    <div class="pkp_list_panel" style="padding: 20px;">
        <div class="pkp_header">
            <h3>Manage Journal Subscriptions & Discounts</h3>
            <p style="color: #666; margin-bottom: 20px;">View current subscription status and set journal-specific discount overrides.</p>
        </div>
        
        <table class="pkpTable">
            <thead>
                <tr>
                    <th>{translate key="context.context"}</th>
                    <th>{translate key="plugins.generic.emspubcore.currentPlan"}</th>
                    <th>{translate key="plugins.generic.emspubcore.subscriptionDate"}</th>
                    <th>{translate key="plugins.generic.emspubcore.nextPayment"}</th>
                    <th style="width: 150px;">Discount (%)</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$emspubcoreJournalManagement item=journal}
                    <tr data-journal-id="{$journal.id}">
                        <td style="font-weight: 600;">{$journal.name|escape}</td>
                        <td>
                            <span style="padding: 2px 8px; border-radius: 4px; background: #e9ecef; font-size: 13px;">
                                {$journal.planName}
                            </span>
                        </td>
                        <td>{$journal.subscriptionDate}</td>
                        <td>{$journal.nextPayment}</td>
                        <td>
                            <div style="display: flex; gap: 5px; align-items: center;">
                                <input type="number" class="journal-discount-input" value="{$journal.discount|default:0}" min="0" max="100" style="width: 60px; padding: 4px; border: 1px solid #ddd; border-radius: 4px;" />
                                <button class="pkp_button save-discount-btn" style="padding: 4px 10px; font-size: 12px;">Save</button>
                            </div>
                        </td>
                    </tr>
                {foreachelse}
                    <tr><td colspan="5" style="text-align: center; padding: 30px;">{translate key="common.none"}</td></tr>
                {/foreach}
            </tbody>
        </table>
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
                            $btn.css('background-color', '#28a745').css('color', 'white').text('Saved!');
                            setTimeout(function() {
                                $btn.prop('disabled', false).css('background-color', '').css('color', '').text('Save');
                            }, 2000);
                        } else {
                            alert('Error: ' + (response.message || 'Unknown error'));
                            $btn.prop('disabled', false).text('Save');
                        }
                    },
                    error: function() {
                        alert('Failed to save discount.');
                        $btn.prop('disabled', false).text('Save');
                    }
                });
            });
        });
    </script>
</tab>
