{**
 * plugins/generic/emspubcore/templates/settingsForm.tpl
 *
 * EmsPubCore plugin settings form template
 *}
<script>
	$(function() {ldelim}
		$('#emspubcoreSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form
	class="pkp_form"
	id="emspubcoreSettings"
	method="POST"
	action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}"
>
	<h3>{translate key="plugins.generic.emspubcore.displayName"} Settings</h3>
	{csrf}

	{fbvFormArea id="emspubcoreStripeSettings" title="plugins.generic.emspubcore.settings.stripe"}
		<p class="pkp_help">{translate key="plugins.generic.emspubcore.settings.stripe.description"}</p>

		{fbvFormSection}
			{fbvElement
				type="checkbox"
				id="stripeTestMode"
				label="plugins.generic.emspubcore.settings.testMode"
				checked=$stripeTestMode
			}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.emspubcore.settings.publishableKey"}
			{fbvElement
				type="text"
				id="stripePublishableKey"
				value=$stripePublishableKey
				size=$fbvStyles.size.LARGE
			}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.emspubcore.settings.secretKey"}
			{fbvElement
				type="text"
				id="stripeSecretKey"
				value=$stripeSecretKey
				size=$fbvStyles.size.LARGE
				password=true
			}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.emspubcore.settings.webhookSecret"}
			{fbvElement
				type="text"
				id="stripeWebhookSecret"
				value=$stripeWebhookSecret
				size=$fbvStyles.size.LARGE
				password=true
			}
			<p class="pkp_help">{translate key="plugins.generic.emspubcore.settings.webhookSecret.description"}</p>
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="emspubcorePaddleSettings" title="plugins.generic.emspubcore.settings.paddle"}
		<p class="pkp_help">{translate key="plugins.generic.emspubcore.settings.paddle.description"}</p>

		{fbvFormSection}
			{fbvElement
				type="checkbox"
				id="paddleTestMode"
				label="plugins.generic.emspubcore.settings.testMode"
				checked=$paddleTestMode
			}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.emspubcore.settings.paddleVendorId"}
			{fbvElement
				type="text"
				id="paddleVendorId"
				value=$paddleVendorId
				size=$fbvStyles.size.LARGE
			}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.emspubcore.settings.paddleApiKey"}
			{fbvElement
				type="text"
				id="paddleApiKey"
				value=$paddleApiKey
				size=$fbvStyles.size.LARGE
				password=true
			}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.emspubcore.settings.paddleClientToken"}
			{fbvElement
				type="text"
				id="paddleClientToken"
				value=$paddleClientToken
				size=$fbvStyles.size.LARGE
			}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.emspubcore.settings.paddleWebhookSecret"}
			{fbvElement
				type="text"
				id="paddleWebhookSecret"
				value=$paddleWebhookSecret
				size=$fbvStyles.size.LARGE
				password=true
			}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.emspubcore.settings.paddleProductIds"}
			{fbvElement
				type="text"
				id="paddleProductIdBasic"
				value=$paddleProductIdBasic
				label="Basic Plan Product ID"
				size=$fbvStyles.size.MEDIUM
			}
			{fbvElement
				type="text"
				id="paddleProductIdStandard"
				value=$paddleProductIdStandard
				label="Standard Plan Product ID"
				size=$fbvStyles.size.MEDIUM
			}
			{fbvElement
				type="text"
				id="paddleProductIdPremium"
				value=$paddleProductIdPremium
				label="Premium Plan Product ID"
				size=$fbvStyles.size.MEDIUM
			}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="emspubcorePaymentGateway" title="plugins.generic.emspubcore.settings.subscriptionGateway"}
		{fbvFormSection}
			{fbvElement
				type="select"
				id="subscriptionPaymentGateway"
				from=$gatewayOptions
				selected=$subscriptionPaymentGateway
				translate=true
				label="plugins.generic.emspubcore.settings.subscriptionGateway.description"
			}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea id="emspubcorePlanLimits" title="plugins.generic.emspubcore.settings.planLimits"}
		<table class="pkpTable">
			<thead>
				<tr>
					<th>{translate key="plugins.generic.emspubcore.plan"}</th>
					<th>{translate key="plugins.generic.emspubcore.submissionsPerMonth"}</th>
					<th>{translate key="plugins.generic.emspubcore.yearlyPrice"}</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><strong>{translate key="plugins.generic.emspubcore.plan.free"}</strong></td>
					<td>{$planLimits.free}</td>
					<td>-</td>
				</tr>
				<tr>
					<td><strong>{translate key="plugins.generic.emspubcore.plan.basic"}</strong></td>
					<td>{$planLimits.basic}</td>
					<td>${$planPrices.basic.yearly / 100}/yr</td>
				</tr>
				<tr>
					<td><strong>{translate key="plugins.generic.emspubcore.plan.premium"}</strong></td>
					<td>{$planLimits.premium}</td>
					<td>${$planPrices.premium.yearly / 100}/yr</td>
				</tr>
			</tbody>
		</table>
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
