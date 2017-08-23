<?php
chdir(dirname(__FILE__));  // Change working directory to script location
require_once '../../../../../Mage.php';  // Include Mage
require_once '../Model/Orderstatusupdate.php';
Mage::app('admin');  // Run Mage app() and set scope to admin

$endDate = null;

if(isset($argv[1])) {
    $endDate = $argv[1];
}
$update = new FW_ZirconProcessing_Model_Orderstatusupdate();
$update->updateOrders($endDate);
