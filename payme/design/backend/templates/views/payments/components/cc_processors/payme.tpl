{if empty($processor_params.checkout_url) }		     {$processor_params.checkout_url="https://checkout.paycom.uz"}{/if}
{if empty($processor_params.checkout_url_for_test) } {$processor_params.checkout_url_for_test="https://test.paycom.uz"}{/if}
{if empty($processor_params.return_url) }			 {$processor_params.return_url="payment_notification.return?payment=payme"|fn_url:"C":"http"}{/if}

{assign var="endpoint_url" value="payment_notification.notification?payment=payme"|fn_url:"C":"http"}

<p> <b>{__("payme_endpoint_url")} : </b> {$endpoint_url} <br> <i>{__("payme_endpoint_url_comment")}</i></p>
<hr>

<div class="control-group">
	<label class="control-label" for="payme_merchant_id">{__("payme_merchant_id")}:</label>
	<div class="controls">
		<input type="text" name="payment_data[processor_params][merchant_id]" id="payme_merchant_id" value="{$processor_params.merchant_id}">
	</div>
</div>

<div class="control-group">
	<label class="control-label" for="payme_secret_key">{__("payme_secret_key")}:</label>
	<div class="controls">
		<input type="text" name="payment_data[processor_params][secret_key]" id="payme_secret_key" value="{$processor_params.secret_key}">
	</div>
</div>

<div class="control-group">
	<label class="control-label" for="payme_secret_key_for_test">{__("payme_secret_key_for_test")}:</label>
	<div class="controls">
		<input type="text" name="payment_data[processor_params][secret_key_for_test]" id="payme_secret_key_for_test" value="{$processor_params.secret_key_for_test}">
	</div>
</div>

<div class="control-group">
	<label class="control-label" for="payme_test_mode">{__("payme_test_mode")}:</label>
	<div class="controls">
		<select name="payment_data[processor_params][test_mode]" id="payme_test_mode" value="{$processor_params.test_mode}">
			<option value="no"{if $processor_params.test_mode == "no"}   selected="selected"{/if}>{__("payme_no")}</option>
			<option value="yes"{if $processor_params.test_mode == "yes"} selected="selected"{/if}>{__("payme_yes")}</option>
		</select>
	</div>
</div>

<hr>

<div class="control-group">
	<label class="control-label" for="payme_checkout_url">{__("payme_checkout_url")}:</label>
	<div class="controls"> 
		<input type="text" name="payment_data[processor_params][checkout_url]" id="payme_checkout_url" value="{$processor_params.checkout_url}" >
	</div>
</div>

<div class="control-group">
	<label class="control-label" for="payme_checkout_url_for_test">{__("payme_checkout_url_for_test")}:</label>
	<div class="controls"> 
		<input type="text" name="payment_data[processor_params][checkout_url_for_test]" id="payme_checkout_url_for_test" value="{$processor_params.checkout_url_for_test}" >
	</div>
</div>

<div class="control-group">
	<label class="control-label" for="payme_return_url">{__("payme_return_url")}:</label>
	<div class="controls"> 
		<input type="text" name="payment_data[processor_params][return_url]" id="payme_return_url" value="{$processor_params.return_url}" >
	</div>
</div>

<hr>

<div class="control-group">
	<label class="control-label" for="payme_add_product_information">{__("payme_add_product_information")}:</label>
	<div class="controls">
		<select name="payment_data[processor_params][add_product_information]" id="payme_add_product_information" value="{$processor_params.add_product_information}">
			<option value="no"{if $processor_params.add_product_information == "no"} selected="selected"{/if}>{__("payme_no")}</option>
			<option value="yes"{if $processor_params.add_product_information == "yes"} selected="selected"{/if}>{__("payme_yes")}</option>
		</select>
	</div>
</div>

<div class="control-group">
	<label class="control-label" for="payme_return_after">{__("payme_return_after")}:</label>
	<div class="controls">
		<select name="payment_data[processor_params][return_after]" id="payme_return_after" value="{$processor_params.return_after}">
			<option value="0"	{if $processor_params.return_after == "0"}	 selected="selected"{/if}>{__("payme_instantly")}</option>
			<option value="15000"{if $processor_params.return_after == "15000"} selected="selected"{/if}>{__("payme_s15")}</option>
			<option value="30000"{if $processor_params.return_after == "30000"} selected="selected"{/if}>{__("payme_s30")}</option>
			<option value="60000"{if $processor_params.return_after == "60000"} selected="selected"{/if}>{__("payme_s60")}</option>
		</select>
	</div>
</div>

<div class="control-group">
	<label class="control-label" for="payme_language">{__("payme_language")}:</label>
	<div class="controls">
		<select name="payment_data[processor_params][language]" id="payme_language" value="{$processor_params.language}">
			<option value="payme_ru"{if $processor_params.language == "payme_ru"} selected="selected"{/if}>{__("payme_ru")}</option>
			<option value="payme_en"{if $processor_params.language == "payme_en"} selected="selected"{/if}>{__("payme_en")}</option>
		</select>
	</div>
</div> 