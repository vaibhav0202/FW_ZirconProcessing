<?php
/**
 * Created by PhpStorm.
 * User: dmatriccino
 * Date: 12/9/14
 * Time: 11:05 AM
 */

class FW_ZirconProcessing_CatalogrequestController extends Mage_Core_Controller_Front_Action  {

    function indexAction()
    {
        $request      = Mage::app()->getRequest();
        $postItems = $request->getPost();
        $requiredUSFields = array('firstName', 'lastName', 'country', 'address1', 'city', 'state', 'zip', 'email');
        $requiredCAFields = array('firstName', 'lastName', 'country', 'ca-address1', 'ca-city', 'ca-zip', 'email');
        if($postItems)
        {
            //TODO: Check formkey

            //Check required fields are filled out
            $requiredFields = ($postItems['country'] == 'USA') ? $requiredUSFields : $requiredCAFields;

            foreach($requiredFields as $requiredField) {
                if(!isset($postItems[$requiredField]) || $postItems[$requiredField] == '') {
                    $msg = "Please fill out required fields";
                    Mage::getSingleton('customer/session')->addError( $msg );
                    $this->_redirect('catalog-request');
                    return;
                }
            }

            //Check if email field is a valid email address
            if(!filter_var($postItems['email'], FILTER_VALIDATE_EMAIL)) {
                $msg = "Please fill out a valid email address";
                Mage::getSingleton('customer/session')->addError( $msg );
                $this->_redirect('catalog-request');
                return;
            }


            if($postItems['country'] != 'USA') {
                $postItems['address1'] = $postItems['ca-address1'];
                $postItems['city'] = $postItems['ca-city'];
                $postItems['zip'] = $postItems['ca-zip'];
            }

            //Create queueData Array with customer info
            $queueItemData = array(
                'firstName' => $postItems['firstName'],
                'lastName'  => $postItems['lastName'],
                'country'   => $postItems['country'],
                'address1'  => $postItems['address1'],
                'address2'  => $postItems['address2'],
                'city'      => $postItems['city'],
                'state'     => $postItems['state'],
                'zip'       => $postItems['zip'],
                'phone'     => $postItems['phone'],
                'email'     => $postItems['email'],
                
                'storeId'   => Mage::app()->getStore()->getId()
            );

            $zirconModel = Mage::getModel('zirconprocessing/zircon');
            $queueArgs = array('function' => 'submitCatalogRequest', 'code' => 'zircon catalog request', 'Zircon Catalog Request', 'desc' => 'Catalog Request for Mage Store ' . Mage::app()->getStore()->getId() . ': ' . $postItems['firstName'] . ' ' . $postItems['lastName'],);
            //Add request to queue
            $zirconModel->createZirconQueueItem($queueItemData, $queueArgs);

            //Load ExactTarget Module
            $fwExactTarget = Mage::getModel('fw_exacttarget/observer');
            $postItems['store_id'] = Mage::app()->getStore()->getStoreId();
            $postItems['website'] = Mage::getModel('core/store')->load($postItems['store_id'])->getName();
            $postItems['Email Address'] = $postItems['email'];
            $postItems['brand'] = $fwExactTarget->loadBrandValue($postItems['website']);

            //Create Email Queue Item
            $fwExactTarget->createQueueItem('exacttarget_email_from_catalogrequest',
                'Exact Target Catalog Request for: ' . $postItems['email'],
                $postItems);

            //Get GA Campaign values and create Campaign Queue item
            $utmArray = Mage::helper('fw_exacttarget')->getUtmCampaign();
            $postItems['campaign'] = $utmArray['utm_campaign'];
            $postItems['source'] = $utmArray['utm_source'];
            $postItems['medium'] = $utmArray['utm_medium'];
            $fwExactTarget->createQueueItem('exacttarget_campaign',
                'Exact Target Campaign from Catalog Request: ' . $postItems['email'] . '. Campaign: ' . $postItems['campaign'],
                $postItems);
        }

        //TODO: redirect to proper page
        $this->_redirect('catalog-request-thank-you');

        return;
    }

} 