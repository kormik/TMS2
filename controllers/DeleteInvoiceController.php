<?php

class DeleteInvoiceController extends Controller {
	function process($parameters) {
		$bitcoinPay = new Bitcoinpay();
		if (!$bitcoinPay->checkLogin()) $this->redirect('error');
		$paymentId = false;
		if (is_numeric($parameters[0])) $paymentId = $parameters[0]; else $this->redirect('error');
		
		//allow delete only for admin
		$paymentUserId = $bitcoinPay->getPaymentUserId($paymentId);
		if (!$bitcoinPay->checkIfIsAdminOfUser($_SESSION['id_user'], $paymentUserId)) {
			$this->redirect('error');
		}
		
		$result = $bitcoinPay->deletePayment($paymentId);
		$this->messages[] = $result;
		
		//navigate to default view for this action
		$this->redirect('checkUsers');
	}
}