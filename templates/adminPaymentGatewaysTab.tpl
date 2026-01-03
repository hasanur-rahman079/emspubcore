<tab id="emspubcorePaymentGateways" label="{translate key="plugins.generic.emspubcore.paymentGateways"}">
    <div class="pkp_form" style="padding: 20px;">
        <form class="pkp_form" id="paymentSettingsForm" method="POST" action="{url router=$smarty.const.ROUTE_PAGE page="emspubcore" op="saveGatewaySettings"}">
            {csrf}
            
            <!-- Gateway Selection -->
            <div class="pkp_form_section" style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                <div class="form-group">
                    <label for="subscriptionPaymentGateway" style="font-weight: bold; display: block; margin-bottom: 10px;">{translate key="plugins.generic.emspubcore.settings.subscriptionGateway"}</label>
                    <select name="subscriptionPaymentGateway" id="subscriptionPaymentGateway" class="pkp_form_input_text" style="width: 100%; max-width: 400px; padding: 8px;">
                        <option value="stripe" {if $subscriptionPaymentGateway == 'stripe'}selected{/if}>Stripe</option>
                        <option value="paddle" {if $subscriptionPaymentGateway == 'paddle'}selected{/if}>Paddle</option>
                    </select>
                    <p class="pkp_help" style="margin-top: 5px; font-size: 12px; color: #666;">{translate key="plugins.generic.emspubcore.settings.subscriptionGateway.description"}</p>
                </div>
            </div>

            <!-- Stripe Configuration Block -->
            <div id="stripeConfigBlock" class="gateway-config-block" style="{if $subscriptionPaymentGateway != 'stripe'}display: none;{/if}">
                <h3 style="margin-top: 0;">{translate key="plugins.generic.emspubcore.settings.stripe"}</h3>
                
                <div class="pkp_form_section">
                    <div class="form-group" style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="stripeTestMode" name="stripeTestMode" {if $stripeTestMode}checked{/if} value="1" />
                        <label for="stripeTestMode" style="margin: 0;">{translate key="plugins.generic.emspubcore.settings.testMode"}</label>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>{translate key="plugins.generic.emspubcore.settings.publishableKey"}</label>
                        <input type="text" name="stripePublishableKey" class="pkp_form_input_text" value="{$stripePublishableKey|escape}" style="width: 100%;" placeholder="pk_test_..." />
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>{translate key="plugins.generic.emspubcore.settings.secretKey"}</label>
                        <input type="text" name="stripeSecretKey" class="pkp_form_input_text" value="{$stripeSecretKey|escape}" style="width: 100%;" placeholder="sk_test_..." />
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>{translate key="plugins.generic.emspubcore.settings.webhookSecret"}</label>
                        <input type="text" name="stripeWebhookSecret" class="pkp_form_input_text" value="{$stripeWebhookSecret|escape}" style="width: 100%;" placeholder="whsec_..." />
                    </div>
                </div>
            </div>

            <!-- Paddle Configuration Block -->
            <div id="paddleConfigBlock" class="gateway-config-block" style="{if $subscriptionPaymentGateway != 'paddle'}display: none;{/if}">
                <h3 style="margin-top: 0;">{translate key="plugins.paymethod.emspubpaddle.displayName"}</h3>
                
                <div class="pkp_form_section">
                    <div class="form-group" style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="paddleTestMode" name="paddleTestMode" {if $paddleTestMode}checked{/if} value="1" />
                        <label for="paddleTestMode" style="margin: 0;">{translate key="plugins.paymethod.emspubpaddle.settings.testMode"}</label>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>{translate key="plugins.paymethod.emspubpaddle.settings.vendorId"}</label>
                        <input type="text" name="paddleVendorId" class="pkp_form_input_text" value="{$paddleVendorId|escape}" style="width: 100%;" />
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>{translate key="plugins.paymethod.emspubpaddle.settings.apiKey"}</label>
                        <input type="text" name="paddleApiKey" class="pkp_form_input_text" value="{$paddleApiKey|escape}" style="width: 100%;" />
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>{translate key="plugins.paymethod.emspubpaddle.settings.clientToken"}</label>
                        <input type="text" name="paddleClientToken" class="pkp_form_input_text" value="{$paddleClientToken|escape}" style="width: 100%;" />
                    </div>
                </div>
            </div>

            <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                <button class="pkp_button pkp_button_primary" type="submit">{translate key="common.save"}</button>
            </div>
        </form>

        <script type="text/javascript">
            $(function() {
                var $select = $('#subscriptionPaymentGateway');
                var toggleBlocks = function() {
                    var selected = $select.val();
                    $('.gateway-config-block').hide();
                    if (selected === 'stripe') {
                        $('#stripeConfigBlock').show();
                    } else if (selected === 'paddle') {
                        $('#paddleConfigBlock').show();
                    }
                };
                
                // Attach event
                $select.on('change', toggleBlocks);
                
                // Initial run
                toggleBlocks();
            });
        </script>
    </div>
</tab>
