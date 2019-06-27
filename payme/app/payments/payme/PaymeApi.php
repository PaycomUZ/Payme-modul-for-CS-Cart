<?php
//file_put_contents(dirname(__FILE__) . "../../../../payme.log", ' -> order_info ='.json_encode($order, JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND);
use Tygh\Registry;

class PaymeApi {

	private $errorInfo ="";
	private $errorCod =0;
	private $request_id=0;
	private $responceType=0;
	private $result =true;
	private $inputArray;
	private $lastTransaction;
	private $statement;
	private $settings;

	public function construct() {}

	public function parseRequest() {

		if ( (!isset($this->inputArray)) || empty($this->inputArray) ) {

			$this->setErrorCod(-32700,"empty inputArray");

		} else {

			$parsingJsonError=false;

			switch (json_last_error()){

				case JSON_ERROR_NONE: break;
				default: $parsingJson=true; break;
			}

			if ($parsingJsonError) {

				$this->setErrorCod(-32700,"parsingJsonError");

			} else {

				// Request ID
				if (!empty($this->inputArray['id']) ) {

					$this->request_id = filter_var($this->inputArray['id'], FILTER_SANITIZE_NUMBER_INT);
				}

					 if ($_SERVER['REQUEST_METHOD']!='POST') $this->setErrorCod(-32300);
				else if(! isset($_SERVER['PHP_AUTH_USER']))  $this->setErrorCod(-32504,"логин пустой");
				else if(! isset($_SERVER['PHP_AUTH_PW']))	 $this->setErrorCod(-32504,"пароль пустой");
			}
		}

		if ($this->result) {
 
			$merchantKey="";
			$payment_id = db_get_field("SELECT a.payment_id FROM ?:payments as a LEFT JOIN ?:payment_processors as b ON a.processor_id = b.processor_id WHERE a.status = 'A' AND b.processor_script = 'payme.php' LIMIT 1");
			$this->settings = fn_get_payment_method_data($payment_id);

			if ($this->settings['processor_params']['test_mode']=='yes'){

				$merchantKey=html_entity_decode($this->settings['processor_params']['secret_key_for_test']);

			} else if ($this->settings['processor_params']['test_mode']=='no'){

				$merchantKey=html_entity_decode($this->settings['processor_params']['secret_key']);
			}
 
			if( $merchantKey != html_entity_decode($_SERVER['PHP_AUTH_PW']) ) {

				$this->setErrorCod(-32504,"неправильный  пароль");

			} else {

				if ( method_exists($this,"payme_".$this->inputArray['method'])) {

					$methodName="payme_".$this->inputArray['method'];
					$this->$methodName();

				} else {

					$this->setErrorCod(-32601, $this->inputArray['method'] );
				}
			}
		}

		return $this->GenerateResponse();
	}

	public function payme_CheckPerformTransaction() {

		$order_id = filter_var( $this->inputArray['params']['account']['order_id'], FILTER_SANITIZE_NUMBER_INT);

		// Поиск заказа по order_id
		$order = fn_get_order_info($order_id);

		// Заказ не найден
		if (! $order ) {

			$this->setErrorCod(-31050,'order_id');

		// Заказ найден
		} else {
 
			// Поиск транзакции по order_id
			$this->getLastTransactionForOrder($order_id);

			// Транзакция нет
			if (! $this->lastTransaction ) {

				// Проверка состояния заказа
				if ($order['status']!='N' ) {

					$this->setErrorCod(-31052, 'order_id');

				// Сверка суммы заказа
				} else  if ( ($order['total']*100) != $this->inputArray['params']['amount'] ) {

					$this->setErrorCod(-31001, 'order_id'); 

				// Allow true
				} else {

					$this->responceType=1;
				} 

			// Существует транзакция
			} else {

				$this->setErrorCod(-31051, 'order_id');
			}
		}
	}

	public function payme_CreateTransaction() {

		// Поиск заказа по order_id
		$order_id = filter_var( $this->inputArray['params']['account']['order_id'], FILTER_SANITIZE_NUMBER_INT);
		$order = fn_get_order_info($order_id);

		// Поиск транзакции по id
		$transactionId=$this->inputArray['params']['id'];
		$this->getLastTransaction($transactionId);

		// Существует транзакция
		if ($this->lastTransaction) {

			$paycom_time_integer=$this->datetime2timestamp($this->lastTransaction['create_time']) *1000;
			$paycom_time_integer=$paycom_time_integer+43200000;

			// Проверка состояния заказа
			if ($order['status']!='N' ) {

				$this->setErrorCod(-31052, 'order_id');

			// Проверка состояния транзакции
			} else if ($this->lastTransaction['state']!=1){

				$this->setErrorCod(-31008, 'order_id');

			// Проверка времени создания транзакции
			} else if ($paycom_time_integer <= $this->timestamp2milliseconds(time())){

				// Отменит reason = 4
				db_query('UPDATE ?:payme_transactions SET cancel_time = ?s, reason = ?s, state = ?s WHERE transaction_id = ?i', date('Y-m-d H:i:s'),4,-1, (int)$this->lastTransaction['transaction_id']);
				
				$pp_response = array();
				$pp_response['order_status'] = 'I';
				$pp_response['reason_text'] = 4;
				
				fn_change_order_status($order_id, 'I');
				fn_finish_payment($order_id, $pp_response);

				$this->getLastTransaction($transactionId);
				$this->responceType=2;

			// Всё OK
			} else {

				$this->responceType=2;
			}

		// Транзакция нет
		} else {

			// Заказ не найден
			if (! $order ) {

				$this->setErrorCod(-31050,'order_id');

			// Заказ найден
			} else {

				// Проверка состояния заказа 
				if ($order['status']!='N' ) {

					$this->setErrorCod(-31052, 'order_id');

				// Сверка суммы заказа 	
				} else  if ( ($order['total']*100) != $this->inputArray['params']['amount'] ) {

					$this->setErrorCod(-31001, 'order_id');

				// Запись транзакцию state=1
				} else {

					// Поиск транзакции по order_id
					$this->getLastTransactionForOrder($order_id);

					// Транзакция нет
					if (! $this->lastTransaction ) {

						$this->SaveOrder((
										$order->$order['total']*100),
										$order_id, 
										$order_id,
										$this->inputArray['params']['time'],
										$this->timestamp2datetime($this->inputArray['params']['time'] ),
										$this->inputArray['params']['id'] 
										);

						$this->getLastTransactionForOrder($order_id);
						$this->responceType=2;

					// Существует транзакция
					} else {

						$this->setErrorCod(-31051, 'order_id');
					}
				}
			}
		}
	}

	public function payme_CheckTransaction() {

		// Поиск транзакции по id
		$transactionId=$this->inputArray['params']['id'];
		$this->getLastTransaction($transactionId);

		// Существует транзакция
		if ($this->lastTransaction) {

			$this->responceType=2;

		// Транзакция нет
		} else {

			$this->setErrorCod(-31003);
		}
	}

	public function payme_PerformTransaction() {

		// Поиск транзакции по id
		$transactionId=$this->inputArray['params']['id'];
		$this->getLastTransaction($transactionId);

		// Существует транзакция
		if ( $this->lastTransaction ) {

			// Поиск заказа по order_id
			$order = fn_get_order_info($this->lastTransaction['order_id']);

			// Проверка состояние транзакцие
			if ($this->lastTransaction['state']==1) {

				$paycom_time_integer=$this->datetime2timestamp($this->lastTransaction['create_time']) *1000;
				$paycom_time_integer=$paycom_time_integer+43200000;

				// Проверка времени создания транзакции	
				if( $paycom_time_integer <= $this->timestamp2milliseconds(time()) ) {

					// Отменит reason = 4
					db_query('UPDATE ?:payme_transactions SET cancel_time = ?s, reason = ?s, state = ?s WHERE transaction_id = ?i', date('Y-m-d H:i:s'),4,-1, (int)$this->lastTransaction['transaction_id']);

					$pp_response = array();
					$pp_response['order_status'] = 'I';
					$pp_response['reason_text'] = 4;
					
					fn_change_order_status($this->lastTransaction['order_id'], 'I');
					fn_finish_payment($this->lastTransaction['order_id'], $pp_response);

				// Всё Ok
				} else {

					// Оплата
					db_query('UPDATE ?:payme_transactions SET perform_time = ?s, state = ?s WHERE transaction_id = ?i', date('Y-m-d H:i:s'),2, (int)$this->lastTransaction['transaction_id']);

					$pp_response = array();
					$pp_response['order_status'] = 'P';
					fn_finish_payment($this->lastTransaction['order_id'], $pp_response);
				}

				$this->responceType=2;
				$this->getLastTransaction($transactionId);

			// Cостояние не 1
			} else {

				// Проверка состояние транзакцие
				if ($this->lastTransaction['state']==2) {

					$this->responceType=2;

				// Cостояние не 2
				} else {

					$this->setErrorCod(-31008);
				}
			}

		// Транзакция нет
		} else {

			$this->setErrorCod(-31003);
		}
	}

	public function payme_CancelTransaction() {

		// Поиск транзакции по id
		$transactionId=$this->inputArray['params']['id'];
		$this->getLastTransaction($transactionId);

		// Существует транзакция
		if ($this->lastTransaction) {

			// Поиск заказа по order_id
			$order = fn_get_order_info($this->lastTransaction['order_id']);

			$reasonCencel=filter_var($this->inputArray['params']['reason'], FILTER_SANITIZE_NUMBER_INT);

			// Проверка состояние транзакцие
			if ($this->lastTransaction['state']==1) {

				// Отменит state = -1
				db_query('UPDATE ?:payme_transactions SET cancel_time = ?s, reason = ?s, state = ?s WHERE transaction_id = ?i', date('Y-m-d H:i:s'),$reasonCencel,-1, (int)$this->lastTransaction['transaction_id']);

				$pp_response = array();
				$pp_response['order_status'] = 'I';
				$pp_response['reason_text'] = $reasonCencel;

				fn_change_order_status($this->lastTransaction['order_id'], 'I');
				fn_finish_payment($this->lastTransaction['order_id'], $pp_response);

			// Cостояние 2
			} else if ($this->lastTransaction['state']==2) {

				// Отменит state = -2
				db_query('UPDATE ?:payme_transactions SET cancel_time = ?s, reason = ?s, state = ?s WHERE transaction_id = ?i', date('Y-m-d H:i:s'),$reasonCencel,-2, (int)$this->lastTransaction['transaction_id']);

				$pp_response = array();
				$pp_response['order_status'] = 'I';
				$pp_response['reason_text'] = $reasonCencel;
				
				fn_change_order_status($this->lastTransaction['order_id'], 'I');
				fn_finish_payment($this->lastTransaction['order_id'], $pp_response);

			// Cостояние
			} else {

				// Ничего не надо делать
			}

			$this->responceType=2;
			$this->getLastTransaction($transactionId);

		// Транзакция нет
		} else {

			$this->setErrorCod(-31003);
		}
	}

	protected function getLastTransaction ($v_transaction_id ) {

		$arrayOfArray=db_get_array("SELECT * FROM ?:payme_transactions WHERE paycom_transaction_id = ?s", $v_transaction_id);

		if($arrayOfArray)
		$this->lastTransaction=$arrayOfArray[0]; 
	}

	protected function getLastTransactionForOrder($v_order_id ) {
	
		$arrayOfArray=db_get_array("SELECT * FROM ?:payme_transactions WHERE order_id = ?s", $v_order_id);

		if($arrayOfArray)
		$this->lastTransaction=$arrayOfArray[0]; 
	}

	public function payme_ChangePassword() {

		$params = db_get_field(
		'SELECT ?:payments.processor_params'
		.' FROM ?:payments'
		.' LEFT JOIN ?:payment_processors'
		.' ON ?:payment_processors.processor_id = ?:payments.processor_id'
		.' WHERE ?:payment_processors.processor_script = ?s'
		.' AND ?:payments.status = ?s', 'payme.php', 'A'
		);

		if (!empty($params)) {

		$params= unserialize($params);
		$params['secret_key_for_test'] = $this->inputArray['params']['password'];
		$params= serialize($params);

		db_query('UPDATE ?:payments as p SET p.processor_params = ?s WHERE p.status = ?s and p.processor_id in '.
				'(SELECT d.processor_id FROM ?:payment_processors as d WHERE d.processor_script = ?s)' ,$params, 'A', 'payme.php');
		}

		$this->responceType=3;
	}

	public function payme_GetStatement() {

		$rows=db_get_array("SELECT t.paycom_time,
									t.paycom_transaction_id,
									t.amount,
									t.order_id,
									t.create_time,
									t.perform_time,
									t.cancel_time,
									t.state,
									t.reason,
									t.receivers FROM ?:payme_transactions as t WHERE t.paycom_time_datetime >= ?s and  t.paycom_time_datetime <= ?s", 
									$this->timestamp2datetime($this->inputArray['params']['from']),
									$this->timestamp2datetime($this->inputArray['params']['to'])
									);

		$responseArray = array();
		$transactions  = array();

		foreach ($rows as $row) {

			array_push($transactions,array(

				"id"		   => $row["paycom_transaction_id"],
				"time"		   => $row['paycom_time']  ,
				"amount"	   => $row["amount"],
				"account"	   => array("order_id" => $row["order_id"]),
				"create_time"  => (is_null($row['create_time']) ? null: $this->datetime2timestamp( $row['create_time']) ) ,
				"perform_time" => (is_null($row['perform_time'])? null: $this->datetime2timestamp( $row['perform_time'])) ,
				"cancel_time"  => (is_null($row['cancel_time']) ? null: $this->datetime2timestamp( $row['cancel_time']) ) ,
				"transaction"  => $row["order_id"],
				"state"		   => (int) $row['state'],
				"reason"	   => (is_null($row['reason'])?null:(int) $row['reason']) ,
				"receivers"	=> null
			)) ;
		}

		$responseArray['result'] = array( "transactions"=> $transactions );

		$this->responceType=4;
		$this->statement=$responseArray;
	}

	public function GenerateResponse() {

		if ($this->errorCod==0) {

			if ($this->responceType==1) {

				$responseArray = array('result'=>array( 'allow' => true )); 

			} else if ($this->responceType==2) {

				$responseArray = array(); 
				$responseArray['id']	 = $this->request_id;
				$responseArray['result'] = array(

					"create_time"	=> $this->datetime2timestamp($this->lastTransaction['create_time']) *1000,
					"perform_time"  => $this->datetime2timestamp($this->lastTransaction['perform_time'])*1000,
					"cancel_time"   => $this->datetime2timestamp($this->lastTransaction['cancel_time']) *1000,
					"transaction"	=>  $this->lastTransaction['cms_order_id'], //$this->order_id,
					"state"			=> (int)$this->lastTransaction['state'],
					"reason"		=> (is_null($this->lastTransaction['reason'])?null:(int)$this->lastTransaction['reason'])
				);

			} else if ($this->responceType==3) {

				$responseArray = array('result'=>array( 'success' => true ));

			} else if ($this->responceType==4) {
				
				$responseArray=$this->statement;
			}

		} else {

			$responseArray['id']	= $this->request_id;
			$responseArray['error'] = array (

				'code'   =>(int)$this->errorCod,
				'message'=> array(

					"ru"=>$this->getGenerateErrorText($this->errorCod,"ru"),
					"uz"=>$this->getGenerateErrorText($this->errorCod,"uz"),
					"en"=>$this->getGenerateErrorText($this->errorCod,"en"),
					"data" =>$this->errorInfo
				)
			);
		}

		return $responseArray;
	}

	public function SaveOrder($amount,$orderId,$cmsOrderId,$paycomTime,$paycomTimeDatetime,$paycomTransactionId ) {

		$transactionCnt=db_get_array("SELECT * FROM ?:payme_transactions WHERE cms_order_id = ?s and order_id = ?s and amount = ?s",
			(is_null($cmsOrderId)? 0:$cmsOrderId),
			(is_null($orderId)?	0:$orderId),
			$amount);

		if (! $transactionCnt) {

			$insertData = array(
					 'create_time'  		 => date('Y-m-d H:i:s'),
					 'amount'  				 => $amount, 
					 'state'  				 => 1, 
					 'order_id'  			 => (is_null( $orderId )  ?  0:$orderId),
					 'cms_order_id' 		 => (is_null( $cmsOrderId )? 0:$cmsOrderId),
					 'paycom_time'		     => $paycomTime,
					 'paycom_time_datetime'  => $paycomTimeDatetime,
					 'paycom_transaction_id' => $paycomTransactionId
				  );
			db_query('INSERT INTO ?:payme_transactions ?e', $insertData);
		}
	}

	public function getGenerateErrorText($codeOfError,$codOfLang){

		$listOfError=array ('-31001' => array(
										  "ru"=>'Неверная сумма.',
										  "uz"=>'Неверная сумма.',
										  "en"=>'Неверная сумма.'
										),
							'-31003' => array(
										  "ru"=>'Транзакция не найдена.',
										  "uz"=>'Транзакция не найдена.',
										  "en"=>'Транзакция не найдена.'
										),
							'-31008' => array(
										  "ru"=>'Невозможно выполнить операцию.',
										  "uz"=>'Невозможно выполнить операцию.',
										  "en"=>'Невозможно выполнить операцию.'
										),
							'-31050' => array(
										  "ru"=>'Заказ не найден.',
										  "uz"=>'Заказ не найден.',
										  "en"=>'Заказ не найден.'
										),
							'-31051' => array(
										  "ru"=>'Существует транзакция.',
										  "uz"=>'Существует транзакция.',
										  "en"=>'Существует транзакция.'
										),
							'-31052' => array(
											"ru"=>'Заказ уже оплачен.',
											"uz"=>'Заказ уже оплачен.',
											"en"=>'Заказ уже оплачен.'
										),
										
							'-32300' => array(
										  "ru"=>'Ошибка возникает если метод запроса не POST.',
										  "uz"=>'Ошибка возникает если метод запроса не POST.',
										  "en"=>'Ошибка возникает если метод запроса не POST.'
										),
							'-32600' => array(
										  "ru"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации',
										  "uz"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации',
										  "en"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации'
										),
							'-32700' => array(
										  "ru"=>'Ошибка парсинга JSON.',
										  "uz"=>'Ошибка парсинга JSON.',
										  "en"=>'Ошибка парсинга JSON.'
										),
							'-32600' => array(
										  "ru"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации.',
										  "uz"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации.',
										  "en"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации.'
										),
							'-32601' => array(
										  "ru"=>'Запрашиваемый метод не найден. В RPC-запросе имя запрашиваемого метода содержится в поле data.',
										  "uz"=>'Запрашиваемый метод не найден. В RPC-запросе имя запрашиваемого метода содержится в поле data.',
										  "en"=>'Запрашиваемый метод не найден. В RPC-запросе имя запрашиваемого метода содержится в поле data.'
										),
							'-32504' => array(
										  "ru"=>'Недостаточно привилегий для выполнения метода.',
										  "uz"=>'Недостаточно привилегий для выполнения метода.',
										  "en"=>'Недостаточно привилегий для выполнения метода.'
										),
							'-32400' => array(
										  "ru"=>'Системная (внутренняя ошибка). Ошибку следует использовать в случае системных сбоев: отказа базы данных, отказа файловой системы, неопределенного поведения и т.д.',
										  "uz"=>'Системная (внутренняя ошибка). Ошибку следует использовать в случае системных сбоев: отказа базы данных, отказа файловой системы, неопределенного поведения и т.д.',
										  "en"=>'Системная (внутренняя ошибка). Ошибку следует использовать в случае системных сбоев: отказа базы данных, отказа файловой системы, неопределенного поведения и т.д.'
										)
							);

		return $listOfError[$codeOfError][$codOfLang];
	}

	public function timestamp2datetime($timestamp){
		// if as milliseconds, convert to seconds
		if (strlen((string)$timestamp) == 13) {
			$timestamp = $this->timestamp2seconds($timestamp);
		}

		// convert to datetime string
		return date('Y-m-d H:i:s', $timestamp);
	}

	public function timestamp2seconds($timestamp) {
		// is it already as seconds
		if (strlen((string)$timestamp) == 10) {
			return $timestamp;
		}

		return floor(1 * $timestamp / 1000);
	}

	public function timestamp2milliseconds($timestamp) {
		// is it already as milliseconds
		if (strlen((string)$timestamp) == 13) {
			return $timestamp;
		}

		return $timestamp * 1000;
	}

	public function datetime2timestamp($datetime) {

		if ($datetime) {

			return strtotime($datetime);
		}

		return $datetime;
	}

	public function setErrorCod($cod_,$info=null) {

		$this->errorCod=$cod_;

		if ($info!=null) $this->errorInfo=$info;

		if ($cod_!=0) {

			$this->result=false;
		}
	}

	public function getInputArray() {

		return $this->inputArray;
	}

	public function setInputArray($i_Array) {

		$this->inputArray = json_decode($i_Array, true); 
	}
}
