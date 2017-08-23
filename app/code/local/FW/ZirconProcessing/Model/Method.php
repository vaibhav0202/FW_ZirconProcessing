<?php
/**
 * @category    FW
 * @package     FW_ZirconProcessing
 * @copyright   Copyright (c) 2015 F+W Media, Inc. (http://www.fwmedia.com)
 * The purpose of this class is to override the authorize method of 
 * Paradox Labs Authorize.net CIM Module in order to short circuit 
 * the method to NOT interface with the gateway
 */    

class FW_ZirconProcessing_Model_Method extends ParadoxLabs_AuthorizeNetCim_Model_Method
{

    /**
     * Pretend Authorize a transaction
     */
    public function authorize( Varien_Object $payment, $amount )
    {
        $ccenc = Mage::getSingleton('core/encryption')->encrypt($payment->getCcNumber());
        $payment->setAdditionalInformation('cc', $ccenc);
        $payment->setAdditionalInformation('cccid', $payment->getCcCid());
        return $this;
    }
}
