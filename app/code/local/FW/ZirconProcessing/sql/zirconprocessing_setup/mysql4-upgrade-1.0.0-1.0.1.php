<?php
//Add new order status of "partial_despatch" to allow to add to order history that the order was partially despatched
//for
$installer = $this;

// Required tables
$statusTable = $installer->getTable('sales/order_status');
$statusStateTable = $installer->getTable('sales/order_status_state');

// Insert statuses
$installer->getConnection()->insertArray(
    $statusTable,
    array(
        'status',
        'label'
    ),
    array(
        array('status' => 'PN', 'label' => 'In Process'),
        array('status' => 'PB', 'label' => 'In Process'),
        array('status' => 'CP', 'label' => 'Shipped'),
        array('status' => 'BO', 'label' => 'Back Ordered'),
        array('status' => 'CA', 'label' => 'Cancelled'),
        array('status' => 'UN', 'label' => 'Not Processed'),
    )
);

// Insert states and mapping of statuses to states
$installer->getConnection()->insertArray(
    $statusStateTable,
    array(
        'status',
        'state',
        'is_default'
    ),
    array(
        array(
            'status' => 'PN',
            'state' => 'processing',
            'is_default' => 0
        ),
        array(
            'status' => 'PB',
            'state' => 'processing',
            'is_default' => 0
        ),
        array(
            'status' => 'BO',
            'state' => 'holded',
            'is_default' => 0
        ),
        array(
            'status' => 'CA',
            'state' => 'canceled',
            'is_default' => 0
        ),
        array(
            'status' => 'CP',
            'state' => 'complete',
            'is_default' => 0
        )
    )
);