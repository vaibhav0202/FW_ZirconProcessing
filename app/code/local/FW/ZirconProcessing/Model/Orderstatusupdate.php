<?php
/**
 * @category    FW
 * @package     FW_ZirconProcessing
 * @copyright   Copyright (c) 2014 F+W, Inc. (http://www.fwcommunity.com)
 * @author      Dan Matriccino <daniel.matriccino@fwcommunity.com>
 */

class FW_ZirconProcessing_Model_Orderstatusupdate extends Mage_Core_Model_Abstract {

    public function updateOrders($date=null) {

        $helper = Mage::helper('zirconprocessing');
	$gateway = new FW_ZirconProcessing_Model_Gateway();

        //Get Zircon order URL from system config, grabbing only fields needed
        $orderStatusUrl = $helper->getOrderStatusEndPoint();
        $orderStatusRequestParams = "?fields=orderno,order_status,line_status,part_no,ship_via,qty";

        if($date != null) {
            //Add end date to the URL
            $dateTotime = strtotime($date);
            $date = date("m/d/y", $dateTotime);
            $orderStatusRequestParams .= "&select=ORDER.DATE>\"{$date}\"";
        }

        //Decode String into JSON object
        $orderJsonObject = $gateway->get($orderStatusRequestParams, $orderStatusUrl);

        if( property_exists($orderJsonObject, "list_item"))
        {
        	//Load Order Array from JSON object
        	$zirconOrderArray = $orderJsonObject->list_item;

        	//Loop through zircon orders and call function to update the order status
        	foreach($zirconOrderArray as $zirconOrder) {
        	    $zirconOrderId = $zirconOrder->orderno;
        	    $zirconOrderStatus = $zirconOrder->order_status;
        	    //Load magento order by zircon id
        	    $orderModel = Mage::getModel('sales/order')->loadByAttribute('zirconorder_id', $zirconOrderId);
        	    //If order exists, update order status and add any shipments needed
        	    if($orderModel->getIncrementId()) {
        	        $this->updateOrderStatus($orderModel, $zirconOrderStatus);

        	        //Only create shipments if order is shippable
        	        if($orderModel->canShip()) {
        	            $this->createOrderShipments($orderModel, $zirconOrder);
        	        }
        	    }
        	}
	}
    }

    //Function to update order status based on new status code and zircon order id
    public function updateOrderStatus($orderModel, $statusCode) {
        $return = false;

        //Only change status if an order is found and the status is different from passed status code
        if($orderModel && $orderModel->getStatus() != $statusCode) {
            //Set order status to new status code
            $orderModel->setStatus($statusCode);
            //Save order
            $orderModel->save();
            //Set return to true
            $return = true;
        }

        //Returns true or false depending on if order status was updated or not
        return $return;
    }

    public function createOrderShipments($orderModel, $zirconOrder) {

        $orderItemIds = array();
        $shipmentItemIds = array();
        $shipmentQtys = array();

        //Load part numbers
        $lineItems = $zirconOrder->part_no;

        //Load item status
        $lineStatus = $zirconOrder->line_status;

        //Load item qtys
        $lineItemQtys = $zirconOrder->qty;

        $orderItems = $orderModel->getAllItems();


        //Build orderItemIds to productSku mapping to pass to shipment if needed
        foreach($orderItems as $item) {
            $itemId = $item->getId();
            $sku = $item->sku;
            if ($item->getParentItemId()) {
                $orderItemIds[$sku] = $item->getParentItemId();
            } else {
                $orderItemIds[$sku] = $itemId;
            }
            //No partial shipments of specific product, so if at least 1 is shipped, we could skip
            //shipping it again
            if($item->getQtyShipped() > 0) {
                $shipmentItemIds[] = $itemId;
            }
        }

        //Loop through items and determine if shipment is needed
        foreach($lineItems as $i=>$lineItem) {
            $itemId = $orderItemIds[$lineItem];
            $lineItemStatus = $lineStatus[$i];
            $lineItemStatusArray = explode("/", $lineItemStatus);

            $isDate = (count($lineItemStatusArray) != 3) ? false : true;

            //If item status is a date and not in shipmentItemIds then we could create shipment
            if(!in_array($itemId, $shipmentItemIds) && $isDate) {
                $shipmentQtys[$itemId] = $lineItemQtys[$i];
            }
        }

        //If we have items in shipmentQtys array, create shipment
        if(count($shipmentQtys) > 0) {
            $shipment = Mage::getModel('sales/service_order', $orderModel)->prepareShipment($shipmentQtys);

            if ($shipment) {
                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);
                $shipment->addComment("Added shipment on " . date("m/d/Y"), false);

                try {
                    $transactionSave = Mage::getModel('core/resource_transaction')
                        ->addObject($shipment)
                        ->addObject($shipment->getOrder())
                        ->save();
                } catch (Mage_Core_Exception $ex) {
                    Mage::log("Exception while creating shipment for order " . $orderModel->getIncrementId() . ": " . $ex->getMessage(), null, "item_shipment.log");
                }
            }
        }
    }
}
