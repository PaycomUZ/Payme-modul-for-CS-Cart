<?php

use Tygh\Registry;

if (!defined('BOOTSTRAP')) die('Access denied');

if (defined('PAYMENT_NOTIFICATION')) {

	if ($mode == 'notification') {

		require_once dirname(__FILE__) . '/payme/PaymeApi.php';
		header('Content-type: application/json charset=utf-8');

		$api = new PaymeApi();
		$api->setInputArray(file_get_contents("php://input"));

		echo json_encode($api->parseRequest());

	} else if ($mode == 'return') {

		$orderID=0;

		if ($_REQUEST['order_id']) $orderID=$_REQUEST['order_id'];

		fn_order_placement_routines('route', $orderID, false);
	}

	exit;

} else {

	$order_no = $order_id;
	if ($order_info['repaid']) {
		$order_no .= '_' . $order_info['repaid'];
	}

	$currency_code = $order_info['secondary_currency'];

	if ($currency_code != CART_SECONDARY_CURRENCY) {

		$order_info['total'] = fn_format_price_by_currency($order_info['total'], CART_SECONDARY_CURRENCY, $currency_code);
	}

	if ($processor_data['processor_params']['test_mode']=='yes'){

		$submit_url = $processor_data['processor_params']['checkout_url_for_test'];

	} else if ($processor_data['processor_params']['test_mode']=='no'){
	
		$submit_url = $processor_data['processor_params']['checkout_url'];
	}

	$t_currency="";
		 if( $currency_code == 'UZS') $t_currency = 860;
	else if( $currency_code == 'USD') $t_currency = 840;
	else if( $currency_code == 'RUB') $t_currency = 643;
	else if( $currency_code == 'EUR') $t_currency = 978;
	else							  $t_currency = 860;

	$post_data = array(
		'merchant'			=> $processor_data['processor_params']['merchant_id'],
		'account[order_id]' => $order_no, 
		'amount'			=> $order_info['total']*100,
		'currency'			=> $t_currency,
		'callback'			=> $processor_data['processor_params']['return_url'].'&order_id='. $order_no,
		'callback_timeout'	=> $processor_data['processor_params']['return_after'],
		'description'		=> $order_info['email'],
	);

	fn_create_payment_form($submit_url, $post_data, 'Payme');

	exit;
}