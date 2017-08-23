<?php
$installer = $this;

$installer->startSetup();

/**
 * Add Zircon Order attributes for sales entities
 */
$entityAttributesCodes = array(
    'zirconorder_id' => Varien_Db_Ddl_Table::TYPE_VARCHAR,
    'zirconorder_status' => Varien_Db_Ddl_Table::TYPE_VARCHAR
);

foreach ($entityAttributesCodes as $code => $type) {
    $installer->addAttribute('order', $code, array('type' => $type, 'visible' => false));
}

/**
* Add Zircon Customer attributes for customer entities
*/
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$entityTypeId     = $setup->getEntityTypeId('customer');
$attributeSetId   = $setup->getDefaultAttributeSetId($entityTypeId);
$attributeGroupId = $setup->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

$setup->addAttribute('customer', 'zirconcustomer_id', array(
    'input'         => 'text',
    'type'          => 'int',
    'label'         => 'Zircon Customer ID',
    'visible'       => 1,
    'required'      => 0,
    'user_defined' => 1,
));

$setup->addAttributeToGroup(
$entityTypeId,
$attributeSetId,
$attributeGroupId,
 'zirconcustomer_id',
 '1000'  //sort_order
);

$setup->addAttribute('customer', 'loyalty_club_balance', array(
    'input'         => 'text',
    'type'          => 'int',
    'label'         => 'Loyalty Club Balance',
    'visible'       => 1,
    'required'      => 0,
    'user_defined' => 1,
));

$setup->addAttributeToGroup(
$entityTypeId,
$attributeSetId,
$attributeGroupId,
 'loyalty_club_balance',
 '1002'  //sort_order
);

$oAttribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'zirconcustomer_id');
$oAttribute->setData('used_in_forms', array('adminhtml_customer'));
$oAttribute->save();

$oAttribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'loyalty_club_expire');
$oAttribute->setData('used_in_forms', array('adminhtml_customer'));
$oAttribute->save();

$oAttribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'loyalty_club_balance');
$oAttribute->setData('used_in_forms', array('adminhtml_customer'));
$oAttribute->save();

$installer->endSetup();