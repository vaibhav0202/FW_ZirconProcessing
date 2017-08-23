<?php
class FW_ZirconProcessing_Model_Zircon extends Mage_Core_Model_Abstract
{
    public function _construct(){
        parent::_construct();
        $this->_init('zirconprocessing/zircon');
    }
	
    public function processZirconOrder($order){
        //LOAD PAYMENT MAGE MODEL
        $payment = $order->getPayment();

        //CHECK PAYMENT METHOD
        $payment_check = explode("_", $payment->getMethod());
        $payment_method = $payment_check[0];

        //CHECK FOR PAYPAL PAYMENT METHOD
        $transactionid = "";
        if($payment_method == "paypal"){
            //LOAD PAYPAL PAYMENT DATA
            $transactionid = $payment->getTransactionId();
        }

        //LOG ZIRCON POSTING PROCESS HAS STARTED
        Mage::log('ZIRCON PROCESS ORDERACCEPT OBSERVER FIRED - ORDER #'.$order->getIncrementId().'- PAYMENT: '.$payment_method.' - TRANSACTIONID: '.$transactionid, null, 'FW_ZirconProcessing.log');

        //INIT ZIRCON ORDER STATUS
        $order->setZirconorderStatus('PENDING');

        //Load order queue data to add to queue
        $orderQueueData = $this->getOrderQueueData($order);

        //Add to queue
        $this->createZirconQueueItem($orderQueueData);

        //CLOSE AND SAVE THIS ORDER
        //CHECK FOR PAYPAL PAYMENT METHOD AND SAVE ZIRCON STATUS
        $state = 'processing';
        $status = 'PN';
        $comment = 'Changing state to Processing and status to Complete Status';
        $isCustomerNotified = true;
        $order->setState($state, $status, $comment, $isCustomerNotified);
        $order->save();
            
        if($payment_method != "paypal" && $order->canInvoice())
        {
            //LOAD AND GENERATE THE INVOICE
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
            $invoice->register();

            $transaction = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

            $transaction->save();
        }
    }

    public function submitToZircon(FW_Queue_Model_Queue $queue){
        //LOAD FW_Queue_Model_Queue STORED DATA
        $queue_data = $queue->getQueueData();
        $order = Mage::getModel('sales/order')->load($queue_data['order_id']); 
        $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
        $payment = $order->getPayment();

        //CHECK IF ORDER IS 7 DAYS OLD - IF SO - REMOVE CC DATA AND CLEAR ZIRCON RE-POSTING STATUS
        $orderDate = strtotime($order->getCreatedAt());
                
        if($orderDate <= strtotime('+1 week'))
        {
            //LOGGING FOR RESPONSE TIME COLLECTION
            $time_start = microtime(true);
                
            //CONNECT AND BUILD ZIRCON GATEWAY XML AND SEND MAGE Varien Objects
            $gw = new FW_ZirconProcessing_Model_Gateway($customer, $order, $payment);

            //BUILD ORDER OBJECT AND GET ZIRCON CUSTOMER ID
            try{
                $orderObj = json_decode($gw->_OrderAcceptObject);
            }
            catch(Exception $e){
                $order->setZirconorderStatus('ERROR');
                $order->save();
                $this->logEndTime($order, $time_start);
                Mage::log('ZIRCON ORDER ' . $order->getIncrementId() . ' NEW CUSTOMER OBSERVER ERROR POSTED: ', null, 'ZIRCON_ORDER_ERROR.log');
                throw new Exception(' --- FAILURE TO CREATE ORDER OBJECT ---');
            }
            
            $xcustid = $orderObj->cm_id[0];

            //CHECK FOR ZIRCON CUSTOMER ID
            if($xcustid == NULL || $xcustid == 0)
            {
                $order->setZirconorderStatus('ERROR');
                $order->save();
                $this->logEndTime($order, $time_start);
                Mage::log('ZIRCON ORDER ' . $order->getIncrementId() . ' NEW CUSTOMER OBSERVER ERROR POSTED', null, 'ZIRCON_ORDER_ERROR.log');
                throw new Exception(' --- INVALID ZIRCON CUSTOMER ID ---');
            }

            //PROCESS Order_Submit POST WITH THE GATEWAY _OrderAcceptObject OBJECT
            try{
                $response = $gw->Order_Submit($gw->_OrderAcceptObject);
            }
            catch(Exception $e){
               //SET ZIRCON ORDER STATUS
                $order->setZirconorderStatus('ERROR');
                //LOG ERRORS
                Mage::log('ZIRCON ORDER ' . $order->getIncrementId() . ' PROCESS ORDERACCEPT ERROR:' . $e->getMessage(), null, 'ZIRCON_ORDER_ERROR.log');
                $order->save();
                $this->logEndTime($order, $time_start);
                throw new Exception('Zircon Order Submission Failed');
            }
                           
            //ORDER SUCCESS SAVE STATUS AND RETURNED ORDERNO
            //SET ZIRCON ORDER STATUS
            $order->setZirconorderStatus('ACCEPTED');
            //SET ZIRCON ORDER ID
            $order->setZirconorderId($orderObj->orderno);
            $this->logEndTime($order, $time_start); 
        }
        else
        {
            //SET ZIRCON ORDER STATUS
            $order->setZirconorderStatus('CANCELED');   
        }
        $order->save();

    }

    public function submitCatalogRequest(FW_Queue_Model_Queue $queue)
    {

        $queueData = $queue->getQueueData();

        $customerData = $queueData;
        if ($queueData['country'] != 'USA') {
            $customerData['address2'] = implode(', ', array($queueData['address1'], $queueData['address2'])); // separate with comma unless no address2, then no comma
            $customerData['address1'] = implode(', ', array($queueData['city'], $queueData['country'])) . ' ' . $queueData['zip'];
            $customerData['state'] = '';
            $customerData['city'] = $queueData['country'];
        }

        $gw = new FW_ZirconProcessing_Model_Gateway();
        $gw->requestCatalog($customerData);

    }

    public function getOrderQueueData($order) {
        return array(
            'type' => 'submission',
            'order_id' => $order->getId(),
            'increment_id' => $order->getIncrementId(),
        );
    }

    public function createZirconQueueItem($queueItemData, $queueArgs=null){
        //Set some defaults (Default is submitting order)
        if($queueArgs == null) {
            
            //If zircon order submission
            if($queueItemData['increment_id']){
                $queueArgs = array('function' => 'submitToZircon',
                               'code' => 'zircon submission',
                               'desc' => 'Zircon Submit for Order: ' . $queueItemData['increment_id']);
            }else{ //catalog request
                $queueArgs = array('function' => 'submitToZircon',
                               'code' => 'zircon submission',
                               'desc' => 'Catalog Request: ' . $queueItemData['firstName'] . ' ' . $queueItemData['lastName']);
            }
        }

        //INIT A NEW FW_Queue_Model_Queue OBJECT
        $queue = Mage::getModel('fw_queue/queue');

        //SEND QUEUE DATA ARRAY AND SUBMIT A NEW QUEUE RECORD
        $queue->addToQueue('zirconprocessing/zircon', $queueArgs['function'], $queueItemData, $queueArgs['code'], $queueArgs['desc']);

    }

    private function logEndTime($order, $time_start){
        //LOGGING FOR RESPONSE TIME COLLECTION
        $time_end = microtime(true);
        $time = $time_end - $time_start;
        
        $sec = intval($time);
        $micro = $time - $sec;

        $final = strftime('%T', mktime(0, 0, $sec)) . str_replace('0.', '.', sprintf('%.3f', $micro));
        Mage::log('ZIRCON RESPONSE TIME:' . $final, null, 'ZIRCON_RESPONSE.log');
 }
}
