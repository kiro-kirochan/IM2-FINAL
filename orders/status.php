<?php
require_once __DIR__ . '/../includes/layout.php';

$orderNo = (int)($_GET['order_no'] ?? $_POST['order_no'] ?? 0);
$o = q_one('SELECT * FROM v_order_summary WHERE order_no = ?', [$orderNo]);
if (!$o || $o['is_deleted']) { flash('error', 'Order not found.'); redirect('orders/'); }

$isDelivery = $o['order_type'] === 'Delivery';
$locked = in_array($o['order_status'], ['Released', 'Delivered', 'Cancelled'], true);
// Delivery trips are controlled from the Delivery Board (so the rider record always matches).
$options = $isDelivery
    ? ['Received', 'Preparing', 'To be Delivered', 'Cancelled']
    : STATUS_WALKIN;
$error = null;

if (!$locked && is_post()) {
    $new = post('status');
    if (!in_array($new, $options, true)) {
        $error = 'That status is not allowed here.';
    } elseif ($new === $o['order_status']) {
        flash('info', 'Status unchanged.');
        redirect('orders/view.php?order_no=' . $orderNo);
    } else {
        try {
            q('UPDATE orders SET order_status = ? WHERE order_no = ?', [$new, $orderNo]);
            flash('ok', order_label($orderNo) . ' is now “' . $new . '”.');
            redirect('orders/view.php?order_no=' . $orderNo);
        } catch (PDOException $e) {
            $error = db_message($e);
        }
    }
}

page_start('Change Status', 'orders', ['sub' => order_label($orderNo) . ' · ' . $o['customer_name'],
    'actions' => head_link('orders/', '← All orders', 'btn')]); ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<section class="card" style="max-width:520px">
  <p>Current status: <?= status_badge($o['order_status']) ?></p>
  <?php if ($locked): ?>
    <div class="note-box">This order is finished (<?= e($o['order_status']) ?>), so its status is locked. You can archive it from the Orders list if you no longer want to see it.</div>
  <?php else: ?>
    <?php if ($isDelivery): ?><div class="note-box">Sending the order out, marking it delivered or failed is done on the <a href="<?= e(url('deliveries/')) ?>">Delivery Board</a>, so the rider and time are recorded.</div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="order_no" value="<?= $orderNo ?>">
      <label class="f"><span>New status</span>
        <select name="status">
          <?php foreach ($options as $s): ?><option <?= $s === $o['order_status'] ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
        </select></label>
      <p class="muted small">“Cancelled” stops further payments and the order cannot be re-opened.</p>
      <div class="form-actions"><button class="btn btn-primary">Update status</button>
        <a class="btn" href="<?= e(url('orders/view.php?order_no=' . $orderNo)) ?>">Cancel</a></div>
    </form>
  <?php endif; ?>
</section>
<?php page_end();
