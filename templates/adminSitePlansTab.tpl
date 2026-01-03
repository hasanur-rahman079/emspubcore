<tab id="emspubcoreSitePlans" label="{translate key="plugins.generic.emspubcore.plans"}">
    <script>
        $(function() {
            // Edit plan handler
            $('.edit-plan').click(function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                var name = $(this).data('name');
                var price = $(this).data('price');
                var discounted = $(this).data('discounted');
                var limit = $(this).data('limit');
                var paddlePriceId = $(this).data('paddle');

                $('#planForm input[name="planId"]').val(id);
                $('#planForm input[name="name"]').val(name);
                $('#planForm input[name="price"]').val(price);
                $('#planForm input[name="discounted_price"]').val(discounted);
                $('#planForm input[name="submission_limit"]').val(limit);
                $('#planForm input[name="paddle_price_id"]').val(paddlePriceId);
                $('#planForm button[type="submit"]').text('{translate key="common.save"}');
                $('html, body').animate({ scrollTop: 0 }, 'fast');
            });
        });
    </script>
    <div class="pkp_form" style="padding: 20px;">
        <h3>{translate key="plugins.generic.emspubcore.addNewPlan"}</h3>
        
        <form class="pkp_form" id="planForm" method="POST" action="{url router=$smarty.const.ROUTE_PAGE page="emspubcore" op="savePlan"}">
            {csrf}
            <input type="hidden" name="planId" value="" />
            
            <div class="pkp_form_section">
                <!-- Name -->
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>{translate key="common.name"}</label>
                    <input type="text" name="name" class="pkp_form_input_text" required style="width: 100%;" placeholder="e.g. Basic Plan" />
                </div>

                <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                <!-- Yearly Price -->
                <div class="form-group" style="flex: 1;">
                    <label>{translate key="plugins.generic.emspubcore.price"} (Yearly USD)</label>
                    <input type="number" step="0.01" name="price" class="pkp_form_input_text" required style="width: 100%;" placeholder="e.g. 290.00" />
                </div>

                <!-- Discounted Price -->
                <div class="form-group" style="flex: 1;">
                    <label>{translate key="plugins.generic.emspubcore.discountedPrice"} (Yearly)</label>
                    <input type="number" step="0.01" name="discounted_price" class="pkp_form_input_text" style="width: 100%;" placeholder="e.g. 250.00" />
                </div>

                    <!-- Submission Count -->
                    <div class="form-group" style="flex: 1;">
                        <label>{translate key="plugins.generic.emspubcore.submissionCount"}</label>
                        <input type="number" name="submission_limit" class="pkp_form_input_text" required style="width: 100%;" placeholder="e.g. 10" />
                    </div>
                </div>

                <!-- Paddle Price ID -->
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Paddle Price ID (Required for Paddle v2)</label>
                    <input type="text" name="paddle_price_id" class="pkp_form_input_text" style="width: 100%;" placeholder="e.g. pri_01j7..." />
                    <small style="color: #666;">Get this from your Paddle Dashboard > Product > Prices.</small>
                </div>

                <button class="pkp_button" type="submit">{translate key="common.add"}</button>
            </div>
        </form>

        <hr style="margin: 30px 0;" />
        
        <h3>{translate key="plugins.generic.emspubcore.existingPlans"}</h3>
        <table class="pkpTable">
            <thead>
                <tr>
                    <th>{translate key="common.name"}</th>
                    <th>{translate key="plugins.generic.emspubcore.price"} (Yearly USD)</th>
                    <th>{translate key="plugins.generic.emspubcore.discountedPrice"}</th>
                    <th>{translate key="plugins.generic.emspubcore.submissions"}</th>
                    <th>Paddle Price ID</th>
                    <th>{translate key="common.action"}</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$emspubcorePlans item=plan}
                    <tr>
                        <td>{$plan->getName()|escape}</td>
                        <td>${$plan->getPrice()|string_format:"%.2f"}</td>
                        <td>
                            {if $plan->getDiscountedPrice()}
                                <span style="color: green;">${$plan->getDiscountedPrice()|string_format:"%.2f"}</span>
                            {else}
                                -
                            {/if}
                        </td>
                        <td>
                            {if $plan->getSubmissionLimit() == 0}
                                Unlimited
                            {else}
                                {$plan->getSubmissionLimit()}
                            {/if}
                        </td>
                        <td>
                            <code>{$plan->getPaddlePriceId()|default:"-"}</code>
                        </td>
                        <td>
                            <a href="#" class="edit-plan" 
                               data-id="{$plan->getId()}" 
                               data-name="{$plan->getName()|escape}" 
                               data-price="{$plan->getPrice()}"
                               data-discounted="{$plan->getDiscountedPrice()}"
                               data-limit="{$plan->getSubmissionLimit()}"
                               data-paddle="{$plan->getPaddlePriceId()}">
                                {translate key="common.edit"}
                            </a>
                            |
                            <a href="{url router=$smarty.const.ROUTE_PAGE page="emspubcore" op="deletePlan" planId=$plan->getId()}" onclick="return confirm('{translate key="common.confirmDelete"}')">
                                {translate key="common.delete"}
                            </a>
                        </td>
                    </tr>
                {foreachelse}
                    <tr><td colspan="5" style="text-align: center;">No plans defined.</td></tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</tab>
