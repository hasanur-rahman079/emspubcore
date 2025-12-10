{**
 * plugins/generic/emspubcore/templates/plans.tpl
 *
 * Plan selection and upgrade page
 *}
{include file="frontend/components/header.tpl"}

<div class="page emspubcore-plans-page">
	<h1>{translate key="plugins.generic.emspubcore.selectPlan"}</h1>

	{if $currentPlan}
		<div class="pkp_notification pkp_notification_primary">
			<p>
				<strong>{translate key="plugins.generic.emspubcore.currentPlan"}:</strong>
				{$currentPlan->getPlanType()|ucfirst}
				({$currentPlan->getBillingCycle()})
				-
				{$currentUsage} / {$currentPlan->getSubmissionsLimit()} {translate key="plugins.generic.emspubcore.submissionsPerMonth"}
			</p>
		</div>
	{/if}

	<div class="emspubcore-billing-toggle">
		<label id="monthlyLabel" class="active">{translate key="plugins.generic.emspubcore.billingCycle.monthly"}</label>
		<div class="toggle-switch" id="billingToggle"></div>
		<label id="yearlyLabel">
			{translate key="plugins.generic.emspubcore.billingCycle.yearly"}
			<span class="emspubcore-save-badge">Save 17%</span>
		</label>
	</div>

	<div class="emspubcore-plan-cards">
		<div class="emspubcore-plan-card">
			<h3>{translate key="plugins.generic.emspubcore.plan.free"}</h3>
			<div class="price">$0</div>
			<div class="limit">{$planLimits.free} {translate key="plugins.generic.emspubcore.submissionsPerMonth"}</div>
			{if !$currentPlan || $currentPlan->getPlanType() == 'free'}
				<button class="pkp_button" disabled>{translate key="common.current"}</button>
			{/if}
		</div>

		<div class="emspubcore-plan-card">
			<h3>{translate key="plugins.generic.emspubcore.plan.basic"}</h3>
			<div class="price monthly-price">${$planPrices.basic.monthly / 100}<span>/mo</span></div>
			<div class="price yearly-price" style="display:none;">${math equation="x/100" x=$planPrices.basic.yearly}<span>/yr</span></div>
			<div class="limit">{$planLimits.basic} {translate key="plugins.generic.emspubcore.submissionsPerMonth"}</div>
			{if $currentPlan && $currentPlan->getPlanType() == 'basic'}
				<button class="pkp_button" disabled>{translate key="common.current"}</button>
			{else}
				<a href="{url page="emspubcore" op="checkout" plan="basic" billing="monthly"}" class="pkp_button monthly-btn">
					{translate key="plugins.generic.emspubcore.upgrade"}
				</a>
				<a href="{url page="emspubcore" op="checkout" plan="basic" billing="yearly"}" class="pkp_button yearly-btn" style="display:none;">
					{translate key="plugins.generic.emspubcore.upgrade"}
				</a>
			{/if}
		</div>

		<div class="emspubcore-plan-card">
			<h3>{translate key="plugins.generic.emspubcore.plan.premium"}</h3>
			<div class="price monthly-price">${$planPrices.premium.monthly / 100}<span>/mo</span></div>
			<div class="price yearly-price" style="display:none;">${math equation="x/100" x=$planPrices.premium.yearly}<span>/yr</span></div>
			<div class="limit">{$planLimits.premium} {translate key="plugins.generic.emspubcore.submissionsPerMonth"}</div>
			{if $currentPlan && $currentPlan->getPlanType() == 'premium'}
				<button class="pkp_button" disabled>{translate key="common.current"}</button>
			{else}
				<a href="{url page="emspubcore" op="checkout" plan="premium" billing="monthly"}" class="pkp_button monthly-btn">
					{translate key="plugins.generic.emspubcore.upgrade"}
				</a>
				<a href="{url page="emspubcore" op="checkout" plan="premium" billing="yearly"}" class="pkp_button yearly-btn" style="display:none;">
					{translate key="plugins.generic.emspubcore.upgrade"}
				</a>
			{/if}
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var toggle = document.getElementById('billingToggle');
	var monthlyLabel = document.getElementById('monthlyLabel');
	var yearlyLabel = document.getElementById('yearlyLabel');
	var monthlyPrices = document.querySelectorAll('.monthly-price');
	var yearlyPrices = document.querySelectorAll('.yearly-price');
	var monthlyBtns = document.querySelectorAll('.monthly-btn');
	var yearlyBtns = document.querySelectorAll('.yearly-btn');
	var isYearly = false;

	toggle.addEventListener('click', function() {
		isYearly = !isYearly;
		toggle.classList.toggle('yearly', isYearly);
		monthlyLabel.classList.toggle('active', !isYearly);
		yearlyLabel.classList.toggle('active', isYearly);

		monthlyPrices.forEach(function(el) { el.style.display = isYearly ? 'none' : 'block'; });
		yearlyPrices.forEach(function(el) { el.style.display = isYearly ? 'block' : 'none'; });
		monthlyBtns.forEach(function(el) { el.style.display = isYearly ? 'none' : 'inline-block'; });
		yearlyBtns.forEach(function(el) { el.style.display = isYearly ? 'inline-block' : 'none'; });
	});
});
</script>

{include file="frontend/components/footer.tpl"}
