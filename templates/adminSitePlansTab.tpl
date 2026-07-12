<tab id="emspubcoreSitePlans" label="{translate key="plugins.generic.emspubcore.plans"}">
    <link rel="stylesheet" href="{$baseUrl}/plugins/generic/emspubcore/styles/emspubcore.css" type="text/css" />

    <script>
        $(function() {
            $('.ems-edit-plan-btn').click(function(e) {
                e.preventDefault();
                var $btn = $(this);
                $('#planForm input[name="planId"]').val($btn.data('id'));
                $('#planForm input[name="name"]').val($btn.data('name'));
                $('#planForm input[name="price"]').val($btn.data('price'));
                $('#planForm input[name="discounted_price"]').val($btn.data('discounted'));
                $('#planForm input[name="submission_limit"]').val($btn.data('limit'));
                $('#planFormSubmitBtn').text('{translate key="common.save"}');
                $('#planFormTitle').text('{translate key="plugins.generic.emspubcore.editPlan"}');
                $('#planFormCancelBtn').show();
                $('html, body').animate({ scrollTop: 0 }, 'fast');
                $('#planForm input[name="name"]').focus();
            });

            $('#planFormCancelBtn').click(function() {
                $('#planForm')[0].reset();
                $('#planForm input[name="planId"]').val('');
                $('#planFormSubmitBtn').text('{translate key="common.add"}');
                $('#planFormTitle').text('{translate key="plugins.generic.emspubcore.addNewPlan"}');
                $(this).hide();
            });
        });
    </script>

    <div class="ems-tab-content">

        <!-- Add / Edit Plan Form -->
        <div class="ems-section">
            <div class="ems-section-header">
                <div class="ems-section-title-group">
                    <h3 id="planFormTitle">{translate key="plugins.generic.emspubcore.addNewPlan"}</h3>
                    <p>Define yearly billing plans available for journals to subscribe to.</p>
                </div>
            </div>

            <div class="ems-card">
                <form class="pkp_form" id="planForm" method="POST"
                      action="{url router=$smarty.const.ROUTE_PAGE page="emspubcore" op="savePlan"}">
                    {csrf}
                    <input type="hidden" name="planId" value="" />

                    <div class="ems-card-body">
                        <div class="ems-form-row">
                            <div class="ems-form-group" style="flex:2;">
                                <label for="planName">{translate key="common.name"}</label>
                                <input type="text" id="planName" name="name"
                                       class="pkpFormField__input pkpFormField--text__input"
                                       required placeholder="e.g. Basic" />
                                <div class="ems-form-hint">Use a short, clear name. This is shown to journal managers.</div>
                            </div>
                            <div class="ems-form-group">
                                <label for="planLimit">{translate key="plugins.generic.emspubcore.submissionCount"}</label>
                                <input type="number" id="planLimit" name="submission_limit" min="0"
                                       class="pkpFormField__input pkpFormField--text__input"
                                       required placeholder="10" />
                                <div class="ems-form-hint">Enter 0 for unlimited.</div>
                            </div>
                        </div>

                        <div class="ems-form-row">
                            <div class="ems-form-group">
                                <label for="planPrice">{translate key="plugins.generic.emspubcore.price"} (USD/yr)</label>
                                <input type="number" id="planPrice" name="price" step="0.01" min="0"
                                       class="pkpFormField__input pkpFormField--text__input"
                                       required placeholder="290.00" />
                            </div>
                            <div class="ems-form-group">
                                <label for="planDiscounted">{translate key="plugins.generic.emspubcore.discountedPrice"} (USD/yr)</label>
                                <input type="number" id="planDiscounted" name="discounted_price" step="0.01" min="0"
                                       class="pkpFormField__input pkpFormField--text__input"
                                       placeholder="250.00" />
                                <div class="ems-form-hint">Optional. Leave blank for no discount.</div>
                            </div>
                        </div>
                    </div>

                    <div class="ems-card-footer">
                        <button type="button" id="planFormCancelBtn" class="pkp_button" style="display:none;">
                            {translate key="common.cancel"}
                        </button>
                        <button type="submit" id="planFormSubmitBtn" class="pkp_button pkp_button_primary">
                            {translate key="common.add"}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Plans Table -->
        <div class="ems-section">
            <div class="ems-section-header">
                <div class="ems-section-title-group">
                    <h3>{translate key="plugins.generic.emspubcore.existingPlans"}</h3>
                    <p>Click <strong>Edit</strong> to modify a plan. Changes take effect immediately for new subscriptions.</p>
                </div>
                <div class="ems-section-meta">
                    <span class="ems-tx-count">{$emspubcorePlans|@count} {translate key="plugins.generic.emspubcore.plans"}</span>
                </div>
            </div>

            <div class="ems-card">
                <table class="ems-table">
                    <thead>
                        <tr>
                            <th>{translate key="common.name"}</th>
                            <th class="ems-col-num">{translate key="plugins.generic.emspubcore.price"} (USD/yr)</th>
                            <th class="ems-col-num">{translate key="plugins.generic.emspubcore.discountedPrice"}</th>
                            <th class="ems-col-num">{translate key="plugins.generic.emspubcore.submissions"}</th>
                            <th class="ems-col-action">{translate key="common.action"}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$emspubcorePlans item=plan}
                            <tr>
                                <td class="ems-table-label">{$plan->getName()|escape}</td>
                                <td class="ems-col-num"><strong>${$plan->getPrice()|string_format:"%.2f"}</strong></td>
                                <td class="ems-col-num">
                                    {if $plan->getDiscountedPrice()}
                                        <span class="ems-badge ems-badge-success">
                                            ${$plan->getDiscountedPrice()|string_format:"%.2f"}
                                        </span>
                                    {else}
                                        <span class="ems-text-muted">&mdash;</span>
                                    {/if}
                                </td>
                                <td class="ems-col-num">
                                    {if $plan->getSubmissionLimit() == 0}
                                        <span class="ems-badge ems-badge-info">Unlimited</span>
                                    {else}
                                        {$plan->getSubmissionLimit()}
                                    {/if}
                                </td>
                                <td class="ems-col-action">
                                    <a href="#" class="pkp_button ems-edit-plan-btn"
                                       data-id="{$plan->getId()}"
                                       data-name="{$plan->getName()|escape}"
                                       data-price="{$plan->getPrice()}"
                                       data-discounted="{$plan->getDiscountedPrice()}"
                                       data-limit="{$plan->getSubmissionLimit()}">
                                        {translate key="common.edit"}
                                    </a>
                                    <a href="{url router=$smarty.const.ROUTE_PAGE page="emspubcore" op="deletePlan" planId=$plan->getId()}"
                                       class="pkp_button pkp_button_offset"
                                       onclick="return confirm('{translate key="common.confirmDelete"}')">
                                        {translate key="common.delete"}
                                    </a>
                                </td>
                            </tr>
                        {foreachelse}
                            <tr class="ems-table-empty">
                                <td colspan="5">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" style="display:block;margin:0 auto 10px;">
                                        <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
                                    </svg>
                                    No plans defined yet. Add your first plan above.
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</tab>
