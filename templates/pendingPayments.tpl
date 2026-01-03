{extends file="layouts/backend.tpl"}

{block name="page"}
    <!-- Custom styling to match specific design requirements -->
    {literal}
    <style>
        /* Force white background on main container */
        .pkp_structure_main, 
        .pkp_structure_content, 
        .app__main,
        .app__page, 
        .app__contentPanel {
            background-color: #fff !important;
            background: #fff !important;
        }

        .app__main{
            background: #fff !important;
        }
        
        .app__contentPanel {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .app__pageHeading {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
        }
        
        .pkpTable {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e5e7eb;
        }
        .pkpTable thead th {
            background-color: #f9fafb;
            color: #6b7280;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }
        
        .payment-title {
            font-weight: 700;
            color: #006798; /* Dark Blue title */
            display: block;
            margin-bottom: 4px;
            font-size: 14px;
            line-height: 1.4;
        }
        
        /* Subtitle as a professional badge */
        .payment-subtitle {
            display: inline-block !important; /* Badge width */
            font-size: 11px;
            color: #4b5563; /* Dark gray text */
            background-color: #f3f4f6; /* Light gray bg */
            margin-top: 4px;
            font-weight: 500;
            padding: 2px 8px;
            border-radius: 4px;
            line-height: 1.4;
            border: 1px solid #e5e7eb;
        }
        
        /* Badges */
        .pkpBadge {
            border-radius: 9999px;
            padding: 4px 12px;
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
            line-height: 1.5;
            min-width: 60px;
            text-align: center;
        }
        .pkpBadge--success {
            background-color: #dcfce7; /* Distinct Light Green */
            color: #166534; /* Dark Green Text */
            border: none;
        }
        .pkpBadge--warning {
            background-color: #fef9c3; /* Light Yellow/Orange */
            color: #854d0e; /* Dark Brown/Orange Text */
            border: none;
        }
        
        /* Buttons */
        .pkpButton {
             display: inline-block;
             text-decoration: none;
             padding: 6px 12px;
             border-radius: 4px;
             font-size: 13px;
             cursor: pointer;
             border: 1px solid transparent;
             line-height: 1.5;
             font-weight: 500;
             transition: all 0.2s;
        }
        .pkpButton--primary {
             background-color: #006798;
             border-color: #006798;
             color: white;
        }
        .pkpButton--primary:hover {
             background-color: #005680;
        }
        .pkpButton--invoice {
             background-color: #fff;
             border-color: #d1d5db;
             color: #374151;
        }
        .pkpButton--invoice:hover {
            background-color: #f3f4f6;
            color: #111827;
            border-color: #9ca3af;
        }
        
        /* Pagination */
        .pkp_pagination {
            font-size: 13px;
            color: #6b7280;
        }
    </style>
    {/literal}

    <h1 class="app__pageHeading">
        {$pageTitle}
    </h1>
    
    <div class="app__contentPanel">
        <div class="app__contentPanel__body">
            <table class="pkpTable">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Article Title</th>
                        <th style="width: 120px;">Fee</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 150px;">Payment Date</th>
                        <th style="width: 140px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$pendingPayments item=payment}
                        <tr>
                            <td style="color: #6b7280; font-size: 13px;">{$payment.id}</td>
                            <td>
                                <div class="payment-title">{$payment.title|escape}</div>
                                {if $payment.status == 'Pending'}
                                    {assign var="badgeBg" value="#fef9c3"}
                                    {assign var="badgeColor" value="#854d0e"}
                                    {assign var="badgeBorder" value="#fef08a"}
                                {else}
                                    {assign var="badgeBg" value="#dcfce7"}
                                    {assign var="badgeColor" value="#166534"}
                                    {assign var="badgeBorder" value="#bbf7d0"}
                                {/if}
                                <div class="payment-subtitle" style="display: inline-block; font-size: 11px; color: {$badgeColor}; background-color: {$badgeBg}; margin-top: 4px; font-weight: 600; padding: 2px 8px; border-radius: 12px; line-height: 1.4; border: 1px solid {$badgeBorder};">
                                    {if $payment.status == 'Pending'}Awaiting Payment{elseif $payment.status == 'Waived'}Submission Submitted{else}Submission Submitted{/if}
                                </div>
                            </td>
                            <td>
                                {if $payment.isDiscounted}
                                    <span class="payment-amount" style="font-weight: 600; color: #059669;">{$payment.amount|string_format:"%.2f"} {$payment.currency}</span>
                                    <div style="margin-top: 4px;">
                                        <span style="font-size: 11px; text-decoration: line-through; color: #9ca3af;">{$payment.originalFee|string_format:"%.2f"} {$payment.currency}</span>
                                        <span style="display: inline-block; font-size: 10px; color: #059669; background-color: #d1fae5; margin-left: 6px; font-weight: 600; padding: 2px 6px; border-radius: 4px; border: 1px solid #a7f3d0;">Discounted</span>
                                    </div>
                                {else}
                                    <span class="payment-amount" style="font-weight: 600; color: #333;">{$payment.amount|string_format:"%.2f"} {$payment.currency}</span>
                                {/if}
                            </td>
                            <td>
                                {if $payment.status == 'Paid'}
                                    <span class="pkpBadge pkpBadge--success" style="background-color: #dcfce7 !important; color: #166534 !important; border: none; border-radius: 9999px; padding: 4px 12px; display: inline-block; font-size: 12px; font-weight: 600;">Paid</span>
                                {elseif $payment.status == 'Waived'}
                                    <span class="pkpBadge pkpBadge--success" style="background-color: #dcfce7 !important; color: #166534 !important; border: none; border-radius: 9999px; padding: 4px 12px; display: inline-block; font-size: 12px; font-weight: 600;">Waived</span>
                                {else}
                                    <span class="pkpBadge pkpBadge--warning" style="background-color: #fef9c3 !important; color: #854d0e !important; border: none; border-radius: 9999px; padding: 4px 12px; display: inline-block; font-size: 12px; font-weight: 600;">Pending</span>
                                {/if}
                            </td>
                            <td style="color: #6b7280;">
                                {if $payment.date != '-' && $payment.date}
                                    {$payment.date|date_format:$dateFormatShort}
                                {else}
                                    -
                                {/if}
                            </td>
                            <td style="text-align: right;">
                                {if $payment.status == 'Pending'}
                                    <a href="{$payment.payUrl}" class="pkpButton pkpButton--primary">Pay Now</a>
                                {elseif $payment.status == 'Paid'}
                                    <a href="{$payment.invoiceUrl}" target="_blank" class="pkpButton pkpButton--invoice">Download Invoice</a>
                                {else}
                                    <span style="color: #6b7280; font-size: 13px; font-style: italic;">Fee Waived</span>
                                {/if}
                            </td>
                        </tr>
                    {foreachelse}
                         <tr><td colspan="6" style="text-align:center; padding: 40px; color: #6b7280;">No records found.</td></tr>
                    {/foreach}
                </tbody>
            </table>
            
            {if $totalItems > 0}
            <div class="pkp_pagination" style="margin-top: 20px; display: flex; justify-content: flex-end; align-items: center; gap: 16px;">
                 <span style="color: #6b7280;">Showing {$startItem} to {$endItem} of {$totalItems} entries</span>
                 <div class="pkp_pagination_pages" style="display: flex; gap: 4px;">
                    {* Previous Button *}
                    {if $currentPage > 1}
                        <a href="{$baseUrl}?page={$currentPage-1}" class="pkpButton" style="min-width: 32px; padding: 4px 8px; background: #fff; border: 1px solid #d1d5db; color: #374151; text-decoration: none;">&lt;</a>
                    {else}
                        <button class="pkpButton" disabled style="min-width: 32px; padding: 4px 8px; background: #fff; border: 1px solid #e5e7eb; color: #d1d5db;">&lt;</button>
                    {/if}
                    
                    {* Page Numbers *}
                    {section name=page loop=$totalPages+1 start=1}
                        {if $smarty.section.page.index <= $totalPages}
                            {if $smarty.section.page.index == $currentPage}
                                <button class="pkpButton pkpButton--active" style="background-color: #006798; color: #fff; border: 1px solid #006798; min-width: 32px; padding: 4px 8px;">{$smarty.section.page.index}</button>
                            {else}
                                <a href="{$baseUrl}?page={$smarty.section.page.index}" class="pkpButton" style="background-color: #fff; color: #374151; border: 1px solid #d1d5db; min-width: 32px; padding: 4px 8px; text-decoration: none;">{$smarty.section.page.index}</a>
                            {/if}
                        {/if}
                    {/section}
                    
                    {* Next Button *}
                    {if $currentPage < $totalPages}
                        <a href="{$baseUrl}?page={$currentPage+1}" class="pkpButton" style="min-width: 32px; padding: 4px 8px; background: #fff; border: 1px solid #d1d5db; color: #374151; text-decoration: none;">&gt;</a>
                    {else}
                        <button class="pkpButton" disabled style="min-width: 32px; padding: 4px 8px; background: #fff; border: 1px solid #e5e7eb; color: #d1d5db;">&gt;</button>
                    {/if}
                 </div>
            </div>
            {/if}
        </div>
    </div>
{/block}
