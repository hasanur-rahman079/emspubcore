{**
 * plugins/generic/emspubcore/templates/paymentsGridFilter.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Filter form for the payments grid.
 *}
<script type="text/javascript">
	// Attach the form handler.
	$(function() {ldelim}
		$('#paymentsGridFilterForm').pkpHandler('$.pkp.controllers.form.ClientFormHandler',
			{ldelim}
				trackFormChanges: false
			{rdelim}
		);
	{rdelim});
</script>
<form class="pkp_form" id="paymentsGridFilterForm" action="{url op="fetchGrid"}" method="post">
	{csrf}
	<style>
		.payment-filter-container {
			display: flex;
			align-items: flex-end; /* Align to bottom so label doesn't push input down relative to button */
			gap: 0; /* No gap for connected look, or small gap */
			max-width: 100%;
		}
		
		.payment-filter-container .pkp_form_item_text {
			flex-grow: 1;
			margin: 0 !important;
		}

		.payment-filter-container .pkp_form_item_button {
			margin: 0 !important;
			margin-left: -1px !important; /* Overlap borders slightly if connected */
		}

		#paymentsGridFilterForm input[type="text"] {
			width: 50vw;
			min-width: 300px;
			padding: 8px 12px;
			border: 1px solid #ccc;
			border-radius: 4px 0 0 4px; /* Left side rounded only */
			font-size: 14px;
			height: 40px;
			box-shadow: inset 0 1px 2px rgba(0,0,0,0.075);
		}
		
		#paymentsGridFilterForm button {
			height: 40px;
			padding: 0 20px;
			border-radius: 0 4px 4px 0; /* Right side rounded only */
			margin-left: 0;
		}
		
		/* Clear OJS floats */
		.payment-filter-container:before,
		.payment-filter-container:after {
			display: none;
		}
	</style>
	<div class="pkp_helpers_clear payment-filter-container">
		<div class="pkp_form_item pkp_form_item_text">
			{fbvElement type="text" name="search" id="search" placeholder="plugins.generic.emspubcore.search.placeholder" value=$filterSelectionData.search size=$FBV_STYLES.size.LARGE inline="true" label="common.search"}
		</div>
		<div class="pkp_form_item pkp_form_item_button">
			{fbvFormButtons hideCancel=true submitText="common.search"}
		</div>
	</div>
</form>
