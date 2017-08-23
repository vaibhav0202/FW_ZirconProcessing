<?php
class FW_ZirconProcessing_Model_Observer {
	
    protected function _construct(){
        $this->_init('zirconprocessing/observer');
    }
    
    public function process_zircon_order_accept(Varien_Event_Observer $observer){
        //GET ORDER IDs
        $orderIds = $observer->getOrderIds();
        
        if (!empty($orderIds) && is_array($orderIds)){
            foreach ($orderIds as $oid){
            //LOAD ORDER MAGE MODEL
            $order = Mage::getSingleton('sales/order');
            if ($order->getId() != $oid) $order->reset()->load($oid);
                Mage::getModel('zirconprocessing/zircon')->processZirconOrder($order);
            }
        }
    }
	
    /**
     * Locates payment records in the past that contain credit card data and blank it out
     */
    public function removeCCData(){
        $date = date('Y-m-d H:i:s', time());
        $orderCreationDate = date ( 'Y-m-d H:i:s' , strtotime ( '-3 day' , strtotime ( $date ) ) );

        try{
            $read = Mage::getSingleton('core/resource')->getConnection('catalog_read');
            $payments = $read->fetchAll('SELECT sfop.entity_id, sfo.increment_id ' .
                ' FROM sales_flat_order sfo ' .
                ' INNER JOIN sales_flat_order_payment sfop ON sfop.parent_id = sfo.entity_id ' .
                ' WHERE sfop.method = \'authnetcim\' ' .
                ' AND sfop.cc_last4 IS NOT null ' .
                ' AND sfo.created_at < \'' . $orderCreationDate . '\' ' .
                ' AND additional_information NOT LIKE \'%REMOVED%\' ' .
                ' AND additional_information LIKE \'%"cc";s:24:%\' ' .
                ' ORDER BY sfo.created_at DESC ' . 
                ' LIMIT 5000');

            foreach($payments as $payment){
                $paymentToProcess = Mage::getModel('sales/order_payment')->load($payment['entity_id']);
                $paymentToProcess->setAdditionalInformation('cc', 'REMOVED');  
                $paymentToProcess->save();
                Mage::log('Removed cc data for ORDER NUMBER:'.$payment['increment_id'].' and PAYMENT ID:'.$paymentToProcess->getEntityId(), null, 'FW_CC_REMOVE.log');
            }
        }catch(Exception $e){
              Mage::log($e->getMessage(), null, 'FW_CC_REMOVE.log');
        }
    }

    /**
     * Adds the Catalog Source Code to the Quote
     *
     * @param Varien_Event_Observer $observer
     */
    public function onCheckoutCartAdvancedAdd(Varien_Event_Observer $observer)
    {
        $param = 'catalog_source_code';
        if ($catalogSourceCode = Mage::app()->getRequest()->getParam($param)) {
            $quote = Mage::getSingleton('checkout/cart')->getQuote();
            $quote->setData($param, $catalogSourceCode);
        }
    }
    
 
}
