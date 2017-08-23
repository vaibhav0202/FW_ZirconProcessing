<?php
/**
 * @category    FW
 * @package     FW_ZirconProcessing
 * @copyright   Copyright (c) 2014 F+W Media, Inc. (http://www.fwmedia.com)
 */    

class FW_ZirconProcessing_Model_CouponUpdate  extends Mage_Core_Model_Abstract
{
    protected $_promoFiles;
    protected $_promosProcessed;
    protected $_promoDir;
    protected $_promoExecutedDir;
    protected $_logFile;
    protected $_companyWebsiteNames;
    protected $_companyWebsiteIds;
    protected $_companyLoyaltyClubNames;
    protected $_companyNonLoyaltyGroupIds;
    protected $_companyLoyaltyGroupIds;
    protected $_errorCount;
    protected $_successCount;
    
    public function __construct()
    {
        $this->logFile = 'Zircon_Coupon_Import.log';
    	$this->errorFile =  'Zircon_Coupon_Import_Error.log';
        $this->errorCount = 0;
        $this->successCount = 0;
        
        //Initialize import directory
        $this->promoDir = Mage::getBaseDir() . '/var/importexport/zircon_inventory/';
        $this->promoExecutedDir = $this->promoDir.'executed';

        $this->companyWebsiteNames = array
        (
            "01" => "KeepSakeQuilting.com",
            "02" => "KeepSakeNeedleArts.com",
            "03" => "PatternWorks.com",
            "05" => "CraftOfQuilting.com"
        );
        
        $this->companyLoyaltyClubNames = array
        (
            "01" => "KQ Loyalty Club",
            "02" => "NA Loyalty Club",
            "03" => "PW Loyalty Club",
        );
        
        //Load Website Ids
        $companyWebsiteIdsTemp = array();
        foreach ($this->companyWebsiteNames as $company=>$companyWebsiteName){
            $website = Mage::getModel('core/website')->load($companyWebsiteName, 'name');
            $companyWebsiteIdsTemp[$company] = $website->getId();
        }
        $this->companyWebsiteIds = $companyWebsiteIdsTemp;
        
        //Load Loyalty Club Group Ids
        $companyLoyaltyGroupIdsTemp = array();
        $companyNonLoyaltyGroupIdsTemp = array();
        $groupCollection = Mage::getModel('customer/group')->getCollection();
        
        foreach ($this->companyLoyaltyClubNames as $company=>$companyLoyaltyClubName){
            foreach ($groupCollection as $customerGroup){
                if($customerGroup->getCustomerGroupCode() == $companyLoyaltyClubName){
                    $companyLoyaltyGroupIdsTemp[$company] = $customerGroup->getId();
                }
            }   
        }
        
        //assign non-loyalty group ids
        foreach ($groupCollection as $customerGroup){
            if(!in_array($customerGroup->getId(),$companyLoyaltyGroupIdsTemp )){
                $companyNonLoyaltyGroupIdsTemp[] = $customerGroup->getId();
            }
        }
        
        $this->companyNonLoyaltyGroupIds = $companyNonLoyaltyGroupIdsTemp;
        $this->companyLoyaltyGroupIds = $companyLoyaltyGroupIdsTemp;
    }
	
    public function import () {    
        $this->getZirconRecords();
        $this->initPromoFiles();
        $this->updatePromos();
        $this->deletePromos();
    }
  
    //Update and Create new Promos
    private function updatePromos(){
        $successCount = 0;
        $errorCount = 0;
        $promoList = array();
        
        //only process coupons from the stores that have been set in the admin
        $helper = Mage::helper('zirconprocessing');
        $storesToProcessConfig = $helper->getStoresToProcess();
        $storesToProcess = explode(",",$storesToProcessConfig);
        
        foreach ($this->promoFiles AS $promoFile) {  
            if (($handle = fopen($promoFile, "r")) !== FALSE){
                $json = json_decode(file_get_contents($promoFile), true);
                foreach($json['Coupon'] as $zirconPromo){
                    $promoCode = trim($zirconPromo["_id"]);
                    $coupon = Mage::getModel('salesrule/coupon')->load($promoCode, 'code');
                    $value = $zirconPromo['value'];
                    $percent = $zirconPromo['percent']; 
                    $company = $zirconPromo['co'];
                    
                    $processThisRecord = false;
                    foreach ($storesToProcess as $storeToProcess){
                        if($storeToProcess == $company){
                            $processThisRecord = true;
                        }
                    }
                    if(!$processThisRecord){
                        continue;
                    }
                    if($value || $percent){
                        $existingRule = new Mage_SalesRule_Model_Rule();
                        if($coupon->getId()){
                            $existingRule = Mage::getModel('salesrule/rule')->load($coupon->getRuleId());
                        }

                        try{
                            $rule = $this->initSalesRule($existingRule, $zirconPromo);
                            $rule->save();
                            $promoList[] = $promoCode;
                            $this->successCount++;
                            Mage::log("Processed PromoCode: ".$promoCode."\r\n",null, $this->logFile);                                    
                        }
                        catch(Exception $e){
                            $this->errorCount++;
                            Mage::log("processPromos Error: ".$e->getMessage(),null, $this->errorFile);
                        } 
                        unset($existingRule); 
                    }
                }
                $this->promosProcessed = $promoList;
            }
            fclose($handle);
            
            $fileNameExecuted = str_replace('.promo', '.executed.' . date('Ymd-His') . '.promo', $promoFile);
	    $fileNameExecuted = str_replace('.PROMO', '.executed.' . date('Ymd-His') . '.PROMO', $promoFile);
            $fileNameExecuted = str_replace($this->promoDir, $this->promoExecutedDir, $fileNameExecuted);
            rename($promoFile, $fileNameExecuted);  
        }
        
        if($this->errorCount > 0)
            $this->sendEmail('Zircon Coupon Code Update Error', 'There was an error(s) in the Zircon Coupon Code Update process', Mage::getBaseDir().'/var/log/' . $this->errorFile, TRUE);
    	
        if($this->successCount > 0)
            $this->sendEmail('Zircon Coupon Code Update', 'Metrics for the Zircon Coupon Code Update process', Mage::getBaseDir().'/var/log/' . $this->logFile, FALSE);

    }
    
    //Send notification email
    public function sendEmail($subject, $body, $fileUrl, $error) {
        try {
            $helper = Mage::helper('zirconprocessing');
            
            if($error){
                $to = $helper->getErrorEmailNotice() . ',' . $helper->getEmailNotice();
            }
            else{
                $to = $helper->getEmailNotice();
            }
            
            $mail = new Zend_Mail('utf-8');                                     // Create the Zend_Mail object
            $mail->addTo(explode(',', $to));                                    // Add recipients
            $mail->setSubject($subject)->setBodyText($body);                    // Add subject and body
            $mail->setFrom("noreply@fwmedia.com");
           
            if($fileUrl != ''){
                $attachment = $mail->createAttachment(file_get_contents($fileUrl));  // Add zip to email as attachment
                $attachment->filename = array_pop(explode('/', $fileUrl));           // Name attachment the same as zip name 
            }

            $mail->send();                                                      // Send the email
        } catch (Exception $e) {                                                // Catch errors
            $this->log($e, 'Error', Zend_Log::ERR);                             // Log errors
        }
        
        return $this;       // Return this model, so chaining is possible $this->method()->method();
    }
    
    //Remove Promos that are no longer in Zircon
    private function deletePromos(){
        $rules = Mage::getResourceModel('salesrule/rule_collection')->load();
        foreach ($rules as $rule) {
            if($rule->getCode() != ""){
                if(!in_array($rule->getCode(),$this->promosProcessed))
                {
                    try{

                        $rule->delete();
                        $this->successCount++;
                        Mage::log("Deleted PromoCode: ".$rule->getCouponCode()."\r\n",null, $this->logFile);                                    
                    }
                    catch(Exception $e){
                        $this->errorCount++;
                        Mage::log("deletePromos Error: ".$e->getMessage(),null, $this->errorFile);
                    }
                }
            }        
        }   
    }

    //Initialize the Magento Product record - not saved though
    private function initSalesRule($existingRule, $zirconPromo){
        $actionType = '';
        $initRule = clone $existingRule;
        
        if($existingRule->getId()){
            //Clear Out current conditions and actions
            $initRule->setActionsSerialized('');
            $initRule->setConditionsSerialized('');
            $initRule->save();
        }

       //by_fixed, cart_fixed, buy_x_get_y)
        $company = $zirconPromo['co'];
        $name = $zirconPromo['desc'];
        $promoCode = $zirconPromo['_id'];
        $ptype = $zirconPromo['ptype'];
        $promoType = $zirconPromo['promo'];
        $percent = $zirconPromo['percent'];
        $value = $zirconPromo['value'];
        $value = substr_replace($value, ".", strlen($value) - 2, 0);
        $applyToShipping = '0';
        
        $minTotal = $zirconPromo['minval'];
        
        if($minTotal){
            $minTotal = substr_replace($minTotal, ".", strlen($minTotal) - 2, 0);
        }
        
        $freeShipping = '0';
        
        //Free Shipping
        if(($promoType == 'A' || $promoType == 'S')){
            $applyToShipping = '0'; 
            $freeShipping = '1';
            $percent = 0;
        }
    
        if($percent <> '' ||$promoType == 'B'  ){
            $actionType = 'by_percent';
            $discount = $percent;
        }
        else{
            $actionType = 'cart_fixed';
            $discount = $value;
        }

        $customer_groups = array();
        $customer_groups = $this->companyNonLoyaltyGroupIds;
        
        if($this->companyLoyaltyGroupIds[$company]){
            $customer_groups[] = $this->companyLoyaltyGroupIds[$company];
        }

        $initRule->setName($name)
               ->setCouponType(2)
               ->setCouponCode($promoCode)
               ->setUsesPerCustomer(1)
               ->setCustomerGroupIds($customer_groups) //an array of customer group pids
               ->setIsActive(1)
               ->setStopRulesProcessing(0)
               ->setIsAdvanced(1)
               ->setProductIds('')
               ->setSortOrder(0)
               ->setSimpleAction($actionType)
               ->setDiscountAmount($discount)
               ->setDiscountQty(null)
               ->setDiscountStep(0)
               ->setSimpleFreeShipping($freeShipping)
               ->setApplyToShipping($applyToShipping)
               ->setIsRss(0)
               ->setWebsiteIds(array($this->companyWebsiteIds[$company]));

         if($minTotal != '' && $minTotal > 0){
            $conditionMinTotal = Mage::getModel('salesrule/rule_condition_address')
                                                ->setType('salesrule/rule_condition_address')
                                                ->setAttribute('base_subtotal')
                                                ->setOperator('>=')
                                                ->setValue($minTotal); 
             $initRule->getConditions()->addCondition($conditionMinTotal);
         }
         
         //Specific FREE Product
         if($promoType == 'B'){
             $prodSkus = '';
             foreach($zirconPromo['item'] as $product){
                $prodSkus = $prodSkus . $product . ",";
             }
             
             if($prodSkus != ''){
                $prodSkus = substr_replace($prodSkus, '', strrpos($prodSkus, ","), 1);
                $conditionProduct = Mage::getModel('salesrule/rule_condition_product')
                                                ->setType('salesrule/rule_condition_product')
                                                ->setAttribute('sku')
                                                ->setOperator('()')
                                                ->setValue($prodSkus);
                
                $conditionSelect = Mage::getModel('salesrule/rule_condition_product_subselect')
                                                ->setType('salesrule/rule_condition_product_subselect')
                                                ->setAttribute('qty')
                                                ->setOperator('>=')
                                                ->setValue(1)
                                                ->setConditions(array($conditionProduct));
                
                $initRule->getConditions()->addCondition($conditionSelect); 
                
                $actionProductType = Mage::getModel('salesrule/rule_condition_product')
                                               ->setType('salesrule/rule_condition_product')
                                               ->setAttribute('sku')
                                               ->setOperator('()')
                                               ->setValue($prodSkus);
                $initRule->getActions()->addCondition($actionProductType); 
                $initRule->setDiscountAmount(100);
            } 
         }

         //Products with specific product types
         if(!$promoType || $promoType == 'T'){
            $productTypes = explode('ý',$zirconPromo['ptype']);

            if(strpos(strtoupper($zirconPromo['ptype']), "ANY") === FALSE){
                $initRule->getActions()->setAggregator('any');
                
                if($promoType == 'T'){
                    $initRule->setSimpleAction('by_percent');
                }
                else{
                    $initRule->setSimpleAction('by_fixed'); 
                }

                foreach($productTypes as $productType){
                    if($productType != ""){
                        $this->addAttributeValue('product_type', $productType);
                        $actionProductType = Mage::getModel('salesrule/rule_condition_product')
                                   ->setType('salesrule/rule_condition_product')
                                   ->setAttribute('product_type')
                                   ->setOperator('==')
                                   ->setValue($this->attributeValueExists('product_type', $productType));

                        $initRule->getActions()->addCondition($actionProductType);  
                    }
                }  
            } 
            
            //Loyalty Club Coupon
            if(!$promoType && $this->companyLoyaltyGroupIds[$company]){
                $initRule->setCustomerGroupIds($this->companyLoyaltyGroupIds[$company]);
            }
         }
         
        // for all rules that do not have specific action conditions tied to a specific sku or product type, then add an action condition that excludes the product types 10,15,32,86
        if($promoType == 'O' || $promoType == 'C'){
            $productTypes = array ('10','15','32','86');
            $initRule->getActions()->setAggregator('all');
            foreach($productTypes as $productType){
                    $this->addAttributeValue('product_type', $productType);
                    $actionProductType = Mage::getModel('salesrule/rule_condition_product')
                               ->setType('salesrule/rule_condition_product')
                               ->setAttribute('product_type')
                               ->setOperator('!=')
                               ->setValue($this->attributeValueExists('product_type', $productType));

                    $initRule->getActions()->addCondition($actionProductType);  
            }    
        }
            
        return 	$initRule;		
    }

    //Retrieve Zircon Records
    private function getZirconRecords(){    
        $helper = Mage::helper('zirconprocessing');
        $api_endpoint_url = $helper->getCouponEndPoint();
        $curl = curl_init($api_endpoint_url);
       
	curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST, 'rsa_aes_256_sha,rsa_aes_128_sha'); 
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 0);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
        $curl_response = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        //write the CURL response to file 
        $local_file = $this->promoDir . '/PROMO_IMPORT_' .  date("Ymd_Hi") . ".PROMO";
        $fp = fopen($local_file, 'w');
        fwrite($fp, $curl_response);
        fclose($fp);   
        
        // Did we get a 200 or an error?
        if($http_status != 200)
        {
            Mage::log('Connection to api import server failed:' . $http_status . ' ' . $curl_response,null,$this->errorFile);
            $to = $helper->getErrorEmailNotice();
            $subject = "Zircon Coupon Update - Connection to API import server failed";
            mail($to, $subject, '');
            throw new Exception('Connection to api import server failed:' . $http_status . ' ' . $curl_response);
            return;
        }
    }
    
    private function initPromoFiles(){
        //Get the files & order by oldest first
        $files = array();
        foreach (glob($this->promoDir.'/*.{promo,PROMO}', GLOB_BRACE) AS $file){    
            $slashPosition = strripos($file,'/');
            
            if($slashPosition == false) $slashPosition = strripos($file,'\\');
             $files[] = $file;
        }
        $this->promoFiles = $files; 
    }
    
    //Check to see if an option value exists for an attribute
    private function attributeValueExists($arg_attribute, $arg_value){
        $attribute_model        = Mage::getModel('eav/entity_attribute');
        $attribute_options_model= Mage::getModel('eav/entity_attribute_source_table') ;

        $attribute_code         = $attribute_model->getIdByCode('catalog_product', $arg_attribute);
        $attribute              = $attribute_model->load($attribute_code);
        
        $attribute_table        = $attribute_options_model->setAttribute($attribute);
        $options                = $attribute_options_model->getAllOptions(false);
        
        foreach($options as $option){	
            if ($option['label'] == $arg_value)return $option['value'];
        }

        return false;
    }
    
   //Create an option value for an attribute
    private function addAttributeValue($arg_attribute, $arg_value){
   
        $attribute_model        = Mage::getModel('eav/entity_attribute');
        $attribute_options_model= Mage::getModel('eav/entity_attribute_source_table') ;

        $attribute_code         = $attribute_model->getIdByCode('catalog_product', $arg_attribute);
        $attribute              = $attribute_model->load($attribute_code);
        
        $attribute_table        = $attribute_options_model->setAttribute($attribute);
        $options                = $attribute_options_model->getAllOptions(false);
        
        if(!$this->attributeValueExists($arg_attribute, $arg_value)){
            $value['option'] = array($arg_value,$arg_value);
            $result = array('value' => $value);
            $attribute->setData('option',$result);

            try {
                $attribute->save();
            } 
            catch (Exception $e) {
                Mage::log("addAttributeValue Error: ".$e->getMessage(),null, $this->logFile);
            }
        }
        
        foreach($options as $option){
            if ($option['label'] == $arg_value)return $option['value'];
        }
        return true;
    }
}

