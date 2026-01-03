{**
 * plugins/generic/emspubcore/templates/plans.tpl
 *
 * Plan selection and upgrade page (Yearly billing only)
 *}
{include file="frontend/components/header.tpl"}

<div class="page emspubcore-plans-page">
	<h1>{translate key="plugins.generic.emspubcore.selectPlan"}</h1>

	{if $currentPlan}
		<div class="pkp_notification pkp_notification_primary">
			<p>
				<strong>{translate key="plugins.generic.emspubcore.currentPlan"}:</strong>
				{$currentPlan->getPlanType()|ucfirst}
				-
				{$currentUsage} / {$currentPlan->getSubmissionsLimit()} {translate key="plugins.generic.emspubcore.submissionsPerYear"}
			</p>
		</div>
	{/if}

	<div class="emspubcore-plan-cards">
		<div class="emspubcore-plan-card">
			<h3>{translate key="plugins.generic.emspubcore.plan.free"}</h3>
			<div class="price">$0<span>/yr</span></div>
			<div class="limit">{$planLimits.free} {translate key="plugins.generic.emspubcore.submissionsPerYear"}</div>
			{if !$currentPlan || $currentPlan->getPlanType() == 'free'}
				<button class="pkp_button" disabled>{translate key="common.current"}</button>
			{/if}
		</div>

		<div class="emspubcore-plan-card">
			<h3>{translate key="plugins.generic.emspubcore.plan.basic"}</h3>
			<div class="price">${math equation="x/100" x=$planPrices.basic.yearly}<span>/yr</span></div>
			<div class="limit">{$planLimits.basic} {translate key="plugins.generic.emspubcore.submissionsPerYear"}</div>
			{if $currentPlan && $currentPlan->getPlanType() == 'basic'}
				<button class="pkp_button" disabled>{translate key="common.current"}</button>
			{else}
				<a href="{url page="emspubcore" op="checkout" plan="basic" billing="yearly"}" class="pkp_button upgrade-btn">
					{translate key="plugins.generic.emspubcore.upgrade"}
				</a>
			{/if}
		</div>

		<div class="emspubcore-plan-card">
			<h3>{translate key="plugins.generic.emspubcore.plan.premium"}</h3>
			<div class="price">${math equation="x/100" x=$planPrices.premium.yearly}<span>/yr</span></div>
			<div class="limit">{$planLimits.premium} {translate key="plugins.generic.emspubcore.submissionsPerYear"}</div>
			{if $currentPlan && $currentPlan->getPlanType() == 'premium'}
				<button class="pkp_button" disabled>{translate key="common.current"}</button>
			{else}
				<a href="{url page="emspubcore" op="checkout" plan="premium" billing="yearly"}" class="pkp_button upgrade-btn">
					{translate key="plugins.generic.emspubcore.upgrade"}
				</a>
			{/if}
		</div>
	</div>
</div>

{include file="frontend/components/footer.tpl"}
