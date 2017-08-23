<?php
/**
 * @category    FW
 * @package     FW_ZirconProcessing
 * @copyright   Copyright (c) 2015 F+W Media, Inc. (http://www.fwmedia.com)
 */
class FW_ZirconProcessing_Helper_Data extends Mage_Core_Helper_Abstract{
    
    /**
     * Config paths for using throughout the code
     */
    const COMMON_XML_PATH  = 'zircon/common/';
    const ORDER_PROCESSING_XML_PATH  = 'zircon/order_processing/';
    const INVENTORY_XML_PATH  = 'zircon/inventory/';
    
    
    ////COMMON CONFIG ELEMENTS
     /**
     * Get API Base Url
     *
     * @param mixed $store
     * @return string
     */
    public function getBaseEndPoint($store = null){
        return Mage::getStoreConfig(self::COMMON_XML_PATH.'base_endpoint', $store);
    }
    
     /**
     * Get Email Notice Address(s)
     *
     * @param mixed $store
     * @return string
     */
    public function getEmailNotice($store = null){
        return Mage::getStoreConfig(self::COMMON_XML_PATH.'emailnotice', $store);
    }
    
     /**
     * Get Error Email Notice Address(s)
     *
     * @param mixed $store
     * @return string
     */
    public function getErrorEmailNotice($store = null){
        return Mage::getStoreConfig(self::COMMON_XML_PATH.'error_emailnotice', $store);
    }
    
    
    ////ORDER PROCESSING CONFIG ELEMENTS
    
    /**
     * Get Company Code
     *
     * @param mixed $store
     * @return string
     */
    public function getCompanyCode($store = null){
        return Mage::getStoreConfig(self::ORDER_PROCESSING_XML_PATH.'company_code', $store);
    }

    /**
     * Get Catalog Type
     *
     * @param mixed $store
     * @return string
     */
    public function getCatalogType($store = null){
        return Mage::getStoreConfig(self::ORDER_PROCESSING_XML_PATH.'catalog_type', $store);
    }
    
     /**
     * Get Catalog Request API End Point
     *
     * @param mixed $store
     * @return string
     */
    public function getCataglogRequestEndPoint($store = null){
        return trim(Mage::getStoreConfig(self::COMMON_XML_PATH.'base_endpoint', $store), '/')
                . '/' . trim(Mage::getStoreConfig(self::ORDER_PROCESSING_XML_PATH.'catalogrequest_suffix', $store), '/');
    }
    
      /**
     * Get Customer Insert API UEnd Point
     *
     * @param mixed $store
     * @return string
     */
    public function getCustomerInsertEndPoint($store = null){
        return trim(Mage::getStoreConfig(self::COMMON_XML_PATH.'base_endpoint', $store), '/') 
                . '/' . trim(Mage::getStoreConfig(self::ORDER_PROCESSING_XML_PATH.'customerinsert_suffix', $store), '/');
    }
    
     /**
     * Get Next Order Id API End Point
     *
     * @param mixed $store
     * @return string
     */
    public function getNextOrderIdEndPoint($store = null){
        return trim(Mage::getStoreConfig(self::COMMON_XML_PATH.'base_endpoint', $store), '/')
                . '/' . trim(Mage::getStoreConfig(self::ORDER_PROCESSING_XML_PATH.'nextorderid_suffix', $store), '/');
    }
        
     /**
     * Get Customer By Address API End Point
     *
     * @param mixed $store
     * @return string
     */
    public function getCustomerByAddressEndPoint($store = null){
        return trim(Mage::getStoreConfig(self::COMMON_XML_PATH.'base_endpoint', $store), '/') 
                . '/' . trim(Mage::getStoreConfig(self::ORDER_PROCESSING_XML_PATH.'customerbyaddress_suffix', $store), '/');
    }
    
     /**
     * Get Set Customer Address API End Point
     *
     * @param mixed $store
     * @return string
     */
    public function getSetCustomerAddressEndPoint($store = null){
        return trim(Mage::getStoreConfig(self::COMMON_XML_PATH.'base_endpoint', $store), '/') 
                . '/' . trim(Mage::getStoreConfig(self::ORDER_PROCESSING_XML_PATH.'setcustomeraddress_suffix', $store), '/');
    }
    
     /**
     * Get Tow Submission API End Point
     *
     * @param mixed $store
     * @return string
     */
    public function getTowSubmissionEndPoint($store = null){
        return trim(Mage::getStoreConfig(self::COMMON_XML_PATH.'base_endpoint', $store), '/') 
                . '/' . trim(Mage::getStoreConfig(self::ORDER_PROCESSING_XML_PATH.'towsubmission_suffix', $store), '/');
    }
    
     /**
     * Get Toww Submission API End Point
     *
     * @param mixed $store
     * @return string
     */
    public function getTowwSubmissionEndPoint($store = null){
        return trim(Mage::getStoreConfig(self::COMMON_XML_PATH.'base_endpoint', $store), '/') 
                . '/' . trim(Mage::getStoreConfig(self::ORDER_PROCESSING_XML_PATH.'towwsubmission_suffix', $store), '/');
    }
    
     /**
     * Get Catalog Source Key
     *
     * @param mixed $store
     * @return string
     */
    public function getCatalogSourceKey($store = null){
        return Mage::getStoreConfig(self::ORDER_PROCESSING_XML_PATH.'catalog_source_key', $store);
    }
    
    /**
     * Get Catalog Request Source Key
     *
     * @param mixed $store
     * @return string
     */
    public function getCatalogRequestSourceKey($store = null){
        return Mage::getStoreConfig(self::ORDER_PROCESSING_XML_PATH.'catalog_request_source_key', $store);
    }
    
    /**
     * Get Order Status API End Point
     *
     * @param mixed $store
     * @return string
     */
    public function getOrderStatusEndPoint($store = null){
        return trim(Mage::getStoreConfig(self::COMMON_XML_PATH.'base_endpoint', $store), '/') 
                . '/' . trim(Mage::getStoreConfig(self::ORDER_PROCESSING_XML_PATH.'orderstatus_suffix', $store), '/');
    }
    
    ////ORDER PROCESSING CONFIG ELEMENTS
    /**
     * Get Coupon API UEnd Point
     *
     * @param mixed $store
     * @return string
     */
    public function getCouponEndPoint($store = null){
        return trim(Mage::getStoreConfig(self::COMMON_XML_PATH.'base_endpoint', $store), '/') 
                . '/' . trim(Mage::getStoreConfig(self::INVENTORY_XML_PATH.'coupon_suffix', $store), '/');
    }
    
     /**
     * Get Gift Card API End Point
     *
     * @param mixed $store
     * @return string
     */
    public function getGiftCardEndPoint($store = null){
        return trim(Mage::getStoreConfig(self::COMMON_XML_PATH.'base_endpoint', $store), '/') 
                . '/' . trim(Mage::getStoreConfig(self::INVENTORY_XML_PATH.'giftcard_suffix', $store), '/');
    }
    
     /**
     * Get Loyalty Account API End Point
     *
     * @param mixed $store
     * @return string
     */
    public function getLoyaltyAccountEndPoint($store = null){
        return  trim(Mage::getStoreConfig(self::COMMON_XML_PATH.'base_endpoint', $store, '/')) 
                . '/' . trim(Mage::getStoreConfig(self::INVENTORY_XML_PATH.'loyaltyaccount_suffix', $store), '/');
    }
    
     /**
     * Get Loyalty Reward API End Point
     *
     * @param mixed $store
     * @return string
     */
    public function getLoyaltyRewardEndPoint($store = null){
        return  trim(Mage::getStoreConfig(self::COMMON_XML_PATH.'base_endpoint', $store, '/')) 
                . '/' . trim(Mage::getStoreConfig(self::INVENTORY_XML_PATH.'loyaltyreward_suffix', $store), '/');
    }
    
     /**
     * Get Product API End Point
     *
     * @param mixed $store
     * @return string
     */
    public function getProductEndPoint($store = null){
        return trim(Mage::getStoreConfig(self::COMMON_XML_PATH.'base_endpoint', $store), '/') 
                . '/' . trim(Mage::getStoreConfig(self::INVENTORY_XML_PATH.'product_suffix', $store), '/');
    }
    
    /**
     * Get Stores to Process
     *
     * @param mixed $store
     * @return string
     */
    public function getStoresToProcess($store = null){
        return Mage::getStoreConfig(self::INVENTORY_XML_PATH.'stores_to_process', $store);
    }
    
}