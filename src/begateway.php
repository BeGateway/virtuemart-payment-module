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
    
    protected function displayLogos ($logo_list) {
      $img = "";
      if (!(empty($logo_list))) {
        $url = JURI::root () . 'plugins/vmpayment/begateway/images/';
        if (!is_array ($logo_list)) {
          $logo_list = (array)$logo_list;
        }
        foreach ($logo_list as $logo) {
          $alt_text = substr ($logo, 0, strpos ($logo, '.'));
          $img .= '<span class="vmCartPaymentLogo" ><img style="width: 150px;" align="middle" src="' . $url . $logo . '"  alt="' . $alt_text . '" /></span> ';
        }
      }
      return $img;
    }

    
    function plgVmConfirmedOrder($cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return NULL;
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }
        
        $currency               = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');
        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total, $method->payment_currency);

        require_once __DIR__ . '/bepaid-api-php/lib/ecomcharge.php';
        \eComCharge\Settings::setShopId($method->ShopId);
        \eComCharge\Settings::setShopKey($method->ShopKey);
        
        $order_id = $order['details']['BT']->order_number;
        
        $transaction = new \eComCharge\GetPaymentPageToken;

        $transaction->money->setAmount($totalInPaymentCurrency['value']);
        $transaction->money->setCurrency($currency);
        $transaction->setTrackingId($_SERVER['HTTP_HOST'].'_'.$order_id);
        $transaction->setDescription('Order #'.$order_id);
        $transaction->setLanguage(substr($order['details']['BT']->order_language, 0, 2));

        if($method->TransactionType == 'authorization') {
          $transaction->setAuthorizationTransactionType();
        }

        $transaction->setNotificationUrl(JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&action=begateway_result'));
        $transaction->setSuccessUrl(JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&action=begateway_success'));
        $transaction->setFailUrl(JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id));
        $transaction->setDeclineUrl(JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id));
        $transaction->setCancelUrl('http://'.$_SERVER['HTTP_HOST']);

        $transaction->customer->setFirstName($order['details']['BT']->first_name);
        $transaction->customer->setLastName($order['details']['BT']->last_name);
        $transaction->customer->setAddress($order['details']['BT']->address_1);
        $transaction->customer->setCity($order['details']['BT']->city);
        $transaction->customer->setZip($order['details']['BT']->zip);
        $transaction->customer->setEmail($order['details']['BT']->email);
        $transaction->customer->setPhone($order['details']['BT']->phone_1);    
        $transaction->customer->setIp($_SERVER['REMOTE_ADDR']);
        
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
        
        $transaction->setAddressHidden();
        
        $response = $transaction->submit();

        if(!$response->isSuccess()) {
          echo $response->getMessage();
          die;
        }
        
        header('Location: https://'.$method->PageUrl.'/checkout?token='.$response->getToken());
        die;
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
        return null;
    }
    
    function plgVmOnPaymentResponseReceived(&$html)
    {
        $get = JRequest::get();
        
        if ($get['action'] == 'begateway_success') {
            if (!class_exists('VirtueMartCart'))
                require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
            $cart = VirtueMartCart::getCart();
            $cart->emptyCart();
            
            return true;
        } else if ($get['action'] == 'begateway_result') {
            $data = file_get_contents('php://input');
            $data = json_decode($data, true);
            $tracking_id = str_replace($_SERVER['HTTP_HOST'].'_', '', $data['transaction']['tracking_id']);
            
            if (!class_exists('VirtueMartModelOrders'))
                require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
            
            $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($tracking_id);
            
            $modelOrder = new VirtueMartModelOrders();
            $order      = $modelOrder->getOrder($virtuemart_order_id);
            
            if (!isset($order['details']['BT']->virtuemart_order_id)) {
                die;
            }
            
            $method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id);
            
            require_once __DIR__ . '/bepaid-api-php/lib/ecomcharge.php';
            \eComCharge\Settings::setShopId($method->ShopId);
            \eComCharge\Settings::setShopKey($method->ShopKey);
            
            $query = new \eComCharge\QueryByTrackingId;
            $query->setTrackingId($_SERVER['HTTP_HOST'].'_'.$tracking_id);
            $query_response = $query->submit();
            $response = $query_response->getResponse();
            
            if ($response->transaction->status == 'successful' && $order['details']['BT']->order_status == 'P') {
                $message = 'UID: '.$response->transaction->uid.'<br>';
                if(isset($response->transaction->three_d_secure_verification)) {
                  $message .= '3-D Secure: '.$response->transaction->three_d_secure_verification->pa_status.'<br>';
                  $message .= '3-D Secure message: '.$response->transaction->three_d_secure_verification->message;
                }
                
                $order['order_status']      = 'C';
                $order['customer_notified'] = 1;
                $order['comments'] = $message;
                $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
            }
        }
    }
}