{**
 * plugins/generic/emspubcore/templates/journalPlanForm.tpl
 *
 * Form for assigning a subscription plan to a journal
 *}
<script>
	$(function() {ldelim}
		$('#journalPlanForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form
	class="pkp_form"
	id="journalPlanForm"
	method="POST"
	action="{url op="assignPlan"}"
>
	{csrf}
	<input type="hidden" name="journalId" value="{$journalId|escape}" />

	{fbvFormArea id="currentPlanInfo"}
		<div class="pkp_notification">
			{if $currentPlan}
				<p>
					<strong>{translate key="plugins.generic.emspubcore.currentPlan"}:</strong>
					{$currentPlan->getPlanType()|ucfirst}
					({$currentPlan->getBillingCycle()})
				</p>
				<p>
					<strong>{translate key="plugins.generic.emspubcore.submissionsUsed"}:</strong>
					{$currentUsage} / {$currentPlan->getSubmissionsLimit()}
				</p>
				{if $currentPlan->getPlanEndDate()}
					<p>
						<strong>{translate key="plugins.generic.emspubcore.validUntil"}:</strong>
						{$currentPlan->getPlanEndDate()|date_format:"%Y-%m-%d"}
					</p>
				{/if}
			{else}
				<p>{translate key="plugins.generic.emspubcore.noPlanAssigned"}</p>
			{/if}
		</div>
	{/fbvFormArea}

	{fbvFormArea id="planSelection" title="plugins.generic.emspubcore.selectPlan"}
		{fbvFormSection}
			{fbvElement
				type="select"
				id="planType"
				from=$planOptions
				selected=$selectedPlan
				label="plugins.generic.emspubcore.plan"
				required=true
			}
		{/fbvFormSection}

		{fbvFormSection}
			{fbvElement
				type="select"
				id="billingCycle"
				from=$billingOptions
				selected=$selectedBilling
				label="plugins.generic.emspubcore.billingCycle"
				required=true
			}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
