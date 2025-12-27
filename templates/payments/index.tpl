{**
 * templates/payments/index.tpl
 *
 * Override for OJS payments/index.tpl
 * Adds "Pending Payments" tab to the subscriptions page
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="manager.subscriptions"}
	</h1>

	<script type="text/javascript">
		// Attach the JS file tab handler.
		$(function() {ldelim}
			$('#subscriptionsTabs').pkpHandler('$.pkp.controllers.TabHandler');
		{rdelim});
	</script>
	<div id="subscriptionsTabs" class="pkp_controllers_tab">
		<ul>
			<li><a name="individualSubscription" href="{url op="subscriptions" path="individual"}">{translate key="subscriptionManager.individualSubscriptions"}</a></li>
			<li><a name="institutionalSubscriptions" href="{url op="subscriptions" path="institutional"}">{translate key="subscriptionManager.institutionalSubscriptions"}</a></li>
			<li><a name="subscriptionTypes" href="{url op="subscriptionTypes"}">{translate key="subscriptionManager.subscriptionTypes"}</a></li>
			<li><a name="subscriptionPolicies" href="{url op="subscriptionPolicies"}">{translate key="manager.subscriptionPolicies"}</a></li>
			<li><a name="paymentTypes" href="{url op="paymentTypes"}">{translate key="manager.paymentTypes"}</a></li>
			<li><a name="payments" href="{url op="payments"}">{translate key="manager.paymentMethod"}</a></li>
			<li><a name="pendingPaymentsAdmin" href="{url page="emspubcore" op="pendingPaymentsAdmin"}">Pending Payments</a></li>
		</ul>
	</div>
{/block}
