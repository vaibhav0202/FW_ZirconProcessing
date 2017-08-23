<?php
    chdir(dirname(__FILE__));  // Change working directory to script location
    require_once '../../../../../Mage.php';  // Include Mage
    require_once 'LoyaltyClubUpdate.php';
    Mage::app('admin');  // Run Mage app() and set scope to admin

    $update = new FW_ZirconProcessing_Model_LoyaltyClubUpdate();
    $update->import();
?>
