<?php

/**
 * @category    FW
 * @package     FW_ZirconProcessing
 * @copyright   Copyright (c) 2015 F+W (http://www.fwcommunity.com)
 * @author      J.P. Daniel <jp.daniel@fwcommunity.com>
 */
class FW_ZirconProcessing_Block_Widget_Catalogrequest
    extends Mage_Directory_Block_Data
    implements Mage_Widget_Block_Interface
{
    /**
     * Internal constructor, that is called from real constructor
     */
    protected function _construct()
    {
        $this->setTemplate('zirconprocessing/widget/catalogrequest.phtml');
    }

    /**
     * Retrieve form action URL
     *
     * @return string
     */
    public function getFormAction()
    {
        return $this->getUrl('zirconprocessing/catalogrequest');
    }
}
