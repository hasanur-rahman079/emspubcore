{extends file="layouts/backend.tpl"}

{block name="page"}
    <link rel="stylesheet" href="{$baseUrl}/plugins/generic/emspubcore/styles/emspubcore.css" type="text/css" />

    {literal}
    <style>
        /* Page-level overrides to blend with OJS backend layout */
        .pkp_structure_main,
        .pkp_structure_content,
        .app__main,
        .app__page,
        .app__contentPanel {
            background-color: #fff !important;
        }

        .app__contentPanel {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        /* Article title cell */
        .ems-article-title {
            font-weight: 600;
            color: #006798;
            display: block;
            font-size: 13px;
            line-height: 1.4;
            margin-bottom: 4px;
        }

        /* Amount display */
        .ems-amount-main {
            font-weight: 600;
            font-size: 14px;
            color: #1e293b;
        }

        .ems-amount-original {
            font-size: 11px;
            text-decoration: line-through;
            color: #94a3b8;
        }

        .ems-amount-discounted {
            font-weight: 600;
            color: #059669;
            font-size: 14px;
        }
    </style>
    {/literal}

    <h1 class="app__pageHeading">{$pageTitle}</h1>

    <div class="app__contentPanel">
        <div class="ems-card" style="border-radius:0; border-left:none; border-right:none; border-top:none;">
            <table class="ems-table">
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th>Article</th>
                        <th style="width:130px;">Fee</th>
                        <th style="width:110px;">Status</th>
                        <th style="width:140px;">Payment Date</th>
                        <th style="width:150px; text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$pendingPayments item=payment}
                        <tr>
                            <td style="color:#94a3b8; font-size:12px;">{$payment.id}</td>

                            <td>
                                <span class="ems-article-title">{$payment.title|escape}</span>
                                {if $payment.status == 'Pending'}
                                    <span class="ems-badge ems-badge-warning">Awaiting Payment</span>
                                {elseif $payment.status == 'Waived'}
                                    <span class="ems-badge ems-badge-info">Fee Waived</span>
                                {else}
                                    <span class="ems-badge ems-badge-success">Submitted</span>
                                {/if}
                            </td>

                            <td>
                                {if $payment.isDiscounted}
                                    <span class="ems-amount-discounted">{$payment.amount|string_format:"%.2f"} {$payment.currency}</span>
                                    <div style="margin-top:3px; display:flex; align-items:center; gap:6px;">
                                        <span class="ems-amount-original">{$payment.originalFee|string_format:"%.2f"} {$payment.currency}</span>
                                        <span class="ems-badge ems-badge-success" style="font-size:10px; padding:2px 6px;">Discounted</span>
                                    </div>
                                {else}
                                    <span class="ems-amount-main">{$payment.amount|string_format:"%.2f"} {$payment.currency}</span>
                                {/if}
                            </td>

                            <td>
                                {if $payment.status == 'Paid'}
                                    <span class="ems-badge ems-badge-success">Paid</span>
                                {elseif $payment.status == 'Waived'}
                                    <span class="ems-badge ems-badge-info">Waived</span>
                                {else}
                                    <span class="ems-badge ems-badge-warning">Pending</span>
                                {/if}
                            </td>

                            <td style="font-size:13px; color:#64748b;">
                                {if $payment.date != '-' && $payment.date}
                                    {$payment.date|date_format:$dateFormatShort}
                                {else}
                                    <span style="color:#d1d5db;">&mdash;</span>
                                {/if}
                            </td>

                            <td style="text-align:right;">
                                {if $payment.status == 'Pending'}
                                    <a href="{$payment.payUrl}" class="ems-btn ems-btn-primary ems-btn-sm">Pay Now</a>
                                {elseif $payment.status == 'Paid'}
                                    <a href="{$payment.invoiceUrl}" target="_blank" class="ems-btn ems-btn-secondary ems-btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                        Invoice
                                    </a>
                                {else}
                                    <span style="color:#94a3b8; font-size:12px; font-style:italic;">Fee Waived</span>
                                {/if}
                            </td>
                        </tr>
                    {foreachelse}
                        <tr class="ems-table-empty">
                            <td colspan="6">
                                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" style="display:block;margin:0 auto 10px;">
                                    <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
                                </svg>
                                No records found.
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>

            {if $totalItems > 0}
            <div class="ems-pagination">
                <span>Showing {$startItem}&ndash;{$endItem} of {$totalItems}</span>
                <div class="ems-page-controls">
                    {if $currentPage > 1}
                        <a href="{$baseUrl}?page={$currentPage-1}" class="ems-page-btn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        </a>
                    {else}
                        <button class="ems-page-btn" disabled>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        </button>
                    {/if}

                    {section name=page loop=$totalPages+1 start=1}
                        {if $smarty.section.page.index <= $totalPages}
                            {if $smarty.section.page.index == $currentPage}
                                <button class="ems-page-btn active">{$smarty.section.page.index}</button>
                            {else}
                                <a href="{$baseUrl}?page={$smarty.section.page.index}" class="ems-page-btn">{$smarty.section.page.index}</a>
                            {/if}
                        {/if}
                    {/section}

                    {if $currentPage < $totalPages}
                        <a href="{$baseUrl}?page={$currentPage+1}" class="ems-page-btn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                    {else}
                        <button class="ems-page-btn" disabled>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </button>
                    {/if}
                </div>
            </div>
            {/if}
        </div>
    </div>
{/block}
