<?php
require_once __DIR__ . '/../includes/helpers.php';
require_post();

$id = (int)post('delivery_id');
$result = post('result');
if (!$id || !in_array($result, ['Delivered', 'Failed'], true)) {
    flash('error', 'Invalid request.');
} else {
    try {
        // triggers set completion_time and the order status
        q('UPDATE deliveries SET delivery_status = ? WHERE delivery_id = ?', [$result, $id]);
        $order = (int)q_val('SELECT order_no FROM deliveries WHERE delivery_id = ?', [$id]);
        $bal = (float)q_val('SELECT balance FROM v_order_summary WHERE order_no = ?', [$order]);
        flash($result === 'Delivered' ? 'ok' : 'info',
            order_label($order) . ($result === 'Delivered' ? ' delivered.' : ' marked as unsuccessful.')
            . ($result === 'Delivered' && $bal > 0 ? ' Balance of ' . peso($bal) . ' is still unpaid.' : ''));
    } catch (PDOException $e) {
        flash('error', db_message($e));
    }
}
redirect('deliveries/');
