<tab id="emspubcorePaymentGateways" label="{translate key="plugins.generic.emspubcore.paymentGateways"}">
    <div class="pkp_form" style="padding: 20px;">
        <h3>{translate key="plugins.generic.emspubcore.settings.stripe"}</h3>
        
        <form class="pkp_form" id="stripeSettingsForm" method="POST" action="{url router=$smarty.const.ROUTE_PAGE page="emspubcore" op="saveGatewaySettings"}">
            {csrf}
            
            <div class="pkp_form_section">
                <!-- Test Mode Toggle -->
                 <div class="form-group" style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" id="stripeTestMode" name="stripeTestMode" {if $stripeTestMode}checked{/if} value="1" />
                    <label for="stripeTestMode" style="margin: 0;">{translate key="plugins.generic.emspubcore.settings.testMode"}</label>
                </div>

                <!-- Publishable Key -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>{translate key="plugins.generic.emspubcore.settings.publishableKey"}</label>
                    <input type="text" name="stripePublishableKey" class="pkp_form_input_text" value="{$stripePublishableKey|escape}" style="width: 100%;" placeholder="pk_test_..." />
                    <p class="pkp_help" style="margin-top: 5px; font-size: 12px; color: #666;">If 'Test Mode' is enabled, use your Sandbox/Test keys.</p>
                </div>

                <!-- Secret Key -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>{translate key="plugins.generic.emspubcore.settings.secretKey"}</label>
                    <input type="text" name="stripeSecretKey" class="pkp_form_input_text" value="{$stripeSecretKey|escape}" style="width: 100%;" placeholder="sk_test_..." />
                </div>
                
                 <!-- Webhook Secret -->
                 <div class="form-group" style="margin-bottom: 20px;">
                    <label>{translate key="plugins.generic.emspubcore.settings.webhookSecret"}</label>
                    <input type="text" name="stripeWebhookSecret" class="pkp_form_input_text" value="{$stripeWebhookSecret|escape}" style="width: 100%;" placeholder="whsec_..." />
                </div>

                <button class="pkp_button pkp_button_primary" type="submit">{translate key="common.save"}</button>
            </div>
        </form>
    </div>
</tab>
