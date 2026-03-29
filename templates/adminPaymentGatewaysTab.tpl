<tab id="emspubcorePaymentGateways" label="{translate key="plugins.generic.emspubcore.paymentGateways"}">
    <link rel="stylesheet" href="{$baseUrl}/plugins/generic/emspubcore/styles/emspubcore.css" type="text/css" />

    <div class="ems-tab-content">

        <div id="stripeModeBanner">
            {literal}
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var cb = document.getElementById('stripeTestMode');
                    var banner = document.getElementById('stripeModeBanner');
                    function updateBanner() {
                        var b = document.getElementById('ems-mode-banner-inner');
                        if (cb && cb.checked) {
                            b.className = 'ems-test-mode-banner';
                            b.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg> Test Mode active &mdash; use Stripe test keys. No real charges will be made.';
                        } else {
                            b.className = 'ems-live-banner';
                            b.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Live Mode &mdash; real payments will be processed. Ensure your live keys are entered below.';
                        }
                    }
                    if (cb) { cb.addEventListener('change', updateBanner); updateBanner(); }
                });
            </script>
            {/literal}
            <div id="ems-mode-banner-inner" class="ems-test-mode-banner"></div>
        </div>

        <form class="pkp_form" id="paymentSettingsForm" method="POST"
              action="{url router=$smarty.const.ROUTE_PAGE page="emspubcore" op="saveGatewaySettings"}">
            {csrf}

            <!-- Stripe Section Header -->
            <div class="ems-gateway-header">
                <div class="ems-stripe-logo">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                        <rect width="24" height="24" rx="4" fill="#635bff"/>
                        <path d="M11.5 9.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5v.5h-3v-.5zM10 9.5C10 7.57 11.57 6 13.5 6S17 7.57 17 9.5V10h1v8H6v-8h1v-.5z" fill="white" opacity="0.9"/>
                    </svg>
                </div>
                <div class="ems-gateway-title">
                    <h3>{translate key="plugins.generic.emspubcore.settings.stripe"}</h3>
                    <p>Stripe Billing &mdash; accept card payments and issue invoices automatically</p>
                </div>
            </div>

            <!-- Test Mode Toggle -->
            <div class="ems-form-check">
                <input type="checkbox" id="stripeTestMode" name="stripeTestMode"
                       {if $stripeTestMode}checked{/if} value="1" />
                <label for="stripeTestMode">{translate key="plugins.generic.emspubcore.settings.testMode"}</label>
            </div>

            <!-- Keys -->
            <div class="ems-form-group">
                <label for="stripePublishableKey">{translate key="plugins.generic.emspubcore.settings.publishableKey"}</label>
                <div class="ems-key-input-wrap">
                    <input type="text" id="stripePublishableKey" name="stripePublishableKey"
                           class="pkp_form_input_text ems-form-group input"
                           style="width:100%; font-family:monospace; font-size:12px;"
                           value="{$stripePublishableKey|escape}"
                           placeholder="pk_test_..." autocomplete="off" />
                </div>
                <div class="ems-form-hint">Starts with <code>pk_test_</code> (test) or <code>pk_live_</code> (live)</div>
            </div>

            <div class="ems-form-group">
                <label for="stripeSecretKey">{translate key="plugins.generic.emspubcore.settings.secretKey"}</label>
                <div class="ems-key-input-wrap">
                    <input type="password" id="stripeSecretKey" name="stripeSecretKey"
                           class="pkp_form_input_text"
                           style="width:100%; font-family:monospace; font-size:12px;"
                           value="{$stripeSecretKey|escape}"
                           placeholder="sk_test_..." autocomplete="new-password" />
                </div>
                <div class="ems-form-hint">Keep this secret. Never expose it in frontend code.</div>
            </div>

            <div class="ems-form-group">
                <label for="stripeWebhookSecret">{translate key="plugins.generic.emspubcore.settings.webhookSecret"}</label>
                <div class="ems-key-input-wrap">
                    <input type="password" id="stripeWebhookSecret" name="stripeWebhookSecret"
                           class="pkp_form_input_text"
                           style="width:100%; font-family:monospace; font-size:12px;"
                           value="{$stripeWebhookSecret|escape}"
                           placeholder="whsec_..." autocomplete="new-password" />
                </div>
                <div class="ems-form-hint">
                    Webhook endpoint: <code>{$baseUrl}/index.php/index/emspubcore/webhook</code>
                    &mdash; listen for <code>checkout.session.completed</code> and <code>payment_intent.succeeded</code>.
                </div>
            </div>

            <div class="ems-form-actions">
                <button class="pkp_button pkp_button_primary" type="submit">{translate key="common.save"}</button>
            </div>
        </form>

    </div>
</tab>
