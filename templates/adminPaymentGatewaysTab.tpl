<tab id="emspubcorePaymentGateways" label="{translate key="plugins.generic.emspubcore.paymentGateways"}">
    <link rel="stylesheet" href="{$baseUrl}/plugins/generic/emspubcore/styles/emspubcore.css" type="text/css" />

    <div class="ems-tab-content">

        <div id="stripeModeBanner">
            {literal}
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var cb = document.getElementById('stripeTestMode');
                    function updateBanner() {
                        var b = document.getElementById('ems-mode-banner-inner');
                        if (cb && cb.checked) {
                            b.className = 'ems-test-mode-banner';
                            b.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg> Test Mode active &mdash; using Stripe test keys. No real charges will be made.';
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

            <!-- Stripe Section -->
            <div class="ems-section">
                <div class="ems-section-header">
                    <div class="ems-section-title-group">
                        <h3>{translate key="plugins.generic.emspubcore.settings.stripe"}</h3>
                        <p>Stripe Billing &mdash; accept card payments and issue invoices automatically</p>
                    </div>
                    <div class="ems-section-meta">
                        <svg width="40" height="24" viewBox="0 0 40 24" fill="#635bff" style="border-radius:4px;"><rect width="40" height="24" rx="4"/></svg>
                    </div>
                </div>

                <!-- Test Mode Toggle -->
                <div class="ems-form-check">
                    <input type="checkbox" id="stripeTestMode" name="stripeTestMode"
                           {if $stripeTestMode}checked{/if} value="1" />
                    <label for="stripeTestMode">{translate key="plugins.generic.emspubcore.settings.testMode"}</label>
                </div>

                <!-- API Keys -->
                <div class="ems-form-group">
                    <label for="stripePublishableKey">{translate key="plugins.generic.emspubcore.settings.publishableKey"}</label>
                    <input type="text" id="stripePublishableKey" name="stripePublishableKey"
                           class="pkpFormField__input pkpFormField--text__input ems-mono-input"
                           value="{$stripePublishableKey|escape}"
                           placeholder="pk_test_..." autocomplete="off" />
                    <div class="ems-form-hint">Starts with <code>pk_test_</code> (test) or <code>pk_live_</code> (live)</div>
                </div>

                <div class="ems-form-group">
                    <label for="stripeSecretKey">{translate key="plugins.generic.emspubcore.settings.secretKey"}</label>
                    <input type="password" id="stripeSecretKey" name="stripeSecretKey"
                           class="pkpFormField__input pkpFormField--text__input ems-mono-input"
                           value="{$stripeSecretKey|escape}"
                           placeholder="sk_test_..." autocomplete="new-password" />
                    <div class="ems-form-hint">Keep this secret. Never expose it in frontend code.</div>
                </div>

                <div class="ems-form-group">
                    <label for="stripeWebhookSecret">{translate key="plugins.generic.emspubcore.settings.webhookSecret"}</label>
                    <input type="password" id="stripeWebhookSecret" name="stripeWebhookSecret"
                           class="pkpFormField__input pkpFormField--text__input ems-mono-input"
                           value="{$stripeWebhookSecret|escape}"
                           placeholder="whsec_..." autocomplete="new-password" />
                    <div class="ems-form-hint">
                        Webhook endpoint: <code>{$baseUrl}/index.php/index/emspubcore/webhook</code>
                        &mdash; listen for <code>checkout.session.completed</code> and <code>payment_intent.succeeded</code>.
                    </div>
                </div>
            </div>

            <div class="ems-form-actions">
                <button class="pkp_button pkp_button_primary" type="submit">{translate key="common.save"}</button>
            </div>
        </form>

    </div>
</tab>
