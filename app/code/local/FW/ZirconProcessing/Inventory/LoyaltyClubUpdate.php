<?php

/**
 * @category    FW
 * @package     FW_ZirconProcessing
 * @copyright   Copyright (c) 2014 F+W Media, Inc. (http://www.fwmedia.com)
 */
class FW_ZirconProcessing_Model_LoyaltyClubUpdate extends Mage_Core_Model_Abstract
{
    protected $_accountFiles;
    protected $_rewardFiles;
    protected $_loyaltyDir;
    protected $_loyaltyExecutedDir;
    protected $_logFile;
    protected $_companyWebsiteNames;
    protected $_companyWebsiteIds;
    protected $_companyLoyaltyClubNames;
    protected $_companyLoyaltyGroupIds;
    protected $_errorCount;
    protected $_successCount;

  public function __construct(){
        $this->logFile = 'Zircon_Loyalty_Import.log';
    	$this->errorFile =  'Zircon_Loyalty_Import_Error.log';
        
        //Initialize import directory
        $this->loyaltyDir = Mage::getBaseDir() . '/var/importexport/zircon_inventory/';
        $this->loyaltyExecutedDir = $this->loyaltyDir.'executed';
        
        $this->companyWebsiteNames = array
        (
            "01" => "KeepSakeQuilting.com",
            "02" => "KeepSakeNeedleArts.com",
            "03" => "PatternWorks.com",
        );
        
        $this->companyLoyaltyClubNames = array
        (
            "loyaltykq" => "KQ Loyalty Club",
            "loyaltyna" => "NA Loyalty Club",
            "loyaltypw" => "PW Loyalty Club",
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
        $groupCollection = Mage::getModel('customer/group')->getCollection();
        foreach ($this->companyLoyaltyClubNames as $company=>$companyLoyaltyClubName){
            foreach ($groupCollection as $customerGroup){
                if($customerGroup->getCustomerGroupCode() == $companyLoyaltyClubName){
                    $companyLoyaltyGroupIdsTemp[$company] = $customerGroup->getId();
                }
            }   
        }
        $this->companyLoyaltyGroupIds = $companyLoyaltyGroupIdsTemp;
       
        $this->errorCount = 0;
        $this->successCount = 0;
        $this->processedKQCustomers = array();
        $this->processedNACustomers = array();
        $this->processedPWCustomers = array();
    }
    
    public function import(){
        $startTime = microtime(true);                      
        $this->getZirconRecords("ACCOUNTS");
        $this->initAccountFiles();
        $this->updateAccounts();
        $this->getZirconRecords("REWARDS");
        $this->initRewardFiles();
        $this->updateBalances();
        $this->removeAccounts();
        
        Mage::log("Time Elapsed for Zircon File Process:".(microtime(true) - $startTime),null,$this->logFile);

        if($this->errorCount > 0)
            $this->sendEmail('Zircon Loyalty Club Update Error', 'There was an error(s) in the Zircon Loyalty Club Update process', Mage::getBaseDir().'/var/log/' . $this->errorFile, TRUE);
    	
        if($this->successCount > 0)
            $this->sendEmail('Zircon Loyalty Club Update', 'Metrics for the Zircon Loyalty Club Update process', Mage::getBaseDir().'/var/log/' . $this->logFile, FALSE);
    }
    
    public function updateAccounts(){
        $tempKQProcessedCustomers = array();
        $tempNAProcessedCustomers = array();
        $tempPWProcessedCustomers = array();
        //Process each found file
        foreach ($this->accountFiles AS $accountFile) {  
            if (($handle = fopen($accountFile, "r")) !== FALSE){
                $json = json_decode(file_get_contents($accountFile), true);
                foreach($json['list_item'] as $zirconLoyalty){
                    $zirconCustid = $zirconLoyalty['cm_id'];
                    $kqLoyaltyMemId = $zirconLoyalty['loyaltykq'];
                    $naLoyaltyMemId = $zirconLoyalty['loyaltyna'];
                    $pwLoyaltyMemId = $zirconLoyalty['loyaltypw'];

                    //Only process the customers with membership ids in Zircon
                    //KQ
                    if($kqLoyaltyMemId != ""){
                        $this->processAccount($zirconCustid, $kqLoyaltyMemId,$this->companyLoyaltyGroupIds['loyaltykq'], $tempKQProcessedCustomers, $this->companyWebsiteIds["01"]);
                    }
                    
                    //NA
                    if($naLoyaltyMemId != ""){
                       $this->processAccount($zirconCustid, $naLoyaltyMemId,$this->companyLoyaltyGroupIds['loyaltyna'], $tempNAProcessedCustomers, $this->companyWebsiteIds["02"]);
                    }
                    
                    //PW
                    if($pwLoyaltyMemId != ""){
                        $this->processAccount($zirconCustid, $pwLoyaltyMemId,$this->companyLoyaltyGroupIds['loyaltypw'], $tempPWProcessedCustomers, $this->companyWebsiteIds["03"]);
                    }
                }
            }
            
            fclose($handle);
	    $fileNameExecuted= str_replace('.ACCOUNTS', '.executed.' . date('Ymd-His') . '.ACCOUNTS', $accountFile);
            $fileNameExecuted = str_replace($this->loyaltyDir, $this->loyaltyExecutedDir, $fileNameExecuted);
            rename($accountFile, $fileNameExecuted);   
        }
        
        $this->processedKQCustomers = $tempKQProcessedCustomers;
        $this->processedNACustomers = $tempNAProcessedCustomers;
        $this->processedPWCustomers = $tempPWProcessedCustomers;
    }
    
    public function processAccount($zirconCustid,$loyaltyClubId,$loyaltyClubGroupId, &$processedCustomers, $websiteId){

        try{
            $rawExpire = substr($loyaltyClubId, strrpos($loyaltyClubId,"-") + 1);
            if(strlen($rawExpire) != 4){
               $expireDate = ''; 
            }else{
                $zirconLoyaltyExpireMonth = substr($loyaltyClubId, strlen($loyaltyClubId) - 4, 2);
                $zirconLoyaltyExpireYear = '20' . substr($loyaltyClubId, strlen($loyaltyClubId) - 2);
                $date = new DateTime($zirconLoyaltyExpireYear . '-' . $zirconLoyaltyExpireMonth);
                $expireDate = $date->modify('last day of this month');
            }
        }catch (Exception $e){
            $expireDate = '';
        }

        $customer = Mage::getModel('customer/customer')
                ->getCollection()
                ->addAttributeToFilter('zirconcustomer_id',$zirconCustid)
                ->addAttributeToFilter('website_id',$websiteId)
                ->load()
                ->getFirstItem();
        
        //for each customer found with this zircon id, update the loyalty balance
        if ($customer->getId()){
            $expired = FALSE;
            if($expireDate != ''){
                $today = new DateTime('');
                if($today->format('Y-m-d') > $expireDate->format('Y-m-d')) {
                    $expired = TRUE;
                }
            }else if($expireDate == ''){
                $expired = TRUE;
            }

            try{
                if($expired){
                    $customer->setGroupId(1); //Set Back to the general group
                }
                else{
                    $customer->setGroupId($loyaltyClubGroupId);
                }

                $customer->save();
                $this->successCount++;
                $processedCustomers[$zirconCustid] = $customer->getId();
                Mage::log("Processed Loyalty Account for Magento customer: ".$customer->getId(). " , Zircon customer: ". $zirconCustid . ", Zircon Mem Id:". $loyaltyClubId . "\r\n",null, $this->logFile);                                    
            }
            catch(Exception $e){
                $this->errorCount++;
                Mage::log("updateAccounts for customer:" . $customer->getId() ." :Error: ".$e->getMessage(),null, $this->errorFile);
            } 
        } 
    }
    
    public function updateBalances(){
        //Process each found file
        
        foreach ($this->rewardFiles AS $rewardFile) {  
            if (($handle = fopen($rewardFile, "r")) !== FALSE){
                $json = json_decode(file_get_contents($rewardFile), true);
                foreach($json['Rewards'] as $zirconLoyalty){
                    $zirconCustid = $zirconLoyalty['customerid'];
                    $zirconLoyaltyBalance = substr($zirconLoyalty['balance'], 0, strlen($zirconLoyalty['balance']) - 2 );

                    //only process the accounts that were found in the Customer File with loyalty club mem IDs
                    //KQ
                    if($this->processedKQCustomers[$zirconCustid]){
                        $customer = Mage::getModel('customer/customer')->load($this->processedKQCustomers[$zirconCustid]);
                        $this->processBalance($zirconCustid,$zirconLoyaltyBalance,$this->companyLoyaltyGroupIds['loyaltykq'],$customer, $this->companyWebsiteIds["01"]);
                    }
                    
                    //NA
                    if($this->processedNACustomers[$zirconCustid]){
                        $customer = Mage::getModel('customer/customer')->load($this->processedNACustomers[$zirconCustid]);
                        $this->processBalance($zirconCustid,$zirconLoyaltyBalance,$this->companyLoyaltyGroupIds['loyaltyna'],$customer, $this->companyWebsiteIds["02"]);
                    }
                    
                    //PW
                    if($this->processedPWCustomers[$zirconCustid]){
                        $customer = Mage::getModel('customer/customer')->load($this->processedPWCustomers[$zirconCustid]);
                        $this->processBalance($zirconCustid,$zirconLoyaltyBalance,$this->companyLoyaltyGroupIds['loyaltypw'],$customer, $this->companyWebsiteIds["03"]);
                    } 
                }
            }
            
            fclose($handle);
	    $fileNameExecuted= str_replace('.REWARDS', '.executed.' . date('Ymd-His') . '.REWARDS', $rewardFile);
            $fileNameExecuted = str_replace($this->loyaltyDir, $this->loyaltyExecutedDir, $fileNameExecuted);
            rename($rewardFile, $fileNameExecuted);   
        }
    }
    
    public function processBalance($zirconCustid,$zirconLoyaltyBalance,$loyaltyGroupId, $customer, $websiteId){

        //if customer is not in loyalty club group then set balance to 0; 
        //this means that the account update removed them from the group because of expiration
        if($customer->getGroupId() != $loyaltyGroupId){
            $zirconLoyaltyBalance = 0;
        }
        try{
            $rewardpoints = Mage::getModel('enterprise_reward/reward')
                ->setCustomer($customer)
                ->setWebsiteId($websiteId)
                ->loadByCustomer();

            $rewardpoints->setPointsBalance($zirconLoyaltyBalance)
                 ->setAction(Enterprise_Reward_Model_Reward::REWARD_ACTION_ADMIN) // Enterprise_Reward_Model_Reward::REWARD_ACTION_ADMIN
                 ->setComment("Zircon Update")
                 ->updateRewardPoints();
        }
        catch(Exception $e){
            $this->errorCount++;
            Mage::log("updateBalances for customer:" . $customer->getId() ." :Error: ".$e->getMessage(),null, $this->errorFile);
        } 

        $customer->save();
        $this->successCount++;
        Mage::log("Processed Loyalty Reward for Magento customer: ".$customer->getId(). " , Zircon customer: ". $zirconCustid . ",Balance: ". $zirconLoyaltyBalance . "\r\n",null, $this->logFile);  
}
    
    public function removeAccounts(){
        //for all the customers currently in the loyalty group that did not get processed - this means their membership has expired - remove from loyalty group and set balance to 0
        // this is just in case a member gets taken out the the zircon file but magento didnt correctly catch the expiration in time
        
        //KQ
        $this->processRemoval($this->companyLoyaltyGroupIds['loyaltykq'],$this->companyWebsiteIds["01"],$this->processedKQCustomers);
        
        //NA
       $this->processRemoval($this->companyLoyaltyGroupIds['loyaltyna'],$this->companyWebsiteIds["02"],$this->processedNACustomers);
        
        //PW
        $this->processRemoval($this->companyLoyaltyGroupIds['loyaltypw'],$this->companyWebsiteIds["03"],$this->processedPWCustomers);
    }
    
    public function processRemoval($loyaltyGroupdId, $websiteId, $processedCustomers){
        $currentLoyaltyCustomers = Mage::getModel('customer/customer')
                ->getCollection()
                ->addAttributeToFilter('group_id',$loyaltyGroupdId);
            
        foreach ($currentLoyaltyCustomers as $currentLoyaltyCustomer){
            if(!in_array($currentLoyaltyCustomer->getId(), $processedCustomers)){
                $currentLoyaltyCustomer->setGroupId(1);
                $currentLoyaltyCustomer->setLoyaltyClubBalance(0);

                try{
                    $rewardpoints = Mage::getModel('enterprise_reward/reward')
                        ->setCustomer($currentLoyaltyCustomer)
                        ->setWebsiteId($websiteId)
                        ->loadByCustomer();

                    $rewardpoints->setPointsBalance(0)
                        ->setAction(Enterprise_Reward_Model_Reward::REWARD_ACTION_ADMIN) // Enterprise_Reward_Model_Reward::REWARD_ACTION_ADMIN
                        ->setComment("Zircon Update")
                        ->updateRewardPoints();

                    $currentLoyaltyCustomer->setGroupId(1); //Set Back to the general group
                    $currentLoyaltyCustomer->save();
                    $this->successCount++;
                    Mage::log("Removed Loyalty Account for customer: ".$currentLoyaltyCustomer->getId(),null, $this->logFile);                                    

                }catch (Exception $e){
                    $this->errorCount++;
                    Mage::log("Removing loyalty for customer:" . $currentLoyaltyCustomer->getId() ." :Error: ".$e->getMessage(),null, $this->errorFile);
                }
            }
        }
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
    private function getZirconRecords($type){ 
        $helper = Mage::helper('zirconprocessing');
        
        if($type == "ACCOUNTS"){
            $api_endpoint_url = $helper->getLoyaltyAccountEndPoint();
        }elseif ($type == "REWARDS"){
            $api_endpoint_url = $helper->getLoyaltyRewardEndPoint();
        }
        
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
        if($type == "ACCOUNTS"){
            $local_file = $this->loyaltyDir . 'LOYALTY_IMPORT_' .  date("Ymd_Hi") . ".ACCOUNTS";
        }elseif ($type == "REWARDS"){
            $local_file = $this->loyaltyDir . 'LOYALTY_IMPORT_' .  date("Ymd_Hi") . ".REWARDS";
        }
        
        $fp = fopen($local_file, 'w');
        fwrite($fp, $curl_response);
        fclose($fp);   
        
        // Did we get a 200 or an error?
        if($http_status != 200)
        {
            Mage::log('Connection to api import server failed:' . $http_status . ' ' . $curl_response,null,$this->errorFile);
            $to = $helper->getErrorEmailNotice();
            $subject = "Zircon Loyalty Club Update - Connection to API import server failed";
            mail($to, $subject, '');
            throw new Exception('Connection to api import server failed:' . $http_status . ' ' . $curl_response);
            return;
        }
    }
    
    function initAccountFiles(){
        $files = array();
        //Get the files & order by oldest first
        foreach (glob($this->loyaltyDir.'/*.{ACCOUNTS}', GLOB_BRACE) AS $file){    
            $slashPosition = strripos($file,'/');
            
            if($slashPosition == false) $slashPosition = strripos($file,'\\');
             $files[] = $file;
        }
        $this->accountFiles = $files; 
    }
    
    function initRewardFiles(){
        $files = array();
        //Get the files & order by oldest first
        foreach (glob($this->loyaltyDir.'/*.{REWARDS}', GLOB_BRACE) AS $file){    
            $slashPosition = strripos($file,'/');
            
            if($slashPosition == false) $slashPosition = strripos($file,'\\');
             $files[] = $file;
        }
        $this->rewardFiles = $files; 
    }

}
