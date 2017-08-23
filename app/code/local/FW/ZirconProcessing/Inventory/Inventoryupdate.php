<?php
/**
 * @category    FW
 * @package     FW_ZirconProcessing_Inventory
 * @copyright   Copyright (c) 2014 F+W Media, Inc. (http://www.fwmedia.com)
 */
 
class FW_ZirconProcessing_Model_Inventoryupdate extends Mage_Core_Model_Abstract
{
    protected $_taxClassIds;
    protected $_logFile;
    protected $_errorFile;
    
    /**
     * Updates inventory, pricing from file(s).
     */
    public function import(){
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        $taxClassIds = array();
        $inventoryFiles = array();
        $startTime = time();
        $successCount = 0;
        $errorCount = 0;

    	$this->_logFile = 'Zircon_Inventory_Update.log';
    	$this->_errorFile =  'Zircon_Inventory_Error.log';

        //create folders
        $baseDir = Mage::getBaseDir() . '/var/importexport';
        if (!file_exists($baseDir)) mkdir($baseDir, 0777);

        $inventoryDir = $baseDir . '/zircon_inventory';
        if (!file_exists($inventoryDir)) mkdir($inventoryDir, 0777);

        $inventoryExecutedDir = $inventoryDir.'/executed';
        if (!file_exists($inventoryExecutedDir)) mkdir($inventoryExecutedDir, 0777);
        
        //DOWNLOAD Inventory Files from ZIRCON server
        try {
            $this->getInventoryUpdateFiles($inventoryDir);
        } 
        catch (Exception $e) {
            Mage::log('Product Retrieval Error: '.$e,null,$this->_errorFile);
            $errorCount++;
        }

        $this->initTaxClasses();
        $this->initInventoryFiles($inventoryDir, $inventoryFiles);

        /* ZIRCON JSON Data
                avail2
         *      desc
                disc_flg
                dropship
                expected.date
                ID
                price
                prod.type
                prodtyp2
         *      retail_price
        */
    	
        //Process each found file
        foreach ($inventoryFiles AS $inventoryFile) {   
            
            Mage::log("Processing File:".$inventoryFile."\r\n",null,$this->_logFile);
            if (($handle = fopen($inventoryFile, "r")) !== FALSE){
                $json = json_decode(file_get_contents($inventoryFile), true);
                
                foreach($json["Parts_web"] as $zirconProduct){
                    $sku = trim($zirconProduct["_id"]);
                    $name = trim($zirconProduct["desc"]);
                    $ptype2 =  trim($zirconProduct["prodtyp2"]);
                    
                    try{
                        if($sku != "" && $ptype2 != ""){ 
                            $inventoryProduct = Mage::getModel('catalog/product')->loadByAttribute('sku',$sku);
    
                            if(!$inventoryProduct){
                                if($name == ""){
                                    continue;
                                }
                                $inventoryProduct = $this->createNewProduct($zirconProduct, $taxClassIds);
                                $newProduct = true;
                            }
                            else{
                                $newProduct = false; 
                            }

                            //Product Update
                            $this->updateProduct($inventoryProduct, $zirconProduct);
                            $inventoryProduct->save();

                            //Stock Update
                            $this->updateStockItem($inventoryProduct,$zirconProduct);

                            //Metrics
                            $successCount++;
                            Mage::log("Updated sku:".$inventoryProduct->getsku()."\r\n",null,$this->_logFile);
                        }
                    }
                    catch (Exception $e){
                        Mage::log($sku.":".$e->getMessage(),null,$this->_errorFile);
                        $errorCount++;
                        if($errorCount > 0){
                            $this->sendEmail('Zircon Inventory Update Error', 'There was an error(s) in the Zircon Inventory Update process', Mage::getBaseDir().'/var/log/' . $this->_errorFile, TRUE);
                        }

                        if($successCount > 0){
                            $this->sendEmail('Zircon Inventory Update', 'Metrics for the Zircon Inventory Update process', Mage::getBaseDir().'/var/log/' . $this->_logFile, FALSE);
                        }
          
                        fclose($handle);
                        return;
                    }
                    unset($inventoryProduct,$sku);
                }
            }
            
            fclose($handle);
            unset($handle);
            $fileNameExecuted = str_replace('.inv', '.executed.' . date('Ymd-His') . '.inv', $inventoryFile);
            $fileNameExecuted = str_replace('.INV', '.executed.' . date('Ymd-His') . '.INV', $inventoryFile);
            $fileNameExecuted = str_replace($inventoryDir, $inventoryExecutedDir, $fileNameExecuted);
            rename($inventoryFile, $fileNameExecuted); 
        }
        
        Mage::log("************************ Inventory Completed ************************\r\n",null,$this->_logFile);
        Mage::log("Time Elapsed :".round(abs(time() - $startTime) /60,2)." minutes\r\n",null,$this->_logFile);
        Mage::log("Successes : ".$successCount."\r\n",null,$this->_logFile);
        Mage::log("Errors : ".$errorCount."\r\n",null,$this->_logFile);
        Mage::log("*********************************************************************\r\n",null,$this->_logFile);
        
        if($errorCount > 0)
            $this->sendEmail('Zircon Inventory Update Error', 'There was an error(s) in the Zircon Inventory Update process', Mage::getBaseDir().'/var/log/' . $this->_errorFile, TRUE);
    	
    	if($successCount > 0)
            $this->sendEmail('Zircon Inventory Update', 'Metrics for the Zircon Inventory Update process', Mage::getBaseDir().'/var/log/' . $this->_logFile, FALSE);

        if(count($inventoryFiles) == 0)
            $this->sendEmail('Zircon Inventory Update - NO FILES FOUND', 'There were no inventory files found to process', '', FALSE);
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
    
    //Update Mage Stock Item 
    private function updateStockItem($inventoryProduct,$zirconProduct){
        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($inventoryProduct->getId());
        
        //If no stock item record exists
        if($stockItem->getId() == "") $stockItem = $this->createStockItem($inventoryProduct);
        $qty = trim($zirconProduct["avail2"]);
        $discontinued = trim($zirconProduct["disc_flg"]);
        $backOrderable = trim($zirconProduct["boflg"]);
        $dropShip = trim($zirconProduct["dropship"]);
        
        //if the product is not discountinued, no stock
        //$backorder = 0 ==> no backorders
        //$backorder = 1 ==> allow with qty < 0
        //$backorder = 2 ==> allow with qty < 0 and notify customer
        if($discontinued == '' 
                || $qty > 0 && $discontinued == ''
                || ($inventoryProduct->getPreOrder() == 1 && $qty <= 0) 
                ){
            $backorder = 2;
            $isInStock = 1;
        }
        elseif($discontinued != '' && $qty <= 0){
            $backorder = 0;
            $isInStock = 0;
        }else if ($discontinued != '' && $qty > 0){
            $backorder = 0;
            $isInStock = 1;
        }
        
        if($inventoryProduct->getTypeId() == 'downloadable' || $dropShip == "1" || $backOrderable == '1'){
            $manageStock = 0;
        }
        else{
            $manageStock = 1;
        }

        $stockItem->setData('manage_stock', $manageStock);
        $stockItem->setData('is_in_stock', $isInStock);
        $stockItem->setData('stock_id', 1);
        $stockItem->setData('qty', $qty);
        $stockItem->setData('backorders', $backorder);
        $stockItem->save(); 
    }

    //Update existing Product
    private function updateProduct($inventoryProduct,$zirconProduct){
        $ptype2 =  trim($zirconProduct["prodtyp2"]);
        $expectedDate = trim($zirconProduct["expected_date"]);
        $prodType = trim($zirconProduct["prod_type"]);
        $cost = trim($zirconProduct["cog"]);
        $origRetail = trim($zirconProduct["orig_retail"]);
        $amazonOrigRetail = trim($zirconProduct["retailprice"]);
        $newpart = trim($zirconProduct["newpartflg"]);
        $additionalShipping = trim($zirconProduct["pah"]);

        $inventoryProduct->setCost($cost);
        $inventoryProduct->setAdditionalShipping($additionalShipping);
        $inventoryProduct->setZirconProductName(trim($zirconProduct["desc"]));
        
        //Pricing
        $price = $zirconProduct["price"];
        $specialPrice = '';

        
        //Product is on sale
        if($origRetail == "" || $origRetail == "0.00")
        {
            if($amazonOrigRetail != "" && $amazonOrigRetail != "0.00 " &&  $price < $amazonOrigRetail){
                $specialPrice = $price;
                $price = $amazonOrigRetail;
                
                if($inventoryProduct->getSoldByLength() == 1){
                    $price = $price / 8;
                }
            }
        }else if ($origRetail != "" && $origRetail != "0.00"){
            if($price < $origRetail){
                $specialPrice = $price;
                $price = $origRetail;
            }
        }   
        
        //Gift Cards should have no price in Magento
        if($ptype2 != "86"){
            $inventoryProduct->setPrice($price);
        } 
        $inventoryProduct->setSpecialPrice($specialPrice);
        if($specialPrice == ''){
            $inventoryProduct->setSpecialFromDate('');
        }

        if($expectedDate != '')$inventoryProduct->setWarehouseAvailDate($expectedDate); 
        
        //Product Type
        $product_type_val = $this->addAttributeValue('product_type', $ptype2);
        $inventoryProduct->setProductType($product_type_val);
        
        if($prodType ==  "00000" || strtoupper($prodType) == "0000S"){
            $inventoryProduct->setHasRuler(1);
        }
        else{
            $inventoryProduct->setHasRuler(0);
        }

        //Translate zircon preorder flat to Magento preorder attribute
        if($newpart == 'Y'){
            $inventoryProduct->setPreorder(1);
        }
        else{
            $inventoryProduct->setPreorder(0);
        }
    }
    
    //Create new Mage Product 
    private function createNewProduct($zirconProduct){
        
        $sku = $zirconProduct["_id"];
        $websiteIds[0] = Mage::getModel('core/website')->load('Main Website', 'name')->getId();
        $storeIds[0] = Mage::getModel('core/store')->load('Default Store View', 'name')->getId();
        
        $product = new Mage_Catalog_Model_Product();
        $product->setWebsiteIDs($websiteIds);
        $product->setStoreIDs($storeIds);
        
        if(trim($zirconProduct["prodtyp2"]) == "120"){
           $product->setTypeId('downloadable'); 
        }
        else{
            $product->setTypeId('simple');
        }
        
        //Attribute Set Id
        $attrSetName = "Default";
        $entityTypeId = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
        $attributeSetName   = $attrSetName;
        $attributeSetId     = Mage::getModel('eav/entity_attribute_set')
                ->getCollection()
                ->setEntityTypeFilter($entityTypeId)
                ->addFieldToFilter('attribute_set_name', $attributeSetName)
                ->getFirstItem()->getAttributeSetId();
        $product->setAttributeSetId($attributeSetId);
        
        $product->setSku($sku);
        $product->setName($zirconProduct["desc"]);
        $product->setVisibility(1);
        $product->setStatus(1);
        $product->setWeight(1);
        
        $product->setTaxClassId($this->taxClassIds["Taxable Goods"]);
        $product->save();
        
        Mage::log("Created Product: ".$product->getSku(),null, $this->_logFile);
    					
    	$this->createStockItem($product);
        return $product;
    }

    //Create new Mage Stock Item
    private function createStockItem($product){
        $stockItem = Mage::getModel('cataloginventory/stock_item');
        $stockItem->assignProduct($product);
        $stockItem->setData('stock_id', 1);
        $stockItem->setData('use_config_manage_stock', 0);
        $stockItem->setData('use_config_min_sale_qty', 0);
        $stockItem->setData('use_config_backorders', 0);
        $stockItem->save();
        
        Mage::log("Created Stock Item for product: ".$product->getSku()."\r\n",null, $this->_logFile);
        return $stockItem;
    }
 
    //Retrieve inventory file(s) from Zircon API Server
    private function getInventoryUpdateFiles($inventoryDir){ 
        $helper = Mage::helper('zirconprocessing');
        $storesToProcessConfig = $helper->getStoresToProcess();
        $storesToProcess = explode(",",$storesToProcessConfig);
        $api_endpoint_url = $helper->getProductEndPoint();
    
        foreach($storesToProcess as $company){
            $curl = curl_init($api_endpoint_url . "?select=COMPANY=". $company);
	    curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST, 'rsa_aes_256_sha,rsa_aes_128_sha');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_TIMEOUT, 120);
            $curl_response = curl_exec($curl);
            $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            $local_file = $inventoryDir . "/INVENTORY_" . $company . "_" . date("Ymd_Hi") . ".INV";
            $fp = fopen($local_file, 'w');
            fwrite($fp, $curl_response);
            fclose($fp);      

            // Did we get a 200 or an error?
            if($http_status != 200)
            {
                Mage::log('Connection to api import server failed:' . $http_status . ' ' . $curl_response,null,$this->_errorFile);
                 $to = $helper->getErrorEmailNotice();
                $subject = "Zircon Inventory Update - Connection to API import server failed";
                mail($to, $subject,'');
                throw new Exception('Connection to api import server failed:' . $http_status . ' ' . $curl_response);
                return;
            } 
        }
        
    }

    //Initialize global arrays used for product insert/update
    private function initTaxClasses(){
        //Pre-load tax class ids
        $tempTaxIds = array();
        $taxClasses = Mage::getModel('tax/class')->getCollection();
        foreach ($taxClasses as $taxClass){
            $tempTaxIds[$taxClass->getClassName()] = $taxClass->getId();
        }
         $this->taxClassIds = $tempTaxIds;
    }

    //Initialize global file array that will store the inventory files to process
    private function initInventoryFiles($inventoryDir, &$inventoryFiles){
        //Get the files & order by oldest first
        foreach (glob($inventoryDir.'/*.{inv,INV}', GLOB_BRACE) AS $file){    
            $slashPosition = strripos($file,'/');

            if($slashPosition == false) $slashPosition = strripos($file,'\\');

            $fileName = substr($file, $slashPosition + 1);
            $currentModified = substr($fileName, strlen($fileName) - 16);
            $currentModified = substr($currentModified, 0, (strlen($currentModified) - 4));
            $file_names[] = $file; 
            $fileDates[] = $currentModified;  
        }

        //Sort the date array by oldest first
        asort($fileDates);

        //Match file_names array to file_dates array
        $file_names_Array = array_keys($fileDates);
        foreach ($file_names_Array as $idx => $name) $name=$file_names[$name]; 
        $fileDates = array_merge($fileDates);

        //Loop through dates array
        $i = 0;
        foreach ($fileDates as $aFileDate){
            $date = (string) $fileDates[$i];
            $j = $file_names_Array[$i];
            $file = $file_names[$j];
            $i++;
            $inventoryFiles[$i] = $file;
        } 
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
                Mage::log("addAttributeValue Error: ".$e->getMessage(),null, $this->_logFile);
            }
        }
        
        foreach($options as $option){
            if ($option['label'] == $arg_value)return $option['value'];
        }
        return true;
    }
}



