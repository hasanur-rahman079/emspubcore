{**
 * templates/pendingPaymentsAdmin.tpl
 *
 * Pending Payments Admin Grid Content (AJAX-loaded tab content)
 * Returns only the content fragment, no full page wrapper
 *}
<div id="pendingPaymentsContent" style="padding: 20px;">
    <h2 style="margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #333;">{$pageTitle|escape}</h2>
    
    {if $pendingPayments && count($pendingPayments) > 0}
        <table class="pkpTable" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #333; width: 60px;">ID</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Title</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #333; width: 120px;">Amount</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #333; width: 140px;">Payment Type</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #333; width: 80px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$pendingPayments item=payment}
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px; color: #555;">{$payment.id}</td>
                        <td style="padding: 12px; color: #333; max-width: 400px;">
                            <span style="display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{$payment.title|escape}">{$payment.title|escape|truncate:80}</span>
                        </td>
                        <td style="padding: 12px; font-weight: 600; color: #006798;">{$payment.amount|string_format:"%.2f"} {$payment.currency}</td>
                        <td style="padding: 12px; color: #555;">{$payment.paymentType}</td>
                        <td style="padding: 12px; text-align: center;">
                            <a href="{$payment.viewUrl}" style="color: #006798; text-decoration: underline; font-size: 13px;">View</a>
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
        
        {* Pagination *}
        {if $totalPages > 1}
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 10px 0; border-top: 1px solid #eee;">
                <span style="color: #666; font-size: 13px;">
                    Showing {$startItem} - {$endItem} of {$totalItems} items
                </span>
                <div style="display: flex; gap: 12px; align-items: center;">
                    {if $currentPage > 1}
                        <a href="#" class="emsPendingPagination" data-page="{$currentPage-1}" style="color: #006798; text-decoration: underline; font-size: 13px; cursor: pointer;">← Previous</a>
                    {/if}
                    <span style="color: #666; font-size: 13px;">{$currentPage} / {$totalPages}</span>
                    {if $currentPage < $totalPages}
                        <a href="#" class="emsPendingPagination" data-page="{$currentPage+1}" style="color: #006798; text-decoration: underline; font-size: 13px; cursor: pointer;">Next →</a>
                    {/if}
                </div>
            </div>
            <script>
                $(function() {
                    $('.emsPendingPagination').on('click', function(e) {
                        e.preventDefault();
                        var page = $(this).data('page');
                        var $tab = $('#subscriptionsTabs');
                        var url = '{$baseUrl}?page=' + page;
                        // Load content via AJAX into the active tab panel
                        $.ajax({
                            url: url,
                            dataType: 'json',
                            success: function(response) {
                                if (response.status === true && response.content) {
                                    // Find the active tab panel and replace content
                                    var $panel = $tab.find('.ui-tabs-panel:visible');
                                    if ($panel.length) {
                                        $panel.html(response.content);
                                    }
                                }
                            }
                        });
                    });
                });
            </script>
        {/if}
    {else}
        <div style="padding: 40px; text-align: center; color: #666; background: #f9f9f9; border-radius: 8px;">
            <p style="font-size: 16px; margin: 0;">No pending payments found.</p>
            <p style="font-size: 13px; margin-top: 8px; color: #999;">All submissions have either been paid or have no publication fee.</p>
        </div>
    {/if}
</div>
