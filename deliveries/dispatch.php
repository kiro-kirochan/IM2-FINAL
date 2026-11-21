<?php
require_once __DIR__ . '/../includes/helpers.php';
require_post();

$orderNo = (int)post('order_no');
$riderId = (int)post('rider_id');
if (!$orderNo || !$riderId) {
    flash('error', 'Choose a rider first.');
} else {
    try {
        // trigger trg_deliveries_bi validates; trg_deliveries_ai sets the order to "Out for Delivery"
        q('INSERT INTO deliveries (order_no, rider_id) VALUES (?, ?)', [$orderNo, $riderId]);
        flash('ok', order_label($orderNo) . ' is out for delivery.');
    } catch (PDOException $e) {
        flash('error', db_message($e));
    }
}
redirect('deliveries/');
