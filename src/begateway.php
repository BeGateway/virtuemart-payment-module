<?php
defined('_JEXEC') or die;

if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVMPaymentBegateway extends vmPSPlugin
{
    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->_loggable   = TRUE;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey  = 'id';
        $this->_tableId    = 'id';
        $varsToPush        = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
        $this->_loadLibrary();
    }

    function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Begateway Table');
    }

    function getTableSQLFields()
    {
        $SQLfields = array(
            'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'order_number' => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency' => 'char(3)',
            'email_currency' => 'char(3)',
            'cost_per_transaction' => 'decimal(10,2)',
            'cost_percent_total' => 'decimal(10,2)',
            'tax_id' => 'smallint(1)'
        );

        return $SQLfields;
    }

    function plgVmConfirmedOrder($cart, $order)
    {

        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return NULL;
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }

        $this->getPaymentCurrency($method);
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
        $db = JFactory::getDBO();
        $db->setQuery($q);
        $currency_code_3 = $db->loadResult();

        $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
        $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);

        \BeGateway\Settings::$shopId = $method->ShopId;
        \BeGateway\Settings::$shopKey = $method->ShopKey;
        \BeGateway\Settings::$gatewayBase = 'https://' . $method->GatewayUrl;
        \BeGateway\Settings::$checkoutBase = 'https://' . $method->PageUrl;

        $order_id = $order['details']['BT']->order_number;

        $transaction = new \BeGateway\GetPaymentToken;

        $transaction->money->setCurrency($currency_code_3);
        $transaction->money->setAmount($totalInPaymentCurrency);
        $transaction->setTrackingId($order['details']['BT']->virtuemart_paymentmethod_id . '|' . $order_id);
        $transaction->setDescription(vmText::_('VMPAYMENT_BEGATEWAY_ORDER') . ' #' . $order_id);
        $transaction->setLanguage(substr($order['details']['BT']->order_language, 0, 2));

        if($method->TransactionType == 'authorization') {
          $transaction->setAuthorizationTransactionType();
        }

        $transaction->setTestMode($method->TestMode == '1');

        if ($method->EnableCards == '1') {
          $transaction->addPaymentMethod(new \BeGateway\PaymentMethod\CreditCard);
        }

        if ($method->EnableHalva == '1') {
          $transaction->addPaymentMethod(new \BeGateway\PaymentMethod\CreditCardHalva);
        }

        if ($method->EnableErip == '1') {
          $transaction->addPaymentMethod(
            new \BeGateway\PaymentMethod\Erip(
              array(
                'order_id' => $order['details']['BT']->virtuemart_order_id,
                'account_number' => strval($order_id)
              )
            )
          );
        }

        $notification_url = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component');
        $notification_url = str_replace('carts.local','webhook.begateway.com:8443', $notification_url);

        $transaction->setNotificationUrl($notification_url);
        $transaction->setSuccessUrl(JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id));
        $transaction->setFailUrl(JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id));
        $transaction->setDeclineUrl(JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id));
        $transaction->setCancelUrl(JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart'));

        $transaction->customer->setFirstName($order['details']['BT']->first_name);
        $transaction->customer->setLastName($order['details']['BT']->last_name);
        $transaction->customer->setAddress($order['details']['BT']->address_1);
        $transaction->customer->setCity($order['details']['BT']->city);
        $transaction->customer->setZip($order['details']['BT']->zip);
        $transaction->customer->setEmail($order['details']['BT']->email);
        $transaction->customer->setPhone($order['details']['BT']->phone_1);

        $countryModel = VmModel::getModel ('country');
        $countries = $countryModel->getCountries (TRUE, TRUE, FALSE);
        foreach ($countries as  $country) {
          if($country->virtuemart_country_id == $order['details']['BT']->virtuemart_country_id) {
            $transaction->customer->setCountry($country->country_2_code);
            break;
          }
        }

        if($country->country_2_code == 'CA' || $country->country_2_code == 'US') {
          $stateModel = VmModel::getModel ('state');
          $states = $stateModel->getStates($order['details']['BT']->virtuemart_country_id);
          foreach ($states as  $state) {
            if($state->virtuemart_state_id == $order['details']['BT']->virtuemart_state_id) {
              $transaction->customer->setState($state->state_2_code);
              break;
            }
          }
        }

        if ($method->debug_mode == 1) {
          vmDebug('BEGATEWAY token request data', print_r($transaction, true));
        }

        $response = $transaction->submit();

        if ($method->debug_mode == 1) {
          vmDebug('BEGATEWAY token request response', print_r($response, true));
        }

        $returnValue = 0;

        if ($response->isSuccess()) {
    			$returnValue = 2;
    			$html = $this->renderByLayout(
            'displaypayment',
            array(
			        'response' => $response->getRedirectUrl()
			      )
          );
    		} else {
    			$html = vmText::_ ('VMPAYMENT_BEGATEWAY_TECHNICAL_ERROR') .
  				" <br /> - " . addslashes ($response->getMessage()) . "<br />" .
  				vmText::_ ('VMPAYMENT_BEGATEWAY_CONTACT_SHOPOWNER');
    		}

        return $this->processConfirmedOrderPaymentResponse ($returnValue, $cart, $order, $html, $this->renderPluginName($method, $order), '');
    }

    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id)
    {
        if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
            return NULL;
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return NULL;
        }
        VmConfig::loadJLang('com_virtuemart');

        $html = '<table class="adminlist table">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
        if ($paymentTable->email_currency) {
            $html .= $this->getHtmlRowBE('STANDARD_EMAIL_CURRENCY', $paymentTable->email_currency);
        }
        $html .= '</table>' . "\n";
        return $html;
    }

    function checkConditions($cart, $method, $cart_prices)
    {
        $this->convert_condition_amount($method);
        $amount  = $this->getCartAmount($cart_prices);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount OR ($method->min_amount <= $amount AND ($method->max_amount == 0)));
        if (!$amount_cond) {
            return FALSE;
        }
        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        if (!is_array($address)) {
            $address                          = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries)) {
            return TRUE;
        }

        return FALSE;
    }

    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {
        return $this->OnSelectCheck($cart);
    }

    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL;
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }
        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
        return;
    }

    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    public function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart)
    {
        return null;
    }

    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {

        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {

        return $this->setOnTablePluginParams($name, $id, $table);
    }

    function plgVmOnPaymentNotification()
    {
      $webhook = new \BeGateway\Webhook;

      vmdebug ('BEGATEWAY plgVmOnPaymentResponseReceived', print_r($webhook->getResponseArray(), true));

      if (!class_exists('VirtueMartModelOrders'))
          require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');

      $tracking_id = explode('|', $webhook->getTrackingId());
      $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($tracking_id[1]);

      $modelOrder = new VirtueMartModelOrders();
      $order      = $modelOrder->getOrder($virtuemart_order_id);

      if (!($method = $this->getVmPluginMethod($tracking_id[0]))) {
        return NULL;
      } // Another method was selected, do nothing

      if (!isset($order['details']['BT']->virtuemart_order_id)) {
          return NULL;
      }

      \BeGateway\Settings::$shopId = $method->ShopId;
      \BeGateway\Settings::$shopKey = $method->ShopKey;
      \BeGateway\Settings::$gatewayBase = 'https://' . $method->GatewayUrl;
      \BeGateway\Settings::$checkoutBase = 'https://' . $method->PageUrl;

      if ($webhook->isAuthorized() && $webhook->isSuccess() && $order['details']['BT']->order_status == $method->status_pending) {
          $message = 'UID: '.$webhook->getUid().'<br>';
          if(isset($webhook->getResponse()->transaction->three_d_secure_verification)) {
            $message .= '3-D Secure: '.$webhook->getResponse()->transaction->three_d_secure_verification->pa_status.'<br>';
          }

          $order['order_status']      = $method->status_success;
          $order['customer_notified'] = 1;
          $order['comments'] = $message;
          $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
          die("OK");
      } else {
        die("ERROR");
      }
    }

    function plgVmOnPaymentResponseReceived(&$html)
    {
      if (!class_exists('VirtueMartCart'))
          require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
      $cart = VirtueMartCart::getCart();
      $cart->emptyCart();

      return true;
    }

    function plgVmOnUserPaymentCancel() {

      if (!class_exists('VirtueMartModelOrders'))
      require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

      $order_number = vRequest::getVar('on');
      if (!$order_number)
      return false;
      $db = JFactory::getDBO();
      $query = 'SELECT ' . $this->_tablename . '.`virtuemart_order_id` FROM ' . $this->_tablename . " WHERE  `order_number`= '" . $order_number . "'";

      $db->setQuery($query);
      $virtuemart_order_id = $db->loadResult();

      if (!$virtuemart_order_id) {
          return null;
      }
      $this->handlePaymentUserCancel($virtuemart_order_id);

      return true;
    }

    private function _loadLibrary() {
      require JPATH_SITE . DS . 'plugins' . DS . 'vmpayment' . DS . 'begateway' . DS . 'begateway-api-php' . DS . 'lib' . DS . 'BeGateway.php';
    }

    public function getVarsToPush() {
      return array(
        'ShopId' => array('', 'char'),
        'ShopKey' => array('', 'char'),
        'GatewayUrl' => array('', 'char'),
        'PageUrl' => array('', 'char'),
        'TransactionType' => array('', 'char'),
        'payment_currency' => array('', 'int'),
        'TestMode' => array(0, 'int'),
        'debug_mode' => array(0, 'int'),
        'status_pending' => array('', 'char'),
        'status_success' => array('', 'char'),
        'EnableCards' => array(0, 'int'),
        'EnableHalva' => array(0, 'int'),
        'EnableErip' => array(0, 'int'),
        'payment_logos' => array('', 'char'),
        'countries' => array(0, 'char'),
        'min_amount' => array(0, 'int'),
        'max_amount' => array(0, 'int')
      );
    }
}
