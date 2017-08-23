<?php

/**
 * @category    FW
 * @package     FW_ZirconProcessing
 * @copyright   Copyright (c) 2014 F+W Media, Inc. (http://www.fwmedia.com)
 */
class FW_ZirconProcessing_Model_GiftCardUpdate extends Mage_Core_Model_Abstract
{
    protected $_giftCardFiles;
    protected $_giftCardDir;
    protected $_giftCardExecutedDir;
    protected $_logFile;
    protected $_errorFile;
    protected $_companyWebsiteNames;
    protected $_companyWebsiteIds;
    
  public function __construct(){
        $this->logFile = 'Zircon_GiftCard_Import.log';
    	$this->errorFile =  'Zircon_GiftCard_Import_Error.log';
        
        //Initialize import directory
        $this->giftCardDir = Mage::getBaseDir() . '/var/importexport/zircon_inventory/';
        $this->giftCardExecutedDir = $this->giftCardDir.'executed';

        $this->companyWebsiteNames = array
        (
            "01" => "KeepSakeQuilting.com",
            "02" => "KeepSakeNeedleArts.com",
            "03" => "PatternWorks.com",
            "05" => "CraftOfQuilting.com"
        );
        
        //Load Website Ids
        $companyWebsiteIdsTemp = array();
        foreach ($this->companyWebsiteNames as $company=>$companyWebsiteName){
            $website = Mage::getModel('core/website')->load($companyWebsiteName, 'name');
            $companyWebsiteIdsTemp[$company] = $website->getId();
        }
        $this->companyWebsiteIds = $companyWebsiteIdsTemp;
    }
    
    //Update and Create Gift Card Accounts
    public function import(){
        $successCount = 0;
        $errorCount = 0;
        $this->getZirconRecords();
        $this->initGiftCardFiles();
         //Process each found file
        foreach ($this->giftCardFiles AS $giftCardFile) {  
            if (($handle = fopen($giftCardFile, "r")) !== FALSE){
                $json = json_decode(file_get_contents($giftCardFile), true);
                foreach($json['Gc'] as $zirconGiftCard){
                    $code = $zirconGiftCard['_id'];
                    
                    if($zirconGiftCard['balance'] < 0){
                      $balance = 0;  
                    }
                    else{
                       $balance = substr_replace($zirconGiftCard['balance'], ".", strlen($zirconGiftCard['balance']) - 2, 0); 
                    }
                    
                    $company = $zirconGiftCard['company'];
                    $purchasedAmt = $zirconGiftCard['purchase_amount'];
                    
                    try{
                        $giftCardAcct = Mage::getModel('enterprise_giftcardaccount/giftcardaccount')->loadByCode($code);
                        $giftCardCodePool = new Enterprise_GiftCardAccount_Model_Resource_Pool();

                         if(!$giftCardCodePool->exists($code)){
                             $giftCardCode = new Enterprise_GiftCardAccount_Model_Pool();
                             $giftCardCode->getResource()->saveCode($code);
                         }
                         
                        $newAcct = FALSE;

                        if(!$giftCardAcct->getId()){
                            $giftCardAcct = new Enterprise_GiftCardAccount_Model_Giftcardaccount();
                            $newAcct = TRUE;
                        }

                        if($newAcct){
                            $giftCardAcct->setCode($code)
                                ->setWebsiteId($this->companyWebsiteIds[$company])
                                ->setStatus(1)
                                ->setBalance($balance)
                                ->save();
                        }
                        else{
                            if($giftCardAcct->getBalance() != $balance ){

                               $giftCardAcct->setBalance($balance)
                                       ->setAdditionalInfo("Zircon Adjustment")
                                       ->save();
                            }  
                        }
                        $successCount++;
                        Mage::log("Processed GC Account: ".$code.":Original Purchase:".$purchasedAmt."\r\n",null, $this->logFile); 
                    }
                    catch(Exception $e){
                        $errorCount++;
                        Mage::log("import Error: ".$e->getMessage().":GC:".$code,null, $this->errorFile);
                    }
                }
            }
            
            fclose($handle);
            $fileNameExecuted= str_replace('.gc', '.executed.' . date('Ymd-His') . '.gc', $giftCardFile);
	    $fileNameExecuted= str_replace('.GC', '.executed.' . date('Ymd-His') . '.GC', $giftCardFile);
            $fileNameExecuted = str_replace($this->giftCardDir, $this->giftCardExecutedDir, $fileNameExecuted);
            rename($giftCardFile, $fileNameExecuted);   
        }
        
        if($errorCount > 0)
            $this->sendEmail('Zircon Gift Card  Update Error', 'There was an error(s) in the Zircon Gift Card Update process', Mage::getBaseDir().'/var/log/' . $this->errorFile, TRUE);
    	
        if($successCount > 0)
            $this->sendEmail('Zircon Gift Card  Update', 'Metrics for the Zircon Gift Card  Update process', Mage::getBaseDir().'/var/log/' . $this->logFile, FALSE);
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
    
    //Retieve Zircon Records
    private function getZirconRecords(){  
        $helper = Mage::helper('zirconprocessing');
        $api_endpoint_url = $helper->getGiftCardEndPoint();
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
        $local_file = $this->giftCardDir . '/GIFTCARD_IMPORT_' . date("Ymd_Hi") . ".GC";
        $fp = fopen($local_file, 'w');
        fwrite($fp, $curl_response);
        fclose($fp);   
        
        // Did we get a 200 or an error?
        if($http_status != 200)
        {
            Mage::log('Connection to api import server failed:' . $http_status . ' ' . $curl_response,null,$this->errorFile);
            $to = $helper->getErrorEmailNotice();
            $subject = "Zircon Gift Card Update - Connection to API import server failed";
            mail($to, $subject,'');
            throw new Exception('Connection to api import server failed:' . $http_status . ' ' . $curl_response);
            return;
        }
    }
    
    function initGiftCardFiles(){
        $files = array();
        //Get the files & order by oldest first
        foreach (glob($this->giftCardDir.'/*.{gc,GC}', GLOB_BRACE) AS $file){    
            $slashPosition = strripos($file,'/');
            
            if($slashPosition == false) $slashPosition = strripos($file,'\\');
             $files[] = $file;
        }
        $this->giftCardFiles = $files; 
    }

}
