<?php
class FW_ZirconProcessing_Block_Adminhtml_Order_View_Tab_Zircon extends Mage_Adminhtml_Block_Template implements Mage_Adminhtml_Block_Widget_Tab_Interface {

	protected function _construct()
    {
        parent::_construct();
    }
    
    public function getOrder()
    {
        return Mage::registry('current_order');
    }
	    
	 /**
     * ######################## TAB settings #################################
     */
	    
	public function getTabLabel()
    {
        return Mage::helper('zirconprocessing')->__('ZIRCON Status');
    }

    public function getTabTitle()
    {
        return Mage::helper('zirconprocessing')->__('ZIRCON Status');
    }

    public function canShowTab()
    {
        return true;
    }

    public function isHidden()
    {
        return false;
    }
    
	public function getTabClass()
    {
        return 'ajax only';
    }
    
    public function getClass()
    {
    	return $this->getTabClass();
    }
    
    public function getTabUrl()
    {
    	return $this->getUrl('*/zirconprocessing/orderZirconView', array('order_id'=>$this->getOrder()->getId()));
    }
}
