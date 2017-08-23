<?php
/**
 *  Columns can be read from sales_flat_order
 *  
 *  ##TODO - what column in sales_flat_order holds a $10 gift cart payment and a $20 ccard payment on a $30 bill
 */

class FW_ZirconProcessing_Model_Gateway {

    public function __construct(Varien_Object $customer = NULL, Varien_Object $order = NULL, Varien_Object $payment = NULL){
        $this->_OrderAcceptObject = $this->_build_OrderAcceptObject($customer, $order, $payment);
    }
	
    public function methods(){
        echo "->Cust_Put(\$data[]);<br>\n";
        echo "->Order_GetId(\$data[]);<br>\n";
        echo "->Catalog_GetCustomerByAddress(\$data[]);<br>\n";
        echo "->Catalog_SetCustomerByAddress(\$data[]);<br>\n";
        echo "->Catalog_CreateRequest(\$data[]);<br>\n";
        echo "->Order_Submit(\$data[]);<br>\n";
    }

    public function __call($name, $request){
        $helper = Mage::helper('zirconprocessing');
     
        switch($name){    
            //JSON RESTful endpoint for this customer lookup or insertion - subroutine
            case "Cust_Put":
                $wsurl = $helper->getCustomerInsertEndPoint();
                //$wsurl = '/coupon1/subroutine/EMAIL.IDENT.SUB.R1';
                return $this->send($request[0],$wsurl);
                break;

            //JSON RESTful endpoint for getting the next OrderId - subroutine
            case "Order_GetId":
                $wsurl = $helper->getNextOrderIdEndPoint();
                //$wsurl = '/coupon1/subroutine/GETORDNR';
                $request[0] = '{}';
                return $this->send($request[0],$wsurl);
                break;	

            //JSON RESTful endpoint for customer retrieval by address - subroutine
            case "Catalog_GetCustomerByAddress":
                 $wsurl = $helper->getCustomerByAddressEndPoint();
                //$wsurl = '/account/subroutine/CMHASH';
                return $this->send($request[0],$wsurl);
                break;		
					
            //JSON RESTful endpoint for setting customer address
            case "Catalog_SetCustomerByAddress":
                 $wsurl = $helper->getSetCustomerAddressEndPoint();
                //$wsurl = '/coupon1/subroutine/CMHASHXX';
                return $this->send($request[0],$wsurl);
                break;		

            //JSON RESTful endpoint for submitting catalog request - subroutine
            case "Catalog_CreateRequest":
                $wsurl = $helper->getCataglogRequestEndPoint($request[0][1]);
                //$wsurl = '/coupon1/Req';
                return $this->send($request[0][0],$wsurl);
                break;

            //write order to TOW and TOWW
            case "Order_Submit":
                //$wsurl = '/account/Toww';
                $wsurl = $helper->getTowwSubmissionEndPoint();
                $this->send($request[0],$wsurl);
                //$wsurl = '/account/Tow';
                $wsurl = $helper->getTowSubmissionEndPoint();
                
                //submit to TOW twice - this was present in legacy code, keeping
                $this->send($request[0],$wsurl); 
                try{
                    return $wsurl = $helper->getTowSubmissionEndPoint();
                }
                catch(Exception $e){
                   //Eat this exception because it means that the first call worked
                    $ignoredException = TRUE;
                }
                break;
            default:
                throw new Exception('Method Choice Error - '.$name.' is not a valid Gateway method name.');
            return FALSE;
            break;
        }
    }
	
    /**
     * Sends a CURL request and returns the result
     * @param string $wsurl endpoint of api for curl
     * @param array $request key value post fields
     * @return json_array is successful, empty if failed
     */
    private function send($request, $wsurl){
        $helper = Mage::helper('zirconprocessing');
        $curl = curl_init($wsurl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        if (!empty($request)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
        }
	curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST, 'rsa_aes_256_sha,rsa_aes_128_sha');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 900);

        $curl_response = curl_exec($curl);
        $json_response = json_decode($curl_response, true);//array 
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        
        //When its a catalog request, sometimes the request has already been created in Zircon, this returns an error - in this case ignore error and treat as sucesss
        if($http_status == 409 && strpos($curl_response,'The+resource+has+already+been+created') !== false){
          return $json_response;  
        }

        // Did we get a 200 or an error?
        if($http_status != 200)
        {
            //write the JSON response to file
            $fileName = 'API_' . date("Ymd_Hi");

            //Log curl response if error
            Mage::log($curl_response, null, $fileName);

            $to = $helper->getErrorEmailNotice();
            $subject = "Order Submission to Zircon  - Connection to API import server failed";
            mail($to, $subject, 'See File:' . Mage::getBaseDir() . '/var/log/' . $fileName);
            throw new Exception("Connection to api import server failed. Status" .$http_status. "Response:" .$curl_response);
            return;
        }
  
        return $json_response;
    }

    /**
     * Sends a CURL get request and returns the result
     * @param string $wsurl endpoint of api for curl
     * @param string $requestparams for get
     * @return json_object is successful, empty if failed
     */
    public function get($requestparams, $wsurl){
        $helper = Mage::helper('zirconprocessing');
        $curl = curl_init($wsurl);

	// Set query data here with the URL
	curl_setopt($curl, CURLOPT_URL, $wsurl . $requestparams); 
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST, 'rsa_aes_256_sha,rsa_aes_128_sha');
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 900);

        $curl_response = curl_exec($curl);
        $json_response = json_decode($curl_response);//array
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        //When its a catalog request, sometimes the request has already been created in Zircon, this returns an error - in this case ignore error and treat as sucesss
        if($http_status == 409 && strpos($curl_response,'The+resource+has+already+been+created') !== false){
          return $json_response;
        }

        // Did we get a 200 or an error?
        if($http_status != 200)
        {
            //write the JSON response to file
            $fileName = 'API_GET_' . date("Ymd_Hi");

            //Log curl response if error
            Mage::log($curl_response, null, $fileName);

            $to = $helper->getErrorEmailNotice();
            $subject = "API GET REQUEST TO Zircon  - Connection to API import server failed";
            mail($to, $subject, 'See File:' . Mage::getBaseDir() . '/var/log/' . $fileName);
            throw new Exception("Connection to api import server failed. Status" .$http_status. "Response:" .$curl_response);
            return;
        }

        return $json_response;
    }

    /**
     *  Returns customerId - and creates it in Zircon if they don't already have a customer id
     *  
     *  param $customer mage object, 
     *  param $order mage object, 
     *  param $payment mage object
     *  return customerId or N/A-ERROR
     */
    public function checkZirconCustomerId($customer, $order, $payment){
	$helper = Mage::helper('zirconprocessing');
        
        //CHECK IF CUSTOMER IS GUEST
	$guest = $order->getCustomerIsGuest();
        
        //LOAD CURRENT company code, KQ=01, NA=02 , PW=03
        $store_company_code =  $helper->getCompanyCode();

        //LOAD CURRENT ZIRCON ORDER STATUS
        $zirconorder_status = $order->getData('zirconorder_status');
		
        //run every time, updating with current shipping info
        if($zirconorder_status != "ACCEPTED"){
            try{
                //build params to send to format into zircon address, billing address 
                $order_billing_address = $order->getBillingAddress();
                $custname = strtoupper(trim($order_billing_address->getFirstname()) . '/' . trim($order_billing_address->getLastname()));
                list($address1, $address2, $city, $state, $zip, $country) = $this->formatMageToZirconAddress($order_billing_address);

                $arr_customer = array(
                    'email'         => strtoupper($order->getCustomerEmail()),
                    'custno'        => '',
                    'fullname'      => $custname,
                    'address1'      => $address1,
                    'address2'      => $address2,
                    'city'          => $city,
                    'state'         => $state,
                    'zip'           => $zip,
                    'phone'         => $order_billing_address->getTelephone(),
                    'company'       => $store_company_code,
                    'createshipto'  => 'create'   //other options are init for searching or shipto, for creating the shipto address related to the billing address
                );
                
                $json_data = json_encode($arr_customer);
                if($customer->getZirconcustomerId() == "" || $customer->getZirconcustomerId() == "0"){
                    $response = $this->Cust_Put($json_data);
                    $zircon_customer_id = $response['custid'];
                }
                else{
                    $zircon_customer_id = $customer->getZirconcustomerId();
                }
    
                //REVIEW ZIRCON GATEWAY RESPONSE
                if(is_numeric($zircon_customer_id)){
                    //SAVE TO MAGENTO PAYMENT ORDER RECORD
                    $payment->setAdditionalInformation('zircon_customer_id', $zircon_customer_id);
                    $payment->save();
                    //SAVE TO MAGENTO CUSTOMER RECORD
                    if(!$guest && $zircon_customer_id != NULL && $customer->getEntityId()){
                        $customer->setZirconcustomerId($zircon_customer_id);
                        $customer->save();
                    }
					
                    // if you have a shipping address, add that as a related child record to the billing address
                    if($order->getShippingAddress()){ 
                        $order_shipping_address = $order->getShippingAddress();
                        list($address1, $address2, $city, $state, $zip, $country) = $this->formatMageToZirconAddress($order_shipping_address);

                        $shipping_name = $order_shipping_address->getFirstname()."/".$order_shipping_address->getLastname();
                        $arr_customer = array(
                            'email'			=> '',
                            'custno'		=> $zircon_customer_id, //instead of email, link to the customer record
                            'fullname'      => strtoupper($shipping_name),
                            'address1'      => $address1,
                            'address2'      => $address2,
                            'city'      	=> $city,
                            'state'      	=> $state,
                            'zip'      		=> $zip,
                            'phone'    		=> $order_shipping_address->getTelephone(),
                            'company'		=> $store_company_code,
                            'createshipto'	=> 'shipto'   //other options are init for searching or shipto, for creating the shipto address related to the billing address
                        );
                        $json_data = json_encode($arr_customer);					
                        $shipaddress_response = $this->Cust_Put($json_data); //don't do anything with the returned json 
                    }
					
                }else{
                    //LOG ERROR
                    Mage::log("ZIRCON NEW CUSTOMER OBSERVER ERROR: - ORDER #".$order->getIncrementId()." - EMAIL: ".$order->getCustomerEmail()."", null, 'FW_ZirconProcessing.log');		 
                    Mage::log($response, null, 'FW_ZirconProcessing.log');		 
                    Mage::log('ZIRCON ORDER ' . $order->getIncrementId() . ' NEW CUSTOMER OBSERVER ERROR POSTED: '.$this->_CustomerInsertObject.'', null, 'ZIRCON_ORDER_ERROR.log');

                    //SET ERROR ZIRCON CUSTOMER ID
                    $zircon_customer_id = 'N/A-ERROR';
                    //SAVE TO MAGENTO PAYMENT ORDER RECORD
                    $payment->setAdditionalInformation('zircon_customer_id', $zircon_customer_id);
                    $payment->save();
                    //SAVE TO MAGENTO CUSTOMER RECORD
                    if(!$guest && $zircon_customer_id != NULL && $customer->getEntityId()){
                        $customer->setZirconcustomerId($zircon_customer_id);
                        $customer->save();
                    }
                }
            }catch(Exception $e){
                //LOG ERROR
                Mage::log("ZIRCON CUSTOMER CHECK OBSERVER CONNECTION ERROR: ".$e->getMessage()."", null, 'FW_ZirconProcessing.log');

                //SET ERROR ZIRCON CUSTOMER ID
                $zircon_customer_id = 'N/A-ERROR';
                //SAVE TO MAGENTO PAYMENT ORDER RECORD
                $payment->setAdditionalInformation('zircon_customer_id', $zircon_customer_id);
                $payment->save();
                //SAVE TO MAGENTO CUSTOMER RECORD
                if(!$guest && $zircon_customer_id != NULL){
                    $customer->setZirconcustomerId($zircon_customer_id);
                    $customer->save();
                }
            }
        }
	
        if($zircon_customer_id == 0 || $zircon_customer_id == NULL){
            //SET ERROR ZIRCON CUSTOMER ID
            $zircon_customer_id = 'N/A-ERROR';
        }
		
        return $zircon_customer_id;	
    }
	
    /**
     *  Builds JSON to send an order to Zircon
     *  
     *  param customer mage object
     *  param order mage object
     *  param payment mage object
     *  return json 
     */
    public function _build_OrderAcceptObject($customer=NULL, $order=NULL, $payment=NULL){
        if(isset($customer) && isset($order) && isset($payment)){			

            //CHECK PAYMENT METHOD
            $payment_check = explode("_", $payment->getMethod());
            $payment_method = $payment_check[0];

            if($payment_method == "paypal"){
                //LOAD PAYPAL PAYMENT DATA
                $paymentid = $payment->getEntityId();
                $collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
                   ->setOrderFilter($order)
                   ->addPaymentIdFilter($paymentid)
                   ->setOrder('created_at', Varien_Data_Collection::SORT_ORDER_DESC)
                   ->setOrder('transaction_id', Varien_Data_Collection::SORT_ORDER_DESC);
                foreach ($collection as $txn) {
                    $transactionid = $txn->getTxnId();
                }
                $identifier = 0;
                $authcode = "";
            }elseif($payment_method == "authnetcim"){
                //LOAD PAYMENT DATA
                $transactionid = $payment->getLastTransId();
                $identifier = $payment->getLastTransId();
                $authcode = $payment->getAdditionalInformation('auth_code');
            }
			
            //Force transmit the current customer billing and shipping address, each order
            $zircon_customer_id = $this->checkZirconCustomerId($customer, $order, $payment);
            $json_order['cm_id'] = $zircon_customer_id;

            //LOAD ORDER BILLING ADDRESS			
            $order_billing_address = $order->getBillingAddress();
            list($json_order['bt_address1'], $json_order['bt_address2'], $json_order['bt_city'], $json_order['bt_state'], $json_order['bt_zip'], $bt_country) = $this->formatMageToZirconAddress($order_billing_address);

            //BUILD CUSTOMER NAME - split by slash
            $json_order['bt_name'] = strtoupper(trim($order_billing_address->getFirstname()) . '/' . trim($order_billing_address->getLastname()));

            //CHECK AND LOAD ORDER SHIPPING ADDRESS - IF NOT USE BILLING ADDRESS
            if($order->getShippingAddress()){
                $order_shipping_address = $order->getShippingAddress();
                list($json_order['st_address1'], $json_order['st_address2'], $json_order['st_city'], $json_order['st_state'], $json_order['st_zip'], $bt_country) = $this->formatMageToZirconAddress($order_shipping_address);

                //BUILD CUSTOMER NAME - split by slash
                $json_order['st_name'] = strtoupper(trim($order_shipping_address->getFirstname()) . '/' . trim($order_shipping_address->getLastname()));			
            }
            $json_order['day_tel_no'] = $order_billing_address->getTelephone();
            $json_order['cc_no'] = Mage::getSingleton('core/encryption')->decrypt($payment->getAdditionalInformation('cc'));
            $json_order['exp_date'] = str_pad($payment->getCcExpMonth(), 2, "0", STR_PAD_LEFT) . substr($payment->getCcExpYear(), -2);
            $json_order['airbill'] = $payment->getAdditionalInformation('cccid'); //3-4 digit code on back of card put code into airbill field.. 	
   
            //GATHER AND LOAD ALL PRODUCTS TO FORMAT
            $order_items = $order->getAllItems();

            //LOOP THRU ORDER ITEMS
            foreach($order_items as $item){

                //LOAD PRODUCT
                $_product = Mage::getModel('catalog/product')->load($item->getProductId());

                //SET SKU OF ITEM
                $sku = htmlentities($item->getSku());
                
                //SET QTY OF ITEM
                $qty = str_replace(".00", "", number_format($item->getQtyOrdered(), 2));
                
                if($item->getProductType() == "configurable" || $item->getProductType() == "bundle"){
                    continue;
                }
  
                //APPEND BUILD LINE ITEM
                $json_order['part_no'][] = $sku;
                $json_order['desc'][] = strtoupper($_product->getZirconProductName());

                if($item->getProductType() == "giftcard"){
                    $productOptions = $item->getProductOptions();
                    $sender = $productOptions['giftcard_sender_name'];
                    $recipient = $productOptions['giftcard_recipient_name'];
                    $json_order['person'][] = $recipient . "\\" . $sender;
                    $json_order['price'][] = $qty . '.00';
                             
                    $tempQty = number_format($item->getPrice(), 2);
                    $tempQty = str_replace(".00", "", $tempQty);
                    $json_order['qty'][] = $tempQty;
                    $extendedPrice = number_format($qty * $tempQty, 2);
                 
                    $json_order['ext_price'][] = $extendedPrice;
                }
                else{
                    
                    if($item->getParentItem() &&  $item->getParentItem()->product_type == 'configurable'){
                        $itemPrice = $item->getParentItem()->getPrice();
                    }else{
                        $itemPrice = $item->getPrice();
                    }
                    $json_order['price'][] = $itemPrice;
                    $json_order['ext_price'][] = $itemPrice;
                    $json_order['qty'][] = $qty;
                    $json_order['person'][] = "";
                }
            }
            
            //CHECK FOR PROMO/COUPON CODE
            $sessionid = ($order->getCouponCode()) ? $order->getCouponCode(): 'consumerweb';

            //CHECK IF PROMO/COUPON CODE HAS A FIXED SALESRULE	
            if($order->getCouponCode()){
                $coupon = Mage::getModel('salesrule/coupon');
                $coupon->load($order->getCouponCode(), 'code');
                $couponCode = $coupon->getCode();
                $couponName = $order->getCouponRuleName();
                
                $rule = Mage::getModel('salesrule/rule')->load($coupon->getRuleId());
                $actionType = $rule->getSimpleAction();
                
                //This is for any coupon that has a fixed $ amount discount and needs to be prefixed with a '99'
                $price = "0";
                if(($actionType == "cart_fixed" || $actionType == "by_fixed") && $rule->getSimpleFreeShipping() == 0){
                    $couponCode = "99" . $couponCode;
                    $price = "-" . number_format($rule->getDiscountAmount(), 2);
                }

                //APPEND COUPON LINE ITEM
                $json_order['part_no'][] = $couponCode; 
                $json_order['desc'][] = $couponName; 
                $json_order['qty'][] = 1;
                $json_order['price'][] = $price;
                $json_order['ext_price'][] = $price;
            }

            //LOAD CURRENT catalog source key, KE4WX, PW14, NW14, if they typed it in quick-order, use that, otherwise use default
            $catalog_source_key = $order->getCatalogSourceCode(); //use source key from the quick-add form
            if (empty($catalog_source_key)) { 
                $helper = Mage::helper('zirconprocessing');
                $catalog_source_key = $helper->getCatalogSourceKey($order->getStoreId()); //use default
            }
            $json_order['catalog'] = strtoupper(trim($catalog_source_key)); //set in the admin which relates to the site/company
            $json_order['order_date'] = date('m/d/y');
            $json_order['source_key'] = $catalog_source_key;
            $json_order['email'] = strtoupper($order->getCustomerEmail());

            //GET OrderID from Zircon, otherwise use MagentoOrderId
            $order_id_response = $this->Order_GetId();
            $orderno = $order_id_response['OrderId'];
            if (is_numeric($orderno)) {
                $json_order['orderno'] = $orderno;
            } else {
                $json_order['orderno'] = $order->getRealOrderId();
            }
			
            //LOAD ORDER SHIPPING METHOD CARRIERCODE
            $json_order['ship_method'] = str_replace("zirconshipping_", "" ,$order->getShippingMethod());//the ship methods they are in Zircon: PD,UPS2DA, UPS1DA, UPS1DP, UPSGND, FREE, 1ST, BULK 
            $json_order['order_amount'] = number_format($order->getGrandTotal(), 2, '.', '');
            $json_order['postage'] = number_format($order->getShippingAmount(), 2, '.', '');
            $json_order['merch'] = number_format($order->getBaseSubtotal(), 2, '.', ''); //shouldnt include coupons/promos
            $json_order['tax'] = number_format($order->getTaxAmount(), 2, '.', '');

            //CHECK FOR GIFT CARDS and REWARDS POINTS USED
            $giftcards = unserialize($order->getGiftCards());
            $gcAmtUsed = $order->getBaseGiftCardsAmount();
            $gcPreOrderBalance = $gcAmtUsed;
            $rewardsAmt = $order->getRewardCurrencyAmount();
			
            //IF GIFT CARD IS USED INCLUDE GIFT 
            if(is_array($giftcards) && $gcAmtUsed > 0){
                $giftCardAcct = Mage::getModel('enterprise_giftcardaccount/giftcardaccount')->loadByCode($giftcards[0]['c']);
                
                if($order->getGrandTotal() == '0.00'){
                    $gcPreOrderBalance = $gcPreOrderBalance + $giftCardAcct->getBalance();
                }
                
                $json_order['giftcert'] .= $giftcards[0]['c'] . '|' . number_format($gcPreOrderBalance, 2);
                $json_order['payment1'] = number_format($gcPreOrderBalance,2,'',''); //1199, no decimal, no commas
                $json_order['F28'] = 'GC'; //gift certificate, otherwise RW = rewards points
                $json_order['payment2'] = number_format($order->getGrandTotal(),2,'',''); //paid on card
                
                if($json_order['payment2'] == '0.00'){
                    $json_order['exp_date'] = "";
                    $json_order['payment2']  = "";
                }
                
                $json_order['order_amount'] = $json_order['merch'] + $json_order['postage'];
            } else if($rewardsAmt != "0.0000") {				
                $json_order['payment1'] = number_format($rewardsAmt,2,'',''); //11//99, no decimal, no commas
                $json_order['F28'] = 'RW'; //RW = rewards points
                $balance = $order->getGrandTotal() - $rewardsAmt;
                $json_order['payment2'] = number_format($balance,2,'',''); //paid on card 				

            } else {
                $mage_cctype = $payment->getCcType();
                $json_order['F28'] = $this->formatMagetoZirconCreditCartType($mage_cctype); // must be: VS, MC, DS, AX, CC 
                $json_order['payment1'] = number_format($order->getGrandTotal(),2,'',''); //1199, no decimal, no commas
                $json_order['payment2'] = '';
            }	
            
            $json_order['mage_order_no'] = $order->getIncrementId();
            $json_order['global'] .= 'Order Number:' . $order->getIncrementId();
            
            if($order->getOnestepcheckoutCustomercomment() != ""){
               $json_order['global'] .= ' Comment:' . $order->getOnestepcheckoutCustomercomment(); 
            }

            $data = json_encode($json_order);
            return $data;
        }
    }
	
    /**
     *  international addresses have 
     *      empty state, 
     *      address2 = address1 + address2, 
     *      address1 = town, province, country, zip 
     *      city = country
     *  param mage address object $order->getBillingAddress();
     *  return array (address1, address2, city, state, zip) no country field
     */
    private function formatMageToZirconAddress($objAddress) {       
        $address1 	= strtoupper($objAddress->getStreet1());
        $address2 	= strtoupper($objAddress->getStreet2());
        
        //ensure that no address gets sento Zircon where the 2nd address is the same as the 1st
        if($address1 == $address2){
            $address2 = "";
        }
        
        $address2 	= strtoupper(trim($address2 . ' '  . $objAddress->getStreet3()));
        $city		= strtoupper($objAddress->getCity());
        $state		= strtoupper($objAddress->getRegionCode());
        $zip		= strtoupper($objAddress->getPostcode());
        $country	= strtoupper($objAddress->getCountryId());
        
        $countryObj = new Mage_Directory_Model_Country();
        $countryName = strtoupper($countryObj->loadByCode($country)->getName());
        
        if(is_numeric($state)){
            $stateObj = new Mage_Directory_Model_Region();
            $state = strtoupper($stateObj->loadByCode($state, $country)->getName());
        }

        //overwrite specific countries
        switch ($country) {
            case 'CORSICA':
            case 'MAYOTTE':
            case 'MONACO':
                $countryName = 'FRANCE';
                break;
            case "CODE D'IVOIRE":
                $countryName = "IVORY COAST";
                break;
            case "CURACAO":
                $countryName = "NTHRLNDS ANTILLES";
                break;
            case "ENGLAND":
               $countryName = "GREAT BRITAIN";
                break;
        }
        //format international and canada addresses
        if ($country != 'US' && $country != 'CA') {
            $address1 = implode(', ', array($city, $zip, $state));
            $address2 = strtoupper($objAddress->getStreet1()) . " " . $address2;
            $state = '';
            $zip = '';
            $city = $countryName;
        }
        
        if($country == 'CA'){
            $address1 = implode(', ', array($city, $state));
            $address2 = strtoupper($objAddress->getStreet1()) . " " . $address2;
            $zip = str_replace(" ", "", $zip);
            $city = $countryName;
        }
        
        //return the array without the country
        $arr_address_info = array($address1, $address2, $city, $state, $zip, $countryName);
        return $arr_address_info;
    }
    
	
    /**
     *  Lookup the zircon credit card code from the magento card type code
     *  @param mage ccard type string
     *  @return zircon ccard type string
     */
    public function formatMagetoZirconCreditCartType($ccard_type) {
        $ccard_type_translation = array( //convert from magento to zircon
                'VI' => 'VS',
                'AE' => 'AX',
                'DI' => 'DS',
                'MC' => 'MC'
        );
        $zircon_credit_card_code = $ccard_type_translation[$ccard_type];
        return $zircon_credit_card_code;
    }
	
    /**
     *  Before sending the catalog mailing request, lookup the customer by address
     *  Then send the request to the REQ file in rbkeep
     *  @params is an array containing 
     */
    public function requestCatalog($customerInfo) {
        $storeId = $customerInfo['storeId'];

        $helper = Mage::helper('zirconprocessing');
        $firstName = trim($customerInfo['firstName']);
        $lastName = trim($customerInfo['lastName']);
        $fullname = strtoupper($firstName . '/' . $lastName);
        $address1 = $customerInfo['address1'];
        $address2 = $customerInfo['address2'];
        $city     = $customerInfo['city'];
        $state    = $customerInfo['state'];
        $zip      = $customerInfo['zip'];
        $phone    = $customerInfo['phone'];
        $email    = $customerInfo['email'];

        $address_json = json_encode(array(
                "fullname" => $fullname,
                "address1" => $address1,
                "state"    => $state,
                "zip"      => $zip
        ));
        $response = $this->Catalog_GetCustomerByAddress($address_json);
        $zircon_customer_id = $response['custid'];

        //create new zircon customer if they didn't already exist
        if(empty($zircon_customer_id)) {
            $response = $this->Catalog_SetCustomerByAddress($address_json);
            $zircon_customer_id = $response['custid'];
        }
        // send to Requests file
        if (!empty($zircon_customer_id)) {

            $request_json[] = json_encode(array(
                'cm_id' 	=> strtoupper($zircon_customer_id),
                'fullname' 	=> strtoupper($fullname),
                'address1'	=> strtoupper($address1),
                'address2' 	=> strtoupper($address2),
                'city'    	=> strtoupper($city),
                'state' 	=> strtoupper($state),
                'zip'           => strtoupper($zip),
                'phone'         => strtoupper($phone),
                'source_key'    => $helper->getCatalogRequestSourceKey($storeId),//LOAD CURRENT catalog source key, KE4WX, PW14, NW14
                'unused'	=> '',
                'sex'           => 'F', //yep
                'cat_type' 	=> $helper->getCatalogType($storeId),//LOAD CURRENT catalog type, KQ=1, NA=5, PW=6
                'company'	=> $helper->getCompanyCode($storeId),//LOAD CURRENT company code, KQ=01, NA=02 , PW=03
                'email'         => strtoupper($email)
            ));
            $request_json[] = $storeId;
            $this->Catalog_CreateRequest($request_json);
        }

        //Submit data to Exact Target
        //Set SubscriberKey to email address
        $postFieldsArray['SubscriberKey'] = $email;
        //ET 2.0 uses "EmailAddress" as the email field name
        $postFieldsArray['EmailAddress'] = $email;
        $postFieldsArray['first_name'] = $firstName;
        $postFieldsArray['last_name'] = $lastName;
        $postFieldsArray['Original Source'] = "Magento";

        //Needed for Smart Capture Forms.
        $postFieldsArray['__contextName'] = "FormPost";
        $postFieldsArray['__executionContext'] = "Post";

        //Smart Capture URL that's defined in the Configuration under the Admin of Magento.
        $url = Mage::getStoreConfig('thirdparty/exacttarget/et2_url', $storeId);
        $postFields = http_build_query($postFieldsArray);

        //Setup cURL to submit the post to ET.
        $ch=curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

        curl_exec($ch);
	}
		
}
