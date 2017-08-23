<?php
/**
 * Adds the Catalog Source Code custom attribute to the quote and order entities
 *
 * @category    FW
 * @package     FW_ZirconProcessing
 * @copyright   Copyright (c) 2015 F+W (http://www.fwcommunity.com)
 * @author		J.P. Daniel <jp.daniel@fwcommunity.com>
 */

/** @var Mage_Sales_Model_Resource_Setup $installer */
$installer = $this;

$entities = array(
    'quote',
    'order'
);

$options = array(
    'type'     => Varien_Db_Ddl_Table::TYPE_VARCHAR,
    'visible'  => true,
    'required' => false
);

$installer->startSetup();

foreach ($entities as $entity) {
    $installer->addAttribute($entity, 'catalog_source_code', $options);
}

$installer->endSetup();
