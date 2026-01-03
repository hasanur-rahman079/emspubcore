{**
 * plugins/generic/emspubcore/templates/payments/paymentTypesForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Payment type form (Overridden by EmsPubCore to include Paddle APC Product ID).
 *}
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#paymentTypesForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
		
		// Find the actual input element - OJS generates complex IDs
		var $paddleInput = $('input[name="paddleApcProductId"]');
		console.log('Paddle input found:', $paddleInput.length, $paddleInput.attr('id'));
		
		// Load Paddle APC Product ID via AJAX on page load
		var loadUrl = '{url page="emspubcore" op="getPaddleApcProductId"}';
		$.ajax({ldelim}
			url: loadUrl,
			type: 'GET',
			dataType: 'json',
			success: function(response) {ldelim}
				console.log('Load response:', response);
				if (response && response.paddleApcProductId) {ldelim}
					$paddleInput.val(response.paddleApcProductId);
				{rdelim}
			{rdelim},
			error: function(xhr, status, error) {ldelim}
				console.error('Load error:', status, error);
			{rdelim}
		{rdelim});
		
		// Intercept form submission to save Paddle APC Product ID BEFORE the form submits
		$('#paymentTypesForm').on('submit', function(e) {ldelim}
			var productId = $paddleInput.val();
			var saveUrl = '{url page="emspubcore" op="savePaddleApcProductId"}';
			console.log('Saving Paddle APC:', productId);
			$.ajax({ldelim}
				url: saveUrl,
				type: 'POST',
				async: false,
				data: {ldelim}
					paddleApcProductId: productId,
					csrfToken: $('input[name="csrfToken"]').val()
				{rdelim},
				success: function(response) {ldelim}
					console.log('Save response:', response);
				{rdelim}
			{rdelim});
		{rdelim});
	{rdelim});
</script>
<form class="pkp_form" id="paymentTypesForm" method="post" action="{url op="savePaymentTypes"}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="paymentTypesFormNotification"}

	{fbvFormArea id="authorFeesArea" title="manager.payment.authorFees"}
		<p>{translate key="manager.payment.authorFeesDescription"}</p>
		{if $publicationFee==0}{assign var=publicationFee value=""}{/if}
		{fbvFormSection}
			{fbvElement type="text" name="publicationFee" id="publicationFee" label="manager.payment.options.publicationFee" value=$publicationFee size=$fbvStyles.size.SMALL}
		{/fbvFormSection}

		{fbvFormSection}
			{fbvElement type="text" name="paddleApcProductId" id="paddleApcProductId" label="plugins.generic.emspubcore.settings.paddleApcProductId" value=$paddleApcProductId size=$fbvStyles.size.MEDIUM}
			<p class="pkp_help">{translate key="plugins.generic.emspubcore.settings.paddleApcProductId.description"}</p>
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormSection title="manager.payment.readerFees"}
		<p>{translate key="manager.payment.readerFeesDescription"}
		{if $purchaseIssueFee==0}{assign var=purchaseIssueFee value=""}{/if}
		{fbvElement type="text" name="purchaseIssueFee" id="purchaseIssueFee" label="manager.payment.options.purchaseIssueFee" value=$purchaseIssueFee size=$fbvStyles.size.SMALL}
		{if $purchaseArticleFee==0}{assign var=purchaseArticleFee value=""}{/if}
		{fbvElement type="text" name="purchaseArticleFee" id="purchaseArticleFee" label="manager.payment.options.purchaseArticleFee" value=$purchaseArticleFee size=$fbvStyles.size.SMALL}
	{/fbvFormSection}
	{fbvFormSection list=true}
		{fbvElement type="checkbox" name="restrictOnlyPdf" id="restrictOnlyPdf" checked=$restrictOnlyPdf label="manager.payment.options.onlypdf" value="1"}
	{/fbvFormSection}

	{fbvFormSection title="manager.payment.generalFees"}
		<p>{translate key="manager.payment.generalFeesDescription"}
		{if $membershipFee==0}{assign var=membershipFee value=""}{/if}
		{fbvElement type="text" name="membershipFee" id="membershipFee" label="manager.payment.options.membershipFee" value=$membershipFee size=$fbvStyles.size.SMALL}
	{/fbvFormSection}

	{fbvFormButtons hideCancel=true submitText="common.save"}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
